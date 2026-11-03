# BE-K05 — Nội dung cập nhật issue

**Trạng thái:** đã kiểm thử và chuẩn bị bàn giao local; chờ người dùng commit/push, PR và Hoàng review.

- Focused: 116 tests / 473 assertions.
- Full suite: 806 tests / 6.970 assertions; không lỗi/skipped.
- Line coverage: 93,10%.
- OpenAPI khớp 122 operations; lint exit 0, còn 59 warning baseline.
- Hai Slack alert thật được chấp nhận; kiểm tra threshold/cooldown/retry/chống vòng lặp đạt.
- Dashboard guest HTTPS 401; các vai trò/session/Livewire và redaction được kiểm tra tự động.
- PHP-FPM/Caddy, worker/scheduler chạy trên Docker local. Health phân biệt worker stale và DB down, phục hồi về 200; các worker tự restart sau DB outage.
- Tất cả service đã được khôi phục; outbox pending/failed và queue/failed jobs cuối đợt bằng 0.

**Cần xử lý tiếp:**
1. Health đúng mã nhưng chậm khi DB container dừng: /up 15,77s, readiness 30,82s. Điều tra timeout/dependency trước khi đặt probe deadline ngắn.
2. Host CPU/RAM/disk/network và cảnh báo tài nguyên: Pending do nhóm chưa chọn host/công cụ.
3. Domain/DNS/TLS công khai và host reboot: chưa kiểm chứng.
4. Runbook backup/restore/rollback đã viết; diễn tập và off-host backup/retention còn chờ evidence/xác nhận.
5. Production-profile local tắt bộ phát hiện alert; delivery thật mới kiểm tra ở development local.
6. Hoàng review, PR, CI từ xa: Pending sau khi người dùng push.

Báo cáo: `docs/handover/BE-K05-test-report.md`.  
Evidence: `docs/handover/evidence/be-k05.7/20261103-103218/`.  
Runbook: `docs/handover/BE-K05.6-backup-restore-rollback-runbook.md`.

Điền link PR và SHA commit sau khi commit/push. Không đóng toàn bộ issue BE-K05 trong lúc các acceptance về host/deployment chưa được giải quyết hoặc được nhóm thống nhất tách phạm vi.
