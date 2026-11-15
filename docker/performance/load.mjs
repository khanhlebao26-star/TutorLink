import { randomUUID } from 'node:crypto';
import { closeSync, openSync, readFileSync, writeSync } from 'node:fs';
import { monitorEventLoopDelay, performance } from 'node:perf_hooks';
import { setTimeout as wait } from 'node:timers/promises';

const [mixName = 'mixed', rateText = '10', secondsText = '30'] = process.argv.slice(2);
const rate = Number(rateText);
const seconds = Number(secondsText);
const maxPending = Number(process.env.PERF_MAX_PENDING ?? 512);
const spikeRate = Number(process.env.PERF_SPIKE_RPS ?? 0);
const searchProfile = process.env.PERF_SEARCH_PROFILE ?? 'repeated';
const root = new URL(process.env.PERF_ORIGIN ?? 'http://proxy:8080');
const frontendOrigin = process.env.PERF_FRONTEND_ORIGIN ?? 'http://benchmark.test';
const expectedUpstreams = (process.env.PERF_EXPECT_UPSTREAMS ?? '').split(/\s+/).filter(Boolean);
const upstreamHeader = 'X-Benchmark-Upstream';
if (root.origin !== 'http://proxy:8080' && process.env.PERF_TARGET_ACK !== 'isolated-staging') {
  throw new Error('An external origin requires PERF_TARGET_ACK=isolated-staging and staging-only test data.');
}
if (![rate, seconds, maxPending].every(Number.isSafeInteger) || rate < 1 || rate > 10000 ||
    seconds < 1 || seconds > 28800 || maxPending < 1 || maxPending > 20000) {
  throw new Error('Expected integer rate (1–10000), duration (1–28800 seconds) and concurrency (1–20000).');
}
if (spikeRate !== 0 && (!Number.isSafeInteger(spikeRate) || spikeRate <= rate || spikeRate > 10000 ||
    seconds < 30 || seconds % 10 !== 0)) {
  throw new Error('A spike requires a higher rate up to 10000 and a duration of at least 30 seconds in multiples of 10.');
}
if (!['repeated', 'diverse'].includes(searchProfile)) {
  throw new Error('Expected PERF_SEARCH_PROFILE=repeated or diverse.');
}

const fixture = JSON.parse(readFileSync(process.env.PERF_FIXTURES ?? '/fixtures/actors.json', 'utf8'));
const publicHeaders = { Accept: 'application/json' };
const authorized = actor => ({ ...publicHeaders, Origin: frontendOrigin, Cookie: actor.cookie });
const runId = randomUUID().replaceAll('-', '').slice(0, 16);
const scheduleStart = Date.now() + 24 * 3600_000;
const timeoutMs = 5000;
const searches = ['English', 'Mathematics', 'Vietnamese calculus', 'English grammar 26', 'zzqnoresult'];
const endpoints = {
  listings: ['public_read', index => ['/listings?page=' + (1 + index % 5)]],
  search: ['public_read', index => {
    const term = searches[index % searches.length];
    const page = term === 'Mathematics' && index % 4 === 1 ? 2200 : 1 + index % 3;
    const params = new URLSearchParams({ q: term, page });
    if (searchProfile === 'diverse') {
      params.set('price_unit', 'session');
      params.set('min_price', 100000 + (index * 17) % 90000);
    }
    return ['/listings?' + params.toString()];
  }],
  tutor: ['public_read', index => ['/tutors/' + fixture.tutors[index % fixture.tutors.length]]],
  catalog: ['public_read', () => ['/catalog/categories']],
  programs: ['auth_read', (_, actor) => ['/programs', { headers: authorized(actor) }]],
  unread: ['auth_read', (_, actor) => ['/conversations/unread-count', { headers: authorized(actor) }]],
  notifications: ['auth_read', (_, actor) => ['/notifications', { headers: authorized(actor) }]],
  sessions: ['auth_read', (_, actor) => [`/programs/${actor.program}/sessions`, { headers: authorized(actor) }]],
  session_create: ['auth_write', (index, actor) => {
    const start = scheduleStart + index * 60_000;
    return [`/programs/${actor.program}/sessions`, {
      method: 'POST',
      headers: {
        ...authorized(actor), 'Content-Type': 'application/json',
        'X-CSRF-TOKEN': actor.csrf, 'Idempotency-Key': randomUUID(),
      },
      body: JSON.stringify({
        title: `Capacity ${runId} ${index}`,
        delivery_mode: 'online',
        planned_starts_at: new Date(start).toISOString(),
        planned_ends_at: new Date(start + 30_000).toISOString(),
      }),
    }];
  }],
};
const mixes = {
  read: { listings: 35, search: 20, tutor: 15, catalog: 5, programs: 10, unread: 5, notifications: 5, sessions: 5 },
  mixed: { listings: 30, search: 20, tutor: 15, catalog: 10, programs: 5, unread: 5, notifications: 5, sessions: 5, session_create: 5 },
  write: { listings: 15, search: 10, tutor: 5, catalog: 5, programs: 10, unread: 5, notifications: 5, sessions: 10, session_create: 35 },
};
const selected = mixes[mixName];
if (!selected || fixture.customers.length < 2 || fixture.owners.length < 2 || fixture.tutors.length === 0) {
  throw new Error('Expected mix read, mixed, or write and multiple isolated fixture accounts.');
}
const slots = Object.entries(selected).flatMap(([name, count]) => Array(count).fill(name));
const endpointAt = index => slots[(index * 37) % slots.length];
const ledgerPath = process.env.PERF_WRITE_LEDGER;
const ledger = ledgerPath ? openSync(ledgerPath, 'wx', 0o600) : null;

