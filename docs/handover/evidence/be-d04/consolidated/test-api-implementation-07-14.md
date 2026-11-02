# Test API implementation · D04-07–14

Test: Progress, Announcement draft/file/publish, Fee, Overview, payload/list/timestamp và idempotency/no-op.

- **Gói lưu trữ:** [test-api-implementation-07-14.zip](test-api-implementation-07-14.zip)
- **Manifest đường dẫn/kích thước/hash từng artifact:** [test-api-implementation-07-14.manifest.json](test-api-implementation-07-14.manifest.json)
- **SHA-256 ZIP:** `1d85e4835bcec16de8a2b9cda0b5d87bcc366fc7ff377e66a4e9982fcac92b54`
- **Nội dung:** 76 artifact · 1,166,105 byte trước nén.
- Từng thành viên đã được đối chiếu SHA-256 với nguồn trước khi gom. Để khôi phục cấu trúc gốc, giải nén ZIP vào `docs/handover/evidence/be-d04/`; manifest giữ đường dẫn tương đối ban đầu.

## Evidence test theo bước

<a id="d04-07"></a>
### D04-07 — Triển khai 3 API Progress.
02/11/2026 · GET/POST/PATCH chạy trên `tutorlink_test`; suite đạt 3 tests/64 assertions. Owner ghi khi Program Active/Paused, Customer chỉ đọc; audit update chỉ lưu projection/hash; no-op không tăng version/time/audit/outbox; stale vẫn 409. DB giữ 55 Progress và không phát sinh audit/outbox; migration D04 vẫn đúng 1 lần.

<a id="d04-08"></a>
### D04-08 — Triển khai Draft Announcement và danh sách.
02/11/2026 · 4 route trước publish đạt 3 tests/115 assertions trên `tutorlink_test`. Owner xem Draft/all; Customer chỉ nhận Published; state/page/order, no-op/stale và file diff đúng contract. PATCH omission giữ link, `[]` tháo link; chỉ file diff ghi audit, không xóa File hay tạo outbox.

<a id="d04-09"></a>
### D04-09 — Hoàn thiện file Announcement từ upload đến download.
02/11/2026 · D04-09 đạt 3 tests/75 assertions; regression D04-07/08/09 đạt 9 tests/254 assertions trên `tutorlink_test`. Upload/complete, metadata, download checksum, scope Draft/Published, membership bị thu hồi, generic-link/delete và giữ bytes khi unlink đều được kiểm tra. Một migration scan-evidence nullable còn thiếu của pipeline K03 được áp dụng riêng trên test DB; 6 migration khác giữ pending. Không kết nối runtime database dự án.

<a id="d04-10"></a>
### D04-10 — Triển khai publish Announcement nguyên tử.
02/11/2026 · Acceptance đạt 3 tests/75 assertions; regression D04-07..10 đạt 12 tests/329 assertions trên `tutorlink_test`. Publish, UUID key/hash/receipt 30 ngày, replay, stale, recipient snapshot, audit/outbox allow-list, Published immutability và rollback do outbox failure đều đạt. DB counts không đổi; 6 migration nền khác giữ pending; không kết nối runtime database dự án thật.

<a id="d04-11"></a>
### D04-11 — Triển khai đủ 7 API Fee.
02/11/2026 · Acceptance Fee đạt 2 tests/143 assertions, schema contract 13/20, regression D04-07..11 đạt 27/492 trên `tutorlink_test`. Cả 7 route, VND/period/amount, quyền, pagination, state transition, version/no-op, idempotency receipt, audit/outbox và rollback đạt. Migration bổ sung CHECK VND sau khi xác nhận 59/59 row hiện có hợp lệ; chỉ chạy migration D04 currency mới trên test DB.

<a id="d04-12"></a>
### D04-12 — Triển khai Overview từ dữ liệu thực.
02/11/2026 · Acceptance Overview đạt 4 tests/62 assertions; regression API/schema D04-07..12 đạt 63/663 trên `tutorlink_test`. Summary dùng Session tương lai, đủ 5 Task status, Progress theo recorded_at và Published Announcement; capabilities đúng scope/state. Test connection thứ hai chứng minh response nhất quán qua thay đổi đồng thời Program/Task. Chạy riêng migration Session version còn Pending vì resource Overview dùng SessionResource; DB fixture giữ nguyên.

<a id="d04-13"></a>
### D04-13 — Kiểm payload, danh sách và timestamp xuyên 16 API.
3 acceptance tests / 144 assertions; regression D04-07..13 đạt 66 tests / 807 assertions. BR-26, filter, stable order, owner Draft visibility, strict integer/unknown field, Unicode/CRLF/whitespace, omission/null và timezone Fee month boundary đã được đối chiếu bằng API trên PostgreSQL `tutorlink_test`.

<a id="d04-14"></a>
### D04-14 — Kiểm idempotency và no-op tách biệt.
4 acceptance tests / 75 assertions; regression D04-07..14 đạt 70 tests / 882 assertions. Same key replay, hash canonical, Fee create replay 201 sau state change, key mismatch, current permission, Fee no-op receipt riêng, stale-same-value, Progress/Draft không dùng key để replay đều được xác nhận.
