# BE-H03 / Issue #75 — Invitation, membership và kết thúc Program

Ngày kiểm chứng: 31/10/2026. Branch: `codex/2w-be-h03`, base `9c9d746` (đã có BE-K03 và BE-D03). Phạm vi backend local; chờ review, chưa commit/push/PR hoặc triển khai production.

## Thay đổi API và schema

- Bổ sung `POST /api/v1/invitations/{invitation}/reject` cho active Customer đích và `POST /api/v1/invitations/{invitation}/cancel` cho active Tutor owner. Request chỉ có `reason` tùy chọn/null, tối đa 2.000 ký tự; lý do lưu trong `audit_logs.reason`, không vào notification payload. Requiredness vẫn cần Product review; đây là lựa chọn runtime, không phải quyết định Product mới.
- Thành công trả 200 `data: InvitationResource`, giữ nguyên Program Draft, offer version/snapshot và lịch sử; không tạo membership. Sai actor trả 404; pending tại/qua expiry, terminal hoặc Program không còn Draft trả 409. Không yêu cầu Idempotency-Key cho hai endpoint mới; request lặp terminal trả 409 và không thêm audit/outbox.
- Thêm command `invitations:expire`, đăng ký scheduler mỗi phút với `withoutOverlapping(5)`. Mỗi row có transaction riêng, kiểm lại pending và server UTC sau khi lấy khóa. Qua nhiều batch vẫn xử lý hết; bản ghi đã đổi trạng thái bị bỏ qua. Lỗi row được report, rollback và tiếp tục row khác; command trả failure nếu có lỗi, row pending được thử lại ở phút sau.
- System expiry ghi audit actor null, responded_at theo expires_at gốc và outbox cho Tutor. Lazy expiry cũ khi PATCH/send Draft vẫn giữ nguyên actor/request của Tutor. Không thêm API expire công khai hoặc migration; dùng schema hiện có.
- Thứ tự khóa dùng chung với accept: Users tăng dần ID → TutorProfile → Listing nếu có → Program → Invitation. Kết quả terminal, audit, outbox và `SyncConversationLifecycle` nằm trong một transaction. Không tự tạo/reopen chat; giữ members/messages.
- Kế thừa AcceptInvitation và TransitionProgram. Program.version vẫn là offer version, tăng khi sửa Draft; không thêm version guard hoặc tăng version cho pause/resume/complete/cancel. Complete/cancel giữ membership/lịch sử, trả blocker trong `errors.blockers.sessions/tasks`; không tự đổi Task.
- OpenAPI xuất bằng Scramble từ schema SQLite memory có guard, không chỉnh JSON tay: **83 operations**, tăng hai so với base; [parity đạt](evidence/be-h03/openapi-parity.txt). Catalog và M05/API docs đã đồng bộ runtime cùng giới hạn.

## Kiểm thử và bằng chứng