function recordWrite(actor, init, status, sessionId) {
  if (ledger === null) return;
  const { title, planned_starts_at, planned_ends_at } = JSON.parse(init.body);
  writeSync(ledger, JSON.stringify({
    key: init.headers['Idempotency-Key'], program_id: actor.program,
    title, planned_starts_at, planned_ends_at, status, session_id: sessionId,
  }) + '\n');
}

function verifyUpstreams(responses) {
  const observed = [...new Set(responses.map(response => response.headers.get(upstreamHeader)))];
  if (expectedUpstreams.length && (observed.length !== expectedUpstreams.length ||
      expectedUpstreams.some(upstream => !observed.includes(upstream)))) {
    throw new Error(`Expected smoke replies from ${expectedUpstreams.join(', ')}, received ${observed.join(', ')}`);
  }
  return observed.filter(Boolean);
}

async function call(path, init = {}) {
  return fetch(new URL('/api/v1' + path, root), {
    headers: publicHeaders, ...init, redirect: 'manual', signal: AbortSignal.timeout(timeoutMs),
  });
}

async function smoke() {
  const health = await fetch(new URL('/up', root), {
    redirect: 'manual', signal: AbortSignal.timeout(timeoutMs),
  });
  await health.arrayBuffer();
  if (health.status !== 200) throw new Error(`Liveness check failed (${health.status})`);
  for (const [name, [, setup]] of Object.entries(endpoints)) {
    if (name === 'session_create') continue;
    const result = await call(...setup(0, fixture.customers[0]));
    await result.arrayBuffer();
    if (result.status !== 200) throw new Error(`Smoke: ${name} returned ${result.status}`);
  }
  const customer = fixture.customers[0];
  const authReads = [];
  for (let index = 0; index < expectedUpstreams.length; index++) {
    const response = await call(...endpoints.programs[1](index, customer));
    const body = await response.json();
    if (response.status !== 200 || !body.data?.some(program => program.id === customer.program)) {
      throw new Error(`Authenticated replica read did not return the fixture Program (${response.status})`);
    }
    authReads.push(response);
  }
  const readUpstreams = verifyUpstreams(authReads);
  if (!selected.session_create) return { sessionId: null, readUpstreams, writeUpstreams: [] };

  const [path, init] = endpoints.session_create[1](-1, fixture.owners[0]);
  const results = await Promise.all([call(path, init), call(path, init)]);
  const bodies = await Promise.all(results.map(result => result.json()));
  if (results.some(result => result.status !== 201) ||
      !bodies[0]?.data?.id || bodies[0].data.id !== bodies[1]?.data?.id) {
    throw new Error(`Concurrent Session replay failed: ${results.map((r, index) => r.status + '/' + (bodies[index]?.code ?? '')).join(', ')}`);
  }
  const writeUpstreams = expectedUpstreams.length ? verifyUpstreams(results) : [];
  recordWrite(fixture.owners[0], init, 201, bodies[0].data.id);
  return { sessionId: bodies[0].data.id, readUpstreams, writeUpstreams };
}

