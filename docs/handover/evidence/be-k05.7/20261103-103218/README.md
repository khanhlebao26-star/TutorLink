# BE-K05.7 evidence — 20261103-103218

- `summary.json`: kết quả tổng hợp và mục Pending.
- `source-metadata.json`, `initial-working-tree.txt`: input working tree; không phải một commit bàn giao đã tạo.
- `format-summary.json`: kết quả formatter quan sát được trước tests.
- `focused-junit.xml`, `focused-cases.json`: 116 case, phân quyền/privacy/sampling/pruning/alerts/runtime/health.
- Bốn JUnit full suite và `test-summary.json`: 806 tests / 6.970 assertions.
- `coverage.txt`: báo cáo coverage toàn backend.
- `slack-delivery.json`: receipt allow-list, hai message thật trên dev local, isolated smoke queue.
- `runtime-redaction-scan.json`: flags, row counts và số match của synthetic canary; không xuất raw record.
- `worker-stale-ready.json`, `runtime-during-worker-stop.json`: stale thực tế. `worker-within-timeout-ready.json` là lượt đầu chưa stale, không được dùng làm proof vượt ngưỡng.
- `db-down-up.json`, `db-down-ready.json`, `db-outage-observations.json`: proof DB được giữ dừng đến khi request hoàn tất; có cảnh báo latency.
- `db-transition-*`: lượt request hoàn tất sau khi DB bật lại; chỉ tham khảo, không tính proof DB-down.
- `*-recovered-ready.json`, `runtime-final-*`: phục hồi và trạng thái cuối.
- `runtime-processes.txt`, image/restart snapshots, redirect, schedule, config: runtime PHP-FPM/Caddy.
- `container-stats.txt`: tham khảo container, không phải chứng cứ host threshold alert.
- `openapi-checks.json`: parity/lint/EOL-normalized equality; 59 warning được phân loại.
- `secret-scan.json`: quét exact secret values đã cấu hình và private-key markers; không xuất giá trị secret.
- `manifest.json`: SHA-256 các evidence sau khi kiểm tra, loại chính manifest để tránh tự tham chiếu.

Raw logs/coverage HTML/export spec/helper nằm trong ignored outputs/build. Không đưa .env thật, DB dump, storage archive, TLS private keys vào evidence.
