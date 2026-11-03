# BE-K05: giám sát backend và cấu hình runtime Docker

Backend có Telescope/Pulse được giới hạn quyền và lọc dữ liệu nhạy cảm, alert critical/slow-query qua Slack có cooldown, cùng heartbeat và health check phân biệt app/DB/worker. Runtime Docker dùng PHP-FPM và Caddy HTTPS; có runbook backup/restore/rollback.

## Phạm vi

- Dashboard, redaction, sampling và pruning.
- Slack job: allow-list payload, retry có giới hạn, chống alert đệ quy.
- Worker heartbeat, outbox lag và queue/failed-job summaries.
- Image no-dev, app/worker/scheduler/Reverb riêng, restart policy và TLS nội bộ.
- Health readiness 200/503, tài liệu và evidence.
- API/schema delta chi tiết trong báo cáo: route hệ thống /health/ready, permission monitoring và sáu bảng telemetry; OpenAPI nghiệp vụ không đổi.

## Validation local

- Pint passed trước test.
- Focused: **116 tests / 473 assertions**.
- Full suite: **806 tests / 6.970 assertions**, không failure/error/skipped.
- Line coverage toàn backend: **93,10% (8.200/8.808)**.
- OpenAPI export/parity: **122 operations**; Redocly 2.60.0 exit 0, **59 warning baseline**.
- Hai alert thật được Slack API chấp nhận, cooldown không thêm intent; mẫu gửi không có canary/SQL/token.
- Guest HTTPS bị chặn 401 ở cả hai dashboard.
- Worker stale => readiness 503; DB dừng => /up 200, readiness 503; sau phục hồi => ready 200.
- Worker tự restart sau DB outage, cuối đợt app/DB/heartbeat đều khỏe.

Báo cáo: [BE-K05-test-report.md](https://github.com/khanhlebao26-star/TutorLink/blob/be-k05/docs/handover/BE-K05-test-report.md).  
Runbook: [BE-K05.6-backup-restore-rollback-runbook.md](https://github.com/khanhlebao26-star/TutorLink/blob/be-k05/docs/handover/BE-K05.6-backup-restore-rollback-runbook.md).  
Evidence chính: [summary.json](https://github.com/khanhlebao26-star/TutorLink/blob/be-k05/docs/handover/evidence/be-k05.7/20261103-103218/summary.json), [JUnit](https://github.com/khanhlebao26-star/TutorLink/blob/be-k05/docs/handover/evidence/be-k05.7/20261103-103218/focused-junit.xml), [Slack](https://github.com/khanhlebao26-star/TutorLink/blob/be-k05/docs/handover/evidence/be-k05.7/20261103-103218/slack-delivery.json), [health timings](https://github.com/khanhlebao26-star/TutorLink/blob/be-k05/docs/handover/evidence/be-k05.7/20261103-103218/db-outage-observations.json).

Các link trỏ đến nhánh be-k05 và sẽ truy cập được sau khi người dùng commit/push các file bàn giao.

## Known limitations / follow-up

- **Health chậm khi DB container dừng:** /up 15,77s, readiness 30,82s; cần xử lý trước khi dùng probe timeout ngắn.
- Chưa chọn hosting/công cụ metric host; chưa test cảnh báo CPU/RAM/disk/network, domain/TLS công khai hoặc host reboot.
- Live Slack được kiểm tra trên development local với isolated smoke queue; bộ phát hiện alert của production profile local đang tắt.
- Runbook đã có, **chưa có evidence restore/rollback drill trong đợt này**.
- Phân quyền/Livewire/redaction có test HTTP kernel; chưa chụp lại phiên trình duyệt.
- 59 OpenAPI warning tồn tại trong contract hiện có; frontend/remote CI chưa chạy lại trong đợt này.

## Review

Reviewer: **Hoàng — Pending**.

Đối chiếu ma trận acceptance với evidence, xem quyết định về độ trễ health, phạm vi host còn Pending và kế hoạch diễn tập phục hồi. Đây là bàn giao Docker local, chưa xác nhận triển khai production.

## Ghi chú người bàn giao

Chưa commit/push hoặc tạo PR từ xa. Sau khi commit, ghi SHA vào PR/issue và xem kết quả GitHub Actions trước khi merge.
