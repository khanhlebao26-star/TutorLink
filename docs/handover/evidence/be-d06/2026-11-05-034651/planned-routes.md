# D23 route status at BE-D06 review

The Catalog has seven D23 Admin routes. Cross-check: the Catalog's status column, generated `docs/api/openapi.json`, and D06 route inventory. One route is registered; six are still planned and must not be presented to frontend callers as available.

| Method and path | Current status | Frontend/API consequence |
|---|---|---|
| `GET /api/v1/admin/reports/{report}/evidence` | Registered in Catalog, OpenAPI and Laravel route inventory. | Available under its Report permission, assignment and open-state checks; contract exists. Registration does not certify every runtime branch. |
| `GET /api/v1/admin/users` | Planned in Catalog; absent from OpenAPI and registered route inventory. | Do not call until implemented and exported. |
| `GET /api/v1/admin/users/{user}` | Planned in Catalog; absent from OpenAPI and registered route inventory. | Do not call until implemented and exported. |
| `PATCH /api/v1/admin/users/{user}` | Planned in Catalog; absent from OpenAPI and registered route inventory. | Do not call until implemented and exported. |
| `POST /api/v1/admin/listings/{listing}/hide` | Planned in Catalog; absent from OpenAPI and registered route inventory. | Do not call until implemented and exported. |
| `POST /api/v1/admin/listings/{listing}/restore-visibility` | Planned in Catalog; absent from OpenAPI and registered route inventory. | Do not call until implemented and exported. |
| `GET /api/v1/admin/metrics` | Planned in Catalog; absent from OpenAPI and registered route inventory. | Do not call until implemented and exported. |

The 15-route planned baseline in [`planned-and-infrastructure-routes.md`](../2026-11-04-201500/planned-and-infrastructure-routes.md) is a dated inventory snapshot; it does not include the Report evidence route because that route was registered at the baseline. Historical totals remain unchanged. The route source of truth is the [API Route Catalog](../../../../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md).
