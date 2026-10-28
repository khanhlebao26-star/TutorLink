<?php

use App\Models\File;
use App\Support\ClamAvScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    $this->owner = TestActors::customer();
    $this->bytes = "%PDF-1.4\nSynthetic test document.\n%%EOF";

    $this->file = File::create([
        'owner_id' => $this->owner->id,
        'disk' => 'local',
        'object_key' => 'tests/document.pdf',
        'original_name' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($this->bytes),
        'visibility' => 'private',
        'checksum_sha256' => hash('sha256', $this->bytes),
        'scan_status' => 'pending',
        'purpose' => 'verification_document',
    ]);

    Storage::disk('local')->put(
        $this->file->object_key,
        $this->bytes,
    );

    $this->url = '/api/v1/files/'.$this->file->id.'/complete';
});

test('clean verdict persists scan evidence and safe replay does not rescan', function (): void {
    $this->mock(ClamAvScanner::class)
        ->shouldReceive('scan')
        ->once()
        ->with($this->bytes)
        ->andReturn([
            'status' => 'clean',
            'version' => 'ClamAV TEST-FAKE/1/test',
            'reason' => 'ok',
        ]);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.scan_status', 'clean')
        ->assertJsonPath('data.antivirus_scanned', true);

    $this->assertDatabaseHas('files', [
        'id' => $this->file->id,
        'scan_engine' => 'clamav',
        'scanned_sha256' => hash('sha256', $this->bytes),
    ]);

    expect($this->file->fresh()->hasVerifiedScan())->toBeTrue();

    $this->postJson($this->url)->assertOk();
});

test('malware rejection is committed even though HTTP returns an error', function (): void {
    $this->mock(ClamAvScanner::class)
        ->shouldReceive('scan')
        ->once()
        ->andReturn([
            'status' => 'rejected',
            'version' => 'ClamAV TEST-FAKE/1/test',
            'reason' => 'malware_detected',
        ]);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($this->file->fresh()->scan_status)->toBe('rejected');
    expect($this->file->fresh()->hasScanEvidence())->toBeTrue();
    expect($this->file->fresh()->hasVerifiedScan())->toBeFalse();

    $this->get('/api/v1/files/'.$this->file->id.'/download')
        ->assertConflict();

    $this->postJson($this->url)->assertUnprocessable();
});

test('scanner error keeps pending returns retry header and blocks use', function (): void {
    $this->owner = TestActors::tutor();
    $this->file->update(['owner_id' => $this->owner->id]);

    $this->mock(ClamAvScanner::class)
        ->shouldReceive('scan')
        ->once()
        ->andReturn([
            'status' => 'unavailable',
            'version' => null,
            'reason' => 'scanner_unavailable',
        ]);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertStatus(503)
        ->assertHeader('Retry-After', '10')
        ->assertJsonPath('code', 'scanner_unavailable');

    expect($this->file->fresh()->scan_status)->toBe('pending');
    expect($this->file->fresh()->scanned_at)->toBeNull();

    $this->get('/api/v1/files/'.$this->file->id.'/download')
        ->assertConflict();

    $this->postJson('/api/v1/tutor/profile/verification-documents', [
        'file_id' => $this->file->id,
        'document_type' => 'identity_card',
    ])->assertConflict();
});

test('metadata violations are rejected before invoking scanner', function (string $change): void {
    $this->mock(ClamAvScanner::class)->shouldNotReceive('scan');

    match ($change) {
        'size' => $this->file->update([
            'size_bytes' => strlen($this->bytes) + 1,
        ]),
        'hash' => $this->file->update([
            'checksum_sha256' => str_repeat('a', 64),
        ]),
        'mime' => $this->file->update([
            'mime_type' => 'image/png',
        ]),
        'extension' => $this->file->update([
            'original_name' => 'document.exe',
        ]),
        'purpose' => $this->file->update([
            'purpose' => 'avatar',
        ]),
        'private' => $this->file->update([
            'visibility' => 'public',
        ]),
        'signature' => Storage::disk('local')->put(
            $this->file->object_key,
            str_repeat('x', strlen($this->bytes)),
        ),
    };

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($this->file->fresh()->scan_status)->toBe('rejected');
    expect($this->file->fresh()->scanned_at)->toBeNull();
})->with([
    'size',
    'hash',
    'mime',
    'extension',
    'purpose',
    'private',
    'signature',
]);

