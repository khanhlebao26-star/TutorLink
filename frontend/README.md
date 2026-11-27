# TutorLink frontend

Giao diện Next.js của TutorLink cho học viên, gia sư và quản trị viên.

## Chạy local

Chạy các lệnh sau trong thư mục `frontend/` với Node.js 22. Backend Laravel local phục vụ API ở cổng 8000.

```bash
npm ci
npm run dev
```

Kiểm tra trước khi bàn giao:

```bash
npm run lint
npm run typecheck
npm test
npm run build
```

## Giao diện và dữ liệu

Shell dùng tiếng Việt, responsive và có focus cho bàn phím. Form, table và trạng thái loading/empty/error dùng chung nằm trong `src/components/shared`.

## Cấu trúc mã nguồn

- `src/app`: route và layout theo quy ước Next.js; giữ tên `page.tsx`, `layout.tsx` và các tham số route.
- `src/components`: nhóm theo chức năng: `admin`, `auth`, `availability`, `favorites`, `layout`, `marketing`, `marketplace`, `messaging`, `notifications`, `programs`, `reports`, `shared`, `tutors`.
- `src/components/programs`: danh sách, chi tiết và form chương trình riêng biệt; `sessions`, `tasks`, `progress` chứa các phần workspace tương ứng.
- `src/components/tutors/onboarding`: cấu hình và hook quản lý trạng thái, kiểm tra dữ liệu, upload hồ sơ gia sư; form hiển thị nằm ở `tutor-onboarding-form.tsx`.
- `src/lib/api`, `src/lib/data`, `src/lib/hooks`: API client, dữ liệu nghiệp vụ và hook dùng lại.
- `src/styles`: CSS dùng chung theo chức năng. `src/app/globals.css` là điểm nạp CSS toàn cục; CSS riêng của route hoặc component vẫn nằm cạnh phần sử dụng.
- `src/tests`: unit/component test được nhóm theo `admin`, `auth`, `programs`, `shared`; `e2e` dành cho Playwright.

File thông thường dùng `kebab-case`; component và type dùng `PascalCase`; hook bắt đầu bằng `use`. Import nội bộ dùng alias `@/` để việc di chuyển file không phụ thuộc số cấp thư mục. Tránh gộp xử lý dữ liệu, form và nhiều màn hình không liên quan vào một component.

## Trạng thái tích hợp

Bảng dưới mô tả nguồn dữ liệu trong mã hiện tại. Đường dẫn màn hình tính từ frontend. Đường dẫn API trong bảng có tiền tố `/api/v1`. Các trang cá nhân và quản trị cần phiên đăng nhập và quyền phù hợp.

| Màn hình | Dữ liệu và thao tác | API tiêu biểu |
| --- | --- | --- |
| Đăng nhập, đăng ký, xác minh email, đặt lại mật khẩu, cài đặt tài khoản | Gửi form tới Laravel. Trình duyệt dùng cookie phiên Sanctum và CSRF khi ghi dữ liệu. | `POST /auth/login`, `PATCH /me`, `PUT /me/password` |
| Hồ sơ gia sư và hồ sơ học tập | Đọc, lưu hồ sơ, gửi hồ sơ gia sư để duyệt, tải tài liệu xác minh. Học viên chỉnh hồ sơ tại `/learning-profile`. | `/tutor/profile`, `/files`, `/me/learning-profile` |
| Marketplace, dịch vụ, lịch rảnh và yêu thích | Tìm dịch vụ, xem hồ sơ và đánh giá, quản lý dịch vụ của gia sư, lưu gia sư yêu thích. Lịch rảnh là khung giờ tham khảo. | `/listings`, `/tutors/{slug}/reviews`, `/me/listings`, `/me/availability-rules`, `/me/favorites` |
| Chương trình và lời mời | Tạo chương trình, gửi và phản hồi lời mời, quản lý buổi học, bài tập, bài nộp, tiến độ, thông báo lớp và quiz. Trang học phí có route riêng nhưng menu tab workspace hiện ẩn mục này. | `/programs`, `/invitations`, `/programs/{id}/quizzes`, `/programs/{id}/fee-records` |
| Tin nhắn và thông báo tài khoản | Mở hội thoại, gửi tin văn bản hoặc tệp, tải lịch sử, đánh dấu đã đọc. Chat đang mở nhận sự kiện Reverb theo phiên và đồng bộ lại qua API; polling dự phòng 10 giây, đồng bộ 60 giây khi đã subscribe. Ngoài chat đang mở, inbox/badge tiếp tục polling. | `/conversations/{id}/messages`, `/files/{id}/complete`, `/notifications` |
| Báo cáo và quản trị | Người dùng gửi và theo dõi báo cáo. Admin xem dashboard, duyệt hồ sơ và xử lý báo cáo theo quyền. | `/reports`, `/me/reports`, `/admin/dashboard`, `/admin/tutor-profiles`, `/admin/reports` |

### Nội dung tĩnh và dữ liệu API

- Trang chủ lấy dịch vụ và gia sư nổi bật từ marketplace. Các thống kê và lời chứng thực minh họa đã được gỡ bỏ. Đánh giá trên trang hồ sơ gia sư lấy từ API.
- Hồ sơ gia sư chỉ hiển thị số học viên và thời gian phản hồi do API trả về; thiếu dữ liệu hiển thị “—” hoặc “chưa cập nhật”.
- Trang `/about` chứa nội dung giới thiệu. Phần FAQ của `/support` chứa câu hỏi và liên kết soạn sẵn. Trang `/monitoring` dẫn quản trị viên sang Telescope, Pulse và dashboard máy chủ nếu đã cấu hình `NEXT_PUBLIC_HOST_DASHBOARD_URL`.

