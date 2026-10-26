# BE-D02 B3 — Báo cáo kiểm thử và bằng chứng

**Ngày chạy:** 27/10/2026
**Phạm vi:** Issue #69 — Program, Invitation, lifecycle và Accept theo [contract giai đoạn A](BE-D02-phase-A-contract.md).
**Trạng thái:** Đã có bằng chứng cho các hành vi BE-D02 hiện triển khai. Hai nhóm race cần writer ngoài phạm vi BE-D02 vẫn là release gate; xem [Giới hạn bằng chứng](#7-giới-hạn-bằng-chứng-và-việc-còn-lại).

## 1. Kết quả tóm tắt

| Database | Kết quả | Ý nghĩa |
| --- | --- | --- |
| PostgreSQL 16, database test dùng một lần | **50 test pass, 325 assertion, 0 fail/skip** | Bao gồm bốn kiểm thử cạnh tranh PostgreSQL. Đây là lần chạy cuối trên database cô lập `tutorlink_test`; container đã được dừng và tự xóa sau chạy. |
| SQLite `:memory:` | **50 test được thu thập: 46 pass, 4 skip, 272 assertion, 0 fail** | Bốn test bị skip có chủ đích vì chúng kiểm chứng row lock và cạnh tranh giữa các PostgreSQL session. |

Phạm vi chạy là sáu test file liên quan BE-D02: `ProgramApiTest`, `InvitationApiTest`, `InvitationAcceptTest`, `ProgramLifecycleApiTest`, `NotificationOutboxWriterTest` và `InvitationAcceptConcurrencyTest`. Kết quả trên là bằng chứng kiểm thử ở local test environment; không phải xác nhận migration hoặc cấu hình đã được áp dụng ở staging/production.

## 2. Cách chạy và cách kiểm chứng race

Lệnh SQLite chạy từ thư mục `backend`:

```bash
php artisan test --compact \
  tests/Feature/ProgramApiTest.php \
  tests/Feature/InvitationApiTest.php \
  tests/Feature/InvitationAcceptTest.php \
  tests/Feature/ProgramLifecycleApiTest.php \
  tests/Feature/NotificationOutboxWriterTest.php \
  tests/Feature/InvitationAcceptConcurrencyTest.php
```

Lệnh PostgreSQL dùng cùng danh sách file trên với `DB_CONNECTION=pgsql`, `DB_DATABASE=tutorlink_test`, `APP_ENV=testing`, `DB_URL` để trống và kết nối tới PostgreSQL 16 dùng một lần tại localhost. Password không được ghi vào report. `TestDatabaseGuard` từ chối database không thuộc allow-list test; database cấu hình sẵn của ứng dụng không được dùng.

Race test tạo fixture đã commit, mở các tiến trình Laravel riêng, giữ khóa hàng `users` trong tiến trình kiểm thử, rồi đợi PostgreSQL xác nhận từng tiến trình đang bị chặn qua `pg_blocking_pids`. Sau đó test thả khóa, chờ các thao tác hoàn tất và kiểm tra status response cùng dữ liệu cuối trong database. Nhờ vậy kiểm thử không suy diễn concurrency từ SQLite hay từ hai query chạy tuần tự.

Trong race Accept-vs-suspend, tiến trình thứ hai gọi `AdminController::suspendAccount` với `AdminDecisionRequest` đã validate. Race test tập trung vào transaction và row lock của writer; middleware/auth HTTP được kiểm chứng riêng bởi các test endpoint hiện có. Các race Accept khác gọi action BE-D02 trực tiếp trong tiến trình mới; endpoint HTTP Accept được kiểm chứng riêng tại `InvitationAcceptTest`.

## 3. Ma trận tiêu chí và bằng chứng

| Tiêu chí | Test và bằng chứng quan sát được |
| --- | --- |
| Create, list, detail và quyền truy cập Program | [`ProgramApiTest`](../../backend/tests/Feature/ProgramApiTest.php): tạo Draft thành công; Customer không tạo được; Tutor chỉ thấy Program trong scope; Customer không đọc Program trước khi active member; Customer đã là active member vẫn không được PATCH Program; Admin bị từ chối list. Test kiểm tra status/error code và số row khi thao tác bị từ chối. |
| Field validation và stale version | [`ProgramApiTest`](../../backend/tests/Feature/ProgramApiTest.php): field/status không hỗ trợ trả 422; update hợp lệ tăng version 1→2; version stale trả 409 và giữ nguyên title/version/status, không tạo audit. |
| Khóa Draft bởi Invitation pending | [`ProgramApiTest`](../../backend/tests/Feature/ProgramApiTest.php) và [`InvitationApiTest`](../../backend/tests/Feature/InvitationApiTest.php): PATCH/send bị từ chối khi Invitation chưa hết hạn; số Invitation/outbox không tăng. Với Invitation quá hạn, PATCH/send chốt expiry cùng transaction; nếu request dùng version stale, Invitation vẫn pending và không có audit/outbox. |
| Snapshot, inbox và quyền đọc trước membership | [`InvitationApiTest`](../../backend/tests/Feature/InvitationApiTest.php): send trả snapshot allow-list, khóa Draft, ghi audit/outbox và retry cùng key không tạo bản ghi thứ hai; Customer đích đọc được Invitation trong khi GET Program vẫn 404; Customer khác không thấy inbox và detail trả 404. Test còn xác nhận GET chỉ trả trạng thái expired hiệu lực mà không tự ghi DB. |
| Accept: actor, expiry, version, eligibility và moderation hold | [`InvitationAcceptTest`](../../backend/tests/Feature/InvitationAcceptTest.php): Customer khác bị 404 không mutation; đúng thời điểm expiry trả 409; version/snapshot/giấy tờ sai hạn/hold trả conflict; Listing inactive không có hold vẫn accept được. Các ca lỗi kiểm tra Program chưa active, Invitation chưa accepted, không có membership/audit/outbox giả. |
| Accept: idempotency và request lỗi | [`InvitationAcceptTest`](../../backend/tests/Feature/InvitationAcceptTest.php): retry cùng key trả lại JSON đã commit, chỉ có hai membership, một audit, một receipt và một outbox; key dùng cho Invitation khác trả 409; body không hỗ trợ hoặc key không phải UUID trả 422; request chưa xác thực trả 401. |
| Lifecycle hợp lệ, sai trạng thái và blocker | [`ProgramLifecycleApiTest`](../../backend/tests/Feature/ProgramLifecycleApiTest.php): pause → resume → pause giữ nguyên offer version và tạo ba event key riêng; transition từ Draft sang pause trả 409, không audit/outbox; cancel Draft hủy Invitation cùng transaction; complete hợp lệ khi Task terminal; Session scheduled/Task chưa terminal làm complete/cancel trả blocker, Program giữ nguyên trạng thái và không phát side effect. Tutor khác nhận 404. |
| Transaction và quy tắc Outbox | [`NotificationOutboxWriterTest`](../../backend/tests/Feature/NotificationOutboxWriterTest.php), cùng các API test phía trên: rollback xóa outbox; worker chưa tạo Notification trước khi xử lý; ghi/replay không nhân đôi; event key dùng lại với payload khác và payload ngoài allow-list bị từ chối; deep link `invitation_accepted` phải trỏ đúng Program ID. |

Tên ca test cụ thể có thể tìm trực tiếp trong các file được liên kết; các test API kiểm tra response và state persistence, không chỉ kiểm tra class/action được gọi.

## 4. Bằng chứng kiểm thử cạnh tranh PostgreSQL

Bốn ca trong [`InvitationAcceptConcurrencyTest`](../../backend/tests/Feature/InvitationAcceptConcurrencyTest.php) đã pass trên PostgreSQL. Mỗi ca kiểm tra cả kết quả thao tác lẫn trạng thái cuối và side effect:

| Race | Kết quả được chấp nhận | Assertion chứng minh |
| --- | --- | --- |
| Accept-vs-Accept | Một request 200, request còn lại 409 | Program active; Invitation accepted; đúng hai membership; một idempotency receipt, một audit `invitation.accept`, một outbox `invitation_accepted`. |
| Accept-vs-lazy-expiry | PATCH Draft thành công, Accept conflict | Invitation expired; Program vẫn Draft và version tăng; không membership/receipt; audit expiry + update và đúng một outbox expiry. |
| Accept-vs-cancel Draft | Kết quả phải khớp một thứ tự transaction hợp lệ | Nếu cancel thắng: Invitation/Program cancelled, không membership và Accept không để side effect. Nếu Accept thắng trước: Accept có membership/audit/outbox, sau đó cancel Active hợp lệ và Program thành cancelled. Test từ chối trạng thái trộn giữa hai thứ tự này. |
| Accept-vs-đình chỉ Tutor | Kết quả phải khớp một thứ tự transaction hợp lệ | Nếu đình chỉ thắng: Tutor suspended, Program Draft, Invitation pending, không membership hay side effect Accept. Nếu Accept thắng trước: Accept đã commit đủ membership/audit/outbox rồi việc đình chỉ mới commit. |

Các test kiểm tra idempotency, audit, membership và outbox trong cùng evidence vì response thành công đơn lẻ không chứng minh mutation nguyên tử.

## 5. Vấn đề được phát hiện và sửa trong lúc kiểm thử

PostgreSQL bắt lỗi fixture Session trong `ProgramLifecycleApiTest`: `planned_ends_at` phải lớn hơn `planned_starts_at`, nhưng test truyền hai giá trị dựa trên cùng một Carbon mutable object. Việc mutate object cho giờ kết thúc cũng làm thay đổi giá trị giờ bắt đầu. Fixture chuyển sang `CarbonImmutable`; giờ kết thúc hiện muộn hơn giờ bắt đầu và test blocker chạy được trên cả PostgreSQL lẫn SQLite.

Bổ sung thêm test transition không hợp lệ để chứng minh `POST .../pause` trên Draft trả 409, không đổi Program và không tạo audit/outbox.

## 6. Những điều kết quả này chứng minh

- Các tiêu chí B1/B2 đã được liệt kê trong ma trận có test chạy qua trên PostgreSQL; nhóm API cũng qua trên SQLite.
- Các transaction cạnh tranh trong bốn race test chạy trên nhiều PostgreSQL backend PID riêng. Test xác nhận chỉ xuất hiện side effect tương ứng với một thứ tự commit hợp lệ.
- Error/rollback path được kiểm tra qua state trong database; test không chỉ dựa trên status code.

## 7. Giới hạn bằng chứng và việc còn lại

1. **Accept-vs-moderation-hold:** test hiện có chứng minh send/Accept bị chặn khi hold đã tồn tại. Tuy nhiên moderation-hold writer chưa được triển khai trong phạm vi BE-D02, nên chưa có writer thật để cạnh tranh và chưa chứng minh được race này.
2. **Complete/cancel-vs-ghi Session/Task:** lifecycle test chứng minh Session/Task đã tồn tại sẽ chặn complete/cancel. Writer tạo/sửa Session/Task chưa thuộc phạm vi BE-D02, nên chưa có race test hành vi thật cho các writer đó.
3. **Full suite:** lần chạy trong report chỉ gồm sáu test file BE-D02 nêu tại mục 1. Chưa chạy toàn bộ backend suite; trước khi merge, còn bước `php artisan test --compact` theo hướng dẫn backend của repo.
4. **Môi trường triển khai:** test không xác nhận migration đã chạy trên môi trường dùng chung, không thực hiện performance/load test và không kiểm chứng Notification worker ngoài các test riêng đã liệt kê.

Vì hai nhóm writer ở mục 1–2 chưa tồn tại, báo cáo không xem các race đó là đã pass và không tạo writer giả chỉ để làm xanh test. Chúng vẫn là release gate khi các module tương ứng có implementation.

## Tài liệu liên quan

- [BE-D02 phase-A contract](BE-D02-phase-A-contract.md), đặc biệt mục 6 về Accept và quy tắc lock.
- [BE-D02 roadmap](BE-D02-roadmap.md), B3 và các giới hạn phạm vi.
- [Business Rules](../Domain%20Documentation/Business%20Rules.md), BR-18, BR-25 và BR-26.
