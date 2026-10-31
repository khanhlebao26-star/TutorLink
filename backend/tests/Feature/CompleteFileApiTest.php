<?php

use App\Models\File;
use App\Support\ZipScanGuard;
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

test('valid file completes without creating antivirus evidence and replay is stable', function (): void {
    $first = $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.scan_status', 'clean')
        ->assertJsonPath('data.complete', true)
        ->assertJsonPath('data.antivirus_scanned', false)
        ->assertJsonPath('data.scanned_at', null);

    $this->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data', $first->json('data'));

    $this->assertDatabaseHas('files', [
        'id' => $this->file->id,
        'scan_status' => 'clean',
        'scan_engine' => null,
        'scan_version' => null,
        'scanned_sha256' => null,
        'scanned_at' => null,
    ]);

    $this->assertDatabaseCount('files', 1);
    $this->assertDatabaseCount('file_links', 0);
});

test('metadata violations are persisted as rejected', function (string $change): void {
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
        'content' => Storage::disk('local')->put(
            $this->file->object_key,
            str_repeat('x', strlen($this->bytes)),
        ),
    };

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($this->file->fresh()->scan_status)->toBe('rejected');
    expect($this->file->fresh()->hasScanEvidence())->toBeFalse();

    $this->get('/api/v1/files/'.$this->file->id.'/download')
        ->assertConflict();
})->with([
    'size',
    'hash',
    'mime',
    'extension',
    'purpose',
    'private',
    'content',
]);

test('missing storage stays pending and returns a storage error', function (): void {
    Storage::disk('local')->delete($this->file->object_key);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertStatus(503)
        ->assertHeader('Retry-After', '10')
        ->assertJsonPath('code', 'file_storage_unavailable');

    expect($this->file->fresh()->scan_status)->toBe('pending');

    $this->get('/api/v1/files/'.$this->file->id.'/download')
        ->assertConflict();
});

test('validation unavailable stays pending and can complete on retry', function (): void {
    $attempt = 0;

    $this->mock(ZipScanGuard::class)
        ->shouldReceive('allows')
        ->twice()
        ->andReturnUsing(function () use (&$attempt): bool {
            if (++$attempt === 1) {
                throw new RuntimeException('Temporary validation failure.');
            }

            return true;
        });

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertStatus(503)
        ->assertHeader('Retry-After', '10')
        ->assertJsonPath('code', 'file_validation_unavailable');

    expect($this->file->fresh()->scan_status)->toBe('pending');
    expect($this->file->fresh()->hasScanEvidence())->toBeFalse();

    $this->get('/api/v1/files/'.$this->file->id.'/download')
        ->assertConflict();

    $this->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.scan_status', 'clean')
        ->assertJsonPath('data.complete', true)
        ->assertJsonPath('data.antivirus_scanned', false)
        ->assertJsonPath('data.scanned_at', null);
});

test('rejected files cannot be completed again', function (): void {
    $this->file->update(['scan_status' => 'rejected']);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($this->file->fresh()->scan_status)->toBe('rejected');
});

test('legacy clean files do not acquire fabricated scan evidence', function (): void {
    $this->file->update(['scan_status' => 'clean']);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.complete', true)
        ->assertJsonPath('data.antivirus_scanned', false)
        ->assertJsonPath('data.scanned_at', null);
});

test('valid historical scan evidence is preserved without invoking a scanner', function (): void {
    // Synthetic database fixture; this test does not perform an antivirus scan.
    $this->file->update([
        'scan_status' => 'clean',
        'scan_engine' => 'clamav',
        'scan_version' => 'Historical test fixture',
        'scanned_sha256' => hash('sha256', $this->bytes),
        'scanned_at' => now('UTC'),
    ]);

    $fields = [
        'scan_engine',
        'scan_version',
        'scanned_sha256',
        'scanned_at',
    ];

    $before = $this->file->fresh()->only($fields);

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.antivirus_scanned', true);

    expect($this->file->fresh()->only($fields))->toEqual($before);
});

test('object changed during validation cannot become complete', function (): void {
    $key = $this->file->object_key;

    $this->mock(ZipScanGuard::class)
        ->shouldReceive('allows')
        ->once()
        ->andReturnUsing(function () use ($key): bool {
            Storage::disk('local')->put(
                $key,
                'Changed during validation.',
            );

            return true;
        });

    $this->actingAs($this->owner)
        ->postJson($this->url)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file_rejected');

    expect($this->file->fresh()->scan_status)->toBe('rejected');
    expect($this->file->fresh()->hasScanEvidence())->toBeFalse();
});

test('wrong owner and suspended owner cannot complete a file', function (): void {
    $this->actingAs(TestActors::customer())
        ->postJson($this->url)
        ->assertForbidden();

    $this->owner->update(['status' => 'suspended']);

    $this->actingAs($this->owner->fresh())
        ->postJson($this->url)
        ->assertForbidden();

    expect($this->file->fresh()->scan_status)->toBe('pending');
});

test('upload does not trust client supplied completion or scan evidence', function (): void {
    $this->actingAs($this->owner)
        ->post('/api/v1/files', [
            'file' => UploadedFile::fake()->createWithContent(
                'document.pdf',
                $this->bytes,
            ),
            'purpose' => 'verification_document',
            'scan_status' => 'clean',
            'scan_engine' => 'clamav',
            'scan_version' => 'Client claim',
            'scanned_sha256' => hash('sha256', $this->bytes),
            'scanned_at' => now('UTC')->toISOString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.scan_status', 'pending')
        ->assertJsonPath('data.complete', false)
        ->assertJsonPath('data.antivirus_scanned', false)
        ->assertJsonPath('data.scanned_at', null);
});
