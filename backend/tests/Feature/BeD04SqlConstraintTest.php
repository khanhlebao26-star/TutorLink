<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BeD04SqlConstraintTest extends TestCase
{
    private int $savepointCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('BE-D04 SQL constraint checks require PostgreSQL.');
        }
    }

    public function test_fee_check_constraints_reject_invalid_sql_rows(): void
    {
        DB::beginTransaction();

        try {
            $feeId = DB::table('fee_records')->where('amount_minor', '>', 0)->value('id');
            $this->assertNotNull($feeId);

            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $feeId)->update(['amount_minor' => 9007199254740992]),
                'fee_records_d04_amount_check',
            );
            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $feeId)->update(['status' => 'confirmed']),
                'fee_records_d04_status_check',
            );
            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $feeId)->update(['period_end' => '2026-11-29']),
                'fee_records_d04_period_check',
            );
            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $feeId)->update(['status' => 'voided']),
                'fee_records_d04_void_metadata_check',
            );
            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $feeId)->update(['tutor_payment_mark' => 'marked_paid']),
                'fee_records_d04_payment_metadata_check',
            );

            $freeFeeId = DB::table('fee_records')->where('amount_minor', 0)->value('id');
            $this->assertNotNull($freeFeeId);
            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $freeFeeId)->update([
                    'tutor_payment_mark' => 'marked_paid',
                    'payment_marked_at' => now(),
                    'payment_marked_by' => DB::table('programs')->where('title', 'D04 Fixture Active')->value('customer_id'),
                ]),
                'fee_records_d04_zero_amount_check',
            );

            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->where('id', $feeId)->update([
                    'tutor_payment_mark' => 'marked_paid',
                    'payment_marked_at' => now(),
                    'payment_marked_by' => 9223372036854775807,
                ]),
                'fee_records_payment_marked_by_foreign',
            );

            $programId = DB::table('programs')->where('title', 'D04 Fixture Active')->value('id');
            $this->assertConstraintRejects(
                fn () => DB::table('fee_records')->insert([
                    'program_id' => $programId,
                    'period_start' => DB::table('fee_records')->where('id', $feeId)->value('period_start'),
                    'period_end' => DB::table('fee_records')->where('id', $feeId)->value('period_end'),
                    'amount_minor' => 1,
                    'currency' => 'VND',
                ]),
                'fee_records_program_id_period_start_unique',
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    #[DataProvider('invalidProgressRows')]
    public function test_progress_check_constraints_reject_invalid_sql_rows(string $column, mixed $value, string $constraint): void
    {
        DB::beginTransaction();

        try {
            $progressId = DB::table('progress_records')
                ->where('note', 'D04_FIXTURE_PROGRESS_001')
                ->value('id');

            $this->assertConstraintRejects(
                fn () => DB::table('progress_records')->where('id', $progressId)->update([$column => $value]),
                $constraint,
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public static function invalidProgressRows(): array
    {
        return [
            'value upper bound' => ['value', 101, 'progress_records_d04_metric_value_check'],
            'wrong metric' => ['metric_name', 'attendance', 'progress_records_d04_metric_value_check'],
            'version lower bound' => ['version', 0, 'progress_records_d04_version_check'],
        ];
    }

    public function test_announcement_version_check_rejects_invalid_sql_row(): void
    {
        DB::beginTransaction();

        try {
            $announcementId = DB::table('announcements')
                ->where('title', 'D04 Fixture Draft Owner Only')
                ->value('id');

            $this->assertConstraintRejects(
                fn () => DB::table('announcements')->where('id', $announcementId)->update(['version' => 0]),
                'announcements_d04_version_check',
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    private function assertConstraintRejects(callable $mutation, string $constraint): void
    {
        $savepoint = 'd04_constraint_'.(++$this->savepointCounter);
        DB::statement("SAVEPOINT {$savepoint}");

        try {
            $mutation();
        } catch (QueryException $exception) {
            DB::statement("ROLLBACK TO SAVEPOINT {$savepoint}");
            DB::statement("RELEASE SAVEPOINT {$savepoint}");
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        DB::statement("RELEASE SAVEPOINT {$savepoint}");
        $this->fail("Expected PostgreSQL to reject invalid row with {$constraint}.");
    }
}
