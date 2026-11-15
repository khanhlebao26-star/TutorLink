#!/bin/sh
set -eu
here=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
state=${PERF_STATE_DIR:-"$here/../../outputs/performance-audit/capacity"}
secrets="$state/.environment"
redis_experiment=${PERF_REDIS_EXPERIMENT:-0}

if [ "$redis_experiment" = 1 ]; then
    export PERF_APP_IMAGE=tutorlink-capacity-redis-image:local
    export COMPOSE_PROFILES=redis
fi

compose() {
    docker compose --env-file "$secrets" --profile replicas -f "$here/compose.yml" "$@"
}

db() {
    compose exec -T db psql -X -v ON_ERROR_STOP=1 -U capacity_runner -d tutorlink_capacity "$@"
}

save_session_state() {
    run_id=$(node -p 'JSON.parse(require("node:fs").readFileSync(process.argv[1])).run_id' "$result/http.json")
    db -At -v run_id="$run_id" < "$here/integrity.sql" > "$result/integrity.json"
    (umask 077; db -At -v run_id="$run_id" < "$here/session-rows.sql" > "$result/session-rows.ndjson")
}

wait_origin_idle() {
    compose exec -T -e "PERF_EXPECT_UPSTREAMS=$(cat "$result/upstreams.txt")" load node --input-type=module - <<'JS'
import { setTimeout as wait } from 'node:timers/promises';
const paths = ['/fpm-status'];
if (process.env.PERF_EXPECT_UPSTREAMS.includes('app-replica:9000')) paths.push('/fpm-status/replica');
const deadline = performance.now() + 45_000;
let idleSamples = 0;
while (performance.now() < deadline) {
  const pools = await Promise.all(paths.map(async path => {
    try {
      const response = await fetch('http://proxy:8081' + path + '?json', { signal: AbortSignal.timeout(1000) });
      return response.ok ? await response.json() : null;
    } catch {
      return null;
    }
  }));
  idleSamples = pools.every(pool => pool?.['active processes'] === 0 && pool?.['listen queue'] === 0) ? idleSamples + 1 : 0;
  if (idleSamples >= 2) break;
  await wait(500);
}
if (idleSamples < 2) {
  console.error('PHP-FPM still has work or could not be sampled. PostgreSQL may still be changing.');
  process.exitCode = 1;
}
JS
}

sample() {
    db -At < "$here/resources.sql" >> "$result/postgres.ndjson"
    if [ "$redis_experiment" = 1 ]; then
        compose exec -T redis redis-cli --raw info stats memory >> "$result/redis.txt"
    fi
    ids=$(compose ps -q)
    # Container IDs are individually passed to Docker.
    # shellcheck disable=SC2086
    docker stats --no-stream --format '{{json .}}' $ids >> "$result/resources.ndjson"
}

