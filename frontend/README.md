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

Shell dùng tiếng Việt, responsive và có focus cho bàn phím. Form, table và trạng thái loading/empty/error dùng chung nằm trong `src/components`.

Chế độ mặc định dùng API thật. Cấu hình kết nối backend:

```bash
NEXT_PUBLIC_DATA_MODE=api
NEXT_PUBLIC_API_URL=http://localhost:8000
```

Mock nằm trong `src/lib/data/mock.ts`, còn API calls nằm trong `src/lib/data/services.ts`. Client dùng credentialed requests và khởi tạo CSRF Sanctum cho mutation. Endpoint/DTO trong `services.ts` là giả định cần đối chiếu OpenAPI khi tích hợp.

Để xem thử cả ba role, đặt `NEXT_PUBLIC_DATA_MODE=mock` trong `.env.local` rồi khởi động lại frontend. Thanh **Dữ liệu mẫu** ở đầu trang cho phép chọn Học viên (`/marketplace`), Gia sư (`/profile`) hoặc Admin (`/admin`) để xem Auth/onboarding/Admin bằng dữ liệu mẫu. Marketplace vẫn cần backend thật. Role được nhớ trong trình duyệt; mặc định là Gia sư. Thanh này chỉ xuất hiện trong mock mode. Nếu dùng Docker Compose, đổi `NEXT_PUBLIC_DATA_MODE` trong `docker-compose.yml` vì biến môi trường container ghi đè `.env.local`.

Admin mock có dữ liệu chờ duyệt, đã duyệt, yêu cầu chỉnh sửa và từ chối. Quyết định duyệt/tạm khóa được giữ trong phiên trang hiện tại và đặt lại khi tải lại toàn bộ trang. Đây là dữ liệu xem UI, không phải bằng chứng chức năng backend đã hoàn tất. Các trang chương trình, tin nhắn, tiến độ và dashboard riêng chưa được triển khai.

Route chính: `/auth/login`, `/auth/register`, `/profile`, `/admin`, `/marketplace` và `/marketplace/[slug]`.

## Marketplace B3

Route bổ sung: `/become-a-tutor`, `/me/listings`, `/me/listings/new`, `/me/listings/[id]`, `/me/listings/[id]/edit`, `/me/availability`, `/tutors/[slug]`, `/tutors/[slug]/reviews`. Marketplace luôn gọi API thật kể cả khi bật preview Auth. Contract đề xuất và các điểm chờ A3 nằm trong `docs/contracts/w1-b3-marketplace-proposal.md`; chưa được dùng làm bằng chứng A3/G1 đã đạt.

Chạy `node scripts/marketplace-query-check.mjs`, `node scripts/availability-check.mjs` và `npx tsc --noEmit --incremental false` bên cạnh lint/build. Browser suite `scripts/issue-15-browser.mjs` cần Playwright và Chrome đã cài (có thể cấu hình `PLAYWRIGHT_MODULE`, `TUTORLINK_CHROME_PATH`, `TUTORLINK_URL`); dùng API fixture chỉ trong test, ghi rõ riêng với live API. Không thêm dependency production.

Để kiểm tra song song với server đang chạy, đặt `TUTORLINK_NEXT_DIST_DIR=.next-issue15` và dùng port riêng; production build dùng `.next-issue15-build`. Build output đã được ignore khỏi Git/ESLint.