function metrics() {
  return { all: new Uint32Array(timeoutMs * 3 + 1), successful: 0, statuses: {}, hits: 0, misses: 0, bytes: 0, invalid: 0 };
}
function record(data, status, ms, valid, bytes, cache) {
  data.all[Math.min(data.all.length - 1, Math.ceil(ms))]++;
  data.statuses[status] = (data.statuses[status] ?? 0) + 1;
  data.successful += Number(valid);
  data.invalid += Number(!valid && status >= 200 && status < 300);
  data.hits += Number(cache === 'HIT');
  data.misses += Number(cache === 'MISS');
  data.bytes += bytes;
}
function summarize(data, elapsed) {
  const attempted = Object.values(data.statuses).reduce((sum, n) => sum + n, 0);
  const percentile = p => {
    if (!attempted) return null;
    let remaining = Math.ceil(attempted * p);
    return data.all.findIndex(count => (remaining -= count) <= 0);
  };
  return {
    completed: attempted, successful: data.successful, successful_rps: data.successful / elapsed,
    errors: attempted - data.successful, invalid_success_responses: data.invalid, statuses: data.statuses,
    p50_ms: percentile(0.50), p95_ms: percentile(0.95), p99_ms: percentile(0.99),
    cache_hits: data.hits, cache_misses: data.misses, decoded_body_bytes: data.bytes,
  };
}

const smokeResult = await smoke();
const stats = { all: metrics() };
for (const [name, [lane]] of Object.entries(endpoints)) {
  stats[name] = metrics();
  stats[lane] ??= metrics();
}
const windows = Array.from({ length: Math.ceil(seconds / 10) }, (_, index) => ({
  start: index * 10,
  seconds: Math.min(10, seconds - index * 10),
  rate: spikeRate && index === Math.floor(seconds / 20) ? spikeRate : rate,
  dropped: 0,
  metrics: metrics(),
}));
const pending = new Set();
const completionWindows = new Uint32Array(Math.ceil(seconds / 60));
const loopDelay = monitorEventLoopDelay({ resolution: 20 });
let dropped = 0;
let peakPending = 0;
const fpm = [];
const fpmTargets = [{ upstream: 'app:9000', path: '/fpm-status' }];
if (expectedUpstreams.includes('app-replica:9000')) {
  fpmTargets.push({ upstream: 'app-replica:9000', path: '/fpm-status/replica' });
}
const upstreamCounts = Object.fromEntries(expectedUpstreams.map(upstream => [upstream, 0]));
let unexpectedUpstreamReplies = 0;
let fpmSampleFailures = 0;
let sampling = false;
async function sampleFpm() {
  if (root.origin !== 'http://proxy:8080' || sampling) return;
  sampling = true;
  await Promise.all(fpmTargets.map(async ({ upstream, path }) => {
    try {
      const response = await fetch('http://proxy:8081' + path + '?json', { signal: AbortSignal.timeout(1000) });
      if (!response.ok) throw new Error('FPM status unavailable');
      const sample = await response.json();
      fpm.push({
        timestamp: new Date().toISOString(), upstream,
        waiting: sample['listen queue'], peak_waiting: sample['max listen queue'],
        active: sample['active processes'], idle: sample['idle processes'],
        max_children_reached: sample['max children reached'],
      });
    } catch {
      fpmSampleFailures++;
    }
  }));
  sampling = false;
}

