<?php

namespace Tests\Feature;

use App\Models\File as FileRecord;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;
use Tests\Support\TestActors;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

class FileLifecycleConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    private User $tutor;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        TestDatabaseGuard::assertSafe(config());

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'File lifecycle races require PostgreSQL.',
            );
        }

        $this->assertSame(
            'read committed',
            DB::selectOne('SHOW transaction_isolation')
                ->transaction_isolation,
        );

        $this->storageRoot = storage_path(
            'framework/testing/file-lifecycle-'.Str::uuid(),
        );

        config([
            'filesystems.disks.local.root' => $this->storageRoot,
        ]);

        Storage::forgetDisk('local');

        $this->tutor = TestActors::tutor();

        $this->actingAs($this->tutor);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->storageRoot)) {
                (new Filesystem)->deleteDirectory(
                    $this->storageRoot,
                );
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_document_attachment_committing_first_prevents_file_deletion(): void
    {
        $file = $this->uploadCompleteFile();
        $bytesBefore = Storage::disk('local')->get(
            $file->object_key,
        );

        $statuses = $this->race(
            fn (): TestResponse => $this
                ->postJson(
                    '/api/v1/tutor/profile/verification-documents',
                    [
                        'file_id' => $file->id,
                        'document_type' => 'identity_card',
                    ],
                )
                ->assertCreated(),
            'DELETE',
            '/api/v1/files/'.$file->id,
        );

        $this->assertSame([201, 409], $statuses);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'scan_status' => 'clean',
        ]);

        $this->assertDatabaseHas('verification_documents', [
            'tutor_profile_id' => $this->tutor->tutorProfile->id,
            'file_id' => $file->id,
        ]);

        $this->assertDatabaseCount('verification_documents', 1);

        $this->assertSame(
            $bytesBefore,
            Storage::disk('local')->get($file->object_key),
        );
    }

    public function test_file_deletion_committing_first_prevents_document_attachment(): void
    {
        $file = $this->uploadCompleteFile();

        $statuses = $this->race(
            fn (): TestResponse => $this
                ->deleteJson('/api/v1/files/'.$file->id)
                ->assertNoContent(),
            'POST',
            '/api/v1/tutor/profile/verification-documents',
            [
                'file_id' => $file->id,
                'document_type' => 'identity_card',
            ],
        );

        $this->assertSame([204, 404], $statuses);

        $this->assertDatabaseMissing('files', [
            'id' => $file->id,
        ]);

        $this->assertDatabaseCount('verification_documents', 0);
        $this->assertDatabaseCount('file_links', 0);

        $this->assertFalse(
            Storage::disk('local')->exists($file->object_key),
        );
    }

    public function test_link_and_delete_preserve_an_existing_verification_document(): void
    {
        foreach ([true, false] as $linkFirst) {
            $file = $this->uploadCompleteFile();

            $documentId = $this->postJson(
                '/api/v1/tutor/profile/verification-documents',
                [
                    'file_id' => $file->id,
                    'document_type' => 'identity_card',
                ],
            )
                ->assertCreated()
                ->json('data.id');

            $fileUrl = '/api/v1/files/'.$file->id;
            $linkUrl = $fileUrl.'/links';

            $payload = [
                'resource_type' => 'verification_document',
                'resource_id' => $documentId,
            ];

            $first = $linkFirst
                ? fn (): TestResponse => $this
                    ->postJson($linkUrl, $payload)
                    ->assertOk()
                : fn (): TestResponse => $this
                    ->deleteJson($fileUrl)
                    ->assertConflict();

            $statuses = $this->race(
                $first,
                $linkFirst ? 'DELETE' : 'POST',
                $linkFirst ? $fileUrl : $linkUrl,
                $linkFirst ? [] : $payload,
            );

            $this->assertSame(
                $linkFirst ? [200, 409] : [409, 200],
                $statuses,
            );

            $this->assertDatabaseHas('files', [
                'id' => $file->id,
            ]);

            $this->assertDatabaseHas('verification_documents', [
                'id' => $documentId,
                'file_id' => $file->id,
            ]);

            $this->assertDatabaseHas('file_links', [
                'file_id' => $file->id,
                'resource_type' => 'verification_document',
                'resource_id' => $documentId,
            ]);

            $this->assertSame(
                1,
                DB::table('file_links')
                    ->where('file_id', $file->id)
                    ->where('resource_type', 'verification_document')
                    ->where('resource_id', $documentId)
                    ->count(),
            );

            $this->assertSame(
                $file->checksum_sha256,
                hash(
                    'sha256',
                    Storage::disk('local')->get($file->object_key),
                ),
            );
        }
    }

    private function uploadCompleteFile(): FileRecord
    {
        $response = $this->post('/api/v1/files', [
            'purpose' => 'verification_document',
            'file' => UploadedFile::fake()->createWithContent(
                'identity.pdf',
                "%PDF-1.4\nFile lifecycle race fixture.\n%%EOF",
            ),
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $fileId = (int) $response->json('data.id');

        $this->postJson('/api/v1/files/'.$fileId.'/complete')
            ->assertOk()
            ->assertJsonPath('data.complete', true)
            ->assertJsonPath('data.antivirus_scanned', false)
            ->assertJsonPath('data.scanned_at', null);

        return FileRecord::findOrFail($fileId);
    }

    /**
     * @param  callable(): TestResponse  $first
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: int}
     */
    private function race(
        callable $first,
        string $method,
        string $uri,
        array $data = [],
    ): array {
        $process = null;

        DB::beginTransaction();

        try {
            $firstResponse = $first();

            $process = $this->startWorker($method, $uri, $data);

            $this->waitForHeldLock($process);

            DB::commit();

            $process->wait();

            $diagnostic = $process->getOutput()
                .$process->getErrorOutput();

            $this->assertSame(
                0,
                $process->getExitCode(),
                $diagnostic,
            );

            $this->assertSame(
                1,
                preg_match(
                    '/RESULT:(\d+)/',
                    $process->getOutput(),
                    $match,
                ),
                $diagnostic,
            );

            return [
                $firstResponse->status(),
                (int) $match[1],
            ];
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($process !== null && $process->isRunning()) {
                $process->stop(0, 9);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function startWorker(
        string $method,
        string $uri,
        array $data,
    ): Process {
        $job = base64_encode(json_encode([
            'database' => DB::connection()->getDatabaseName(),
            'actor_id' => $this->tutor->id,
            'storage_root' => $this->storageRoot,
            'method' => $method,
            'uri' => $uri,
            'data' => $data,
        ], JSON_THROW_ON_ERROR));

        $script = <<<'PHP'
            require 'vendor/autoload.php';

            $app = require 'bootstrap/app.php';
            $app->make(
                \Illuminate\Contracts\Console\Kernel::class,
            )->bootstrap();

            \Tests\Support\TestDatabaseGuard::assertSafe(
                $app['config'],
            );

            $job = json_decode(
                base64_decode($argv[1], true),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $connection = \Illuminate\Support\Facades\DB::connection();

            if (
                $connection->getDriverName() !== 'pgsql'
                || $connection->getDatabaseName() !== $job['database']
            ) {
                exit(2);
            }

            config([
                'filesystems.disks.local.root' => $job['storage_root'],
            ]);

            \Illuminate\Support\Facades\Storage::forgetDisk('local');

            $actor = \App\Models\User::findOrFail(
                $job['actor_id'],
            );

            \Illuminate\Support\Facades\Auth::guard('web')
                ->setUser($actor);

            $request = \Illuminate\Http\Request::create(
                $job['uri'],
                $job['method'],
                $job['data'],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json'],
            );

            $request->setUserResolver(fn () => $actor);

            $pid = $connection->selectOne(
                'SELECT pg_backend_pid() AS pid',
            )->pid;

            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);

            try {
                $kernel = $app->make(
                    \Illuminate\Contracts\Http\Kernel::class,
                );

                $response = $kernel->handle($request);

                $status = $response->getStatusCode();

                if ($status >= 500) {
                    fwrite(STDERR, $response->getContent());
                }

                $kernel->terminate($request, $response);

                fwrite(STDOUT, "RESULT:{$status}\n");
            } catch (\Throwable $exception) {
                fwrite(
                    STDERR,
                    get_class($exception).': '.$exception->getMessage(),
                );

                exit(3);
            }
            PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script, $job],
            base_path(),
            ['APP_ENV' => 'testing'],
        );

        $process->setTimeout(25);
        $process->start();

        return $process;
    }

    private function waitForHeldLock(Process $process): void
    {
        $parentPid = (int) DB::selectOne(
            'SELECT pg_backend_pid() AS pid',
        )->pid;

        $deadline = microtime(true) + 10;

        do {
            if (
                preg_match(
                    '/READY:(\d+)/',
                    $process->getOutput(),
                    $match,
                )
            ) {
                $workerPid = (int) $match[1];

                if ($workerPid === $parentPid) {
                    $this->fail(
                        'The worker must use another PostgreSQL session.',
                    );
                }

                $lock = DB::selectOne(
                    <<<'SQL'
                        SELECT CASE
                            WHEN CAST(? AS integer) = ANY(
                                pg_blocking_pids(CAST(? AS integer))
                            )
                            THEN 1
                            ELSE 0
                        END AS blocked
                        SQL,
                    [$parentPid, $workerPid],
                );

                if ((int) $lock->blocked === 1) {
                    return;
                }
            }

            if (! $process->isRunning()) {
                break;
            }

            usleep(20_000);
        } while (microtime(true) < $deadline);

        $this->fail(
            'Worker did not wait for the transaction lock. '
            .$process->getOutput()
            .$process->getErrorOutput(),
        );
    }
}
