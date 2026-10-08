# Current status

Snapshot ngày 08/10/2026 từ read-only inspection; branch `feature/W1-C2-admin-approval-audit`, HEAD `5db7404`. Không chạy test/browser/Docker health trong lượt tạo docs; re-check trước mọi implementation.

## Đã thấy trong source

Auth/File/TutorProfile/Admin/Catalog controllers/routes và FE auth/profile/admin hiện có. Schema Marketplace/Messaging/Program/Workspace/Trust đã có nền. FE marketplace gọi /listings; backend routes chưa có Listing API. Không thấy API Messaging/Program/Workspace/Review/Report/Notification trong routes hiện tại.

Compose định nghĩa db/frontend/backend/mailpit; không worker/scheduler/Reverb. Mailpit đã nằm trong commit baseline, không do bộ docs này sinh container. Catalog seed hiện Languages→English→Conversation English; AdminSeeder cần credential local riêng.

## Dirty changes phải giữ

`.gitignore`, `backend/phpunit.xml`, `backend/tests/TestCase.php`, `backend/tests/Feature/DatabaseSchemaTest.php`, `backend/tests/Feature/AuthFileProfileTest.php`, `docker-compose.yml`. `outputs/` chứa artifact chưa tracked tùy ignore. Docs mới thêm trong lượt này, không sửa sáu file trên.

## Evidence lịch sử không phải chạy lại

Proposal §13.4 ghi phiên trước: backend 34/34 tests, 158 assertions; FE lint/typecheck/build; Mailpit verify/reset/login/throttle. Đây là historical evidence, không chứng nhận current full AC/G1.

## Chưa Accepted

A2/B2/C2 chưa đóng hoàn toàn: guard test P1 config cache, Admin seed/browser approval/CSRF, reject/restore permission/audit rollback và completeness evidence. G1 còn Listing/search/public UI thật. G2–G4 chưa có evidence đủ.

TestCase env forcing chưa fail-fast kiểm config hiệu lực trước RefreshDatabase; assertion trong method quá muộn. Không chạy suite destructive cho tới harness được bảo vệ. Docs không sửa lỗi này.

Bước tiếp: closeout prerequisites; chốt OQ01–03/contract rồi A3/B3; C3 chạy G1 và rà Messaging. Không báo nhóm phase cũ hoàn tất chỉ vì docs sẵn sàng.
