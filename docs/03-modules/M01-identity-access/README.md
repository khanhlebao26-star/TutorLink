# M01 Identity and Access

Đọc [PRD](prd.md), [vai trò](../../01-product/actors-and-roles.md) và [security](../../05-non-functional-requirements/security.md). Entry code: `backend/app/Http/Controllers/Api/V1/AuthController.php`, `backend/routes/api.php`, `frontend/src/components/auth-form.tsx`, `auth-support-form.tsx`, `frontend/src/lib/api/client.ts`.

Tái dùng Auth hiện có; không thêm auth provider hay localStorage bearer token trong task Marketplace. Owner nền Khanh, FE Dũng; mọi thay đổi phải giữ tương thích cookie/CSRF.