case "${1:-}" in
    up)
        mkdir -p "$state"
        if [ ! -s "$secrets" ]; then
            (umask 077
             printf 'PERF_APP_KEY=base64:%s\nPERF_DB_PASSWORD=%s\n' \
                 "$(openssl rand -base64 32)" "$(openssl rand -hex 24)" > "$secrets")
        fi
        PERF_APP_IMAGE=tutorlink-capacity-image:local compose build app proxy
        if [ "$redis_experiment" = 1 ]; then
            docker build -f "$here/Dockerfile.redis" -t "$PERF_APP_IMAGE" "$here"
            compose up -d --wait redis
        fi
        compose up -d --wait db
        compose run --rm fixtures php artisan migrate --force --no-interaction
        compose run --rm fixtures php /benchmark/seed.php
        compose up -d --wait --no-build app proxy load worker
        ;;
    cache-store)
        if [ "$redis_experiment" != 1 ]; then
            echo 'Set PERF_REDIS_EXPERIMENT=1 for both cache-store variants.' >&2
            exit 1
        fi
        case "${2:-}" in database|redis) ;; *) echo 'Expected database or redis.' >&2; exit 1;; esac
        store=$2
        set -- app
        if compose ps --status running --services | grep -qx app-replica; then
            set -- "$@" app-replica
        fi
        PERF_MARKETPLACE_CACHE_STORE="$store" compose up -d --wait --force-recreate --no-deps --no-build "$@"
        ;;
    replicas)
        case "${2:-}" in
            1)
                PERF_FPM_UPSTREAMS=app:9000 compose up -d --wait --force-recreate --no-deps --no-build proxy
                compose stop app-replica
                ;;
            2)
                store=$(compose exec -T app printenv MARKETPLACE_CACHE_STORE)
                PERF_MARKETPLACE_CACHE_STORE="$store" compose up -d --wait --no-deps --no-build app-replica
                PERF_FPM_UPSTREAMS='app:9000 app-replica:9000' compose up -d --wait --force-recreate --no-deps --no-build proxy
                ;;
            *) echo 'Expected 1 or 2 replicas.' >&2; exit 1;;
        esac
        ;;
    measure)
        if [ "$#" -ne 5 ]; then
            echo 'Usage: run.sh measure RUN_NAME {read|mixed|write} RPS DURATION_SECONDS' >&2
            exit 1
        fi
        case "$2" in ''|*[!a-zA-Z0-9_-]*) echo 'Invalid run name.' >&2; exit 1;; esac
        result="$state/$2"
        mkdir "$result"
        ledger_path="/tmp/tutorlink-writes-$(openssl rand -hex 12).ndjson"
        compose exec -T app printenv MARKETPLACE_CACHE_STORE > "$result/cache-store.txt"
        compose exec -T proxy printenv PERF_FPM_UPSTREAMS > "$result/upstreams.txt"
        db -Atc 'select pg_stat_statements_reset()' >/dev/null
        compose exec -T \
            -e "PERF_SEARCH_PROFILE=${PERF_SEARCH_PROFILE:-repeated}" \
            -e "PERF_SPIKE_RPS=${PERF_SPIKE_RPS:-0}" \
            -e "PERF_MAX_PENDING=${PERF_MAX_PENDING:-512}" \
            -e "PERF_EXPECT_UPSTREAMS=$(cat "$result/upstreams.txt")" \
            -e "PERF_WRITE_LEDGER=$ledger_path" \
            load node /benchmark/load.mjs "$3" "$4" "$5" > "$result/http.json" &
        runner=$!
        trap 'kill "$runner" 2>/dev/null || true' EXIT HUP INT TERM
        while kill -0 "$runner" 2>/dev/null; do
            sample
            sleep 8
        done
        status=0
        wait "$runner" || status=$?
        trap - EXIT HUP INT TERM
        (umask 077; compose exec -T load cat "$ledger_path" > "$result/writes.ndjson")
        compose exec -T load rm -f "$ledger_path"
        if [ ! -s "$result/http.json" ]; then
            exit 1
        fi
        sample
        db -Atc 'select json_agg(q) from (select queryid, calls, rows, total_exec_time, mean_exec_time, shared_blks_read, temp_blks_written, wal_bytes from pg_stat_statements order by total_exec_time desc limit 20) q' > "$result/queries.json"
        db -Atc "select json_build_object(
            'cache_table_select_calls', coalesce(sum(calls) filter (where lower(query) like 'select % from \"cache\" %'), 0),
            'cache_table_insert_calls', coalesce(sum(calls) filter (where lower(query) like 'insert into \"cache\" %'), 0),
            'marketplace_generation_calls', coalesce(sum(calls) filter (where lower(query) like 'select % from \"marketplace_cache_versions\" %'), 0)
        ) from pg_stat_statements" > "$result/cache-sql.json"
        if wait_origin_idle; then
            echo true > "$result/origin-idle.txt"
        else
            echo false > "$result/origin-idle.txt"
            status=1
        fi
        save_session_state
        node "$here/phase-e.mjs" "$result" > "$result/phase-e.json"
        node - "$result" <<'JS' || status=1
const fs = require('node:fs');
const dir = process.argv[2];
const http = JSON.parse(fs.readFileSync(dir + '/http.json'));
const state = JSON.parse(fs.readFileSync(dir + '/integrity.json'));
const expected = (http.results.session_create?.successful ?? 0) + Number(http.smoke_session_id !== null);
const integrity = state.sessions === expected && state.distinct_titles === expected &&
  state.distinct_slots === expected && state.missing_or_duplicate_create_audits === 0 &&
  state.missing_or_duplicate_receipts === 0;
console.log(JSON.stringify({
  results: dir, offered_rps: http.target_rps, successful_rps: http.results.all.successful_rps,
  p95_ms: http.results.all.p95_ms, p99_ms: http.results.all.p99_ms,
  dropped: http.dropped, errors: http.results.all.errors, slo: http.application_rps_slo_pass,
  read_endpoints_over_latency: http.read_endpoints_over_latency,
  write_integrity: integrity, expected_session_rows: expected, ...state,
}, null, 2));
if (!integrity) process.exitCode = 1;
JS
        exit "$status"
        ;;
    replay)
        case "${2:-}" in ''|*[!a-zA-Z0-9_-]*) echo 'Invalid run name.' >&2; exit 1;; esac
        result="$state/$2"
        wait_origin_idle
        echo true > "$result/origin-idle.txt"
        save_session_state
        node "$here/phase-e.mjs" "$result" > "$result/phase-e.json"
        replay_status=0
        if [ -s "$result/pending-replay.ndjson" ]; then
            (umask 077; compose exec -T load node --input-type=module -e "$(cat "$here/replay-receipts.mjs")" \
                < "$result/pending-replay.ndjson" >> "$result/receipt-replays.ndjson") || replay_status=$?
            save_session_state
            node "$here/phase-e.mjs" "$result" > "$result/phase-e.json"
        fi
        cat "$result/phase-e.json"
        if [ "$replay_status" -ne 0 ]; then exit "$replay_status"; fi
        node -e 'const {writes}=JSON.parse(require("node:fs").readFileSync(process.argv[1])); if (!writes.consistent || writes.pending_replay !== 0) process.exitCode=1;' "$result/phase-e.json"
        ;;
    down)
        compose --profile redis down --volumes
        rm -f "$secrets"
        ;;
    *)
        echo 'Usage: run.sh up | cache-store {database|redis} | replicas {1|2} | measure RUN_NAME {read|mixed|write} RPS DURATION_SECONDS | replay RUN_NAME | down' >&2
        exit 1
        ;;
esac
