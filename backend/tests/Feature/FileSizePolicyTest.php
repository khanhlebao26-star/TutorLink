<?php

use App\Models\File as FileRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    $this->owner = TestActors::customer();
});

function k037bFileBytes(string $purpose, int $size): string
{
    $prefix = $purpose === 'avatar'
        ? base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jAZkAAAAASUVORK5CYII=',
            true,
        )
        : "%PDF-1.4\nSynthetic size-policy fixture.\n%%EOF";

    return str_pad($prefix, $size, ' ');
}

test('upload and complete enforce the decimal byte boundary', function (string $purpose, int $size): void {
    $bytes = k037bFileBytes($purpose, $size);
    $name = $purpose === 'avatar' ? 'avatar.png' : 'document.pdf';

    $response = $this->actingAs($this->owner)
        ->post('/api/v1/files', [
            'purpose' => $purpose,
            'file' => UploadedFile::fake()->createWithContent(
                $name,
                $bytes,
            ),
        ], [
            'Accept' => 'application/json',
        ]);

    if ($size > 5_000_000) {
        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('files', 0);
        expect(Storage::disk('local')->allFiles())->toBeEmpty();

        return;
    }

    $response
        ->assertCreated()
        ->assertJsonPath('data.size_bytes', $size)
        ->assertJsonPath('data.complete', false);

    $fileId = $response->json('data.id');

    $this->postJson('/api/v1/files/'.$fileId.'/complete')
        ->assertOk()
        ->assertJsonPath('data.scan_status', 'clean')
        ->assertJsonPath('data.complete', true)
        ->assertJsonPath('data.antivirus_scanned', false)
        ->assertJsonPath('data.scanned_at', null);

    $this->assertDatabaseCount('file_links', 0);
})->with([
    'avatar',
    'verification_document',
    'task_attachment',
    'profile_document',
])->with([
    4_999_999,
    5_000_000,
    5_000_001,
]);

test('complete rejects an oversized object despite smaller declared metadata', function (): void {
    $bytes = k037bFileBytes('profile_document', 5_000_001);

    $file = FileRecord::create([
        'owner_id' => $this->owner->id,
        'disk' => 'local',
        'object_key' => 'size-policy/oversized.pdf',
        'original_name' => 'oversized.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 5_000_000,
        'visibility' => 'private',
        'checksum_sha256' => hash('sha256', $bytes),
        'scan_status' => 'pending',
        'purpose' => 'profile_document',
    ]);

    Storage::disk('local')->put($file->object_key, $bytes);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/files/'.$file->id.'/complete')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($file->fresh()->scan_status)->toBe('rejected');
    expect($file->fresh()->hasScanEvidence())->toBeFalse();

    $this->get('/api/v1/files/'.$file->id.'/download')
        ->assertConflict();
});

test('complete rejects an oversized object even when metadata matches', function (): void {
    $bytes = k037bFileBytes('profile_document', 5_000_001);

    $file = FileRecord::create([
        'owner_id' => $this->owner->id,
        'disk' => 'local',
        'object_key' => 'size-policy/matching-oversized.pdf',
        'original_name' => 'matching-oversized.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($bytes),
        'visibility' => 'private',
        'checksum_sha256' => hash('sha256', $bytes),
        'scan_status' => 'pending',
        'purpose' => 'profile_document',
    ]);

    Storage::disk('local')->put($file->object_key, $bytes);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/files/'.$file->id.'/complete')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($file->fresh()->scan_status)->toBe('rejected');
});
