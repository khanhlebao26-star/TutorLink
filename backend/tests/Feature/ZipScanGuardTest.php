<?php

use App\Models\File;
use App\Support\ZipScanGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @param array<string,string> $entries */
function k037GuardZip(array $entries, bool $encrypted = false): string
{
    $path = tempnam(sys_get_temp_dir(), 'guard-test-');
    $zip = new ZipArchive;

    try {
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create ZIP fixture.');
        }

        foreach ($entries as $name => $body) {
            $zip->addFromString($name, $body);

            if (
                $encrypted
                && ! $zip->setEncryptionName(
                    $name,
                    ZipArchive::EM_AES_256,
                    'test-password',
                )
            ) {
                throw new RuntimeException('Cannot encrypt ZIP fixture.');
            }
        }

        $zip->close();

        return file_get_contents($path);
    } finally {
        unlink($path);
    }
}

test('ZIP guard accepts ordinary entries and non ZIP', function (): void {
    $guard = app(ZipScanGuard::class);
    $deadline = hrtime(true) + 10_000_000_000;

    expect($guard->allows('plain data', $deadline))->toBeTrue();

    $bytes = k037GuardZip([
        'word/document.xml' => '<document/>',
        'notes/' => '',
        'notes/hello.txt' => 'Hello.',
    ]);

    expect($guard->allows($bytes, $deadline))->toBeTrue();
});

test('ZIP guard refuses unsafe or incomplete archives', function (string $case): void {
    $bytes = match ($case) {
        'file' => k037GuardZip([
            'large.txt' => str_repeat('A', 100),
        ]),
        'total' => k037GuardZip([
            'one.txt' => str_repeat('A', 100),
            'two.txt' => str_repeat('B', 100),
        ]),
        'count' => k037GuardZip([
            'one.txt' => '',
            'two.txt' => '',
            'three.txt' => '',
        ]),
        'encrypted' => k037GuardZip([
            'secret.txt' => 'secret',
        ], true),
        'nested' => k037GuardZip([
            'nested.zip' => k037GuardZip([
                'inner.txt' => 'inner',
            ]),
        ]),
        'malformed' => substr(
            k037GuardZip(['one.txt' => 'one']),
            0,
            -5,
        ),
    };

    if ($case === 'file') {
        config(['file-validation.archive.max_file_bytes' => 99]);
    }

    if ($case === 'total') {
        config([
            'file-validation.archive.max_total_bytes' => strlen($bytes) + 150,
        ]);
    }

    if ($case === 'count') {
        config(['file-validation.archive.max_files' => 2]);
    }

    $deadline = hrtime(true) + 10_000_000_000;

    expect(
        app(ZipScanGuard::class)->allows($bytes, $deadline)
    )->toBeFalse();
})->with([
    'file',
    'total',
    'count',
    'encrypted',
    'nested',
    'malformed',
]);

test('ZIP guard reports an expired deadline as unavailable', function (): void {
    $bytes = k037GuardZip(['word/document.xml' => '<document/>']);

    expect(fn () => app(ZipScanGuard::class)->allows($bytes, hrtime(true) - 1))
        ->toThrow(RuntimeException::class);
});

test('ZIP guard reports invalid limits as unavailable', function (): void {
    $bytes = k037GuardZip(['word/document.xml' => '<document/>']);
    config(['file-validation.archive.max_files' => 0]);

    expect(fn () => app(ZipScanGuard::class)->allows($bytes, hrtime(true) + 10_000_000_000))
        ->toThrow(RuntimeException::class);
});

test('archive policy rejection blocks a previously complete file', function (): void {
    Storage::fake('local');

    $actor = TestActors::customer();
    $bytes = "%PDF-1.4\nSynthetic fixture.\n%%EOF";

    $file = File::create([
        'owner_id' => $actor->id,
        'disk' => 'local',
        'object_key' => 'guard/fixture.pdf',
        'original_name' => 'fixture.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($bytes),
        'purpose' => 'verification_document',
        'visibility' => 'private',
        'scan_status' => 'clean',
        'checksum_sha256' => hash('sha256', $bytes),
        'scan_engine' => 'clamav',
        'scan_version' => 'ClamAV test-fake/1/test',
        'scanned_sha256' => hash('sha256', $bytes),
        'scanned_at' => now('UTC'),
    ]);

    Storage::disk('local')->put($file->object_key, $bytes);

    $this->mock(ZipScanGuard::class)
        ->shouldReceive('allows')
        ->once()
        ->andReturnFalse();

    $this->actingAs($actor)
        ->postJson('/api/v1/files/'.$file->id.'/complete')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($file->fresh()->scan_status)->toBe('rejected');
    expect($file->fresh()->hasScanEvidence())->toBeFalse();

    $this->get('/api/v1/files/'.$file->id.'/download')
        ->assertConflict();
});
