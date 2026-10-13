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

Chế độ mặc định dùng mock theo schemaDB. Để kết nối backend thật, đặt:

```bash
NEXT_PUBLIC_DATA_MODE=api
NEXT_PUBLIC_API_URL=http://localhost:8000
```

Mock nằm trong `src/lib/data/mock.ts`, còn API calls nằm trong `src/lib/data/services.ts`. Client dùng credentialed requests và khởi tạo CSRF Sanctum cho mutation. Endpoint/DTO trong `services.ts` là giả định cần đối chiếu OpenAPI khi tích hợp.

Để xem thử cả ba role, đặt `NEXT_PUBLIC_DATA_MODE=mock` trong `.env.local` rồi khởi động lại frontend. Thanh **Dữ liệu mẫu** ở đầu trang cho phép chọn Học viên (`/marketplace`), Gia sư (`/profile`) hoặc Admin (`/admin`) mà không cần đăng nhập hay chạy backend. Role được nhớ trong trình duyệt; mặc định là Gia sư. Thanh này chỉ xuất hiện trong mock mode. Nếu dùng Docker Compose, đổi `NEXT_PUBLIC_DATA_MODE` trong `docker-compose.yml` vì biến môi trường container ghi đè `.env.local`.

Admin mock có dữ liệu chờ duyệt, đã duyệt, yêu cầu chỉnh sửa và từ chối. Quyết định duyệt/tạm khóa được giữ trong phiên trang hiện tại và đặt lại khi tải lại toàn bộ trang. Đây là dữ liệu xem UI, không phải bằng chứng chức năng backend đã hoàn tất. Các trang chương trình, tin nhắn, tiến độ và dashboard riêng chưa được triển khai.

Route chính: `/auth/login`, `/auth/register`, `/profile`, `/admin`, `/marketplace` và `/marketplace/[slug]`.
