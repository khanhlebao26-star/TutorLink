# BE-D02 — Full backend suite và đồng bộ Docker dùng chung

**Ngày:** 27/10/2026  
**Nhánh:** `be-d02`  
**Phạm vi:** backend trong repository và PostgreSQL của Docker Compose local (`tutorlink`). Không bao gồm staging/production.

## Kết luận

Toàn bộ backend suite trên PostgreSQL test riêng đã đạt **238/238 test, 1.398 assertions, 0 warning, 0 failure**. Backend Docker dùng chung đang `healthy`; ba migration còn thiếu đã chạy thành công và `migrate:status` không còn mục `Pending`. OpenAPI route parity đạt 60 operations; Redocly xác nhận mô tả API hợp lệ, còn 52 cảnh báo tài liệu.

## 1. Lỗi, nguyên nhân và bản sửa

| Hiện tượng | Nguyên nhân đã xác nhận | Bản sửa và bằng chứng |
| --- | --- | --- |
| Lần chạy đầu: 214 warning và 17 failure | Khi ghi đè `command` của `backend-test` bằng `php artisan test`, bước tạo file môi trường rỗng trong Compose không chạy. Laravel/phpdotenv cảnh báo không đọc được `/var/www/html/.env`. | [compose.testing.yml](../../compose.testing.yml) mount trực tiếp `backend/.env.docker` vào đường dẫn Laravel đọc, cùng cách backend Docker đang chạy dùng file này; bỏ bước tạo file rỗng. Compose vẫn đặt `APP_ENV=testing` và database test riêng. Chạy lại trước khi sửa cô lập DB: 221 pass, 17 failure, **0 warning**; các warning này không phải do thiếu Slack/API key. |
| 17 failure còn lại đều thấy `audit_logs`, `program_invitations` hoặc `notification_outbox` dư một bản ghi | Test dùng `DatabaseTruncation` tạo dữ liệu đã commit để các tiến trình PostgreSQL độc lập nhìn thấy nhau. Trait chỉ xóa bảng **trước** mỗi test; test cuối của nhóm để lại dữ liệu. Test dùng `RefreshDatabase` chạy sau đó bắt đầu transaction trên dữ liệu còn sót. | [TestCase.php](../../backend/tests/TestCase.php) đăng ký dọn bảng khi kết thúc test dùng `DatabaseTruncation`, trước lúc hủy application. Chạy tuần tự bốn race test rồi 12 Accept test: **16 pass, 134 assertions, 0 warning**. |
| Backend Docker trước đó báo thiếu `Pest\\Laravel\\PestServiceProvider` | Named volume `backend_vendor` cũ che `vendor` của image và thiếu package Pest. | Ở lần kiểm tra trước đã đồng bộ Composer dependencies vào volume và recreate backend. Sau đồng bộ DB lần này, backend tiếp tục `healthy`, cổng 8000 được publish. Không có thay đổi runtime application code cho lỗi này. |

Các thay đổi code trong lần xử lý này chỉ chạm test harness; hành vi Program/Invitation của application không đổi. Backend Docker và test Docker đều đọc file `backend/.env.docker` qua mount Compose. Test Docker đặt `APP_ENV=testing` và các biến database test riêng qua `environment`, dùng mạng Docker nội bộ không truy cập Internet và bị `TestDatabaseGuard` chặn nếu trỏ nhầm database. Test Slack dùng token giả, `Http::fake()` và `Http::preventStrayRequests()`; không gọi Slack thật.

## 2. Bằng chứng kiểm thử

