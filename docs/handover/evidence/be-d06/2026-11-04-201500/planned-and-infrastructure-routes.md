# Planned and infrastructure routes

Catalog rows labeled **Dự kiến**, extracted for separation from the registered OpenAPI operation count.

Planned Catalog routes extracted: **15**

- `GET /api/v1/customer/profile` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `PUT /api/v1/customer/profile` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `POST /api/v1/me/listings/{listing}/publish` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `POST /api/v1/me/listings/{listing}/pause` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `GET /api/v1/programs/{program}/members` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `GET /api/v1/notifications` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `GET /api/v1/notifications/unread-count` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `PATCH /api/v1/notifications/{notification}/read` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `POST /api/v1/notifications/read-all` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `POST /api/v1/admin/listings/{listing}/hide` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `POST /api/v1/admin/listings/{listing}/restore-visibility` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `GET /api/v1/admin/users` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `GET /api/v1/admin/users/{user}` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `PATCH /api/v1/admin/users/{user}` — Catalog status planned; not counted in the 123 OpenAPI operations.
- `GET /api/v1/admin/metrics` — Catalog status planned; not counted in the 123 OpenAPI operations.

## Infrastructure routes outside `/api/v1`

- `GET /sanctum/csrf-cookie` — infrastructure route; excluded from API operation count. Catalog evidence is documented in the source Catalog. Runtime registration was not checked.
- `POST /broadcasting/auth` — infrastructure route; excluded from API operation count. Catalog evidence is documented in the source Catalog. Runtime registration was not checked.
