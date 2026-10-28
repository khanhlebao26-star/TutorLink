<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\File;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MessagingSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('K03.2 acceptance requires PostgreSQL constraints and transactional DDL.');
        }
    }

    public function test_members_and_messages_follow_conversation_relationships(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $otherTutor = User::factory()->create(['role' => 'tutor']);
        $conversation = $this->conversation($customer, $tutor);
        $otherConversation = $this->conversation($customer, $otherTutor);
        $message = $this->message($conversation, $customer);
        $this->message($otherConversation, $customer);

        $conversation->members()->updateExistingPivot($customer->id, [
            'last_read_message_id' => $message->id,
            'last_read_at' => now(),
        ]);

        $this->assertEqualsCanonicalizing(
            [$customer->id, $tutor->id],
            $conversation->members()->pluck('users.id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$conversation->id, $otherConversation->id],
            $customer->conversations()->pluck('conversations.id')->all(),
        );
        $this->assertSame([$message->id], $conversation->messages()->pluck('id')->all());
        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($message->sender->is($customer));

        $member = $conversation->members()->where('users.id', $customer->id)->firstOrFail();
        $this->assertSame($message->id, (int) $member->pivot->last_read_message_id);
        $this->assertNotNull($member->pivot->last_read_at);
        $this->assertNotNull($member->pivot->joined_at);
    }

    public function test_attachments_do_not_include_other_resource_types_with_the_same_id(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $message = $this->message($this->conversation($customer, $tutor), $customer);
        $attachment = $this->file($customer);
        $taskFile = $this->file($customer);

        $message->attachments()->attach($attachment->id, ['purpose' => 'message_attachment']);
        DB::table('file_links')->insert([
            'file_id' => $taskFile->id,
            'resource_type' => 'task',
            'resource_id' => $message->id,
            'purpose' => 'task_attachment',
        ]);

        $this->assertSame([$attachment->id], $message->attachments()->pluck('files.id')->all());
        $this->assertDatabaseHas('file_links', [
            'file_id' => $attachment->id,
            'resource_type' => 'message',
            'resource_id' => $message->id,
        ]);
        $this->assertDatabaseHas('file_links', [
            'file_id' => $taskFile->id,
            'resource_type' => 'task',
            'resource_id' => $message->id,
        ]);
    }

    public function test_database_defaults_hash_column_and_cursor_index_match_the_contract(): void
    {
        $id = DB::table('conversations')->insertGetId(['direct_key' => 'schema:'.Str::uuid()]);
        $row = DB::table('conversations')->where('id', $id)->first();

        $this->assertSame('active', $row->status);
        $this->assertSame('direct', $row->type);
        $this->assertTrue(Schema::hasIndex('messages', ['conversation_id', 'created_at', 'id']));
        $this->assertFalse(Schema::hasIndex('messages', 'messages_conversation_created_index'));
        $this->assertTrue(Schema::hasIndex('conversations', ['direct_key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('messages', ['sender_id', 'client_message_id'], 'unique'));

        $column = DB::selectOne(
            'SELECT data_type, character_maximum_length, is_nullable FROM information_schema.columns '
            ."WHERE table_schema = current_schema() AND table_name = 'messages' AND column_name = 'payload_hash'"
        );

        $this->assertNotNull($column);
        $this->assertSame('character', $column->data_type);
        $this->assertSame(64, (int) $column->character_maximum_length);
        $this->assertSame('YES', $column->is_nullable);
    }

    public function test_database_rejects_duplicate_direct_keys(): void
    {
        $key = 'duplicate:'.Str::uuid();
        DB::table('conversations')->insert(['direct_key' => $key]);

        $this->assertRejectedWithSqlState('23505', function () use ($key): void {
            DB::table('conversations')->insert(['direct_key' => $key]);
        });

        $this->assertSame(1, DB::table('conversations')->where('direct_key', $key)->count());
    }

    public function test_message_keys_are_unique_per_sender_across_conversations(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $otherTutor = User::factory()->create(['role' => 'tutor']);
        $conversation = $this->conversation($customer, $tutor);
        $otherConversation = $this->conversation($customer, $otherTutor);
        $key = (string) Str::uuid();
        $this->message($conversation, $customer, $key);

        $this->assertRejectedWithSqlState('23505', function () use ($conversation, $customer, $key): void {
            $this->message($conversation, $customer, $key);
        });
        $this->assertRejectedWithSqlState('23505', function () use ($otherConversation, $customer, $key): void {
            $this->message($otherConversation, $customer, $key);
        });

        $this->message($conversation, $tutor, $key);
        $this->assertSame(2, DB::table('messages')->where('client_message_id', $key)->count());
    }

    public function test_postgres_rejects_legacy_statuses_types_and_missing_direct_keys(): void
    {
        foreach (['pending', 'expired'] as $status) {
            $this->assertRejectedWithSqlState('23514', function () use ($status): void {
                DB::table('conversations')->insert([
                    'direct_key' => 'status:'.Str::uuid(),
                    'status' => $status,
                ]);
            });
        }

        $this->assertRejectedWithSqlState('23514', function (): void {
            DB::table('conversations')->insert(['type' => 'program']);
        });
        $this->assertRejectedWithSqlState('23514', function (): void {
            DB::table('conversations')->insert(['type' => 'direct', 'direct_key' => null]);
        });

        DB::table('conversations')->insert([
            'direct_key' => 'cancelled:'.Str::uuid(),
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('conversations', ['status' => 'cancelled']);
    }

    public function test_migration_can_round_trip_on_empty_messaging_tables(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('messages', 'payload_hash'));
        $this->assertTrue(Schema::hasIndex('messages', 'messages_conversation_created_index'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('messages', 'payload_hash'));
        $this->assertTrue(Schema::hasIndex('messages', ['conversation_id', 'created_at', 'id']));
        $this->assertFalse(Schema::hasIndex('messages', 'messages_conversation_created_index'));
    }

    public function test_migration_refuses_legacy_conversations_without_modifying_them(): void
    {
        $migration = $this->migration();
        $migration->down();

        foreach ([['direct', 'pending'], ['direct', 'expired'], ['program', 'active']] as [$type, $status]) {
            $id = DB::table('conversations')->insertGetId([
                'type' => $type,
                'status' => $status,
                'direct_key' => $type === 'direct' ? 'legacy:'.Str::uuid() : null,
            ]);
            $before = (array) DB::table('conversations')->where('id', $id)->first();

            $this->assertMigrationRefused(
                $migration,
                'Legacy conversations require an explicit mapping before migration.',
            );

            $this->assertSame($before, (array) DB::table('conversations')->where('id', $id)->first());
            $this->assertFalse(Schema::hasColumn('messages', 'payload_hash'));
            $this->assertTrue(Schema::hasIndex('messages', 'messages_conversation_created_index'));
            $this->assertFalse(Schema::hasIndex('messages', ['conversation_id', 'created_at', 'id']));

            DB::table('conversations')->where('id', $id)->delete();
        }

        $migration->up();
    }

    public function test_migration_refuses_existing_messages_without_modifying_them(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $conversation = $this->conversation($customer, $tutor);
        $message = $this->message($conversation, $customer);
        $migration = $this->migration();
        $migration->down();
        $before = (array) DB::table('messages')->where('id', $message->id)->first();

        $this->assertMigrationRefused(
            $migration,
            'Existing messages require a canonical payload backfill plan before migration.',
        );

        $this->assertSame($before, (array) DB::table('messages')->where('id', $message->id)->first());
        $this->assertDatabaseHas('conversations', ['id' => $conversation->id, 'status' => 'active']);
        $this->assertSame(2, DB::table('conversation_members')->where('conversation_id', $conversation->id)->count());
        $this->assertFalse(Schema::hasColumn('messages', 'payload_hash'));
        $this->assertTrue(Schema::hasIndex('messages', 'messages_conversation_created_index'));
    }

    private function conversation(User $customer, User $tutor): Conversation
    {
        $ids = [$customer->id, $tutor->id];
        sort($ids, SORT_NUMERIC);
        $conversation = Conversation::create(['direct_key' => implode(':', $ids)]);
        $conversation->members()->attach([$customer->id, $tutor->id]);

        return $conversation;
    }

    private function message(Conversation $conversation, User $sender, ?string $key = null): Message
    {
        return Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'client_message_id' => $key ?? (string) Str::uuid(),
            'message_type' => 'text',
            'body' => 'Schema test message',
        ]);
    }

    /** Metadata-only fixture: no upload or antivirus claim is made by this test. */
    private function file(User $owner): File
    {
        return File::create([
            'owner_id' => $owner->id,
            'disk' => 'local',
            'object_key' => 'tests/'.Str::uuid().'.pdf',
            'original_name' => 'attachment.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'visibility' => 'private',
            'scan_status' => 'pending',
            'purpose' => 'message_attachment',
        ]);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_28_034616_align_messaging_contract.php');
    }

    private function assertRejectedWithSqlState(string $state, callable $operation): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0] ?? $exception->getCode());

            return;
        }

        $this->fail('Expected PostgreSQL SQLSTATE '.$state.'.');
    }

    private function assertMigrationRefused(Migration $migration, string $message): void
    {
        try {
            $migration->up();
        } catch (RuntimeException $exception) {
            $this->assertSame($message, $exception->getMessage());

            return;
        }

        $this->fail('Expected the migration to refuse legacy data.');
    }
}
