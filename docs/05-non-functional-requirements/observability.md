# Observability

Mục tiêu theo slice: request_id ở response/log và audit, structured log route/status/duration/actor khi phù hợp; queue job event_key/attempt/failure; scheduler expiry counts. Không coi request_id là response bắt buộc khi code baseline chưa trả.

Không log password/token/cookie/signed URL/document/message body. Redact trước gửi nhóm. Theo dõi API error/p95, queue failures/backlog, scheduler last run, storage/database usage; cảnh báo 70% hạn mức dự kiến chỉ sau khi provider/quota được chốt.

W4 evidence cần commit/environment/time range, route metrics và lỗi còn mở. Correlation không thay audit transactional. Không thêm telemetry SDK/service bên ngoài ngoài scope đã duyệt.
