# FE-K01 / FE-D01 / FE-H01 — Bàn giao code

Ngày: 05/11/2026. Branch: `codex/2w-fe-integration`. Base: `7bbccbe`.

**Trạng thái: đã triển khai code, chờ người dùng tự test và reviewer nghiệm thu.** Người dùng yêu cầu dừng test trong phiên này; không coi kết quả chạy trước đó là nghiệm thu phiên bản cuối. Chưa commit/push/PR/merge và chưa xác nhận Gate FE.

## Thiết kế và phạm vi

Tham chiếu Figma [TutorLink — Desktop Tutor Platform](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink-%E2%80%94-Desktop-Tutor-Platform), page `03 — Screens`, các frame Program/Tin nhắn/Mời học viên. Dùng lại AppShell, UI components, auth/onboarding, marketplace, font và asset có sẵn. Tham chiếu thêm PNG export trong Downloads của người dùng.

Giữ navy/hồng, nền lavender, sidebar, thẻ Program và bố cục danh sách hội thoại + lịch sử + thông tin bên phải. Điều chỉnh theo runtime: VND, hạn lời mời từ server, không thêm số liệu giả, chat chỉ text theo K03.1; không thêm nút upload/antivirus trong chat.

## FE-D01

- Shared client chuẩn hóa flat/nested error code, field errors, request ID, Retry-After; session cookie + Sanctum CSRF, không lưu token bí mật trong FE.
- Vitest/RTL/coverage và frontend CI; coverage tính trên toàn bộ `src`, không thu hẹp source để đạt số.
- `/programs`, `/programs/new`, `/programs/{id}`, `/programs/{id}/edit`: list/pagination/status, tạo/sửa Draft, version, snapshot preview và gửi invitation.
- `/invitations`, `/invitations/{id}`: snapshot/expiry, accept/reject/cancel, giữ key khi retry, accept rồi chuyển workspace.
- Customer trước membership chỉ xem invitation. Draft không gọi Overview; backend tiếp tục quyết định quyền đọc Program.
- Marketplace/Tutor detail dùng public DTO thực, pagination listing trên Tutor detail và CTA mở chat.

## FE-K01

- `/messages`, `/messages/{id}`: cursor danh sách, tìm theo tên trong các hội thoại đã tải, lịch sử cũ, polling cursor mới, read cursor.
- Composer text tối đa 4.000 ký tự; retry giữ nguyên nội dung/UUID, chỉnh sửa thì dùng lần gửi mới; merge kết quả send/poll theo ID.
- Polling không chồng request, dừng hidden/offline, backoff và tôn trọng Retry-After, dừng khi mất quyền/phiên.
- Onboarding sửa document_type theo API; qualification claims, identity requirement, revision payload/version, khóa revision submitted/rejected; hiển thị metadata/trạng thái tài liệu của owner.
- Form gửi lại email xác minh nhận email của khách; signed verification/reset/logout tiếp tục dùng API hiện có.

## FE-H01

- Filament dùng `/admin/login`, `/admin/operations`, `/admin/tutor-profiles`, `/admin/reports`, `/admin/reviews`.
- Resource chỉ liệt kê metadata cần thiết; trang điều hành lấy DTO bằng reader chung. Mọi quyết định gọi shared action/policy, không CRUD trực tiếp model.
- Hồ sơ/document decision, assignment riêng, review hide/restore giữ nội dung, report resolve/dismiss và evidence trong scope, account/profile suspend/restore.
- Actor/permission/assignment được đọc lại ở mỗi request; version snapshot được khóa ở Livewire, stale không tự ghi đè. Audit DTO allow-list và read-only; evidence không lưu trong public Livewire properties.
- Blade riêng `/admin-business/assigned-profiles`: Eloquent qua reader chung, filter/pagination/detail, form POST, CSRF, validation, escaping và Blade components. Không mặc định Filament thay thế rubric Blade.
- Giữ nguyên trang Admin Next.js làm phương án chuyển tiếp; cập nhật payload assignment/revision version và xóa detail khi mất quyền.
- Panel opt-in `ADMIN_PANEL_ENABLED=true`; mặc định false trong env example. Tắt flag để đóng panel/Blade; API cũ và trang Next.js vẫn giữ. Asset Filament sinh qua Composer post-autoload-dump hoặc `php artisan filament:assets`.

## API / schema delta

Không thêm migration/bảng/cột nghiệp vụ. Thêm readonly Eloquent projection Report/Review cho Filament.

