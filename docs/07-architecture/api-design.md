# API design

[OpenAPI](../api/openapi.yaml) là contract wire cần đối chiếu route thực. Tại W1 closeout 15/10/2026, routes có Auth/me/File/Profile/Admin/Catalog, Listing public/owner, Tutor public/reviews và availability. Program/Messaging/Workspace và Review write còn là thiết kế W2/W3; GET reviews không chứng minh lifecycle Completed đã hoạt động.

Base /api/v1, snake_case, opaque positive integer ID, UTC ISO8601. Money integer *_minor/currency/unit; VND exponent0. Success `{data}`; pagination meta theo endpoint; errors `{message, errors?}` và HTTP status. request_id/error.code/allowed_actions thêm cùng implementation/contract/test, không ép FE đọc field chưa có.

Collections filter/sort whitelist server, bounded page/per_page hoặc cursor; marketplace count sau filter. Message cursor created_at+id; read monotonic. Owner Listing numeric ID, public slug. DELETE Listing/Session nghĩa archive/cancel lịch sử theo contract.

Status: 200/201/204 thành công, 401 session, 403 permission, 404 missing/hidden, 409 stale/conflict, 419 CSRF baseline, 422 validation, 429 rate limit. FE không parse message string làm state machine.

Retry contract theo từng command: message UUID, invitation idempotency key, notification event_key. Chốt same-key/different-payload và replay status trước code; không mặc định mọi mutation POST idempotent. Contract thay đổi cần reviewer A/C + FE Dũng, migration/type/service/test cập nhật cùng slice.

## Contract W1 đã chốt

- Settings gọi GET `/me`, PATCH `/me` chỉ gửi `full_name`. Email/role/status/xác minh chỉ đọc trong UI; API `/me` giữ contract hiện có, không tự tuyên bố khóa các field khác ở backend. Chưa password/avatar Settings; Auth giữ nguyên.
- Owner GET `/me/listings` có status filter `all|draft|active|inactive|cancelled`. `meta.total` sau filter, `meta.status_counts` trên toàn owner trước filter/page, `meta.allowed_actions` cho create và row/detail `allowed_actions` cho edit/publish/pause/archive. Không hidden. Public Listing có `tutor_slug`.
- Public GET `/tutors/{slug}/reviews` chỉ Completed + đúng Tutor/Customer membership; default per_page=5, max50; sort cố định created_at/id DESC, không có sort query tùy ý. `aggregate` tính toàn bộ tập trước page. Comment/date nullable, không public Customer/Program IDs hoặc email. Hiện trả rỗng200; W3-C3 chịu migration/lifecycle và populated PostgreSQL regression.

[Chi tiết quyết định và visibility](../plans/w1-c3-closeout-contract.md). Không thêm API hoặc migration ngoài scope này trong lượt đồng bộ tài liệu.
