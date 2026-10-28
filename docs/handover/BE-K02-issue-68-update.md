# Nội dung cập nhật issue #68 — bản nháp chưa đăng

**Trạng thái:** hoàn tất triển khai và evidence K02.1–K02.8 ở local, sẵn sàng commit/PR để review.

- [x] Writer transaction, outbox và UNIQUE recipient/type/event_key; rollback không để lại event.
- [x] Worker claim, retry/crash recovery, failed/replay có quyền/reason/audit, scheduler và retention.
- [x] Slack chat.postMessage dùng secret backend; timeout/429/Retry-After/ok=false/lỗi vĩnh viễn và lọc log.
- [x] Bộ test nghiệm thu và runbook; demo Slack thật có ảnh và log đối chiếu.

**Validation:** 74 tests passed, 463 assertions; exit 0; SIGKILL trước commit xử lý lại được, không tạo notification trùng. OpenAPI parity 48 operations; lint hợp lệ, còn 51 warnings mô tả.

**Demo:** channel C0C8BM660TB; alert bc6b9a95-3438-4480-a784-e6155a364cf6; outbox 67e77d46-3367-4f3f-baeb-2530d6153fec; processed_at có giá trị, failed_at null, notification count 1. Ảnh và log trong docs/handover/evidence/be-k02-k02.8/.

**Bàn giao:** docs/handover/BE-K02-handover.md, BE-K02-K02.8-test-report.md, BE-K02-K02.6-runbook.md, BE-K02-K02.8-demo.md. API delta: xem pending/failed và replay; schema delta: precision timestamp 6. Known limitations đầy đủ trong handover; không tuyên bố exactly-once cho Slack.

**Việc tiếp theo:** Khanh commit/push, tạo hoặc cập nhật PR và bổ sung URL vào issue; Hoàng review, theo dõi CI rồi merge theo quy trình nhóm. Chưa đóng issue.

**Blocker:** không có blocker kỹ thuật trong phạm vi đã kiểm; cần GitHub username của Hoàng nếu muốn gán reviewer. Chưa có PR/commit K02.8 được publish trong lượt này theo yêu cầu người dùng.
