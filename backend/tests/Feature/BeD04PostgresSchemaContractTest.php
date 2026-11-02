<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BeD04PostgresSchemaContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('BE-D04 schema contract checks require PostgreSQL.');
        }
    }

    #[DataProvider('feeMetadataColumns')]
    public function test_fee_schema_has_contract_metadata(string $column): void
    {
        $exists = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'fee_records')
            ->where('column_name', $column)
            ->exists();

        $this->assertTrue($exists, "D04-DB-01 missing fee_records.{$column}");
    }

    public static function feeMetadataColumns(): array
    {
        return [
            'status' => ['status'],
            'tutor payment mark' => ['tutor_payment_mark'],
            'version' => ['version'],
            'void timestamp' => ['voided_at'],
            'void actor' => ['voided_by'],
            'void reason' => ['void_reason'],
            'payment timestamp' => ['payment_marked_at'],
            'payment actor' => ['payment_marked_by'],
            'payment reason' => ['payment_mark_reason'],
            'last change reason' => ['last_change_reason'],
        ];
    }

    public function test_progress_schema_uses_integer_value_and_version(): void
    {
        $columns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'progress_records')
            ->whereIn('column_name', ['value', 'version'])
            ->pluck('data_type', 'column_name')
            ->all();

        $this->assertSame('smallint', $columns['value'] ?? null, 'D04-DB-02 Progress value must be smallint.');
        $this->assertSame('integer', $columns['version'] ?? null, 'D04-DB-02 Progress version column is missing.');
    }

    public function test_announcement_schema_has_version(): void
    {
        $exists = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', 'announcements')
            ->where('column_name', 'version')
            ->exists();

        $this->assertTrue($exists, 'D04-DB-03 Announcement version column is missing.');
    }

    public function test_fee_check_constraints_cover_contract_states_and_bounds(): void
    {
        $definitions = DB::table('pg_constraint')
            ->where('conrelid', DB::raw("'public.fee_records'::regclass"))
            ->where('contype', 'c')
            ->selectRaw('pg_get_constraintdef(oid) AS definition')
            ->pluck('definition')
            ->map(fn (string $definition): string => strtolower($definition))
            ->implode(' ');

        foreach ([
            'amount_minor <=',
            "currency = 'vnd'",
            'tutor_payment_mark',
            'voided_at',
            'payment_marked_at',
            'period_start',
            'period_end',
        ] as $requiredFragment) {
            $this->assertStringContainsString(
                $requiredFragment,
                $definitions,
                "D04-DB-04 missing Fee CHECK coverage for {$requiredFragment}.",
            );
        }
    }
}
