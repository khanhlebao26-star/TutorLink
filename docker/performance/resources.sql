WITH outbox AS (
    SELECT count(*) AS pending, min(created_at) AS oldest
    FROM notification_outbox
    WHERE processed_at IS NULL AND resolved_at IS NULL
      AND (failed_at IS NULL OR replay_requested_at > failed_at)
)
SELECT json_build_object(
    'timestamp', clock_timestamp(),
    'client_connections', (
        SELECT count(*) FROM pg_stat_activity
        WHERE datname = current_database() AND pid <> pg_backend_pid() AND backend_type = 'client backend'
    ),
    'connection_limit', current_setting('max_connections')::integer,
    'client_sessions_cumulative', (SELECT sessions FROM pg_stat_database WHERE datname = current_database()),
    'lock_waiters', (
        SELECT count(*) FROM pg_stat_activity
        WHERE datname = current_database() AND wait_event_type = 'Lock'
    ),
    'deadlocks_cumulative', (SELECT deadlocks FROM pg_stat_database WHERE datname = current_database()),
    'wal_bytes_cumulative', (SELECT wal_bytes FROM pg_stat_wal),
    'pending_outbox', outbox.pending,
    'oldest_outbox_seconds', coalesce(greatest(0, extract(epoch FROM clock_timestamp() - outbox.oldest)), 0),
    'failed_outbox', (
        SELECT count(*) FROM notification_outbox
        WHERE processed_at IS NULL AND resolved_at IS NULL AND failed_at IS NOT NULL
          AND (replay_requested_at IS NULL OR replay_requested_at <= failed_at)
    ),
    'queued_jobs', (SELECT count(id) FROM jobs),
    'failed_jobs', (SELECT count(id) FROM failed_jobs)
)
FROM outbox;
