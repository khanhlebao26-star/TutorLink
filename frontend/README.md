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

Bảng dưới mô tả nguồn dữ liệu trong mã hiện tại ở chế độ `api`. Đường dẫn màn hình tính từ frontend. Đường dẫn API trong bảng có tiền tố `/api/v1`. Các trang cá nhân và quản trị cần phiên đăng nhập và quyền phù hợp.

| Màn hình | Dữ liệu và thao tác | API tiêu biểu |
| --- | --- | --- |
| Đăng nhập, đăng ký, xác minh email, đặt lại mật khẩu, cài đặt tài khoản | Gửi form tới Laravel. Trình duyệt dùng cookie phiên Sanctum và CSRF khi ghi dữ liệu. | `POST /auth/login`, `PATCH /me`, `PUT /me/password` |
| Hồ sơ gia sư và hồ sơ học tập | Đọc, lưu hồ sơ, gửi hồ sơ gia sư để duyệt, tải tài liệu xác minh. Học viên chỉnh hồ sơ tại `/learning-profile`. | `/tutor/profile`, `/files`, `/me/learning-profile` |
| Marketplace, dịch vụ, lịch rảnh và yêu thích | Tìm dịch vụ, xem hồ sơ và đánh giá, quản lý dịch vụ của gia sư, lưu gia sư yêu thích. Lịch rảnh là khung giờ tham khảo. | `/listings`, `/tutors/{slug}/reviews`, `/me/listings`, `/me/availability-rules`, `/me/favorites` |
| Chương trình và lời mời | Tạo chương trình, gửi và phản hồi lời mời, quản lý buổi học, bài tập, bài nộp, tiến độ, thông báo lớp và quiz. Trang học phí có route riêng nhưng menu tab workspace hiện ẩn mục này. | `/programs`, `/invitations`, `/programs/{id}/quizzes`, `/programs/{id}/fee-records` |
| Tin nhắn và thông báo tài khoản | Mở hội thoại, gửi tin văn bản hoặc tệp, tải lịch sử, đánh dấu đã đọc. Chat đang mở nhận sự kiện Reverb theo phiên và đồng bộ lại qua API; polling dự phòng 10 giây, đồng bộ 60 giây khi đã subscribe. Ngoài chat đang mở, inbox/badge tiếp tục polling. | `/conversations/{id}/messages`, `/files/{id}/complete`, `/notifications` |
| Báo cáo và quản trị | Người dùng gửi và theo dõi báo cáo. Admin xem dashboard, duyệt hồ sơ và xử lý báo cáo theo quyền. | `/reports`, `/me/reports`, `/admin/dashboard`, `/admin/tutor-profiles`, `/admin/reports` |

### Nội dung tĩnh và dữ liệu minh họa

- Trang chủ lấy dịch vụ và gia sư nổi bật từ marketplace. Các con số giới thiệu nền tảng và lời chứng thực trên cùng trang được viết sẵn. Đánh giá trên trang hồ sơ gia sư lấy từ API.
- Hồ sơ gia sư ưu tiên số học viên và thời gian phản hồi do API trả về. Ba dịch vụ demo có giá trị minh họa dự phòng trong [tutor-profile-demo.ts](src/lib/data/tutor-profile-demo.ts).
- Trang `/about` chứa nội dung giới thiệu. Phần FAQ của `/support` chứa câu hỏi và liên kết soạn sẵn. Trang `/monitoring` dẫn quản trị viên sang Telescope, Pulse và dashboard máy chủ nếu đã cấu hình `NEXT_PUBLIC_HOST_DASHBOARD_URL`.

### Chọn chế độ dữ liệu

Chạy frontend độc lập với cấu hình sau trong `frontend/.env.local`, rồi khởi động lại Next.js.

```dotenv
NEXT_PUBLIC_DATA_MODE=api
NEXT_PUBLIC_API_URL=http://localhost:8000
```

`api` là mặc định. Khi đặt `NEXT_PUBLIC_DATA_MODE=mock`, thanh **Dữ liệu mẫu** cho phép chọn vai trò để xem thử đăng nhập, hồ sơ, onboarding và duyệt hồ sơ. Vai trò được lưu trong trình duyệt. Marketplace, chương trình, chat và dashboard vẫn gọi backend. Khi chạy bằng Docker, service `frontend` đặt chế độ `api` trong [docker-compose.yml](../docker-compose.yml).

Đối chiếu lời gọi trong [các module dữ liệu](src/lib/data/) với [route Laravel](../backend/routes/api.php). Xem [hướng dẫn OpenAPI](../docs/api/README.md) để biết phạm vi bản xuất đã lưu.

## Kiểm tra riêng cho marketplace

Chạy `node scripts/marketplace-query-check.mjs`, `node scripts/availability-check.mjs` và `npx tsc --noEmit --incremental false` bên cạnh lint/build. Browser suite `scripts/issue-15-browser.mjs` cần Playwright và Chrome đã cài (có thể cấu hình `PLAYWRIGHT_MODULE`, `TUTORLINK_CHROME_PATH`, `TUTORLINK_URL`); dùng API fixture chỉ trong test, ghi rõ riêng với live API. Không thêm dependency production.

Để kiểm tra song song với server đang chạy, đặt `TUTORLINK_NEXT_DIST_DIR=.next-issue15` và dùng port riêng; production build dùng `.next-issue15-build`. Build output đã được ignore khỏi Git/ESLint.

## Realtime configuration

Set `NEXT_PUBLIC_REVERB_APP_KEY` to the public Reverb key and `NEXT_PUBLIC_REVERB_URL` to the browser-reachable WebSocket origin before building (local: `ws://localhost:8080`, HTTPS deployment: `wss://your-host`). Keep the app secret on the backend. Docker local supplies these variables. Missing settings use polling. The client authenticates a private session-scoped conversation channel using Sanctum/CSRF, reconnects with backoff, resynchronizes from HTTP, and disconnects while hidden/offline or on logout. Events refresh the inbox and unread badge for the open conversation; this is not a global user channel.