### Prerender trang public

Trang chủ, `/about`, `/become-a-tutor` và hồ sơ `/tutors/[slug]` dùng static rendering/ISR với chu kỳ revalidate 300 giây. Tutor profiles lấy tối đa sáu slug khác nhau từ 50 listing public mới nhất để prerender; slug khác render on-demand. Trang tutor lấy dữ liệu profile public ở server để HTML ban đầu có nội dung, còn review và các trang listing tiếp theo giữ cơ chế tải phía client. API lỗi khi build không chặn build: danh sách slug lỗi thì không prebuild tutor nào; dữ liệu profile lỗi thì để client tải lại khi có lượt truy cập.

Đây là chu kỳ tái tạo, không phải cam kết độ mới tối đa: request đầu sau 300 giây có thể nhận trang cũ trong lúc ISR chạy; cache API public phía backend có thể cộng thêm tối đa 60 giây. Khi tái tạo lỗi, Next.js giữ bản prerender thành công gần nhất. Chỉ dữ liệu public được đưa vào HTML ISR; route tài khoản và quản trị không áp dụng.

### Kết nối backend

Chạy frontend độc lập với cấu hình sau trong `frontend/.env.local`, rồi khởi động lại Next.js.

```dotenv
NEXT_PUBLIC_API_URL=http://localhost:8000
```

Frontend chỉ dùng API Laravel; không còn chế độ mock, đăng nhập giả hoặc thanh đổi vai trò. Biến `NEXT_PUBLIC_DATA_MODE` cũ không còn được sử dụng. Fixture trong `src/tests` và dataset dành riêng cho database test vẫn được giữ để kiểm thử.

Đối chiếu lời gọi trong [các module dữ liệu](src/lib/data/) với [route Laravel](../backend/routes/api.php). Xem [hướng dẫn OpenAPI](../docs/api/README.md) để biết phạm vi bản xuất đã lưu.

## Kiểm tra riêng cho marketplace

Chạy `node scripts/marketplace-query-check.mjs`, `node scripts/availability-check.mjs` và `npx tsc --noEmit --incremental false` bên cạnh lint/build. Browser suite `scripts/issue-15-browser.mjs` cần Playwright và Chrome đã cài (có thể cấu hình `PLAYWRIGHT_MODULE`, `TUTORLINK_CHROME_PATH`, `TUTORLINK_URL`); dùng API fixture chỉ trong test, ghi rõ riêng với live API. Không thêm dependency production.

Để kiểm tra song song với server đang chạy, đặt `TUTORLINK_NEXT_DIST_DIR=.next-issue15` và dùng port riêng; production build dùng `.next-issue15-build`. Build output đã được ignore khỏi Git/ESLint.

## Realtime configuration

Set `NEXT_PUBLIC_REVERB_APP_KEY` to the public Reverb key and `NEXT_PUBLIC_REVERB_URL` to the browser-reachable WebSocket origin before building (local: `ws://localhost:8080`, HTTPS deployment: `wss://your-host`). Keep the app secret on the backend. Docker local supplies these variables. Missing settings use polling. The client authenticates a private session-scoped conversation channel using Sanctum/CSRF, reconnects with backoff, resynchronizes from HTTP, and disconnects while hidden/offline or on logout. Events refresh the inbox and unread badge for the open conversation; this is not a global user channel.

## Vercel production preparation

Use `frontend` as the Vercel project root, Node.js 22, `npm ci`, and `npm run build`. Configure [the production environment example](.env.production.example) before building. The backend is deployed separately on the VPS. Use a frontend/API pair under the same custom parent domain with the matching backend `SESSION_DOMAIN`, Sanctum stateful host, HTTPS and Reverb allowed origin. See [the deployment runbook](../docs/handover/TutorLink-deploy-readiness-2026-11-19.md).

[.vercelignore](.vercelignore) excludes local builds, browser scripts, tests and QA output from CLI uploads. Git integration builds from the repository checkout; only route dependencies and public assets belong in the deployed application. Backend production builds separately exclude test seeders, local storage, caches and development tooling through [Dockerfile.production.dockerignore](../backend/Dockerfile.production.dockerignore).

`npm run test:coverage` enforces frontend line and statement coverage above 80%. Latest local result on November 27, 2026 after the Tailwind migration checks: 210 tests in 40 files passed, with **80.57% statements** and **85.70% lines**. See the [Tailwind migration evidence](../docs/handover/evidence/3a-tailwind-migration/README.md) and the [prerender benchmark and prior coverage report](../docs/handover/FE-prerender-benchmark-coverage-2026-11-27.md). The shared CI gate independently verifies backend coverage. Public page ISR is documented in the [project performance strategy](../README.md#614-public-page-prerendering-and-tutor-isr). The public/private option in `useAsyncData` prevents a guest `/me` response from cancelling public requests. The homepage defers the below-fold tutor section but retains a single candidate snapshot for both featured sections. `not-found`, route/global error boundaries and `instrumentation.ts` provide user recovery and structured server-error logs.
