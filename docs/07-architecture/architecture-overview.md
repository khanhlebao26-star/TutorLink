# Architecture overview

Backend Laravel monolith hiện có `app/Http/Controllers/Api/V1`, Requests, Resources, Models, Middleware, Support. Controller nhận command; validation/policy server; critical command dùng transaction/lock; Resource xuất DTO allow-list. Tách service khi command thật cần, không dựng abstraction cho mọi bảng.

Frontend Next.js App Router: `src/app`, reusable `src/components`, `src/lib/api/client.ts`, `src/lib/data/services.ts`/`types.ts`/`mock.ts`. UI gọi services/client chung; API mode là chế độ nghiệm thu, mock chỉ phát triển layout độc lập. Không nuốt lỗi API rồi fallback mock.

Compose định nghĩa bốn service db/backend/frontend/mailpit. Đọc config không chứng minh health. Queue/scheduler thêm theo slice, không tồn tại mặc định; `sessions` infrastructure khác buổi học.

Contract-first: chốt OpenAPI + state/money + migration delta → backend tests → FE types/service → browser integration → gate. Dũng có thể dựng UI theo agreed contract, nhưng mock không đủ Verified.

## Đồng bộ W1 closeout ngày 15/10/2026

Giữ kiến trúc Laravel monolith + Next.js và Auth Sanctum session cookie/CSRF hiện có. Không chuyển Breeze/starter kit; phương án đó chỉ optional sau. `/settings` là UI account dùng GET/PATCH `/api/v1/me`, chỉ sửa `full_name` và đọc thông tin tài khoản. Chưa avatar/password Settings, không thêm service Auth hoặc bảng account mới.

Public reviews là read projection GET `/api/v1/tutors/{slug}/reviews`, tách khỏi command tạo Review/lifecycle W3. Projection chỉ nhận Program `completed`; PostgreSQL hiện chưa cho trạng thái này, vì vậy trả rỗng đúng scope. Không mở constraint trước W3 chỉ để có dữ liệu hiển thị. [Contract và giới hạn](../plans/w1-c3-closeout-contract.md); [evidence và gate](../06-acceptance/issue-16-closeout.md).