test('storage read failure stays pending without antivirus evidence', function (): void {
    $this->mock(ClamAvScanner::class)->shouldNotReceive('scan');

    Storage::disk('local')->delete($this->file->object_key);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertStatus(503)
        ->assertJsonPath('code', 'file_storage_unavailable');

    expect($this->file->fresh()->scan_status)->toBe('pending');
});

test('legacy clean has no scan evidence and complete rescans it', function (): void {
    $this->file->update(['scan_status' => 'clean']);

    $this->actingAs($this->owner)
        ->getJson('/api/v1/files/'.$this->file->id)
        ->assertOk()
        ->assertJsonPath('data.complete', true)
        ->assertJsonPath('data.antivirus_scanned', false)
        ->assertJsonPath('data.scanned_at', null);

    $this->mock(ClamAvScanner::class)
        ->shouldReceive('scan')
        ->once()
        ->andReturn([
            'status' => 'clean',
            'version' => 'ClamAV TEST-FAKE/1/test',
            'reason' => 'ok',
        ]);

    $this->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.antivirus_scanned', true);
});

test('legacy clean becomes pending when scan cannot complete', function (): void {
    $this->file->update(['scan_status' => 'clean']);

    $this->mock(ClamAvScanner::class)
        ->shouldReceive('scan')
        ->once()
        ->andReturn([
            'status' => 'unavailable',
            'version' => null,
            'reason' => 'incomplete_scan',
        ]);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertStatus(503);

    expect($this->file->fresh()->scan_status)->toBe('pending');
    expect($this->file->fresh()->hasScanEvidence())->toBeFalse();

    $this->get('/api/v1/files/'.$this->file->id.'/download')
        ->assertConflict();
});

test('object changed during scan cannot inherit a clean verdict', function (): void {
    $key = $this->file->object_key;

    $this->mock(ClamAvScanner::class)
        ->shouldReceive('scan')
        ->once()
        ->andReturnUsing(function () use ($key): array {
            Storage::disk('local')->put(
                $key,
                'Changed after scanning snapshot.',
            );

            return [
                'status' => 'clean',
                'version' => 'ClamAV TEST-FAKE/1/test',
                'reason' => 'ok',
            ];
        });

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertUnprocessable();

    expect($this->file->fresh()->scan_status)->toBe('rejected');
    expect($this->file->fresh()->scanned_at)->toBeNull();
});

test('wrong owner and suspended owner cannot invoke scanner', function (): void {
    $this->mock(ClamAvScanner::class)->shouldNotReceive('scan');

    $this->actingAs(TestActors::customer())
        ->postJson($this->url)
        ->assertForbidden();

    $this->owner->update(['status' => 'suspended']);

    $this->actingAs($this->owner->fresh())
        ->postJson($this->url)
        ->assertForbidden();

    expect($this->file->fresh()->scan_status)->toBe('pending');
});

test('upload remains quarantined and client cannot supply scan evidence', function (): void {
    $this->mock(ClamAvScanner::class)->shouldNotReceive('scan');

    $this->actingAs($this->owner)
        ->post('/api/v1/files', [
            'file' => UploadedFile::fake()->createWithContent(
                'document.pdf',
                $this->bytes,
            ),
            'purpose' => 'verification_document',
            'scan_status' => 'clean',
            'scan_engine' => 'clamav',
            'scanned_sha256' => hash('sha256', $this->bytes),
        ])
        ->assertCreated()
        ->assertJsonPath('data.scan_status', 'pending')
        ->assertJsonPath('data.antivirus_scanned', false);
});
