WITH sessions AS (
    SELECT id, title, program_id, planned_starts_at, planned_ends_at
    FROM program_sessions
    WHERE title LIKE 'Capacity ' || :'run_id' || ' %'
), create_audits AS (
    SELECT a.resource_id AS session_id, count(*) AS total
    FROM audit_logs a JOIN sessions s ON a.resource_id = s.id
    WHERE a.resource_type = 'session' AND a.action = 'session.create'
    GROUP BY a.resource_id
), create_receipts AS (
    SELECT s.id AS session_id, count(*) AS total
    FROM idempotency_receipts r JOIN sessions s ON r.response_snapshot #>> '{data,id}' = s.id::text
    WHERE r.method = 'POST' AND r.response_status = 201 AND r.canonical_path LIKE '%/sessions'
    GROUP BY s.id
)
SELECT json_build_object(
    'sessions', (SELECT count(*) FROM sessions),
    'distinct_titles', (SELECT count(DISTINCT title) FROM sessions),
    'distinct_slots', (SELECT count(DISTINCT (program_id, planned_starts_at, planned_ends_at)) FROM sessions),
    'missing_or_duplicate_create_audits', (
        SELECT count(*) FROM sessions s LEFT JOIN create_audits a ON a.session_id = s.id
        WHERE a.total IS DISTINCT FROM 1
    ),
    'missing_or_duplicate_receipts', (
        SELECT count(*) FROM sessions s LEFT JOIN create_receipts r ON r.session_id = s.id
        WHERE r.total IS DISTINCT FROM 1
    )
);
