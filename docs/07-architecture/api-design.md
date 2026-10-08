# API design

[OpenAPI](../api/openapi.yaml) là contract wire cần đối chiếu route thực. Baseline có Auth/me/File/Profile/Admin/Catalog; /listings và /me/listings có trong spec/FE caller nhưng chưa có trong `backend/routes/api.php`. Program/Messaging/Workspace còn là thiết kế.

Base /api/v1, snake_case, opaque positive integer ID, UTC ISO8601. Money integer *_minor/currency/unit; VND exponent0. Success `{data}`; pagination meta theo endpoint; errors `{message, errors?}` và HTTP status. request_id/error.code/allowed_actions thêm cùng implementation/contract/test, không ép FE đọc field chưa có.

Collections filter/sort whitelist server, bounded page/per_page hoặc cursor; marketplace count sau filter. Message cursor created_at+id; read monotonic. Owner Listing numeric ID, public slug. DELETE Listing/Session nghĩa archive/cancel lịch sử theo contract.

Status: 200/201/204 thành công, 401 session, 403 permission, 404 missing/hidden, 409 stale/conflict, 419 CSRF baseline, 422 validation, 429 rate limit. FE không parse message string làm state machine.

Retry contract theo từng command: message UUID, invitation idempotency key, notification event_key. Chốt same-key/different-payload và replay status trước code; không mặc định mọi mutation POST idempotent. Contract thay đổi cần reviewer A/C + FE Dũng, migration/type/service/test cập nhật cùng slice.
