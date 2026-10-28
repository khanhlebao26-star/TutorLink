# BE-K02 — Transactional outbox, notification và Slack operational alerts

Refs #68.

Mutation Tutor Profile giờ ghi outbox cùng transaction nghiệp vụ và audit. Worker PostgreSQL tạo notification sau commit, chống trùng theo recipient/type/event_key và retry có giới hạn; operator xem/replay failed với quyền, reason và audit. Slack chỉ nhận cảnh báo vận hành failed/pending, bàn giao qua database queue cùng transaction và không làm rollback nghiệp vụ khi Slack lỗi.

Thêm Docker outbox-worker/slack-worker/scheduler, monitor và retention có audit. Demo Slack thật đã nhận cảnh báo pending ở channel thử nghiệm, alert ID khớp log slack_sent; sau bật worker, event có đúng một notification.

## Validation

- 74 tests passed, 463 assertions trên PostgreSQL 16; gồm hai kết nối độc lập, replay, retry, SIGKILL trước commit, Slack error paths và transaction handoff.
- OpenAPI được sinh lại từ Scramble; parity 48 operations; Redocly hợp lệ, còn 51 warnings chất lượng mô tả.
- [Test report](BE-K02-K02.8-test-report.md), [runbook](BE-K02-K02.6-runbook.md), [Slack demo](BE-K02-K02.8-demo.md).

## API/schema delta

- GET /api/v1/admin/notification-outbox và POST /api/v1/admin/notification-outbox/{outbox}/replay; session/CSRF và quyền operator riêng.
- Dùng lại outbox/UNIQUE từ BE-D01; migration K02 nâng precision 6 cho các timestamp retry/replay để tránh xung đột cùng giây.
- Secret chỉ ở backend; không commit token thật. Xem [handover](BE-K02-handover.md) để biết config/quyền/retention.

## Limitations

Không hứa exactly-once cho Slack; timeout có thể tạo tin trùng. Chưa có heartbeat/out-of-band detector; DB role retention riêng chưa được kiểm chứng. Các module ngoài quyết định Tutor Profile cần tích hợp writer theo contract; chưa có toàn bộ REST Notification Inbox. Không suy ra toàn bộ repo/CI pass từ bộ test K02.

Reviewer dự kiến: Hoàng. Giữ issue mở đến khi review/merge theo quy trình nhóm.

---

Ghi chú khi copy sang GitHub: đây là bản nháp local, chưa đăng. Chuyển các link tài liệu tương đối thành URL `https://github.com/khanhlebao26-star/TutorLink/blob/be-k02/docs/handover/<tên-file>` sau khi push nhánh.
