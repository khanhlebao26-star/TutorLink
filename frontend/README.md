# TutorLink frontend

Next.js shell cho Auth, Profile, Admin và Marketplace.

## Chạy local

```bash
npm ci
npm run dev
```

Kiểm tra trước khi bàn giao:

```bash
npm run lint
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

Chế độ mặc định dùng API thật. Cấu hình kết nối backend:

```bash
NEXT_PUBLIC_DATA_MODE=api
NEXT_PUBLIC_API_URL=http://localhost:8000
```

Mock nằm trong `src/lib/data/mock.ts`, còn API calls nằm trong `src/lib/data/services.ts`. Client dùng credentialed requests và khởi tạo CSRF Sanctum cho mutation. Endpoint/DTO trong `services.ts` là giả định cần đối chiếu OpenAPI khi tích hợp.

Để xem thử cả ba role, đặt `NEXT_PUBLIC_DATA_MODE=mock` trong `.env.local` rồi khởi động lại frontend. Thanh **Dữ liệu mẫu** ở đầu trang cho phép chọn Học viên (`/marketplace`), Gia sư (`/profile`) hoặc Admin (`/admin`) để xem Auth/onboarding/Admin bằng dữ liệu mẫu. Marketplace vẫn cần backend thật. Role được nhớ trong trình duyệt; mặc định là Gia sư. Thanh này chỉ xuất hiện trong mock mode. Nếu dùng Docker Compose, đổi `NEXT_PUBLIC_DATA_MODE` trong `docker-compose.yml` vì biến môi trường container ghi đè `.env.local`.

Admin mock có dữ liệu chờ duyệt, đã duyệt, yêu cầu chỉnh sửa và từ chối. Quyết định duyệt/tạm khóa được giữ trong phiên trang hiện tại và đặt lại khi tải lại toàn bộ trang. Đây là dữ liệu xem UI, không phải bằng chứng chức năng backend đã hoàn tất. Chương trình, tin nhắn, tiến độ và dashboard quản trị đã có giao diện; các phần này cần API thật tương ứng.

Route chính: `/auth/login`, `/auth/register`, `/profile`, `/admin`, `/marketplace` và `/marketplace/[slug]`.

## Marketplace B3

Route bổ sung: `/become-a-tutor`, `/me/listings`, `/me/listings/new`, `/me/listings/[id]`, `/me/listings/[id]/edit`, `/me/availability`, `/tutors/[slug]`, `/tutors/[slug]/reviews`. Marketplace luôn gọi API thật kể cả khi bật preview Auth. Contract đề xuất và các điểm chờ A3 nằm trong `docs/contracts/w1-b3-marketplace-proposal.md`; chưa được dùng làm bằng chứng A3/G1 đã đạt.

Chạy `node scripts/marketplace-query-check.mjs`, `node scripts/availability-check.mjs` và `npx tsc --noEmit --incremental false` bên cạnh lint/build. Browser suite `scripts/issue-15-browser.mjs` cần Playwright và Chrome đã cài (có thể cấu hình `PLAYWRIGHT_MODULE`, `TUTORLINK_CHROME_PATH`, `TUTORLINK_URL`); dùng API fixture chỉ trong test, ghi rõ riêng với live API. Không thêm dependency production.

Để kiểm tra song song với server đang chạy, đặt `TUTORLINK_NEXT_DIST_DIR=.next-issue15` và dùng port riêng; production build dùng `.next-issue15-build`. Build output đã được ignore khỏi Git/ESLint.