- [Focused PostgreSQL](evidence/be-h03/focused-postgres.txt): **117 test, 1.071 assertions đạt**. Bao gồm InvitationDecisionApiTest, InvitationExpiryTest, InvitationAcceptConcurrencyTest, ProgramLifecycleApiTest và các test Program/Invitation/Task liên quan.
- `InvitationDecisionApiTest`: reject/cancel đúng actor; từ chối outsider, role sai, đối phương, suspended hoặc thiếu auth; validation reason/unknown fields; biên expiry và terminal; retry không lặp side effect; sau quyết định có thể sửa Draft và gửi Invitation mới mà snapshot cũ không đổi.
- `InvitationExpiryTest`: past/exact boundary/future/terminal, parties suspended, system audit/recipient đúng, hai lần command không lặp, 101 row qua nhiều batch, row không còn tồn tại hoặc đã xử lý được bỏ qua; giữ chat active khi có Program paused và đóng khi quan hệ kết thúc. Fault PostgreSQL tại outbox chứng minh rollback row lỗi, tiếp tục row khác và retry thành công.
- `InvitationAcceptConcurrencyTest`: PHP process độc lập, parent giữ row lock, chờ `pg_blocking_pids` xác nhận worker bị chặn rồi nhả khóa. Double accept; accept-vs-Invitation reject/cancel theo cả hai thứ tự; hai expiry worker cạnh tranh accept trước/sau hạn; expiry-vs-cancel; các race có sẵn accept-vs-Draft-cancel/account-suspend/atomic-profile-suspend tiếp tục đạt. Membership chỉ có khi accept commit, hai row Tutor/Customer; audit/outbox không nhân đôi.
- Race complete-vs-create-Task giữ Program lock, kiểm cả hai thứ tự: complete trước → không tạo Task sau terminal; Task trước → complete bị chặn, Task còn todo và Program active. Không coi race Task là chứng nhận Session writer chưa có.
- `ProgramLifecycleApiTest`: mọi TaskStatus qua complete/cancel; todo/in_progress/submitted trả blocker đúng ID/status/required_action và không ghi side effect; completed/cancelled cho phép. Luồng HTTP pending Invitation chưa được đọc Program/Task → accept → tạo Task → pause → start/submit → request-revision → resubmit version 2 → accept feedback → complete. Hai Submission giữ lịch sử, membership và quyền đọc sau terminal; không tạo Task hoặc resume Program terminal.
- Fault PostgreSQL tại insert outbox cho cả accept/reject/cancel Invitation trả 500 và rollback Invitation/Program, membership, audit, receipt/outbox; không có Notification giả.
- [Lượt full suite với image cũ](evidence/be-h03/full-postgres-old-image-missing-zip.txt): **524 đạt, 9 lỗi** tại ZipScanGuardTest vì PHP runtime thiếu `ZipArchive`. Đây là lỗi image local cũ, không dùng làm kết quả full suite cuối. [Build image từ Dockerfile hiện tại](evidence/be-h03/runtime-build.txt) đã hoàn tất. [ZIP test trên runtime mới](evidence/be-h03/zip-runtime-postgres.txt): **10/10, 16 assertions đạt**. [Toàn backend trên PostgreSQL, runtime mới](evidence/be-h03/full-postgres.txt): **533/533, 3.747 assertions đạt**, exit 0, `--fail-on-warning`; thời gian 103,13 giây. [Runtime](evidence/be-h03/runtime.txt): PHP 8.3.36, ZIP và pdo_pgsql available. Log image cũ được giữ để truy vết, đã được thay bằng kết quả full đạt này.
- [Scramble export](evidence/be-h03/openapi-export.txt), [route parity](evidence/be-h03/openapi-parity.txt), [Redocly lint](evidence/be-h03/openapi-lint.txt), [scheduler](evidence/be-h03/schedule.txt), Pint [tracked](evidence/be-h03/pint-dirty.txt) và [new files](evidence/be-h03/pint-new-files.txt). Redocly hợp lệ, có 59 warnings; [lint artifact base](evidence/be-h03/openapi-baseline-lint.txt) cũng có 59, không phát sinh warning mới; không khẳng định zero-warning.

## Tái lập

Từ repository root, dùng database test riêng trong `compose.testing.yml`; các test có guard chỉ chấp nhận PostgreSQL `tutorlink_test`/`tutorlink_phase2_test` hoặc SQLite memory. `--build` cần thiết nếu image local cũ chưa có ZIP/Reverb.

```sh
docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest tests/Feature/InvitationDecisionApiTest.php tests/Feature/InvitationExpiryTest.php tests/Feature/InvitationAcceptConcurrencyTest.php tests/Feature/ProgramLifecycleApiTest.php
docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest --display-warnings --fail-on-warning
```

Xuất OpenAPI theo `docs/api/README.md` hoặc CI guarded memory export. Để bật tự động expiry trong môi trường ứng dụng, dùng service `scheduler` hiện hữu cùng `outbox-worker` tại `compose.workers.yml`; command ghi outbox, worker chuyển notification sau commit. Không tự chạy scheduler trên database ứng dụng trong lượt kiểm thử này.

## Giới hạn và bước tiếp theo

- Chưa triển khai UI Invitation/Program, staging/production, tải vận hành hoặc dữ liệu production-like. Test DB và fault triggers đều cô lập; không migrate/fresh database ứng dụng.
- Session API/writer và Listing moderation writer chưa triển khai trong phạm vi này. Có kiểm blocker scheduled Session và hold từ trước trong AcceptInvitationTest, nhưng không chứng nhận race với writer chưa có.
- Policy bắt buộc nhập reason reject/cancel vẫn cần Product review. Không tự gắn nhãn quyết định đó đã được duyệt.
- Kiểm thử mới xác nhận tính nguyên tử của audit/outbox, không chạy lại delivery worker hoặc WebSocket transport đã được bàn giao ở BE-K02/BE-K03.
- Scheduler cần được vận hành và theo dõi command failure khi rollout; outbox giữ retry/monitor hiện có từ BE-K02. Không thêm hệ thống retry vô hạn qua queue hoặc sửa outbox contract.
- Code/evidence local chờ Dũng review. Chưa đăng comment, đóng issue, commit/push hoặc mở PR; không coi #75 đã được review/merge.
