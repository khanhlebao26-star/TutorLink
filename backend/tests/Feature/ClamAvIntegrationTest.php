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
    if (getenv('TEST_REAL_CLAMAV') !== '1') {
        $this->markTestSkipped(
            'Run explicitly against real ClamAV. A skipped test is not antivirus evidence.'
        );
    }

    Storage::fake('local');
});

test('real INSTREAM detects EICAR and scans a clean payload', function (): void {
    $scanner = app(ClamAvScanner::class);

    $clean = $scanner->scan(
        'A harmless integration test document.'
    );

    expect($clean['status'])->toBe('clean');

    // Construct the standard harmless test signature in container memory.
    $eicar = 'X5O!P%@AP[4'
        .'\\PZX54(P^)7CC)7}'
        .'$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    $infected = $scanner->scan($eicar);

    expect($infected['status'])->toBe('rejected');

    fwrite(
        STDOUT,
        'REAL CLAMAV VERSION: '.$clean['version']
        ."; clean=clean; EICAR=rejected\n",
    );
});

test('real complete stores matching evidence and socket failure blocks use', function (): void {
    $owner = TestActors::customer();

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jAZkAAAAASUVORK5CYII='
    );

    $this->actingAs($owner);

    $uploaded = $this->post('/api/v1/files', [
        'file' => UploadedFile::fake()->createWithContent(
            'avatar.png',
            $png,
        ),
        'purpose' => 'avatar',
    ])->assertCreated();

    $id = $uploaded->json('data.id');

    $this->postJson('/api/v1/files/'.$id.'/complete')
        ->assertOk()
        ->assertJsonPath('data.antivirus_scanned', true);

    expect(File::findOrFail($id)->scanned_sha256)
        ->toBe(hash('sha256', $png));

    $unavailable = $this->post('/api/v1/files', [
        'file' => UploadedFile::fake()->createWithContent(
            'avatar.png',
            $png,
        ),
        'purpose' => 'avatar',
    ])->assertCreated()->json('data.id');

    config([
        'file-scanning.host' => '127.0.0.1',
        'file-scanning.port' => 1,
    ]);

    $this->postJson('/api/v1/files/'.$unavailable.'/complete')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '10')
        ->assertJsonPath('code', 'scanner_unavailable');

    expect(File::findOrFail($unavailable)->scan_status)
        ->toBe('pending');

    $this->get('/api/v1/files/'.$unavailable.'/download')
        ->assertConflict();
});

test('archive guard refuses oversized entries before scanner acceptance', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'k037-');

    if ($temporary === false) {
        throw new RuntimeException(
            'Cannot create integration fixture.'
        );
    }

    $path = $temporary.'.zip';

    try {
        $archive = new PharData(
            $path,
            0,
            null,
            Phar::ZIP,
        );

        $archive->addFromString(
            'large.txt',
            str_repeat('A', 26 * 1024 * 1024),
        );

        $archive->compressFiles(Phar::GZ);
        unset($archive);

        $bytes = file_get_contents($path);

        expect(strlen($bytes))
            ->toBeLessThan(5 * 1024 * 1024);

        $result = app(ClamAvScanner::class)->scan($bytes);

        expect($result['status'])->toBe('unavailable');

        // A connection error alone must not pass this assertion.
        expect($result['reason'])->toBe('incomplete_scan');

        fwrite(
            STDOUT,
            "BACKEND ZIP GUARD: 26 MiB entry refused before scanner acceptance; not clean\n",
        );
    } finally {
        if (is_file($path)) {
            unlink($path);
        }

        unlink($temporary);
    }
});

test('ordinary ZIP passes guard and is scanned by real daemon', function (): void {
    $temporary = tempnam(sys_get_temp_dir(), 'k037-safe-');
    $path = $temporary.'.zip';

    try {
        $archive = new PharData($path, 0, null, Phar::ZIP);

        $archive->addFromString(
            'word/document.xml',
            '<document><body>Hello.</body></document>',
        );

        unset($archive);

        $result = app(ClamAvScanner::class)->scan(
            file_get_contents($path)
        );

        expect($result['status'])->toBe('clean');
        expect($result['version'])->toStartWith('ClamAV ');

        fwrite(
            STDOUT,
            'REAL CLAMAV: ordinary ZIP passed preflight and daemon scan; '
            .$result['version']."\n",
        );
    } finally {
        if (is_file($path)) {
            unlink($path);
        }

        unlink($temporary);
    }
});
