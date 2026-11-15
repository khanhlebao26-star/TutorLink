import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkCapacity, reconcileWrites } from './phase-e.mjs';

const saved = {
  key: 'first', session_id: 103, program_id: 22, title: 'Capacity test 1',
  planned_starts_at: '2026-11-16T08:00:00Z', planned_ends_at: '2026-11-16T08:30:00Z',
};
const state = {
  sessions: 1, distinct_titles: 1, distinct_slots: 1,
  missing_or_duplicate_create_audits: 0, missing_or_duplicate_receipts: 0,
};
function targetRun(overrides = {}) {
  return {
    run_id: 'test', duration_seconds: 3600, mix: 'mixed', spike_rps: null, smoke_session_id: 103,
    completion_windows: Array.from({ length: 60 }, (_, i) => ({ start_seconds: i * 60, seconds: 60, successful: 60_500 })),
    results: { all: { successful_rps: 1008 } }, dropped_or_failed_rate: 0.001,
    read_endpoints_over_latency: [], ...overrides,
  };
}

test('requires confirmed capacity in every minute and a full hour of measured load', async () => {
  const writes = await reconcileWrites([{ ...saved, status: 201 }], [saved]);
  const http = targetRun();
  assert.equal(checkCapacity(http, state, writes).capacity_checks_pass, true);
  http.completion_windows.at(-1).successful = 59_999;
  assert.equal(checkCapacity(http, state, writes).checks.successful_rps_at_least_1000, false);
  assert.equal(checkCapacity(targetRun({ duration_seconds: 3599 }), state, writes).capacity_checks_pass, false);
  assert.equal(checkCapacity(targetRun({ completion_windows: [] }), state, writes).capacity_checks_pass, false);
});

test('detects a missing successful write even when the total number of writes matches', async () => {
  const writes = await reconcileWrites([{ ...saved, key: 'different-key', status: 201 }], [saved]);
  assert.equal(writes.result.acknowledged_missing, 1);
  assert.equal(writes.result.unexpected_database_rows, 1);
  assert.equal(checkCapacity(targetRun(), state, writes).checks.write_integrity, false);
});

test('pairs a missing response with its commit and requires an exact replay', async () => {
  const second = { ...saved, key: 'second', session_id: 104, title: 'Capacity test 2' };
  const writes = await reconcileWrites([{ ...saved, status: 201 }, { ...second, session_id: null, status: 0 }], [saved, second]);
  const http = targetRun({ results: { all: { successful_rps: 1008 }, session_create: { completed: 1, successful: 0 } } });
  const records = { ...state, sessions: 2, distinct_titles: 2, distinct_slots: 2 };
  assert.equal(writes.committedWithoutReply[0].expected_session_id, 104);
  assert.equal(checkCapacity(http, records, writes).checks.write_integrity, false);
  assert.equal(checkCapacity(http, records, writes, new Set(['second'])).checks.write_integrity, true);
});
