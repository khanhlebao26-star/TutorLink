# Architecture overview

Backend Laravel monolith hiện có `app/Http/Controllers/Api/V1`, Requests, Resources, Models, Middleware, Support. Controller nhận command; validation/policy server; critical command dùng transaction/lock; Resource xuất DTO allow-list. Tách service khi command thật cần, không dựng abstraction cho mọi bảng.

Frontend Next.js App Router: `src/app`, reusable `src/components`, `src/lib/api/client.ts`, `src/lib/data/services.ts`/`types.ts`/`mock.ts`. UI gọi services/client chung; API mode là chế độ nghiệm thu, mock chỉ phát triển layout độc lập. Không nuốt lỗi API rồi fallback mock.

Compose định nghĩa bốn service db/backend/frontend/mailpit. Đọc config không chứng minh health. Queue/scheduler thêm theo slice, không tồn tại mặc định; `sessions` infrastructure khác buổi học.

Contract-first: chốt OpenAPI + state/money + migration delta → backend tests → FE types/service → browser integration → gate. Dũng có thể dựng UI theo agreed contract, nhưng mock không đủ Verified.
