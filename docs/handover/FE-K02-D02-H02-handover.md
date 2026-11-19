# FE phase 2 — bổ sung code, chờ người dùng kiểm thử

Không chạy test/build/E2E/coverage hoặc gửi alert trong phiên này, theo yêu cầu người dùng. Chưa nghiệm thu, chưa commit/push/PR/CI hay reviewer sign-off. Không có số liệu performance/coverage mới.

## FE-K02

- Session edit đã thêm planned start/end, hiển thị theo giờ máy của người dùng và gửi ISO timestamp có timezone. Active cho tạo/sửa; Paused cho complete/cancel theo backend policy; terminal chỉ đọc. Actual chỉ lấy từ server.
- Notification đã bổ sung theo bốn route trong Catalog: GET `/notifications`, GET `/notifications/unread-count`, PATCH `/notifications/{notification}/read`, POST `/notifications/read-all`, trong `/api/v1` và dưới session/active-actor middleware.
- List/read/read-all chỉ tác động recipient hiện tại; read lặp giữ timestamp đầu. DTO chỉ gồm id/type/read_at/created_at/deep_link; không trả raw payload/event_key/recipient khác. Deep link được allow-list và ánh xạ về route FE; Task/Program kiểm lại quyền hiện tại. Màn đích tiếp tục gọi API kiểm quyền.
- `/notifications` có filter unread, phân trang, count, read/read-all, trạng thái lỗi và link không còn khả dụng. Sidebar đã thêm entry.
- File bài nộp có progress/retry, giữ uploaded ID nếu complete lỗi, chặn submit khi upload. File lịch sử/Task tải qua request có session; lỗi quyền hiện ra và không cache URL công khai.

## FE-D02

- Lịch sử bài nộp đã phân trang riêng, không unmount form đang soạn khi đổi trang lịch sử. Bài nộp/version/feedback vẫn theo server contract.
- Form dùng safe integer/min/max, datetime có timezone, PATCH có thể clear optional field bằng null. Retry giữ UUID cho cùng URL/method/payload; đổi nội dung đổi key; conflict giữ form và bắt tải lại.
- Sửa parser blockers: server trả object `{id,status,required_action}`, UI lấy đúng ID Session/Task khi complete Program bị chặn.
- Tách navigation khỏi module tab lớn; file uploader tải bằng dynamic import. Có `frontend/scripts/bundle-report.mjs` để ghi JS bytes/gzip/build ID và delta; **chưa có số đo trước/sau**.
- Overview/Sessions/Tasks/Progress/Announcements/Fees dùng route riêng. Không thêm payment gateway, Session attachment, video hay attendance.

## FE-H02 và cấu hình quality

- `/monitoring` dành cho Admin, link Telescope/Pulse về backend; backend tiếp tục quyết định `admin.monitoring.view`. Host dashboard chỉ hiện khi cấu hình URL hợp lệ qua `NEXT_PUBLIC_HOST_DASHBOARD_URL`; đây là URL public, không được chứa credentials/token.
- Cài và khóa Pest Browser plugin 4.x, Playwright cho backend và @playwright/test cho frontend. Image PHP cũ thiếu sockets: Dockerfile dev/testing đã thêm sockets; testing image thêm Node để dùng browser driver. Cần rebuild image trước khi chạy, không bỏ kiểm platform khi vận hành. Chưa cài browser binary/chạy browser trong phiên này.
- `backend/phpunit.browser.xml` tách Browser suite khỏi regression mặc định. Pest Browser smoke bảo vệ guest; Playwright journey dùng API/DB thật, không mock network: đăng nhập, marketplace/chat, accept invitation, Session complete, nộp bài/feedback, Program complete/review và notification. Có scenario Admin approve fixture qua Filament.
- FrontendDemoSeeder bổ sung pending profile đủ dữ liệu định danh/avatar/chuyên môn cho flow approve. Chỉ dùng DB test có guard. Dữ liệu phải mới vì E2E có mutation; seeder không reset dữ liệu cũ.
- Thêm RTL cho giữ draft khi 409 và retry UUID; feature test notification isolation/payload/read replay; test structured blockers.
- Workflow **manual** `.github/workflows/fe-phase2-quality.yml` cài dependencies, dùng PostgreSQL test riêng, chạy từng lane, Browser/E2E, upload reports; coverage frontend/backend đọc từ report riêng và gate **mỗi codebase >70%**. Không thu hẹp source và không khẳng định đã đạt. Workflow chưa chạy.

