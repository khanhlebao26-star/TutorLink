\getenv password PERF_MONITOR_PASSWORD

SELECT 'CREATE ROLE capacity_monitor LOGIN'
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'capacity_monitor')
\gexec

ALTER ROLE capacity_monitor WITH PASSWORD :'password';
ALTER ROLE capacity_monitor SET default_transaction_read_only = on;
ALTER ROLE capacity_monitor SET statement_timeout TO '2s';
GRANT pg_read_all_stats TO capacity_monitor;
GRANT SELECT (processed_at, resolved_at, failed_at, replay_requested_at, created_at)
    ON notification_outbox TO capacity_monitor;
GRANT SELECT (id) ON jobs, failed_jobs TO capacity_monitor;
