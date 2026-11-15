import { readFileSync } from 'node:fs';
import { createInterface } from 'node:readline';
import { setTimeout as wait } from 'node:timers/promises';

const root = new URL(process.env.PERF_ORIGIN ?? 'http://proxy:8080');
if (root.origin !== 'http://proxy:8080' && process.env.PERF_TARGET_ACK !== 'isolated-staging') {
  throw new Error('An external origin requires PERF_TARGET_ACK=isolated-staging and staging-only test data.');
}
const frontendOrigin = process.env.PERF_FRONTEND_ORIGIN ?? 'http://benchmark.test';
const { owners } = JSON.parse(readFileSync(process.env.PERF_FIXTURES ?? '/fixtures/actors.json', 'utf8'));
const actors = new Map(owners.map(owner => [owner.program, owner]));

for await (const line of createInterface({ input: process.stdin, crlfDelay: Infinity })) {
  if (!line) continue;
  const write = JSON.parse(line);
  const actor = actors.get(write.program_id);
  if (!actor || !/^Capacity [a-f0-9]{16} -?\d+$/.test(write.title) || !Number.isSafeInteger(write.expected_session_id)) {
    throw new Error('Expected a committed Session from an isolated benchmark fixture.');
  }
  const response = await fetch(new URL(`/api/v1/programs/${write.program_id}/sessions`, root), {
    method: 'POST', redirect: 'manual', signal: AbortSignal.timeout(5000),
    headers: {
      Accept: 'application/json', Origin: frontendOrigin, Cookie: actor.cookie,
      'Content-Type': 'application/json', 'X-CSRF-TOKEN': actor.csrf, 'Idempotency-Key': write.key,
    },
    body: JSON.stringify({
      title: write.title, delivery_mode: 'online',
      planned_starts_at: write.planned_starts_at, planned_ends_at: write.planned_ends_at,
    }),
  });
  const body = await response.json();
  const sessionId = body.data?.id ?? null;
  console.log(JSON.stringify({ key: write.key, status: response.status, session_id: sessionId }));
  if (response.status !== 201 || sessionId !== write.expected_session_id) process.exitCode = 2;
  await wait(40);
}