- Public Tutor DTO thêm `messaging_user_id` để mở conversation với đúng Tutor; vẫn qua public eligibility gate, không trả email/private file.
- TutorProfile DTO thêm `verification_documents` metadata **chỉ owner**, gồm id/file_id/type/status/expiry, phục vụ trạng thái onboarding. Admin private documents vẫn theo permission và assignment hiện có.
- `POST /api/v1/auth/email/verification-notification` nhận email của guest, phản hồi trung tính/rate limit; authenticated caller cũ vẫn có thể bỏ email. Đây là phần đồng bộ BR-12 cần để tài khoản unverified gửi lại mail trước login.
- CORS expose `X-Request-ID`, `Retry-After` để SPA đọc được.
- Tách reader hồ sơ/report/audit và action moderation review để API + Filament/Blade dùng chung.
- Routes Filament/Blade là web/session routes, không đưa vào số operation `/api/v1`; không đăng ký endpoint D23 conditional.

## Người dùng tự kiểm tra

```sh
cd frontend
npm ci
npm run lint
npm run typecheck
npm run test:coverage
npm run build
```

Backend: dùng PostgreSQL **test riêng** với `TestDatabaseGuard`, không chạy migrate:fresh/test trên DB làm việc. Trong runner có cấu hình test đúng:

```sh
php scripts/test-console.php check
php scripts/test-suite.php --coverage
php scripts/test-console.php openapi:export
php scripts/verify-openapi-routes.php
```

Snapshot OpenAPI hiện giữ bản PostgreSQL trước đó; cần export lại trên PostgreSQL đã migrate để nhận các API delta ở trên. Bản sinh offline không có schema đã được bỏ, không dùng làm contract phát hành. Sinh OpenAPI từ code; validate bằng Redocly theo `docs/api/README.md`. Không sửa JSON thủ công.

Có thêm `AdminFrontendTest`, `GuestVerificationResendTest` và test FE về contract, stale draft, invitation, retry text, polling, auth/onboarding/file. Test đã được để lại để người dùng tự chạy; không có cam kết pass cho code sau yêu cầu dừng test.

`FrontendDemoSeeder` chỉ chạy với DB test được guard, không được gọi từ DatabaseSeeder chính. Cần dev dependencies để dùng fixture helpers. Trong DB test riêng đã migrate, chạy `php artisan db:seed --class=FrontendDemoSeeder`; dataset gồm Tutor/Customer/Admin, public listing, active/draft/completed Program, pending invitation, chat, assigned profile/report và review. Tài khoản demo `fe-{customer,tutor,admin}@example.test`, mật khẩu chỉ dành cho dataset test: `Demo-pass-2026`.

Tự kiểm các luồng: login → marketplace/Tutor → chat → Tutor tạo Draft/gửi invitation → Customer accept → Overview; resubmit/revision/file limit; hidden/offline/retry; Filament/Blade với admin đủ/thiếu quyền; reassignment rồi request evidence tiếp theo; stale version; audit không JSONB nhạy cảm; responsive/keyboard.

## Giới hạn và việc tiếp theo

- Chưa nghiệm thu UI thực tế/mobile/keyboard hoặc luồng đầy đủ trên bản cuối; người dùng nhận phần test.
- Bộ lọc Program áp dụng trên trang hiện tại vì API list chưa có filter status. Tìm hội thoại áp dụng dữ liệu đã tải.
- Thao tác phân công dùng ID admin; không gọi API danh sách user D23 chưa duyệt.
- Không triển khai tab Session/Task/Fee đầy đủ thuộc các issue FE tiếp theo; Overview chỉ tổng hợp dữ liệu thật.
- Terms consent version/time theo BR-12 còn là chênh lệch backend đã ghi trong tài liệu gốc; không tự tạo nội dung/version pháp lý trong FE. Cần nhóm chốt contract và đồng bộ riêng trước release.
- Dependency Next.js đã cập nhật bản vá 16.4.5 và dependency tương thích. Cảnh báo moderate từ lint toolchain vẫn cần nhóm theo dõi; không downgrade framework hay dùng audit fix --force.
- Coverage FE chưa đạt Gate FE toàn dự án. Chưa CI remote, reviewer sign-off, PR hay merge.
- Khanh/Dũng/Hoàng review phần việc tương ứng; chỉ đóng issue sau khi người dùng test và reviewer xác nhận.

## Comment issue gợi ý

> Đã triển khai code FE-K01/FE-D01/FE-H01 trên `codex/2w-fe-integration`, tham chiếu Figma và tái sử dụng FE hiện có. Đã nối API Program/Invitation/chat, cập nhật auth/onboarding, và thêm Filament + trang Blade dùng shared action/policy. Chat chỉ text theo K03.1. Có API delta nhỏ và test/fixture để chạy theo handover. **Chưa đánh dấu Done: phiên bản cuối chờ Hoàng tự test, reviewer nghiệm thu, CI/PR/merge.** Bước tiếp theo: chạy checklist/contract generation, sửa lỗi nếu có, lấy reviewer xác nhận. Xem `docs/handover/FE-K01-D01-H01-handover.md`.
