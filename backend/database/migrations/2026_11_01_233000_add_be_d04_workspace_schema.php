<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAX_SAFE_AMOUNT = 9007199254740991;

    public function up(): void
    {
        $this->assertConvertibleLegacyData();

        DB::statement(
            'ALTER TABLE progress_records '
            .'ALTER COLUMN value TYPE smallint USING value::smallint',
        );

        Schema::table('progress_records', function (Blueprint $table): void {
            $table->integer('version')->default(1);
        });

        DB::statement(
            'ALTER TABLE progress_records '
            .'ADD CONSTRAINT progress_records_d04_metric_value_check '
            ."CHECK (metric_name = 'progress_percent' AND value BETWEEN 0 AND 100)",
        );
        DB::statement(
            'ALTER TABLE progress_records '
            .'ADD CONSTRAINT progress_records_d04_version_check CHECK (version >= 1)',
        );
        DB::statement(
            'CREATE INDEX progress_records_program_recorded_id_d04_index '
            .'ON progress_records (program_id, recorded_at DESC, id DESC)',
        );

        Schema::table('announcements', function (Blueprint $table): void {
            $table->integer('version')->default(1);
        });
        DB::statement(
            'ALTER TABLE announcements '
            .'ADD CONSTRAINT announcements_d04_version_check CHECK (version >= 1)',
        );
        DB::statement(
            'CREATE INDEX announcements_program_published_id_d04_index '
            .'ON announcements (program_id, published_at DESC, id DESC)',
        );
        DB::statement(
            'CREATE INDEX announcements_program_draft_created_id_d04_index '
            .'ON announcements (program_id, created_at DESC NULLS LAST, id DESC) '
            .'WHERE published_at IS NULL',
        );

        DB::statement('ALTER TABLE fee_records DROP CONSTRAINT fee_records_amount_check');
        DB::statement('ALTER TABLE fee_records DROP CONSTRAINT fee_records_period_check');
        DB::statement('ALTER TABLE fee_records DROP CONSTRAINT fee_records_status_check');
        DB::statement('ALTER TABLE fee_records RENAME COLUMN declared_status TO status');

        Schema::table('fee_records', function (Blueprint $table): void {
            $table->string('tutor_payment_mark', 20)->default('unmarked');
            $table->integer('version')->default(1);
            $table->timestampTz('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestampTz('payment_marked_at')->nullable();
            $table->foreignId('payment_marked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('payment_mark_reason')->nullable();
            $table->text('last_change_reason')->nullable();
        });

        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_amount_check '
            .'CHECK (amount_minor BETWEEN 0 AND '.self::MAX_SAFE_AMOUNT.')',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_period_check '
            .'CHECK (period_start = date_trunc(\'month\', period_start::timestamp)::date '
            .'AND period_end = (period_start + interval \'1 month - 1 day\')::date)',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_status_check CHECK (status IN (\'declared\', \'voided\'))',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_payment_mark_check '
            .'CHECK (tutor_payment_mark IN (\'unmarked\', \'marked_paid\', \'marked_unpaid\'))',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_version_check CHECK (version >= 1)',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_zero_amount_check '
            .'CHECK (amount_minor <> 0 OR tutor_payment_mark = \'unmarked\')',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_void_metadata_check '
            .'CHECK ((status = \'declared\' AND voided_at IS NULL AND voided_by IS NULL AND void_reason IS NULL) '
            .'OR (status = \'voided\' AND voided_at IS NOT NULL AND voided_by IS NOT NULL '
            .'AND void_reason IS NOT NULL AND btrim(void_reason) <> \'\' AND length(void_reason) <= 2000))',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_payment_metadata_check '
            .'CHECK ((tutor_payment_mark = \'unmarked\' AND payment_marked_at IS NULL '
            .'AND payment_marked_by IS NULL AND payment_mark_reason IS NULL) '
            .'OR (tutor_payment_mark IN (\'marked_paid\', \'marked_unpaid\') '
            .'AND payment_marked_at IS NOT NULL AND payment_marked_by IS NOT NULL '
            .'AND (payment_mark_reason IS NULL OR '
            .'(btrim(payment_mark_reason) <> \'\' AND length(payment_mark_reason) <= 2000))))',
        );
        DB::statement(
            'ALTER TABLE fee_records '
            .'ADD CONSTRAINT fee_records_d04_last_change_reason_check '
            .'CHECK (last_change_reason IS NULL OR '
            .'(btrim(last_change_reason) <> \'\' AND length(last_change_reason) <= 2000))',
        );
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Automatic rollback is disabled because it would discard Fee payment/void metadata '
            .'and cannot infer the legacy meaning of a voided record. Restore the test snapshot '
            .'or use a reviewed forward-fix.',
        );
    }

    private function assertConvertibleLegacyData(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('BE-D04 schema migration requires PostgreSQL.');
        }

        $ambiguousFeeStatuses = DB::table('fee_records')
            ->whereIn('declared_status', ['confirmed', 'cancelled'])
            ->selectRaw('declared_status, count(*) AS row_count')
            ->groupBy('declared_status')
            ->pluck('row_count', 'declared_status')
            ->all();

        if ($ambiguousFeeStatuses !== []) {
            throw new RuntimeException(
                'BE-D04 migration refused: legacy Fee confirmed/cancelled status requires explicit mapping '
                .json_encode($ambiguousFeeStatuses, JSON_THROW_ON_ERROR).'.',
            );
        }

        $invalidProgress = DB::table('progress_records as r')
            ->leftJoin('programs as p', 'p.id', '=', 'r.program_id')
            ->leftJoin('tutor_profiles as tp', 'tp.id', '=', 'p.owner_tutor_profile_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('p.id')
                    ->orWhereNull('tp.id')
                    ->orWhere('r.metric_name', '<>', 'progress_percent')
                    ->orWhereRaw('r.value <> trunc(r.value)')
                    ->orWhere('r.value', '<', 0)
                    ->orWhere('r.value', '>', 100)
                    ->orWhereColumn('r.customer_id', '<>', 'p.customer_id')
                    ->orWhereColumn('r.recorded_by', '<>', 'tp.user_id');
            })
            ->count();

        $invalidAnnouncements = DB::table('announcements as a')
            ->where(function ($query): void {
                $query
                    ->whereRaw('length(btrim(a.title)) NOT BETWEEN 1 AND 180')
                    ->orWhereRaw('length(a.body) NOT BETWEEN 1 AND 10000')
                    ->orWhereRaw("btrim(a.body) = ''");
            })
            ->count();

        $invalidAnnouncementLinks = DB::table('file_links as l')
            ->leftJoin('announcements as a', function ($join): void {
                $join->on('a.id', '=', 'l.resource_id')->where('l.resource_type', '=', 'announcement');
            })
            ->leftJoin('files as f', 'f.id', '=', 'l.file_id')
            ->leftJoin('programs as p', 'p.id', '=', 'a.program_id')
            ->leftJoin('tutor_profiles as tp', 'tp.id', '=', 'p.owner_tutor_profile_id')
            ->where('l.resource_type', 'announcement')
            ->where(function ($query): void {
                $query
                    ->whereNull('a.id')
                    ->orWhereNull('f.id')
                    ->orWhereNull('tp.id')
                    ->orWhereColumn('a.author_id', '<>', 'tp.user_id')
                    ->orWhereColumn('f.owner_id', '<>', 'a.author_id')
                    ->orWhereNull('l.purpose')
                    ->orWhere('l.purpose', '<>', 'announcement_attachment')
                    ->orWhereNull('f.purpose')
                    ->orWhere('f.purpose', '<>', 'announcement_attachment')
                    ->orWhere('f.scan_status', '<>', 'clean');
            })
            ->count();

        $tooManyAnnouncementFiles = DB::selectOne(
            'SELECT count(*) AS count FROM (SELECT resource_id FROM file_links '
            ."WHERE resource_type = 'announcement' GROUP BY resource_id "
            .'HAVING count(DISTINCT file_id) > 5) too_many_files',
        );

        $invalidFee = DB::table('fee_records as f')
            ->leftJoin('programs as p', 'p.id', '=', 'f.program_id')
            ->leftJoin('pg_timezone_names as tz', 'tz.name', '=', 'p.timezone')
            ->where(function ($query): void {
                $query
                    ->whereNull('p.id')
                    ->orWhereNull('p.activated_at')
                    ->orWhereNull('tz.name')
                    ->orWhere('f.amount_minor', '<', 0)
                    ->orWhere('f.amount_minor', '>', self::MAX_SAFE_AMOUNT)
                    ->orWhere('f.currency', '<>', 'VND')
                    ->orWhereRaw('extract(day from f.period_start) <> 1')
                    ->orWhereRaw("f.period_end <> (f.period_start + interval '1 month - 1 day')::date")
                    ->orWhereRaw("f.period_start < date_trunc('month', p.activated_at AT TIME ZONE p.timezone)::date")
                    ->orWhereRaw("f.period_start > date_trunc('month', CURRENT_TIMESTAMP AT TIME ZONE p.timezone)::date");
            })
            ->count();

        $duplicateFeeMonths = DB::selectOne(
            'SELECT count(*) AS count FROM ('
            .'SELECT program_id, date_trunc(\'month\', period_start)::date AS month_start '
            .'FROM fee_records GROUP BY program_id, date_trunc(\'month\', period_start)::date '
            .'HAVING count(*) > 1) duplicate_months',
        );

        $legacyPaymentAudit = DB::table('audit_logs')
            ->whereIn('resource_type', ['fee', 'fee_record', 'fee_records'])
            ->where(function ($query): void {
                $query
                    ->where('action', 'ILIKE', '%mark%')
                    ->orWhere('action', 'ILIKE', '%paid%')
                    ->orWhere('action', 'ILIKE', '%confirm%')
                    ->orWhereRaw("coalesce(after::text, '') ~* 'marked_paid|marked_unpaid|payment_marked'");
            })
            ->count();

        $issues = [
            'invalid Progress rows' => $invalidProgress,
            'invalid Announcement content rows' => $invalidAnnouncements,
            'invalid Announcement file links' => $invalidAnnouncementLinks,
            'Announcements with more than five linked files' => (int) ($tooManyAnnouncementFiles->count ?? 0),
            'invalid Fee rows' => $invalidFee,
            'duplicate normalized Fee months' => (int) ($duplicateFeeMonths->count ?? 0),
            'legacy Fee payment audit evidence rows' => $legacyPaymentAudit,
        ];
        $issues = array_filter($issues);

        if ($issues !== []) {
            throw new RuntimeException(
                'BE-D04 migration refused before schema changes: '
                .json_encode($issues, JSON_THROW_ON_ERROR).'.',
            );
        }
    }
};
