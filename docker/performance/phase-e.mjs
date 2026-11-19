import { createReadStream, existsSync, readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createInterface } from 'node:readline';
import { pathToFileURL } from 'node:url';

async function* rows(path) {
  const lines = createInterface({ input: createReadStream(path), crlfDelay: Infinity });
  for await (const line of lines) {
    if (line) yield JSON.parse(line);
  }
}

export async function reconcileWrites(writes, databaseRows) {
  const persisted = new Map();
  const keys = new Set();
  const committedWithoutReply = [];
  const result = {
    attempts: 0, persisted: 0, confirmed: 0, not_committed: 0,
    acknowledged_missing: 0, mismatched: 0, duplicate_attempt_keys: 0,
    invalid_database_keys: 0, unexpected_database_rows: 0,
  };
  for await (const row of databaseRows) {
    result.persisted++;
    if (!row.key || persisted.has(row.key)) result.invalid_database_keys++;
    else persisted.set(row.key, row);
  }
  for await (const write of writes) {
    result.attempts++;
    if (!write.key || keys.has(write.key)) {
      result.duplicate_attempt_keys++;
      continue;
    }
    keys.add(write.key);
    const saved = persisted.get(write.key);
    if (!saved) {
      if (write.status === 201) result.acknowledged_missing++;
      else result.not_committed++;
      continue;
    }
    persisted.delete(write.key);
    if (saved.title !== write.title || saved.program_id !== write.program_id ||
        Date.parse(saved.planned_starts_at) !== Date.parse(write.planned_starts_at) ||
        Date.parse(saved.planned_ends_at) !== Date.parse(write.planned_ends_at)) {
      result.mismatched++;
    } else if (write.status !== 201) {
      committedWithoutReply.push({ ...write, expected_session_id: saved.session_id });
    } else if (saved.session_id !== write.session_id) {
      result.mismatched++;
    } else {
      result.confirmed++;
    }
  }
  result.unexpected_database_rows = persisted.size;
  return { result, committedWithoutReply };
}

export function checkCapacity(http, state, writes, replayedKeys = new Set()) {
  const { result: r, committedWithoutReply } = writes;
  const expectedAttempts = (http.results.session_create?.completed ?? 0) + Number(http.smoke_session_id !== null);
  const windows = http.completion_windows ?? [];
  const completeWindows = windows.length > 0 && windows.length === Math.ceil(http.duration_seconds / 60) &&
    windows.every((window, index) => window.start_seconds === index * 60 &&
      window.seconds === Math.min(60, http.duration_seconds - window.start_seconds) &&
      Number.isSafeInteger(window.successful) && window.successful >= 0);
  const minimumMinuteRps = completeWindows ? Math.min(...windows.map(window => window.successful / window.seconds)) : null;
  const pendingReplay = committedWithoutReply.filter(row => !replayedKeys.has(row.key));
  const consistent = r.attempts === expectedAttempts && r.persisted === state.sessions &&
    r.confirmed === (http.results.session_create?.successful ?? 0) + Number(http.smoke_session_id !== null) &&
    r.acknowledged_missing + r.mismatched + r.duplicate_attempt_keys + r.invalid_database_keys + r.unexpected_database_rows === 0 &&
    state.distinct_titles === state.sessions && state.distinct_slots === state.sessions &&
    state.missing_or_duplicate_create_audits + state.missing_or_duplicate_receipts === 0;
  const checks = {
    duration_seconds_at_least_3600: http.duration_seconds >= 3600,
    constant_mixed_or_write_workload: ['mixed', 'write'].includes(http.mix) && !http.spike_rps && !!http.smoke_session_id,
    successful_rps_at_least_1000: http.results.all.successful_rps >= 1000 && minimumMinuteRps !== null && minimumMinuteRps >= 1000,
    error_rate_below_one_percent: http.dropped_or_failed_rate < 0.01,
    read_endpoint_latency: http.read_endpoints_over_latency.length === 0,
    write_integrity: consistent && pendingReplay.length === 0,
  };
  return {
    run_id: http.run_id, checks,
    capacity_checks_pass: Object.values(checks).every(Boolean),
    minimum_completion_window_rps: minimumMinuteRps,
    successful_rps: http.results.all.successful_rps,
    dropped_or_failed_rate: http.dropped_or_failed_rate,
    read_endpoints_over_latency: http.read_endpoints_over_latency,
    writes: { ...r, consistent, committed_without_reply: committedWithoutReply.length, pending_replay: pendingReplay.length },
  };
}

if (process.argv[1] && import.meta.url === pathToFileURL(realpathSync(process.argv[1])).href) {
  const [dir, flag] = process.argv.slice(2);
  if (!dir || flag && flag !== '--require-target') throw new Error('Usage: node phase-e.mjs RUN_DIRECTORY [--require-target]');
  const read = name => JSON.parse(readFileSync(join(dir, name), 'utf8'));
  const http = read('http.json');
  const state = read('integrity.json');
  const writes = await reconcileWrites(rows(join(dir, 'writes.ndjson')), rows(join(dir, 'session-rows.ndjson')));
  const replayFile = join(dir, 'receipt-replays.ndjson');
  const requiredReplays = new Map(writes.committedWithoutReply.map(row => [row.key, row.expected_session_id]));
  const replayedKeys = new Set();
  if (existsSync(replayFile)) {
    for await (const row of rows(replayFile)) {
      if (row.status === 201 && requiredReplays.has(row.key) && requiredReplays.get(row.key) === row.session_id) replayedKeys.add(row.key);
    }
  }
  const unresolved = writes.committedWithoutReply.filter(row => !replayedKeys.has(row.key));
  writeFileSync(join(dir, 'pending-replay.ndjson'), unresolved.map(row => JSON.stringify(row) + '\n').join(''), { mode: 0o600 });
  const report = checkCapacity(http, state, writes, replayedKeys);
  const idleFile = join(dir, 'origin-idle.txt');
  if (existsSync(idleFile)) {
    report.checks.origin_queue_drained = readFileSync(idleFile, 'utf8').trim() === 'true';
    report.capacity_checks_pass &&= report.checks.origin_queue_drained;
  }
  console.log(JSON.stringify(report, null, 2));
  if (flag && !report.capacity_checks_pass) process.exitCode = 2;
}
