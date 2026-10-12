# Evidence - W1-A1 Platform Contracts

## Task và phạm vi

- Task/issue: W1-A1 Platform Contracts
- Plan code: W1-A1
- Owner: Khanh
- Reviewer: Hoàng review policy/backend; Dũng review frontend setup và API contract
- Branch triển khai: `feature/platform-contracts-followup`
- Branch đã merge: `main`
- Commit hiện tại trên `main`: `d5e437c`
- Thời điểm ghi evidence: 2026-10-12

## Acceptance Criteria

| Acceptance Criteria | Trạng thái | Evidence / ghi chú |
| --- | --- | --- |
| ERD nền Auth/File/Listing được ghi lại và review | Pending review | Có `docs/database/erd.md`; chưa có sign-off của Hoàng. |
| Auth/File/Listing API contract đầy đủ | Pending review | Có `docs/api/openapi.yaml` và `docs/api/conventions.md`; cần Dũng và Hoàng xác nhận. |
| CI chạy lint, build, test và OpenAPI | Partial | `.github/workflows/ci.yml` có Pint, backend test, frontend lint/build và OpenAPI lint; chưa có CI run hoàn chỉnh được ghi nhận. |
| PostgreSQL migration chạy sạch trong Docker | Pass with limitation | Clean checkout đã chạy migration và seed; fresh migration trên database disposable riêng chưa được ghi nhận. |
| Sanctum, CORS và CSRF được kiểm tra | Not run | Chưa có đầy đủ Network evidence cho CSRF cookie, login, `/me`, logout và request thiếu CSRF. |
| Setup docs cho clean checkout được cập nhật | Pass with limitation | `docs/setup.md` đã mô tả setup; clean checkout đã khởi động frontend/backend. |
| Contract được bàn giao cho Dũng và Hoàng | Pending | Chưa có reviewer decision hoặc ngày sign-off. |

## Commit, working tree và môi trường

Commands:

```powershell
git branch --show-current
git rev-parse --short HEAD
git status --short --branch
```

Kết quả:

```text
Branch: main
Commit: d5e437c
Working tree: 4 files modified, pending commit
```

Môi trường Docker được tài liệu hóa:

- PHP 8.3
- PostgreSQL 16
- Node.js 22
- Docker Compose v2
- Backend dùng `DB_HOST=db`, `DB_PORT=5432`
- Host truy cập PostgreSQL qua `127.0.0.1:5433`
- Setup: `docs/setup.md`

## Database evidence

Đã kiểm tra:

```sql
SELECT status, COUNT(*) FROM users GROUP BY status;
SELECT approval_status, COUNT(*) FROM tutor_profiles GROUP BY approval_status;
```

Kết quả:

```text
users:
active | 3

tutor_profiles:
(0 rows)
```

Kết luận:

- Không có dữ liệu `pending` hoặc `suspended` cần mapping trong `users`.
- Không có tutor profile cần backfill `approval_status`.
- Không chạy `migrate:fresh` trên database phát triển chính.
- Database clean checkout đã chạy migration và seed bằng Docker.

## Lint, build và test evidence

Laravel Pint:

```powershell
docker compose run --rm backend vendor/bin/pint --test
```

Kết quả:

```text
PASS - 73 files
```

CI đã khai báo các bước backend Pint, backend migration/test, frontend lint/build và OpenAPI lint.

Backend test:

```powershell
docker compose run --rm backend php artisan test
```

Kết quả:

```text
PASS - 41 tests, 173 assertions
```

Các test schema PostgreSQL đã trực tiếp chứng minh:

- `users.status` chấp nhận `draft`, `active`, `inactive`, `cancelled`.
- `users.status` từ chối legacy `pending`, `suspended`.
- `tutor_profiles.approval_status` chấp nhận `pending`, `changes_requested`, `active`, `rejected`.
- `tutor_profiles.approval_status` từ chối `cancelled`, `expired`.

Frontend production build và một CI run hoàn chỉnh chưa được ghi nhận lại trong phiên evidence này: `Not run / Not captured`.

## Clean checkout evidence

Clean checkout:

```text
test/tutorlink-clean-checkout
```

Commands đã chạy:

```powershell
docker compose up -d db mailpit
docker compose run --rm backend php artisan migrate --force
docker compose run --rm backend php artisan db:seed --force
docker compose up -d
```

Kết quả:

- PostgreSQL container: healthy.
- Catalog seeder và Admin seeder: completed.
- Backend chạy trên port `8000`.
- Frontend chạy trên port `3000`.
- `http://localhost:3000`: truy cập được.
- `http://localhost:8000/up`: HTTP 200.
- Mailpit: `http://localhost:8025`.

Admin local dùng `LOCAL_ADMIN_EMAIL` và `LOCAL_ADMIN_PASSWORD`; không ghi credential vào repository/evidence.

## Sanctum, CORS và CSRF smoke test

Trạng thái: `Not run / chưa đủ evidence`.

Cần hoàn tất các assertion:

```text
GET /sanctum/csrf-cookie: HTTP 204
Có XSRF-TOKEN và session cookie
Access-Control-Allow-Origin: http://localhost:3000
Access-Control-Allow-Credentials: true
POST /api/v1/auth/login: HTTP 200
GET /api/v1/me sau login: HTTP 200
Cookie session được gửi
POST /api/v1/auth/logout: HTTP 204
GET /api/v1/me sau logout: HTTP 401
Request mutation thiếu/sai CSRF: HTTP 419
```

Browser screenshot và Network log chưa được lưu vào repository. Khi bổ sung phải che cookie, token và thông tin cá nhân.

## Log, screenshot và đường dẫn evidence

- CI: `.github/workflows/ci.yml`
- Setup: `docs/setup.md`
- OpenAPI: `docs/api/openapi.yaml`
- API conventions: `docs/api/conventions.md`
- ERD: `docs/database/erd.md`
- Database schema: `docs/database/w1-a1-database-schema.md`
- Business contract: `docs/contracts/w1-c1-business-contracts.md`
- Browser/Network screenshots: chưa lưu vào repository

## Known defects và test chưa chạy

- Sanctum/CORS/CSRF browser smoke test chưa hoàn tất.
- Chưa có evidence frontend production build và CI run hoàn chỉnh trong phiên này.
- Clean checkout log có cảnh báo npm về 5 high severity vulnerabilities; cần team triage riêng.
- Chưa có reviewer sign-off của Hoàng và Dũng.

## Reviewer decision

```text
Hoàng - ERD/database policy/backend policy: Pending
Dũng - frontend setup/API contract/UI enum mapping: Pending
Reviewer decision: Pending
Review date: Pending
```

Acceptance Criteria chỉ chuyển sang `Accepted` sau khi hoàn tất smoke test và có xác nhận của Hoàng/Dũng.
