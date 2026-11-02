<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

class BeD04MigrationPreflightTest extends TestCase
{
    private const MIGRATION = '2026_11_01_233000_add_be_d04_workspace_schema';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('BE-D04 migration preflight tests require PostgreSQL.');
        }
    }

    #[DataProvider('ambiguousFeeStatuses')]
    public function test_ambiguous_legacy_fee_status_fails_before_schema_changes(string $status): void
    {
        DB::beginTransaction();

        try {
            $programId = DB::table('programs')
                ->where('title', 'D04 Fixture Completed')
                ->value('id');

            DB::table('fee_records')->insert([
                'program_id' => $programId,
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-30',
                'amount_minor' => 300000,
                'currency' => 'VND',
                'declared_status' => $status,
            ]);

            [$exitCode, $output] = $this->runMigration();

            $this->assertNotSame(0, $exitCode, $output);
            $this->assertStringContainsString('requires explicit mapping', $output);
            $this->assertTrue(Schema::hasColumn('fee_records', 'declared_status'));
            $this->assertFalse(Schema::hasColumn('fee_records', 'status'));
            $this->assertFalse(
                DB::table('migrations')->where('migration', self::MIGRATION)->exists(),
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public static function ambiguousFeeStatuses(): array
    {
        return [
            'confirmed requires decision' => ['confirmed'],
            'cancelled requires decision' => ['cancelled'],
        ];
    }

    public function test_fractional_legacy_progress_fails_before_type_change(): void
    {
        DB::beginTransaction();

        try {
            DB::table('progress_records')
                ->where('note', 'D04_FIXTURE_PROGRESS_001')
                ->update(['value' => 35.5]);

            [$exitCode, $output] = $this->runMigration();

            $this->assertNotSame(0, $exitCode, $output);
            $this->assertStringContainsString('invalid Progress rows', $output);
            $this->assertFalse(
                DB::table('migrations')->where('migration', self::MIGRATION)->exists(),
            );
            $this->assertSame(
                'numeric',
                DB::table('information_schema.columns')
                    ->where('table_schema', 'public')
                    ->where('table_name', 'progress_records')
                    ->where('column_name', 'value')
                    ->value('data_type'),
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_wrong_legacy_progress_metric_fails_before_type_change(): void
    {
        DB::beginTransaction();

        try {
            DB::table('progress_records')
                ->where('note', 'D04_FIXTURE_PROGRESS_001')
                ->update(['metric_name' => 'attendance']);

            [$exitCode, $output] = $this->runMigration();

            $this->assertNotSame(0, $exitCode, $output);
            $this->assertStringContainsString('invalid Progress rows', $output);
            $this->assertFalse(
                DB::table('migrations')->where('migration', self::MIGRATION)->exists(),
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_wrong_legacy_progress_scope_fails_before_type_change(): void
    {
        DB::beginTransaction();

        try {
            $outsiderId = DB::table('users')
                ->where('email', 'be-d04-outsider@tutorlink.test')
                ->value('id');

            DB::table('progress_records')
                ->where('note', 'D04_FIXTURE_PROGRESS_001')
                ->update(['customer_id' => $outsiderId]);

            [$exitCode, $output] = $this->runMigration();

            $this->assertNotSame(0, $exitCode, $output);
            $this->assertStringContainsString('invalid Progress rows', $output);
            $this->assertFalse(
                DB::table('migrations')->where('migration', self::MIGRATION)->exists(),
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_invalid_legacy_announcement_content_fails_before_version_backfill(): void
    {
        DB::beginTransaction();

        try {
            DB::table('announcements')
                ->where('title', 'D04 Fixture Draft Owner Only')
                ->update(['title' => '   ']);

            [$exitCode, $output] = $this->runMigration();

            $this->assertNotSame(0, $exitCode, $output);
            $this->assertStringContainsString('invalid Announcement content rows', $output);
            $this->assertFalse(
                DB::table('migrations')->where('migration', self::MIGRATION)->exists(),
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public function test_invalid_fee_period_fails_before_status_rename(): void
    {
        DB::beginTransaction();

        try {
            $programId = DB::table('programs')
                ->where('title', 'D04 Fixture Completed')
                ->value('id');

            DB::table('fee_records')->insert([
                'program_id' => $programId,
                'period_start' => '2026-09-01',
                'period_end' => '2026-09-02',
                'amount_minor' => 300000,
                'currency' => 'VND',
                'declared_status' => 'declared',
            ]);

            [$exitCode, $output] = $this->runMigration();

            $this->assertNotSame(0, $exitCode, $output);
            $this->assertStringContainsString('invalid Fee rows', $output);
            $this->assertTrue(Schema::hasColumn('fee_records', 'declared_status'));
            $this->assertFalse(Schema::hasColumn('fee_records', 'status'));
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    private function runMigration(): array
    {
        try {
            $exitCode = Artisan::call('migrate', [
                '--path' => 'database/migrations/'.self::MIGRATION.'.php',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            return [$exitCode, Artisan::output()];
        } catch (Throwable $error) {
            return [1, Artisan::output().' '.$error->getMessage()];
        }
    }
}
