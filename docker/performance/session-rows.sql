WITH benchmark_sessions AS (
    SELECT id, program_id, title, planned_starts_at, planned_ends_at
    FROM program_sessions
    WHERE title LIKE 'Capacity ' || :'run_id' || ' %'
)
SELECT json_build_object(
    'key', r.idempotency_key,
    'session_id', s.id,
    'program_id', s.program_id,
    'title', s.title,
    'planned_starts_at', s.planned_starts_at,
    'planned_ends_at', s.planned_ends_at
)
FROM benchmark_sessions s
LEFT JOIN idempotency_receipts r ON r.response_snapshot #>> '{data,id}' = s.id::text
    AND r.method = 'POST' AND r.response_status = 201 AND r.canonical_path LIKE '%/sessions';