| Kiểm tra | Lệnh/phạm vi | Kết quả |
| --- | --- | --- |
| Tái hiện đúng thứ tự gây lỗi | `InvitationAcceptConcurrencyTest.php` rồi `InvitationAcceptTest.php` trên PostgreSQL test | 16 pass / 134 assertions / 0 warning. [Log](evidence/be-d02-full-suite/isolation-regression.txt). |
| Full backend suite | `docker compose -f compose.testing.yml run --rm backend-test php artisan test --compact --display-warnings --fail-on-warning --no-ansi` | **238 pass / 1.398 assertions / 0 warning / exit 0**. [Log](evidence/be-d02-full-suite/full-backend-suite.txt). |
| Code style | `vendor/bin/pint --dirty --format agent` từ `backend/` | Pass. |
| OpenAPI route parity | `php scripts/verify-openapi-routes.php` từ `backend/` | Pass, 60 operations. |
| Redocly lint | CLI 2.60.0 trong Node Docker, đọc `docs/api/openapi.json` và `.redocly.yaml` | Hợp lệ, exit 0; **52 warning** về tài liệu, gồm thiếu summary/license, localhost server URL và một số response 4xx. [Log](evidence/be-d02-full-suite/redocly-lint.txt). |
| Whitespace | `git diff --check` | Pass. |

`compose.testing.yml` dùng PostgreSQL 16 `tutorlink_test` trên `tmpfs`, `APP_ENV=testing`, mail giả và cấu hình test riêng. Nó không dùng database `tutorlink` của dự án đang chạy. Compose có cảnh báo về orphan containers do hai compose file cùng project name; đây là thông báo Docker, không phải warning của Pest.

## 3. Đồng bộ PostgreSQL Docker dùng chung

Trước khi ghi, `migrate:status` báo ba migration `Pending`. Dữ liệu liên quan lúc kiểm tra: 18 Tutor Profile, 11 Verification Document đều `pending`, 0 outbox event. Đã tạo backup định dạng PostgreSQL custom tại `/tmp/tutorlink-shared-pre-sync-20261027.dump` (155.104 byte, quyền `600`) trước khi chạy migration.

`docker compose exec -T backend php artisan migrate --force --no-ansi` đã chạy thành công:

1. `2026_10_13_162057_add_public_slug_to_tutor_profiles_table` — thêm `public_slug`, backfill đủ 18 profile.
2. `2026_10_25_000000_preserve_outbox_timestamp_precision` — giữ precision cho các timestamp outbox.
3. `2026_10_26_212524_add_invitation_acceptance_support_tables` — thêm `listing_moderation_holds`, `idempotency_receipts`, `verification_documents.expires_at` và constraint/index liên quan BE-D02.

Kiểm tra sau migration: tất cả mục trong `migrate:status` là `Ran`; hai bảng BE-D02 và cột `expires_at` tồn tại; 18/18 Tutor Profile có slug; backend và database đều `healthy`; chín route `/api/v1/programs...` vẫn đăng ký. Backup nằm trên máy local và không được đưa vào Git vì chứa dữ liệu của database.

## 4. Giới hạn và phần phối hợp còn lại

- **Hai race liên module:** Accept-vs-moderation-hold và complete/cancel-vs-Session/Task writer chưa có writer thật trong phạm vi BE-D02 để chạy thử cạnh tranh. Test hiện có kiểm tra hold/blocker đã tồn tại; các race này vẫn cần kiểm chứng khi module writer được triển khai.
- **Redocly:** mô tả API hợp lệ nhưng 52 warning tài liệu còn tồn tại ở nhiều route; chưa xem là lint sạch warning.
- **Môi trường:** chỉ đồng bộ PostgreSQL Docker Compose local. Chưa xác nhận hoặc thay đổi staging/production.
- **Quy trình:** chưa mở PR, chưa merge và chưa đăng comment lên Issue #69. Draft comment trong [B4 handoff](BE-D02-B4-handover.md) vẫn dành cho người phụ trách kiểm duyệt và tự đăng.

Report này bổ sung cho [B3 test report](BE-D02-B3-test-report.md): B3 ghi bằng chứng 50 test BE-D02 tại checkpoint trước; kết quả 238 test ở đây là full suite sau khi sửa cô lập test.
