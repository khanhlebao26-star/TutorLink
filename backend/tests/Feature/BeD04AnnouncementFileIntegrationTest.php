<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Support\TestActors;
use Tests\TestCase;

class BeD04AnnouncementFileIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_announcement_attachment_upload_and_complete_use_the_shared_private_pipeline(): void
    {
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $bytes = $this->pdfBytes();

        $uploaded = $this->actingAs($owner)->postJson('/api/v1/files', [
            'purpose' => 'announcement_attachment',
            'file' => UploadedFile::fake()->createWithContent('announcement.pdf', $bytes),
        ]);
        $this->recordApi('D04-09-upload-announcement-purpose', 'owner', 'POST', '/api/v1/files', [
            'purpose' => 'announcement_attachment', 'file' => 'announcement.pdf',
        ], $uploaded);
        $uploaded->assertCreated()
            ->assertJsonPath('data.purpose', 'announcement_attachment')
            ->assertJsonPath('data.scan_status', 'pending')
            ->assertJsonPath('data.complete', false);
        $fileId = (int) $uploaded->json('data.id');
        $file = File::query()->findOrFail($fileId);
        $this->assertSame('private', $file->visibility);
        $this->assertSame($owner->id, $file->owner_id);

        $pendingMetadata = $this->getJson('/api/v1/files/'.$fileId);
        $this->recordApi('D04-09-owner-pending-metadata', 'owner', 'GET', '/api/v1/files/'.$fileId, null, $pendingMetadata);
        $pendingMetadata->assertOk()
            ->assertJsonPath('data.id', $fileId)
            ->assertJsonPath('data.scan_status', 'pending')
            ->assertJsonPath('data.complete', false);

        $other = $this->user('be-d04-other-tutor@tutorlink.test');
        $wrongOwnerMetadata = $this->actingAs($other)->getJson('/api/v1/files/'.$fileId);
        $this->recordApi('D04-09-wrong-owner-pending-metadata', 'other-tutor', 'GET', '/api/v1/files/'.$fileId, null, $wrongOwnerMetadata);
        $wrongOwnerMetadata->assertForbidden();

        $complete = $this->actingAs($owner)->postJson('/api/v1/files/'.$fileId.'/complete');
        $this->recordApi('D04-09-complete-announcement-file', 'owner', 'POST', '/api/v1/files/'.$fileId.'/complete', null, $complete);
        $complete->assertOk()
            ->assertJsonPath('data.purpose', 'announcement_attachment')
            ->assertJsonPath('data.scan_status', 'clean')
            ->assertJsonPath('data.complete', true)
            ->assertJsonPath('data.antivirus_scanned', false);

        $invalidPurpose = $this->postJson('/api/v1/files', [
            'purpose' => 'not_an_announcement_attachment',
            'file' => UploadedFile::fake()->createWithContent('invalid.pdf', $bytes),
        ]);
        $this->recordApi('D04-09-invalid-purpose-rejected', 'owner', 'POST', '/api/v1/files', [
            'purpose' => 'not_an_announcement_attachment', 'file' => 'invalid.pdf',
        ], $invalidPurpose);
        $invalidPurpose->assertUnprocessable()->assertJsonValidationErrors('purpose');

        $oversize = $this->postJson('/api/v1/files', [
            'purpose' => 'announcement_attachment',
            'file' => UploadedFile::fake()->create('too-large.pdf', 5001, 'application/pdf'),
        ]);
        $this->recordApi('D04-09-oversize-upload-rejected', 'owner', 'POST', '/api/v1/files', [
            'purpose' => 'announcement_attachment', 'file' => 'too-large.pdf (5001 KiB)',
        ], $oversize);
        $oversize->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseHas('files', ['id' => $fileId, 'purpose' => 'announcement_attachment', 'scan_status' => 'clean']);
    }

    public function test_draft_file_access_is_owner_only_and_generic_link_or_delete_cannot_bypass_announcement_api(): void
    {
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $program = $this->program('D04 Fixture Active');
        $file = $this->cleanFile($owner, 'draft-announcement.pdf');
        $path = '/api/v1/programs/'.$program->id.'/announcements';

        $created = $this->actingAs($owner)->postJson($path, [
            'title' => 'Tệp nháp',
            'body' => 'Thông báo có file riêng tư.',
            'file_ids' => [$file->id],
        ]);
        $this->recordApi('D04-09-POST-draft-with-file', 'owner', 'POST', $path, [
            'title' => 'Tệp nháp', 'body' => 'Thông báo có file riêng tư.', 'file_ids' => [$file->id],
        ], $created);
        $created->assertCreated()->assertJsonPath('data.files.0.id', $file->id);
        $announcementId = (int) $created->json('data.id');

        $ownerMetadata = $this->actingAs($owner)->getJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-draft-owner-metadata', 'owner', 'GET', '/api/v1/files/'.$file->id, null, $ownerMetadata);
        $ownerMetadata->assertOk()->assertJsonPath('data.id', $file->id)->assertJsonPath('data.complete', true);

        $hiddenMetadata = $this->actingAs($member)->getJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-draft-member-metadata-hidden', 'member', 'GET', '/api/v1/files/'.$file->id, null, $hiddenMetadata);
        $hiddenMetadata->assertNotFound()->assertJsonPath('code', 'file_not_found');

        $ownerDownload = $this->actingAs($owner)->get('/api/v1/files/'.$file->id.'/download');
        $this->recordDownload('D04-09-draft-owner-download', 'owner', '/api/v1/files/'.$file->id.'/download', $ownerDownload, $file->checksum_sha256);
        $ownerDownload->assertOk()->assertDownload('draft-announcement.pdf');

        $hiddenDownload = $this->actingAs($member)->get('/api/v1/files/'.$file->id.'/download');
        $this->recordApi('D04-09-draft-member-download-hidden', 'member', 'GET', '/api/v1/files/'.$file->id.'/download', null, $hiddenDownload);
        $hiddenDownload->assertNotFound()->assertJsonPath('code', 'file_not_found');

        $genericLink = $this->actingAs($owner)->postJson('/api/v1/files/'.$file->id.'/links', [
            'resource_type' => 'announcement',
            'resource_id' => $announcementId,
            'purpose' => 'announcement_attachment',
        ]);
        $this->recordApi('D04-09-generic-announcement-link-conflict', 'owner', 'POST', '/api/v1/files/'.$file->id.'/links', [
            'resource_type' => 'announcement', 'resource_id' => $announcementId, 'purpose' => 'announcement_attachment',
        ], $genericLink);
        $genericLink->assertConflict();

        $deleteLinkedDraft = $this->actingAs($owner)->deleteJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-delete-linked-draft-conflict', 'owner', 'DELETE', '/api/v1/files/'.$file->id, null, $deleteLinkedDraft);
        $deleteLinkedDraft->assertConflict();
        Storage::disk('local')->assertExists($file->object_key);
        $this->assertDatabaseHas('file_links', [
            'file_id' => $file->id,
            'resource_type' => 'announcement',
            'resource_id' => $announcementId,
            'purpose' => 'announcement_attachment',
        ]);

        $detach = $this->actingAs($owner)->patchJson('/api/v1/announcements/'.$announcementId, [
            'expected_version' => 1,
            'file_ids' => [],
        ]);
        $this->recordApi('D04-09-draft-detach-keeps-file-bytes', 'owner', 'PATCH', '/api/v1/announcements/'.$announcementId, [
            'expected_version' => 1, 'file_ids' => [],
        ], $detach);
        $detach->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.files', []);
        Storage::disk('local')->assertExists($file->object_key);
        $this->assertDatabaseHas('files', ['id' => $file->id, 'object_key' => $file->object_key]);
    }

    public function test_published_announcement_file_requires_current_owner_or_active_member_and_remains_immutable(): void
    {
        $owner = $this->user('be-d04-owner@tutorlink.test');
        $member = $this->user('be-d04-late-member@tutorlink.test');
        $program = $this->program('D04 Fixture Active');
        $file = $this->cleanFile($owner, 'published-announcement.pdf');
        $now = now('UTC')->format('Y-m-d H:i:s.uP');
        $announcementId = DB::table('announcements')->insertGetId([
            'program_id' => $program->id,
            'author_id' => $owner->id,
            'title' => 'Thông báo đã phát hành',
            'body' => 'Tệp đã gắn không được thay qua API khác.',
            'published_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('file_links')->insert([
            'file_id' => $file->id,
            'resource_type' => 'announcement',
            'resource_id' => $announcementId,
            'purpose' => 'announcement_attachment',
            'created_at' => $now,
        ]);

        foreach ([['owner', $owner], ['member', $member]] as [$label, $actor]) {
            $metadata = $this->actingAs($actor)->getJson('/api/v1/files/'.$file->id);
            $this->recordApi('D04-09-published-'.$label.'-metadata', $label, 'GET', '/api/v1/files/'.$file->id, null, $metadata);
            $metadata->assertOk()->assertJsonPath('data.id', $file->id)->assertJsonPath('data.complete', true);

            $download = $this->actingAs($actor)->get('/api/v1/files/'.$file->id.'/download');
            $this->recordDownload('D04-09-published-'.$label.'-download', $label, '/api/v1/files/'.$file->id.'/download', $download, $file->checksum_sha256);
            $download->assertOk()->assertDownload('published-announcement.pdf');
        }

        $outsider = TestActors::customer();
        foreach (['metadata' => 'GET', 'download' => 'GET'] as $kind => $method) {
            $path = $kind === 'metadata'
                ? '/api/v1/files/'.$file->id
                : '/api/v1/files/'.$file->id.'/download';
            $response = $this->actingAs($outsider)->getJson($path);
            $this->recordApi('D04-09-published-outsider-'.$kind.'-denied', 'outsider', $method, $path, null, $response);
            $response->assertNotFound()->assertJsonPath('code', 'file_not_found');
        }

        $admin = TestActors::admin();
        $adminMetadata = $this->actingAs($admin)->getJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-published-admin-metadata-denied', 'admin', 'GET', '/api/v1/files/'.$file->id, null, $adminMetadata);
        $adminMetadata->assertNotFound()->assertJsonPath('code', 'file_not_found');

        $formerUploader = $this->user('be-d04-other-tutor@tutorlink.test');
        $foreignLinkedFile = $this->cleanFile($formerUploader, 'wrong-announcement-owner.pdf');
        DB::table('file_links')->insert([
            'file_id' => $foreignLinkedFile->id,
            'resource_type' => 'announcement',
            'resource_id' => $announcementId,
            'purpose' => 'announcement_attachment',
            'created_at' => $now,
        ]);
        $foreignOwnerMetadata = $this->actingAs($formerUploader)->getJson('/api/v1/files/'.$foreignLinkedFile->id);
        $this->recordApi('D04-09-announcement-link-does-not-use-uploader-bypass', 'former-uploader', 'GET', '/api/v1/files/'.$foreignLinkedFile->id, null, $foreignOwnerMetadata);
        $foreignOwnerMetadata->assertNotFound()->assertJsonPath('code', 'file_not_found');
        $foreignOwnerDownload = $this->actingAs($formerUploader)->getJson('/api/v1/files/'.$foreignLinkedFile->id.'/download');
        $this->recordApi('D04-09-announcement-download-does-not-use-uploader-bypass', 'former-uploader', 'GET', '/api/v1/files/'.$foreignLinkedFile->id.'/download', null, $foreignOwnerDownload);
        $foreignOwnerDownload->assertNotFound()->assertJsonPath('code', 'file_not_found');

        DB::table('programs')->where('id', $program->id)->update(['status' => 'completed']);
        $terminalRead = $this->actingAs($member)->getJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-terminal-program-member-metadata-allowed', 'member', 'GET', '/api/v1/files/'.$file->id, null, $terminalRead);
        $terminalRead->assertOk()->assertJsonPath('data.id', $file->id);

        $this->assertDatabaseHas('files', ['id' => $file->id, 'object_key' => $file->object_key]);
        $deletePublished = $this->actingAs($owner)->deleteJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-delete-published-file-conflict', 'owner', 'DELETE', '/api/v1/files/'.$file->id, null, $deletePublished);
        $deletePublished->assertConflict();
        Storage::disk('local')->assertExists($file->object_key);

        DB::table('program_members')
            ->where('program_id', $program->id)
            ->where('user_id', $member->id)
            ->where('member_role', 'customer')
            ->update(['status' => 'cancelled']);
        $revokedMetadata = $this->actingAs($member)->getJson('/api/v1/files/'.$file->id);
        $this->recordApi('D04-09-revoked-member-metadata-denied', 'revoked-member', 'GET', '/api/v1/files/'.$file->id, null, $revokedMetadata);
        $revokedMetadata->assertNotFound()->assertJsonPath('code', 'file_not_found');

        $revokedDownload = $this->actingAs($member)->getJson('/api/v1/files/'.$file->id.'/download');
        $this->recordApi('D04-09-revoked-member-download-denied', 'revoked-member', 'GET', '/api/v1/files/'.$file->id.'/download', null, $revokedDownload);
        $revokedDownload->assertNotFound()->assertJsonPath('code', 'file_not_found');
    }

    private function cleanFile(User $owner, string $name): File
    {
        $bytes = $this->pdfBytes();
        $objectKey = 'd04-09/'.bin2hex(random_bytes(12)).'.pdf';
        Storage::disk('local')->put($objectKey, $bytes);

        return File::query()->create([
            'owner_id' => $owner->id,
            'disk' => 'local',
            'object_key' => $objectKey,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'visibility' => 'private',
            'checksum_sha256' => hash('sha256', $bytes),
            'scan_status' => 'clean',
            'purpose' => 'announcement_attachment',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
    }

    private function program(string $title): Program
    {
        return Program::query()->where('title', $title)->firstOrFail();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    /** @param array<string,mixed>|null $body */
    private function recordApi(string $case, string $actor, string $method, string $path, ?array $body, TestResponse $response): void
    {
        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor,
            'request' => ['method' => $method, 'path' => $path, 'body' => $body],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => ['X-Request-ID' => $response->headers->get('X-Request-ID')],
                'body' => json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);
    }

    private function recordDownload(string $case, string $actor, string $path, TestResponse $response, string $expectedSha256): void
    {
        $baseResponse = $response->baseResponse;
        if ($baseResponse instanceof StreamedResponse) {
            $downloadBytes = $response->streamedContent();
        } else {
            $downloadBytes = $response->getContent();
        }
        $sha256 = hash('sha256', is_string($downloadBytes) ? $downloadBytes : '');

        fwrite(STDOUT, json_encode([
            'case' => $case,
            'actor' => $actor,
            'request' => ['method' => 'GET', 'path' => $path],
            'response' => [
                'status' => $response->getStatusCode(),
                'headers' => [
                    'Content-Disposition' => $response->headers->get('Content-Disposition'),
                    'Content-Type' => $response->headers->get('Content-Type'),
                    'X-Request-ID' => $response->headers->get('X-Request-ID'),
                ],
                'download_sha256' => $sha256,
                'matches_expected_sha256' => hash_equals($expectedSha256, $sha256),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR).PHP_EOL);

        $this->assertSame($expectedSha256, $sha256, 'The authenticated download must serve the stored file bytes.');
    }
}