## API/schema và tài liệu

Không thêm migration/cột. Thêm 4 notification endpoints theo Catalog dự kiến. Chưa export OpenAPI trên PostgreSQL có schema; snapshot generated và trạng thái Catalog cần được đối chiếu sau khi người dùng chạy. Không tự sửa JSON, không công bố operation count mới là đã nghiệm thu.

Nguồn cài Browser: https://pestphp.com/docs/browser-testing . Phiên bản/API được đối chiếu thêm với package đã cài trong vendor. Test source không phải evidence chạy thành công.

## Lệnh để người dùng chạy

Frontend: `npm ci`, `npm run typecheck`, `npm run lint`, `npm run test:coverage`, `npm run build`.

Backend: rebuild image có sockets; trong runner PostgreSQL test được guard chạy `php scripts/test-console.php check`, `php scripts/test-suite.php --coverage`, `php scripts/test-console.php openapi:export`, `php scripts/verify-openapi-routes.php` và validate Scramble theo docs/api/README.md. Không dùng DB làm việc để reset/test.

Browser: cài binary bằng `npx playwright install --with-deps chromium` trong cùng môi trường Linux/host chạy mỗi runner. Trong backend `npm ci`, `vendor/bin/pest --configuration=phpunit.browser.xml --ci --trace`. Trong frontend `E2E_BASE_URL=http://localhost:3000 E2E_API_URL=http://localhost:8000 E2E_PASSWORD=Demo-pass-2026 npm run test:e2e`. Hai server phải cùng dùng DB test riêng đã seed FrontendDemoSeeder, cùng cookie/session/CORS, và Admin panel bật. Không đặt mật khẩu thật vào E2E fixture.

Gate coverage: `python3 frontend/scripts/check-codebase-coverage.py frontend/coverage/coverage-summary.json backend/build/reports/clover.xml`. Gate cố ý fail nếu một codebase chưa >70%; cần bổ sung test từ phần report còn thiếu, không chỉnh source filter để làm đẹp số.

Bundle: trên hai build cùng cấu hình, lần lượt `node scripts/bundle-report.mjs before.json` và `node scripts/bundle-report.mjs after.json before.json`; giữ build/commit provenance. Script chỉ đọc artifact đã build, không tự build.

## Còn pending nghiệm thu

- Người dùng chạy RTL/Pest/Playwright, sửa lỗi phát hiện; coverage từng codebase và bundle before/after chưa có evidence.
- Demo critical/slow-query/host alert và Slack thật cần môi trường, host dashboard/channel thử do nhóm cấp; không gửi notification ra ngoài trong phiên này. Không khai Pass cho host chưa được cấu hình.
- Có thêm `account-mailpit.spec.ts` cho register/verify/login/reset qua SMTP và link Mailpit thật; đặt `E2E_MAILPIT_URL`. Workflow manual đã có Mailpit service. Các scenario workspace/admin dùng fixture tách riêng. Chưa chạy bất kỳ scenario nào.
- Xác nhận keyboard/responsive, race/replay với API thật, DTO/deep-link mất quyền; reviewer Khanh/Dũng/Hoàng ký phần việc tương ứng rồi mới đóng issue.

Comment issue gợi ý: Đã bổ sung code các phần còn thiếu phase 2: Session edit timestamps, submission-history pagination, notification API/UI recipient-scoped, file UX, monitoring links và bộ cấu hình/test Browser/E2E/quality gate. Chưa đánh dấu Done: người dùng nhận chạy test, export/validate contract, đo coverage/bundle và demo alerts; chưa có evidence hoặc CI/reviewer sign-off.
