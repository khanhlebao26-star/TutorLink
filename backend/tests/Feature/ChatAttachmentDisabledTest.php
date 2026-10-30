<?php

use App\Models\File as FileRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');

    $this->customer = TestActors::customer();
    $this->tutor = ProgramApiFixtures::eligibleTutor();
    $this->chat = Fixtures::conversation(
        $this->customer,
        $this->tutor,
    );
    $this->url = '/api/v1/conversations/'
        .$this->chat.'/messages';
});

/** Synthetic file fixture, without antivirus evidence. */
function k038FileFixture(User $owner, string $purpose): FileRecord
{
    $bytes = "%PDF-1.4\nSynthetic K03.8 fixture.\n%%EOF";

    $file = FileRecord::create([
        'owner_id' => $owner->id,
        'disk' => 'local',
        'object_key' => 'k038/'.Str::uuid().'.pdf',
        'original_name' => 'fixture.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($bytes),
        'visibility' => 'private',
        'checksum_sha256' => hash('sha256', $bytes),
        'scan_status' => 'clean',
        'purpose' => $purpose,
    ]);

    Storage::disk('local')->put($file->object_key, $bytes);

    return $file;
}

test('file_ids accepts only omission or an empty JSON array', function (string $rawValue): void {
    $payload = Fixtures::payload();
    $payload['file_ids'] = json_decode(
        $rawValue,
        flags: JSON_THROW_ON_ERROR,
    );

    $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('file_ids');

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('file_links', 0);
})->with([
    'empty object' => ['{}'],
    'null' => ['null'],
    'empty string' => ['""'],
    'string' => ['"1"'],
    'number' => ['1'],
    'false' => ['false'],
    'true' => ['true'],
    'file ID array' => ['[1]'],
    'empty string array' => ['[""]'],
    'object array' => ['[{}]'],
    'object with file ID' => ['{"file_id":1}'],
]);

test('omitted file_ids and empty array replay the same text', function (): void {
    $payload = Fixtures::payload("  Chào\r\nthầy  ");
    unset($payload['file_ids']);

    $first = $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertCreated();

    $payload['file_ids'] = [];

    $this->postJson($this->url, $payload)
        ->assertOk()
        ->assertJsonPath('data', $first->json('data'));

    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseCount('file_links', 0);
});

test('client cannot send file or system messages', function (string $messageType): void {
    $payload = Fixtures::payload();
    $payload['message_type'] = $messageType;

    $this->actingAs($this->customer)
        ->postJson($this->url, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message_type');

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('file_links', 0);
})->with(['file', 'system']);

test('multipart cannot attach a binary file to a text message', function (): void {
    $filesBefore = DB::table('files')->count();

    $this->actingAs($this->customer)
        ->post($this->url, [
            ...Fixtures::payload(),
            'file' => UploadedFile::fake()->createWithContent(
                'attachment.pdf',
                "%PDF-1.4\nSynthetic attachment.\n%%EOF",
            ),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('file_links', 0);
    $this->assertDatabaseCount('files', $filesBefore);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('message attachment purpose cannot be uploaded', function (): void {
    $filesBefore = DB::table('files')->count();

    $this->actingAs($this->customer)
        ->post('/api/v1/files', [
            'purpose' => 'message_attachment',
            'file' => UploadedFile::fake()->createWithContent(
                'attachment.pdf',
                "%PDF-1.4\nSynthetic attachment.\n%%EOF",
            ),
        ], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('purpose');

    $this->assertDatabaseCount('files', $filesBefore);
    $this->assertDatabaseCount('file_links', 0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('an owned complete file cannot be linked to a real message', function (): void {
    $messageId = Fixtures::seed(
        $this->chat,
        $this->customer,
    );
    $file = k038FileFixture(
        $this->customer,
        'profile_document',
    );

    $this->actingAs($this->customer)
        ->postJson('/api/v1/files/'.$file->id.'/links', [
            'resource_type' => 'message',
            'resource_id' => $messageId,
            'purpose' => 'message_attachment',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resource_type');

    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseCount('file_links', 0);
    Storage::disk('local')->assertExists($file->object_key);
});

test('legacy message files and links remain unchanged when new linking is denied', function (): void {
    $messageId = Fixtures::seed(
        $this->chat,
        $this->customer,
        [
            'message_type' => 'file',
            'body' => null,
        ],
    );

    $file = k038FileFixture(
        $this->customer,
        'message_attachment',
    );

    DB::table('file_links')->insert([
        'file_id' => $file->id,
        'resource_type' => 'message',
        'resource_id' => $messageId,
        'purpose' => 'message_attachment',
        'created_at' => now(),
    ]);

    $messageBefore = DB::table('messages')
        ->where('id', $messageId)
        ->first();
    $linksBefore = DB::table('file_links')
        ->where('resource_type', 'message')
        ->where('resource_id', $messageId)
        ->get()
        ->toJson();

    $this->actingAs($this->customer)
        ->postJson('/api/v1/files/'.$file->id.'/links', [
            'resource_type' => 'message',
            'resource_id' => $messageId,
            'purpose' => 'message_attachment',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('resource_type');

    $payload = Fixtures::payload('Không đổi lịch sử');
    $payload['client_message_id'] = $messageBefore->client_message_id;

    $this->postJson($this->url, $payload)
        ->assertConflict()
        ->assertJsonPath('code', 'client_message_id_conflict');

    expect(
        DB::table('messages')->where('id', $messageId)->first()
    )->toEqual($messageBefore);

    expect(
        DB::table('file_links')
            ->where('resource_type', 'message')
            ->where('resource_id', $messageId)
            ->get()
            ->toJson()
    )->toBe($linksBefore);

    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseCount('file_links', 1);
    Storage::disk('local')->assertExists($file->object_key);
});
