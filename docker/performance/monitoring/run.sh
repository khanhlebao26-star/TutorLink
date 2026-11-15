#!/bin/sh
set -eu
here=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
state=${PERF_STATE_DIR:-"$here/../../../outputs/performance-audit/capacity"}
secrets="$state/.environment"

compose() {
    docker compose --env-file "$secrets" --profile replicas -f "$here/../compose.yml" -f "$here/compose.yml" "$@"
}

case "${1:-}" in
    up)
        PERF_MONITOR_UPSTREAMS=$(compose exec -T proxy printenv PERF_FPM_UPSTREAMS)
        PERF_MONITOR_PASSWORD=$(openssl rand -hex 24)
        export PERF_MONITOR_UPSTREAMS PERF_MONITOR_PASSWORD
        compose exec -T -e "PERF_MONITOR_PASSWORD=$PERF_MONITOR_PASSWORD" db \
            psql -X -v ON_ERROR_STOP=1 -U capacity_runner -d tutorlink_capacity < "$here/create-role.sql"
        compose up -d --no-build runtime-metrics prometheus
        ;;
    status)
        compose exec -T load node --input-type=module - <<'JS'
const base = 'http://prometheus:9090/api/v1/';
async function get(path) {
  const res = await fetch(base + path, { signal: AbortSignal.timeout(5000) });
  const body = await res.json();
  if (!res.ok || body.status !== 'success') throw new Error('Prometheus API failed (' + res.status + ')');
  return body.data;
}
const series = [
  'up', 'tutorlink:api_requests_per_second:30s', 'tutorlink:api_p95_seconds:30s',
  'tutorlink:api_5xx_ratio:30s', 'tutorlink_db_up', 'tutorlink_db_client_connections',
  'tutorlink_db_connection_limit', 'tutorlink_db_lock_waiters', 'tutorlink_db_wal_bytes_total',
  'tutorlink_db_oldest_outbox_seconds', 'tutorlink_db_pending_outbox', 'tutorlink_db_failed_outbox',
  'tutorlink_db_queued_jobs', 'tutorlink_db_failed_jobs', 'tutorlink_fpm_up', 'tutorlink_fpm_listen_queue',
];
const [alerts, ...values] = await Promise.all([
  get('alerts'), ...series.map(metric => get('query?' + new URLSearchParams({ query: metric }))),
]);
console.log(JSON.stringify({
  checked_at: new Date().toISOString(),
  alerts: alerts.alerts.map(({ labels, state, activeAt, value }) => ({ labels, state, activeAt, value })),
  metrics: Object.fromEntries(series.map((metric, index) => [metric, values[index].result])),
}, null, 2));
JS
        ;;
    down)
        compose rm -sfv runtime-metrics prometheus
        compose exec -T db psql -X -v ON_ERROR_STOP=1 -U capacity_runner -d tutorlink_capacity <<'SQL'
SELECT 'DROP OWNED BY capacity_monitor; DROP ROLE capacity_monitor'
WHERE EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'capacity_monitor')
\gexec
SQL
        ;;
    *) echo 'Usage: monitoring/run.sh {up|status|down}' >&2; exit 1;;
esac
