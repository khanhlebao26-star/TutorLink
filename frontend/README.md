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

Route chính: `/auth/login`, `/auth/register`, `/profile`, `/admin`, `/marketplace` và `/marketplace/[slug]`.
