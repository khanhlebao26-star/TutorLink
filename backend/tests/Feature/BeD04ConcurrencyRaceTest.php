<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\TestDatabaseGuard;
use Tests\TestCase;

class BeD04ConcurrencyRaceTest extends TestCase
{
    private User $owner;

    private Program $active;

    private Program $paused;

    protected function setUp(): void
    {
        parent::setUp();
        TestDatabaseGuard::assertConnectedIdentity(config());
        $this->assertSame('pgsql', DB::getDriverName());
        $this->owner = User::query()->where('email', 'be-d04-owner@tutorlink.test')->firstOrFail();
        $this->active = Program::query()->where('title', 'D04 Fixture Active')->firstOrFail();
        $this->paused = Program::query()->where('title', 'D04 Fixture Paused')->firstOrFail();
        $this->preparePausedProgram();
    }

    public function test_contract_races_repeat_three_times_and_observe_real_postgres_lock_waits(): void
    {
        $this->feeCreateRaces();
        $this->pauseRaces();
        $this->terminalRaces();
        $this->feeMutationRaces();
        $this->announcementAndFileRaces();
    }

    private function feeCreateRaces(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $period = sprintf('2026-%02d', 7 + $run);
            $key = (string) Str::uuid();
            $path = "/api/v1/programs/{$this->paused->id}/fee-records";
            $body = ['period' => $period, 'amount_minor' => 1000 + $run];
            $race = $this->race('fee-create-same-key', $run, $this->owner,
                ['method' => 'POST', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => $key]],
                ['method' => 'POST', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => $key]]);
            $this->assertSame([201, 201], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame($this->canonicalize($race['first']['body']), $this->canonicalize($race['second']['body']));
            $this->assertSame((int) $race['first']['body']['data']['id'], (int) $race['second']['body']['data']['id']);
            $this->assertSame(1, DB::table('fee_records')->where('program_id', $this->paused->id)->where('period_start', $period.'-01')->count());
        }

        for ($run = 1; $run <= 3; $run++) {
            $period = sprintf('2026-%02d', 4 + $run);
            $path = "/api/v1/programs/{$this->paused->id}/fee-records";
            $body = ['period' => $period, 'amount_minor' => 2000 + $run];
            $race = $this->race('fee-create-different-keys-same-month', $run, $this->owner,
                ['method' => 'POST', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => (string) Str::uuid()]],
                ['method' => 'POST', 'path' => $path, 'body' => $body, 'headers' => ['Idempotency-Key' => (string) Str::uuid()]]);
            $this->assertSame([201, 409], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame('fee_period_exists', $race['second']['body']['code']);
            $this->assertSame(1, DB::table('fee_records')->where('program_id', $this->paused->id)->where('period_start', $period.'-01')->count());
        }

        for ($run = 1; $run <= 3; $run++) {
            $key = (string) Str::uuid();
            $firstPath = "/api/v1/programs/{$this->paused->id}/fee-records";
            $secondPath = "/api/v1/programs/{$this->active->id}/fee-records";
            $race = $this->race('same-key-across-fee-program-resources', $run, $this->owner,
                ['method' => 'POST', 'path' => $firstPath, 'body' => ['period' => sprintf('2026-%02d', $run), 'amount_minor' => 3000], 'headers' => ['Idempotency-Key' => $key]],
                ['method' => 'POST', 'path' => $secondPath, 'body' => ['period' => '2026-11', 'amount_minor' => 3000], 'headers' => ['Idempotency-Key' => $key]]);
            $this->assertSame([201, 409], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame('idempotency_conflict', $race['second']['body']['code']);
        }
    }

    private function pauseRaces(): void
    {
        $pausePath = "/api/v1/programs/{$this->paused->id}/pause";
        $activePausePath = "/api/v1/programs/{$this->active->id}/pause";
        $createPath = "/api/v1/programs/{$this->paused->id}/fee-records";

        for ($run = 1; $run <= 3; $run++) {
            $this->setProgramStatus($this->paused->id, 'active');
            $period = sprintf('2023-%02d', $run);
            $race = $this->race('fee-create-before-pause', $run, $this->owner,
                ['method' => 'POST', 'path' => $createPath, 'body' => ['period' => $period, 'amount_minor' => 5000], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]],
                ['method' => 'POST', 'path' => $pausePath, 'body' => []]);
            $this->assertSame([201, 200], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame('paused', DB::table('programs')->where('id', $this->paused->id)->value('status'));
            $this->assertSame(1, DB::table('fee_records')->where('program_id', $this->paused->id)->where('period_start', $period.'-01')->count());
        }

        for ($run = 1; $run <= 3; $run++) {
            $this->setProgramStatus($this->paused->id, 'active');
            $period = sprintf('2023-%02d', 3 + $run);
            $race = $this->race('pause-before-fee-create', $run, $this->owner,
                ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/pause", 'body' => []],
                ['method' => 'POST', 'path' => $createPath, 'body' => ['period' => $period, 'amount_minor' => 6000], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]]);
            $this->assertSame([200, 409], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame('program_state_conflict', $race['second']['body']['code']);
            $this->assertSame(0, DB::table('fee_records')->where('program_id', $this->paused->id)->where('period_start', $period.'-01')->count());
        }

        $feeIds = DB::table('fee_records')->where('program_id', $this->active->id)->where('status', 'declared')->where('tutor_payment_mark', 'unmarked')->where('amount_minor', '>', 0)->where('version', 1)->orderBy('period_start')->limit(6)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertCount(6, $feeIds);

        foreach (['progress', 'draft', 'publish', 'fee'] as $kind) {
            foreach ([true, false] as $writerFirst) {
                for ($run = 1; $run <= 3; $run++) {
                    $this->setProgramStatus($this->active->id, 'active');
                    $writer = $this->pauseWriter($kind, $run, $writerFirst, $feeIds);
                    $progressCountBefore = $kind === 'progress' ? DB::table('progress_records')->where('program_id', $this->active->id)->count() : null;
                    $pause = ['method' => 'POST', 'path' => $activePausePath, 'body' => []];
                    $race = $writerFirst
                        ? $this->race("{$kind}-writer-before-pause", $run, $this->owner, $writer, $pause)
                        : $this->race("pause-before-{$kind}-writer", $run, $this->owner, $pause, $writer);
                    $writerResult = $writerFirst ? $race['first'] : $race['second'];
                    $pauseResult = $writerFirst ? $race['second'] : $race['first'];
                    $this->assertSame($kind === 'progress' ? 201 : 200, $writerResult['status']);
                    $this->assertSame(200, $pauseResult['status']);
                    $this->assertSame('paused', DB::table('programs')->where('id', $this->active->id)->value('status'));
                    $this->assertPauseWriterCommitted($kind, $run, $writer, $progressCountBefore);
                }
            }
        }
    }

    private function terminalRaces(): void
    {
        $this->preparePausedProgram();
        $periods = 0;

        foreach ([true, false] as $writerFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $period = $this->test_period('2025', ++$periods);
                $feeId = $this->insertFee($this->paused->id, $period, 10000);
                $this->setProgramStatus($this->paused->id, 'paused');
                $feeWrite = ['method' => 'PATCH', 'path' => "/api/v1/fee-records/{$feeId}", 'body' => ['expected_version' => 1, 'amount_minor' => 10100, 'reason' => "Fee update {$run}"], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $transition = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/complete", 'body' => []];
                $race = $writerFirst
                    ? $this->race('fee-update-before-complete', $run, $this->owner, $feeWrite, $transition)
                    : $this->race('complete-before-fee-update', $run, $this->owner, $transition, $feeWrite);
                $writeResult = $writerFirst ? $race['first'] : $race['second'];
                $transitionResult = $writerFirst ? $race['second'] : $race['first'];
                $this->assertSame($writerFirst ? 200 : 409, $writeResult['status']);
                $this->assertSame(200, $transitionResult['status']);
                $this->assertSame('completed', DB::table('programs')->where('id', $this->paused->id)->value('status'));
                $this->assertDatabaseHas('fee_records', [
                    'id' => $feeId,
                    'version' => $writerFirst ? 2 : 1,
                    'amount_minor' => $writerFirst ? 10100 : 10000,
                ]);
                $this->assertSame($writerFirst ? 1 : 0, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
                if (! $writerFirst) {
                    $this->assertSame('program_state_conflict', $writeResult['body']['code']);
                }
                $this->setProgramStatus($this->paused->id, 'paused');
            }
        }

        $newFeePeriod = 0;
        foreach (['complete', 'cancel'] as $transitionName) {
            foreach ([true, false] as $writerFirst) {
                for ($run = 1; $run <= 3; $run++) {
                    $this->setProgramStatus($this->paused->id, 'active');
                    $period = $this->test_period('2022', ++$newFeePeriod);
                    $writer = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/fee-records", 'body' => ['period' => $period, 'amount_minor' => 15000 + $run], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                    $transition = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/{$transitionName}", 'body' => []];
                    $race = $writerFirst
                        ? $this->race("fee-create-before-{$transitionName}", $run, $this->owner, $writer, $transition)
                        : $this->race("{$transitionName}-before-fee-create", $run, $this->owner, $transition, $writer);
                    $writeResult = $writerFirst ? $race['first'] : $race['second'];
                    $transitionResult = $writerFirst ? $race['second'] : $race['first'];
                    $this->assertSame($writerFirst ? 201 : 409, $writeResult['status']);
                    $this->assertSame(200, $transitionResult['status']);
                    $this->assertSame($writerFirst ? 1 : 0, DB::table('fee_records')->where('program_id', $this->paused->id)->where('period_start', $period.'-01')->count());
                    $this->assertSame($transitionName === 'complete' ? 'completed' : 'cancelled', DB::table('programs')->where('id', $this->paused->id)->value('status'));
                    if (! $writerFirst) {
                        $this->assertSame('program_state_conflict', $writeResult['body']['code']);
                    }
                }
            }
        }

        foreach ([true, false] as $writerFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->paused->id, 'paused');
                $month = $run + ($writerFirst ? 0 : 3);
                $feeId = $this->insertFee($this->paused->id, $this->test_period('2020', $month), 16000);
                $writer = ['method' => 'POST', 'path' => "/api/v1/fee-records/{$feeId}/mark-paid", 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $transition = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/complete", 'body' => []];
                $race = $writerFirst
                    ? $this->race('mark-paid-before-complete', $run, $this->owner, $writer, $transition)
                    : $this->race('complete-before-mark-paid', $run, $this->owner, $transition, $writer);
                $writeResult = $writerFirst ? $race['first'] : $race['second'];
                $transitionResult = $writerFirst ? $race['second'] : $race['first'];
                $this->assertSame($writerFirst ? 200 : 409, $writeResult['status']);
                $this->assertSame(200, $transitionResult['status']);
                $this->assertSame('completed', DB::table('programs')->where('id', $this->paused->id)->value('status'));
                $this->assertDatabaseHas('fee_records', [
                    'id' => $feeId,
                    'version' => $writerFirst ? 2 : 1,
                    'tutor_payment_mark' => $writerFirst ? 'marked_paid' : 'unmarked',
                ]);
                if (! $writerFirst) {
                    $this->assertSame('program_state_conflict', $writeResult['body']['code']);
                }
            }
        }

        foreach ([true, false] as $writerFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->paused->id, 'paused');
                $month = $run + ($writerFirst ? 0 : 3);
                $feeId = $this->insertFee($this->paused->id, $this->test_period('2021', $month), 17000);
                $writer = ['method' => 'POST', 'path' => "/api/v1/fee-records/{$feeId}/void", 'body' => ['expected_version' => 1, 'reason' => 'Terminal race void'], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $transition = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/cancel", 'body' => []];
                $race = $writerFirst
                    ? $this->race('void-before-cancel', $run, $this->owner, $writer, $transition)
                    : $this->race('cancel-before-void', $run, $this->owner, $transition, $writer);
                $writeResult = $writerFirst ? $race['first'] : $race['second'];
                $transitionResult = $writerFirst ? $race['second'] : $race['first'];
                $this->assertSame($writerFirst ? 200 : 409, $writeResult['status']);
                $this->assertSame(200, $transitionResult['status']);
                $this->assertSame('cancelled', DB::table('programs')->where('id', $this->paused->id)->value('status'));
                $this->assertDatabaseHas('fee_records', ['id' => $feeId, 'version' => $writerFirst ? 2 : 1, 'status' => $writerFirst ? 'voided' : 'declared']);
                if (! $writerFirst) {
                    $this->assertSame('program_state_conflict', $writeResult['body']['code']);
                }
            }
        }

        foreach ([true, false] as $writerFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->paused->id, 'paused');
                $announcementId = $this->createDraftForProgram($this->paused->id, "Terminal publish {$writerFirst}-{$run}");
                $writer = ['method' => 'POST', 'path' => "/api/v1/announcements/{$announcementId}/publish", 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $transition = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/cancel", 'body' => []];
                $race = $writerFirst
                    ? $this->race('announcement-publish-before-cancel', $run, $this->owner, $writer, $transition)
                    : $this->race('cancel-before-announcement-publish', $run, $this->owner, $transition, $writer);
                $writeResult = $writerFirst ? $race['first'] : $race['second'];
                $transitionResult = $writerFirst ? $race['second'] : $race['first'];
                $this->assertSame($writerFirst ? 200 : 409, $writeResult['status']);
                $this->assertSame(200, $transitionResult['status']);
                $this->assertSame('cancelled', DB::table('programs')->where('id', $this->paused->id)->value('status'));
                $this->assertDatabaseHas('announcements', ['id' => $announcementId, 'version' => $writerFirst ? 2 : 1]);
                $publishedAt = DB::table('announcements')->where('id', $announcementId)->value('published_at');
                if ($writerFirst) {
                    $this->assertNotNull($publishedAt);
                } else {
                    $this->assertNull($publishedAt);
                }
                if (! $writerFirst) {
                    $this->assertSame('program_state_conflict', $writeResult['body']['code']);
                }
            }
        }

        foreach ([true, false] as $writerFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->paused->id, 'paused');
                $progressCountBefore = DB::table('progress_records')->where('program_id', $this->paused->id)->count();
                $writer = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/progress-records", 'body' => ['metric_name' => 'progress_percent', 'value' => 60 + $run]];
                $transition = ['method' => 'POST', 'path' => "/api/v1/programs/{$this->paused->id}/cancel", 'body' => []];
                $race = $writerFirst
                    ? $this->race('progress-create-before-cancel', $run, $this->owner, $writer, $transition)
                    : $this->race('cancel-before-progress-create', $run, $this->owner, $transition, $writer);
                $writeResult = $writerFirst ? $race['first'] : $race['second'];
                $transitionResult = $writerFirst ? $race['second'] : $race['first'];
                $this->assertSame($writerFirst ? 201 : 409, $writeResult['status']);
                $this->assertSame(200, $transitionResult['status']);
                $this->assertSame('cancelled', DB::table('programs')->where('id', $this->paused->id)->value('status'));
                $this->assertSame($progressCountBefore + ($writerFirst ? 1 : 0), DB::table('progress_records')->where('program_id', $this->paused->id)->count());
                if (! $writerFirst) {
                    $this->assertSame('program_state_conflict', $writeResult['body']['code']);
                }
            }
        }
    }

    private function feeMutationRaces(): void
    {
        $this->setProgramStatus($this->paused->id, 'paused');

        for ($run = 1; $run <= 3; $run++) {
            $feeId = $this->insertFee($this->paused->id, $this->test_period('2024', $run), 12000);
            $race = $this->race('same-version-two-fee-edits', $run, $this->owner,
                ['method' => 'PATCH', 'path' => "/api/v1/fee-records/{$feeId}", 'body' => ['expected_version' => 1, 'amount_minor' => 12100, 'reason' => 'Concurrent edit A'], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]],
                ['method' => 'PATCH', 'path' => "/api/v1/fee-records/{$feeId}", 'body' => ['expected_version' => 1, 'amount_minor' => 12200, 'reason' => 'Concurrent edit B'], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]]);
            $this->assertSame([200, 409], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame('stale_version', $race['second']['body']['code']);
            $this->assertFeeRaceCommittedOnce($feeId);
            $this->assertContains((int) DB::table('fee_records')->where('id', $feeId)->value('amount_minor'), [12100, 12200]);
        }

        foreach (['mark-paid-first', 'void-first'] as $firstAction) {
            for ($run = 1; $run <= 3; $run++) {
                $feeId = $this->insertFee($this->paused->id, $this->test_period('2024', 3 + $run + ($firstAction === 'void-first' ? 3 : 0)), 13000);
                $paid = ['method' => 'POST', 'path' => "/api/v1/fee-records/{$feeId}/mark-paid", 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $void = ['method' => 'POST', 'path' => "/api/v1/fee-records/{$feeId}/void", 'body' => ['expected_version' => 1, 'reason' => 'Concurrent void'], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $race = $firstAction === 'mark-paid-first'
                    ? $this->race('same-version-mark-paid-vs-void', $run, $this->owner, $paid, $void)
                    : $this->race('same-version-void-vs-mark-paid', $run, $this->owner, $void, $paid);
                $this->assertSame([200, 409], [$race['first']['status'], $race['second']['status']]);
                $this->assertSame('stale_version', $race['second']['body']['code']);
                $this->assertFeeRaceCommittedOnce($feeId);
                $fee = DB::table('fee_records')->where('id', $feeId)->first();
                if ($firstAction === 'mark-paid-first') {
                    $this->assertSame('declared', $fee->status);
                    $this->assertSame('marked_paid', $fee->tutor_payment_mark);
                    $this->assertNotNull($fee->payment_marked_at);
                    $this->assertNull($fee->voided_at);
                } else {
                    $this->assertSame('voided', $fee->status);
                    $this->assertNotNull($fee->voided_at);
                    $this->assertNotNull($fee->voided_by);
                }
            }
        }
    }

    private function announcementAndFileRaces(): void
    {
        for ($run = 1; $run <= 3; $run++) {
            $this->setProgramStatus($this->active->id, 'active');
            $announcementId = $this->createDraft("Concurrent publish {$run}");
            $path = "/api/v1/announcements/{$announcementId}/publish";
            $publish = ['method' => 'POST', 'path' => $path, 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
            $race = $this->race('same-key-concurrent-announcement-publish', $run, $this->owner, $publish, $publish);
            $this->assertSame([200, 200], [$race['first']['status'], $race['second']['status']]);
            $this->assertSame($this->canonicalize($race['first']['body']), $this->canonicalize($race['second']['body']));
            $this->assertSame('published', $race['first']['body']['data']['state']);
            $this->assertSame(2, (int) DB::table('announcements')->where('id', $announcementId)->value('version'));
            $this->assertSame(1, DB::table('audit_logs')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->where('action', 'announcement.publish')->count());
            $this->assertSame(1, DB::table('notification_outbox')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
            $this->assertSame(1, DB::table('idempotency_receipts')->where('actor_id', $this->owner->id)->where('idempotency_key', $publish['headers']['Idempotency-Key'])->count());
        }

        foreach ([true, false] as $patchFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->active->id, 'active');
                $originalTitle = "PATCH race {$patchFirst}-{$run}";
                $announcementId = $this->createDraft($originalTitle);
                $itemPath = "/api/v1/announcements/{$announcementId}";
                $patch = ['method' => 'PATCH', 'path' => $itemPath, 'body' => ['expected_version' => 1, 'title' => "Edited {$run}"]];
                $publish = ['method' => 'POST', 'path' => $itemPath.'/publish', 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $race = $patchFirst
                    ? $this->race('draft-patch-before-publish', $run, $this->owner, $patch, $publish)
                    : $this->race('publish-before-draft-patch', $run, $this->owner, $publish, $patch);
                $this->assertSame([200, 409], [$race['first']['status'], $race['second']['status']]);
                $announcement = DB::table('announcements')->where('id', $announcementId)->first();
                $this->assertSame(2, (int) $announcement->version);
                $this->assertSame($patchFirst ? "Edited {$run}" : $originalTitle, $announcement->title);
                if ($patchFirst) {
                    $this->assertNull($announcement->published_at);
                } else {
                    $this->assertNotNull($announcement->published_at);
                }
            }
        }

        foreach ([true, false] as $replaceFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->active->id, 'active');
                $fileA = $this->cleanFile("race-a-{$run}");
                $fileB = $this->cleanFile("race-b-{$run}");
                $announcementId = $this->createDraft("File replace race {$replaceFirst}-{$run}", [$fileA]);
                $itemPath = "/api/v1/announcements/{$announcementId}";
                $replace = ['method' => 'PATCH', 'path' => $itemPath, 'body' => ['expected_version' => 1, 'file_ids' => [$fileB]]];
                $publish = ['method' => 'POST', 'path' => $itemPath.'/publish', 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $race = $replaceFirst
                    ? $this->race('file-replace-before-publish', $run, $this->owner, $replace, $publish)
                    : $this->race('publish-before-file-replace', $run, $this->owner, $publish, $replace);
                $this->assertSame([200, 409], [$race['first']['status'], $race['second']['status']]);
                $this->assertSame(1, DB::table('file_links')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->count());
                $this->assertSame(2, (int) DB::table('announcements')->where('id', $announcementId)->value('version'));
                $linkedFileId = (int) DB::table('file_links')->where('resource_type', 'announcement')->where('resource_id', $announcementId)->value('file_id');
                $this->assertSame($replaceFirst ? $fileB : $fileA, $linkedFileId);
                $publishedAt = DB::table('announcements')->where('id', $announcementId)->value('published_at');
                if ($replaceFirst) {
                    $this->assertNull($publishedAt);
                } else {
                    $this->assertNotNull($publishedAt);
                }
            }
        }

        foreach ([true, false] as $deleteFirst) {
            for ($run = 1; $run <= 3; $run++) {
                $this->setProgramStatus($this->active->id, 'active');
                $fileId = $this->cleanFile("race-delete-{$run}");
                $announcementId = $this->createDraft("File delete race {$deleteFirst}-{$run}", [$fileId]);
                $itemPath = "/api/v1/announcements/{$announcementId}";
                $delete = ['method' => 'DELETE', 'path' => "/api/v1/files/{$fileId}", 'body' => []];
                $publish = ['method' => 'POST', 'path' => $itemPath.'/publish', 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
                $race = $deleteFirst
                    ? $this->race('file-delete-before-publish', $run, $this->owner, $delete, $publish)
                    : $this->race('publish-before-file-delete', $run, $this->owner, $publish, $delete);
                $this->assertSame($deleteFirst ? [409, 200] : [200, 409], [$race['first']['status'], $race['second']['status']]);
                $this->assertDatabaseHas('files', ['id' => $fileId]);
                $this->assertDatabaseHas('file_links', ['file_id' => $fileId, 'resource_type' => 'announcement', 'resource_id' => $announcementId]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function pauseWriter(string $kind, int $run, bool $writerFirst, array $feeIds): array
    {
        return match ($kind) {
            'progress' => ['method' => 'POST', 'path' => "/api/v1/programs/{$this->active->id}/progress-records", 'body' => ['metric_name' => 'progress_percent', 'value' => 10 + $run]],
            'draft' => $this->draftPatchWriter($run, $writerFirst),
            'publish' => $this->publishWriter($run, $writerFirst),
            'fee' => $this->feePatchWriter($feeIds[($writerFirst ? 0 : 3) + $run - 1]),
        };
    }

    private function assertPauseWriterCommitted(string $kind, int $run, array $writer, ?int $progressCountBefore): void
    {
        if ($kind === 'progress') {
            $this->assertSame($progressCountBefore + 1, DB::table('progress_records')->where('program_id', $this->active->id)->count());

            return;
        }

        preg_match('~/(?:announcements|fee-records)/(\d+)~', $writer['path'], $matches);
        $id = (int) ($matches[1] ?? 0);
        $this->assertGreaterThan(0, $id);

        if ($kind === 'draft') {
            $this->assertDatabaseHas('announcements', ['id' => $id, 'version' => 2, 'title' => "Pause edit {$run}", 'published_at' => null]);
        } elseif ($kind === 'publish') {
            $this->assertDatabaseHas('announcements', ['id' => $id, 'version' => 2]);
            $this->assertNotNull(DB::table('announcements')->where('id', $id)->value('published_at'));
        } else {
            $this->assertDatabaseHas('fee_records', ['id' => $id, 'version' => 2, 'amount_minor' => $writer['body']['amount_minor']]);
        }
    }

    /** @return array<string, mixed> */
    private function draftPatchWriter(int $run, bool $writerFirst): array
    {
        $id = $this->createDraft("Pause draft {$writerFirst}-{$run}");

        return ['method' => 'PATCH', 'path' => "/api/v1/announcements/{$id}", 'body' => ['expected_version' => 1, 'title' => "Pause edit {$run}"]];
    }

    /** @return array<string, mixed> */
    private function publishWriter(int $run, bool $writerFirst): array
    {
        $id = $this->createDraft("Pause publish {$writerFirst}-{$run}");

        return ['method' => 'POST', 'path' => "/api/v1/announcements/{$id}/publish", 'body' => ['expected_version' => 1], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
    }

    /** @return array<string, mixed> */
    private function feePatchWriter(int $feeId): array
    {
        $amount = (int) DB::table('fee_records')->where('id', $feeId)->value('amount_minor');

        return ['method' => 'PATCH', 'path' => "/api/v1/fee-records/{$feeId}", 'body' => ['expected_version' => 1, 'amount_minor' => $amount + 7, 'reason' => 'D04-15 pause race'], 'headers' => ['Idempotency-Key' => (string) Str::uuid()]];
    }

    /** @return array<string, mixed> */
    private function race(string $case, int $run, User $actor, array $first, array $second): array
    {
        $process = null;
        DB::beginTransaction();

        try {
            $firstResult = $this->perform($actor, $first);
            $process = $this->startWorker($actor, $second);
            $lock = $this->waitForHeldLock($process);
            DB::commit();
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
            $this->assertSame(1, preg_match('/RESULT:(\{[^\n]+\})/', $process->getOutput(), $match), $process->getErrorOutput().$process->getOutput());
            $secondResult = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
            $result = ['first' => $firstResult, 'second' => $secondResult];
            fwrite(STDOUT, json_encode([
                'race' => $case,
                'run' => $run,
                'barrier' => $lock,
                'first_request' => $first,
                'second_request' => $second,
                'first' => $firstResult,
                'second' => $secondResult,
                'worker_exit_code' => $process->getExitCode(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

            return $result;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process !== null && $process->isRunning()) {
                $process->stop(0, 9);
            }
        }
    }

    /** @param array<string, mixed> $operation
     * @return array{status:int, body:array|null, request_id:string|null}
     */
    private function perform(User $actor, array $operation): array
    {
        $headers = $operation['headers'] ?? [];
        $body = $operation['body'] ?? [];
        $response = match (strtoupper($operation['method'])) {
            'PATCH' => $this->actingAs($actor)->patchJson($operation['path'], $body, $headers),
            'DELETE' => $this->actingAs($actor)->deleteJson($operation['path'], [], $headers),
            'POST' => $body === [] && $headers === []
                ? $this->actingAs($actor)->postJson($operation['path'])
                : $this->actingAs($actor)->postJson($operation['path'], $body, $headers),
            default => $this->actingAs($actor)->postJson($operation['path'], $body, $headers),
        };

        return [
            'status' => $response->getStatusCode(),
            'body' => $response->json(),
            'request_id' => $response->headers->get('X-Request-ID'),
        ];
    }

    /** @param array<string, mixed> $operation */
    private function startWorker(User $actor, array $operation): Process
    {
        $job = base64_encode(json_encode([
            'database' => DB::connection()->getDatabaseName(),
            'actor_id' => $actor->id,
            ...$operation,
        ], JSON_THROW_ON_ERROR));
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            \Tests\Support\TestDatabaseGuard::assertSafe($app['config']);
            $job = json_decode(base64_decode($argv[1], true), true, 512, JSON_THROW_ON_ERROR);
            $connection = \Illuminate\Support\Facades\DB::connection();
            if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== $job['database']) exit(2);
            $actor = \App\Models\User::findOrFail($job['actor_id']);
            \Illuminate\Support\Facades\Auth::guard('web')->setUser($actor);
            $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
            foreach (($job['headers'] ?? []) as $name => $value) {
                $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
            }
            $payload = $job['body'] ?? new stdClass();
            if ($payload === []) $payload = new stdClass();
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
            $request = \Illuminate\Http\Request::create($job['path'], $job['method'], [], [], [], $server, $body);
            $request->setUserResolver(fn () => $actor);
            $pid = $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;
            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);
            $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
            $response = $kernel->handle($request);
            $result = [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent() === '' ? null : json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR),
                'request_id' => $response->headers->get('X-Request-ID'),
            ];
            $kernel->terminate($request, $response);
            fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            PHP;
        $process = new Process([PHP_BINARY, '-r', $script, $job], base_path(), ['APP_ENV' => 'testing']);
        $process->setTimeout(90);
        $process->start();

        return $process;
    }

    /** @return array{parent_pid:int,worker_pid:int,wait_event_type:string} */
    private function waitForHeldLock(Process $process): array
    {
        $parentPid = (int) DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
        $deadline = microtime(true) + 10;

        do {
            if (preg_match('/READY:(\d+)/', $process->getOutput(), $match)) {
                $workerPid = (int) $match[1];
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::selectOne(
                    'SELECT wait_event_type, CASE WHEN CAST(? AS integer) = ANY(pg_blocking_pids(CAST(? AS integer))) THEN 1 ELSE 0 END AS blocked FROM pg_stat_activity WHERE pid = CAST(? AS integer)',
                    [$parentPid, $workerPid, $workerPid],
                );
                // Refresh the transaction statistics snapshot and wait for both lock observations.
                if ($blocked !== null && (int) $blocked->blocked === 1 && $blocked->wait_event_type === 'Lock') {
                    $this->assertSame('Lock', $blocked->wait_event_type);

                    return ['parent_pid' => $parentPid, 'worker_pid' => $workerPid, 'wait_event_type' => (string) $blocked->wait_event_type];
                }
            }
            if (! $process->isRunning()) {
                break;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        $this->fail('Worker did not wait on the parent PostgreSQL row lock. '.$process->getOutput().$process->getErrorOutput());
    }

    private function createDraft(string $title, array $fileIds = []): int
    {
        return $this->createDraftForProgram($this->active->id, $title, $fileIds);
    }

    private function createDraftForProgram(int $programId, string $title, array $fileIds = []): int
    {
        $body = ['title' => $title, 'body' => 'D04-15 race fixture.'];
        if ($fileIds !== []) {
            $body['file_ids'] = $fileIds;
        }
        $response = $this->actingAs($this->owner)->postJson("/api/v1/programs/{$programId}/announcements", $body);
        $response->assertCreated();

        return (int) $response->json('data.id');
    }

    private function cleanFile(string $label): int
    {
        $bytes = "%PDF-1.4\nD04-15 race fixture {$label}.\n%%EOF";
        $now = now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('files')->insertGetId([
            'owner_id' => $this->owner->id,
            'disk' => 'local',
            'object_key' => 'd04-15/'.Str::uuid().'.pdf',
            'original_name' => $label.'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'visibility' => 'private',
            'checksum_sha256' => hash('sha256', $bytes),
            'scan_status' => 'clean',
            'purpose' => 'announcement_attachment',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function insertFee(int $programId, string $period, int $amount): int
    {
        $start = $period.'-01';
        $end = date('Y-m-t', strtotime($start));
        $now = now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('fee_records')->insertGetId([
            'program_id' => $programId,
            'period_start' => $start,
            'period_end' => $end,
            'amount_minor' => $amount,
            'currency' => 'VND',
            'status' => 'declared',
            'tutor_payment_mark' => 'unmarked',
            'version' => 1,
            'payment_marked_at' => null,
            'payment_marked_by' => null,
            'payment_mark_reason' => null,
            'voided_at' => null,
            'voided_by' => null,
            'void_reason' => null,
            'last_change_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function assertFeeRaceCommittedOnce(int $feeId): void
    {
        $this->assertSame(2, (int) DB::table('fee_records')->where('id', $feeId)->value('version'));
        $this->assertSame(1, DB::table('audit_logs')->where('resource_type', 'fee_record')->where('resource_id', $feeId)->count());
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function preparePausedProgram(): void
    {
        $now = now('UTC')->format('Y-m-d H:i:s.uP');
        DB::table('programs')->where('id', $this->paused->id)->update([
            'status' => 'active',
            'activated_at' => '2022-01-01 00:00:00+07:00',
            'ends_at' => null,
            'updated_at' => $now,
        ]);
        DB::table('program_members')
            ->where('program_id', $this->paused->id)
            ->where('user_id', $this->paused->customer_id)
            ->update(['status' => 'active', 'left_at' => null, 'updated_at' => $now]);
    }

    private function setProgramStatus(int $programId, string $status): void
    {
        DB::table('programs')->where('id', $programId)->update([
            'status' => $status,
            'ends_at' => in_array($status, ['completed', 'cancelled'], true) ? now('UTC') : null,
            'updated_at' => now('UTC'),
        ]);
    }

    private function test_period(string $year, int $month): string
    {
        return sprintf('%s-%02d', $year, $month);
    }
}