async function send(index, window) {
  const name = endpointAt(index);
  const [lane, setup] = endpoints[name];
  const actors = lane === 'auth_write' ? fixture.owners : fixture.customers;
  const actor = actors[(Math.floor(index / slots.length) * 7 + index) % actors.length];
  const [path, init] = setup(index, actor);
  const t0 = performance.now();
  let status = 0, size = 0, cache = null, valid = false;
  let sessionId = null;
  try {
    const result = await call(path, init);
    cache = result.headers.get('X-Marketplace-Cache');
    const bytes = await result.arrayBuffer();
    status = result.status;
    size = bytes.byteLength;
    valid = status === (name === 'session_create' ? 201 : 200);
    if (valid && name === 'session_create') {
      const body = JSON.parse(new TextDecoder().decode(bytes));
      valid = Number.isSafeInteger(body.data?.id);
      if (valid) sessionId = body.data.id;
    }
    if (valid && expectedUpstreams.length) {
      const upstream = result.headers.get(upstreamHeader);
      if (Object.hasOwn(upstreamCounts, upstream)) upstreamCounts[upstream]++;
      else unexpectedUpstreamReplies++;
    }
  } catch {
    // A failure to receive the complete response counts as a transport error.
  }
  const completedAt = performance.now();
  if (valid && completedAt - start < seconds * 1000) {
    completionWindows[Math.floor((completedAt - start) / 60_000)]++;
  }
  if (name === 'session_create') recordWrite(actor, init, status, sessionId);
  const duration = completedAt - t0;
  for (const aggregate of [stats.all, stats[name], stats[lane], window]) {
    record(aggregate, status, duration, valid, size, cache);
  }
}

await sampleFpm();
const timer = setInterval(sampleFpm, 1000);
loopDelay.enable();
const start = performance.now();
let scheduled = 0;
for (const window of windows) {
  const count = window.rate * window.seconds;
  for (let position = 0; position < count; position++) {
    const index = scheduled++;
    const due = start + window.start * 1000 + position * 1000 / window.rate;
    const delay = due - performance.now();
    if (delay > 0) await wait(delay);
    if (pending.size >= maxPending || performance.now() - due > 250) {
      window.dropped++;
      dropped++;
      continue;
    }
    const work = send(index, window.metrics).finally(() => pending.delete(work));
    pending.add(work);
    peakPending = Math.max(peakPending, pending.size);
  }
}
await Promise.all(pending);
if (ledger !== null) closeSync(ledger);
const elapsed = (performance.now() - start) / 1000;
loopDelay.disable();
clearInterval(timer);
await sampleFpm();
const results = Object.fromEntries(Object.entries(stats).filter(([key, data]) => key === 'all' || Object.keys(data.statuses).length)
  .map(([key, data]) => [key, summarize(data, elapsed)]));
const errorBudget = (dropped + results.all.errors) / scheduled;
const readBreaches = Object.entries(endpoints).flatMap(([name, [lane]]) => {
  const metric = results[name];
  return lane !== 'auth_write' && metric && (metric.p95_ms >= 300 || metric.p99_ms >= 1000) ? [name] : [];
});
const slo = dropped === 0 && errorBudget < 0.01 && results.all.successful_rps >= (scheduled / seconds) * 0.99 &&
  results.all.p95_ms < 300 && results.all.p99_ms < 1000 && readBreaches.length === 0 &&
  unexpectedUpstreamReplies === 0 && Object.values(upstreamCounts).every(count => count > 0);
console.log(JSON.stringify({
  run_id: runId, mix: mixName, target_rps: rate, duration_seconds: seconds, scheduled, elapsed_seconds: elapsed, dropped,
  completion_windows: Array.from(completionWindows, (successful, index) => ({
    start_seconds: index * 60, seconds: Math.min(60, seconds - index * 60), successful,
  })),
  search_profile: searchProfile, spike_rps: spikeRate || null,
  dropped_or_failed_rate: errorBudget, peak_pending: peakPending,
  generator_event_loop_p99_ms: loopDelay.percentile(99) / 1e6,
  application_rps_slo_pass: slo, read_endpoints_over_latency: readBreaches, smoke_session_id: smokeResult.sessionId,
  smoke_authenticated_read_upstreams: smokeResult.readUpstreams, smoke_replay_upstreams: smokeResult.writeUpstreams,
  successful_replies_by_upstream: upstreamCounts, unexpected_upstream_replies: unexpectedUpstreamReplies,
  fixture_listings: fixture.listings,
  results,
  windows: windows.map(window => ({
    start_seconds: window.start, offered_rps: window.rate, dropped: window.dropped,
    ...summarize(window.metrics, window.seconds),
  })),
  fpm_sample_failures: fpmSampleFailures, fpm,
}, null, 2));
if (!slo) process.exitCode = 2;
