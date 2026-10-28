# BE-K02 — Transactional outbox, notification và Slack API

Owner: Khanh. Reviewer dự kiến: Hoàng. Issue: [#68](https://github.com/khanhlebao26-star/TutorLink/issues/68). Branch: `be-k02`. Ngày bàn giao: 25/10/2026. Trạng thái kỹ thuật: K02.1–K02.8 đã có code/test/runbook/evidence; chờ review và CI của PR, chưa merge hoặc đóng issue.

Theo yêu cầu người dùng, phần K02.8 hiện để trong working tree để tự commit. Chưa push/mở PR/cập nhật issue trong lượt bàn giao này. Nội dung chuẩn bị sẵn: [PR description](BE-K02-PR-description.md) và [issue update](BE-K02-issue-68-update.md).

## Kết quả và phạm vi

- Writer dùng chung kiểm type/payload/deep link, yêu cầu transaction nghiệp vụ, ghi một row mỗi recipient và phát hiện replay sai dữ liệu. Không gọi Slack trong mutation.
- Worker PostgreSQL dùng FOR UPDATE SKIP LOCKED; tạo notification và processed_at cùng transaction, UNIQUE(user_id,type,event_key) chống trùng khi replay. Savepoint rollback delivery lỗi, giữ metadata retry ở transaction ngoài; tối đa 10 lần hoặc 60 phút kể từ lần thử đầu, backoff có jitter tối đa 300 giây.
- API operator xem pending/failed và replay có quyền/reason/audit; giữ event_key và payload, failed_at lịch sử; replay thành công đặt resolved_at.
- Job Slack đọc secret backend lúc chạy; xử lý timeout, HTTP/API rate limit và Retry-After, ok=false, lỗi tạm thời/vĩnh viễn; chỉ ghi mã lỗi và thông tin vận hành an toàn.
- Failed marker, database queue job và trạng thái failed cùng transaction; monitor backfill failed và gộp pending quá 5 phút. Pending tối đa một alert/10 phút; failed nhắc lại tối đa một lần/ngày cho mỗi lần failed chưa xử lý.
- Compose chạy outbox-worker, slack-worker, scheduler; pcntl, timeout/retry_after và restart policy đã cấu hình. Retention có preview, execute kiểm quyền và audit theo batch.

Tích hợp nghiệp vụ đã triển khai trong task: approve/request-changes/reject/suspend/restore Tutor Profile tại AdminController. Các module còn lại sử dụng writer theo [contract K02.1](BE-K02-K02.1-contract.md) khi triển khai mutation của mình; bảng contract không phải tuyên bố đã tích hợp toàn bộ 25 type. REST Inbox notification còn là route dự kiến, không thuộc hai API vận hành bổ sung bên dưới.

## API delta

| Method/path | Quyền | Hành vi |
| --- | --- | --- |
| GET /api/v1/admin/notification-outbox?status=pending hoặc failed | admin.notification_outbox.view | Mặc định failed; phân trang 20; không trả payload |
| POST /api/v1/admin/notification-outbox/{outbox}/replay | admin.notification_outbox.replay | UUID; reason không rỗng, tối đa 500; 202 replay_pending; transaction với audit outbox.replay |

Xác thực session Sanctum và CSRF cho POST. 403 thiếu quyền; 422 lỗi validation; 404 không có row; 409 đã processed/resolved hoặc đã có replay pending. Chi tiết và quy trình vận hành: [runbook mục 10](BE-K02-K02.6-runbook.md#10-xem-và-replay-failed-outbox).

[OpenAPI](../api/openapi.json) được xuất lại bằng Scramble từ code trên SQLite tạm, không chỉnh JSON bằng tay. Route parity: **48 operations**. [API Catalog](<../Architecture & Technical Design/API Route Catalog.md>) bổ sung hai route là Đã đăng ký. Bộ test PostgreSQL K02.7 kiểm hành vi runtime độc lập với việc export schema.

Redocly 2.60.0: exit 0, schema/reference hợp lệ, **51 warnings** về chất lượng mô tả API (bao gồm operation-summary); warning chưa được xử lý toàn bộ trong task này. Evidence không được diễn giải thành OpenAPI không còn warning.

## Schema/config delta

- Schema nền từ BE-D01: migration `2026_10_22_073015_create_notification_outbox_and_add_event_key_to_notifications.php` đã có outbox, notifications.event_key và hai UNIQUE recipient/type/event_key; K02 dùng lại, không tạo bảng trùng.
- Migration K02: `2026_10_25_000000_preserve_outbox_timestamp_precision.php` đổi retry_started_at, available_at, processed_at, failed_at, replay_requested_at, resolved_at sang timestamp precision 6, tránh replay cùng giây bị coi chưa mới hơn failed_at.
- Dùng lại jobs/failed_jobs/cache/cache_locks. Queue và database marker phải cùng connection với outbox để bảo đảm commit/rollback chung.
- Biến backend: SLACK_BOT_USER_OAUTH_TOKEN, SLACK_BOT_USER_DEFAULT_CHANNEL, NOTIFICATION_PURGE_ACTOR_ID. Chỉ ví dụ trống được commit; không đưa token thật vào tài liệu hoặc evidence.
- Quyền mới: admin.notification_outbox.view/replay/purge và admin.audit_logs.purge. Tài khoản purge phải active Admin, có cả hai quyền purge. Thêm hằng quyền không tự cấp quyền cho tài khoản đã tồn tại.
- Khởi chạy bằng `docker-compose.yml` kết hợp `compose.workers.yml`; migration thông thường qua `php artisan migrate`. Không migrate:fresh database phát triển. Không tự động rollback toàn bộ migration vì có migration nền không hỗ trợ down; cần backup/quy trình reverse đã review khi rollback triển khai.

## Test và evidence

| Phần | Kết quả / tài liệu |
| --- | --- |
| Contract | [K02.1](BE-K02-K02.1-contract.md) |
| Bộ nghiệm thu PostgreSQL | [K02.7 report](BE-K02-K02.7-test-report.md): **74 passed, 463 assertions**, exit 0; 0 failure/error/skipped |
| SIGKILL trước commit | Tiến trình con bị kill sau INSERT; PostgreSQL rollback notification/metadata và giải phóng khóa; worker mới xử lý lại, không trùng |
| Docker restart và scheduler | [K02.6 report](BE-K02-K02.6-test-report.md), [runbook](BE-K02-K02.6-runbook.md) |
| Slack thật | [K02.8 demo](BE-K02-K02.8-demo.md): ảnh channel, alert ID khớp log slack_sent, DB notification count 1 |
| API contract | [OpenAPI lint](evidence/be-k02-k02.8/openapi-lint.txt), [route parity](evidence/be-k02-k02.8/openapi-parity.txt) |

Lần test 74/463 chạy trên working tree nền `27edf19`; code test và evidence đã được lưu trong commit `ef3cfa5` (K02.7). K02.8 chỉ cập nhật tài liệu, OpenAPI sinh ra và evidence; không thay code ứng dụng. Không suy ra rằng toàn bộ test repo hoặc CI đã pass từ bộ test chọn lọc này.

Đã build và chạy lại cùng bộ test trên source ứng dụng/test tại `ef3cfa5` lúc **20:53:28–20:53:41 ngày 25/10/2026 (UTC+07)**: **74 passed / 463 assertions**, exit 0, không failure/error/skipped. Xem [báo cáo kiểm tra cuối K02.8](BE-K02-K02.8-test-report.md).

## Known limitations

- Không tuyên bố exactly-once cho Slack; timeout sau khi Slack nhận tin có thể dẫn tới gửi trùng khi retry. Notification nội bộ deduplicate trong database bằng UNIQUE và transaction.
- Chưa có heartbeat/out-of-band detector worker/scheduler chết. Job có hỗ trợ định dạng worker_down nhưng không tự phát hiện sự cố; không dùng scheduler để hứa cảnh báo khi chính scheduler chết.
- Job Slack giữ cửa sổ retry 60 phút gốc; queue:retry sau hạn không mở cửa sổ mới, cần cảnh báo mới. Monitor có giới hạn tần suất nhắc lại.
- Quyền purge được kiểm trong ứng dụng; chưa có bằng chứng DB role retention tách riêng. Các batch purge đã commit vẫn giữ kết quả nếu batch sau lỗi.
- Test claim dùng hai kết nối PostgreSQL độc lập; không phải load test nhiều worker. SIGKILL test không mô phỏng mất cả máy.
- Demo Slack thật kiểm đường thành công pending; lỗi Slack được mô phỏng HTTP trong test. Không cố tình gây rate limit/lỗi token trên Slack thật.
- Chưa có permalink Slack được cung cấp; ảnh gốc và log cùng alert_id là evidence. Không đưa toàn bộ log ứng dụng chứa stack trace không liên quan vào bàn giao.
- OpenAPI còn 51 warnings theo cấu hình lint hiện tại; không có lỗi lint làm thất bại. Source export SQLite cũng làm thay đổi inference kiểu một trường total trong artifact; JSON được giữ theo kết quả Scramble, không sửa bằng tay.

## Checklist reviewer và bước tiếp theo

1. Đọc contract, schema/API delta và known limitations; kiểm các module gọi writer trong transaction nghiệp vụ + audit.
2. Chạy bộ test theo báo cáo K02.7; đọc JUnit và test SIGKILL.
3. Theo runbook kiểm ba service, schedule:list, outbox:purge preview, quyền xem/replay và failed_jobs. Không bắt buộc purge dữ liệu thật để review.
4. Đối chiếu ảnh Slack, alert `bc6b9a95-3438-4480-a784-e6155a364cf6`, outbox `67e77d46-3367-4f3f-baeb-2530d6153fec` và notification count 1.
5. Review PR/CI rồi merge theo quy trình nhóm; cập nhật issue sau review. Không có blocker kỹ thuật còn mở trong scope đã kiểm; review và CI chưa được thay thế bởi báo cáo này.
