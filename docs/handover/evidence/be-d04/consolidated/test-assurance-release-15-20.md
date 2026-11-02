# Test assurance and release · D04-15–20

Test: PostgreSQL race, rollback/fault, outbox/retention, regression/OpenAPI/docs, HTTP matrix và release candidate.

- **Gói lưu trữ:** [test-assurance-release-15-20.zip](test-assurance-release-15-20.zip)
- **Manifest đường dẫn/kích thước/hash từng artifact:** [test-assurance-release-15-20.manifest.json](test-assurance-release-15-20.manifest.json)
- **SHA-256 ZIP:** `5d8a2b44687c0a73a98f6bb8e1d8d0e1b23031ca7f390be838b8cd34695c01e4`
- **Nội dung:** 142 artifact · 3,258,476 byte trước nén.
- Từng thành viên đã được đối chiếu SHA-256 với nguồn trước khi gom. Để khôi phục cấu trúc gốc, giải nén ZIP vào `docs/handover/evidence/be-d04/`; manifest giữ đường dẫn tương đối ban đầu.

## Evidence test theo bước

<a id="d04-15"></a>
### D04-15 — Kiểm race trên PostgreSQL thật.
Artifact chi tiết được liệt kê theo đường dẫn gốc trong manifest.

<a id="d04-16"></a>
### D04-16 — Kiểm rollback bằng fault injection ở test.
02/11/2026 · 4 test / 137 assertions PASS trên `tutorlink_test`; 8 fault case (file link/unlink, Fee audit/outbox/receipt, Announcement audit/outbox/receipt) đều trả 500 và giữ nguyên state đã ghi nhận trước request. Gỡ từng hook trước retry; cả 8 retry thành công. Không còn trigger/function D04-16; DB snapshot trước/sau trùng khớp.

<a id="d04-17"></a>
### D04-17 — Kiểm outbox delivery và retention receipt.
02/11/2026 · 8 test / 157 assertions PASS: D04-17 (3/58), committed-event worker (1/5), Announcement publish regression (3/75), terminal replay/current-owner regression (1/19). Announcement và Fee chỉ phát một notification mỗi event đúng recipient/type/event_key/payload; trì hoãn worker không phát notification. Fee receipts `expires_at=NULL` vẫn tồn tại và replay snapshot sau 31 ngày, Fee voided, Program completed và cleanup audit; publish Announcement hết TTL trả 409, receipt expired được dọn, không tăng publish/audit/outbox. `tutorlink_test` đã restore sau worker regression; snapshot trước/sau khớp.

<a id="d04-18"></a>
### D04-18 — Hoàn tất regression, OpenAPI và tài liệu kỹ thuật.
02/11/2026 · PASS trên `tutorlink_test`/PostgreSQL: D04 focused 77 test/1.077 assertions; race 1/893; migration preflight trên clone legacy 7/27; full backend còn lại 627/4.545. Tổng 712 test/6.542 assertions, không failure/error/skipped. Full suite reset fixture test; `tutorlink_test` được phục hồi từ checkpoint và snapshot sau phục hồi trùng baseline (29 migrations, D04 batch 11/13); clone legacy đã xóa. OpenAPI Scramble có 105 operation so với baseline 89: đủ 16 D04, 0 route bị mất; response/request/error/header schema check và route parity PASS. Redocly exit 0 cả hai bản; 59 warning fingerprint giữ nguyên, 0 mới/0 resolved. Catalog và Database Design/M09/M06/M07/M08/WF05/API README đã đồng bộ; graphify update PASS, có ghi nhận SQL extractor chưa có.

<a id="d04-19"></a>
### D04-19 — Chạy demo HTTP và thu evidence API/ảnh trên test.
02/11/2026 · PASS · 96/96 HTTP cases đúng expected status, bao phủ 16/16 D04 operation với session/CSRF thật trên `tutorlink_test`. Có request/response cho 401/403/404/409/419/422, replay/no-op, upload→complete→attach/replace/detach→publish→download, Fee create→edit→paid→unpaid→void và Overview. Active/Paused/Completed/Cancelled được gọi qua HTTP: Progress được ghi trên Paused; capability ghi bị tắt và Progress/Fee/Announcement write bị chặn trên Completed/Cancelled. Audit before/after, outbox recipient, receipt retention và checksum file được đối chiếu theo resource ID. Race/fault/worker/retention tiếp tục tham chiếu test riêng D04-15/16/17. Sau test, `tutorlink_test` được phục hồi và snapshot khớp D04-18 byte-for-byte; backend-test dừng.

<a id="d04-20"></a>
### D04-20 — Chốt phiên bản đã kiểm chứng, đủ điều kiện chuyển môi trường.
02/11/2026 · PASS_TEST_RELEASE_CANDIDATE. D04-01..19 đều PASS và có report/evidence được link trong index. Chốt base commit `135efcc8ec5c4c73cff8d58cabc62a17772234bf`, dirty backend build-context SHA-256, image ID/digest `sha256:95916de4c50255c4694cfd2dafa20878a2939f09fe46272101fe113f13a371ad`, 2 D04 migration records + 5 supporting migrations dùng trong HTTP runtime, config delta test, contract/OpenAPI checksum và test run IDs. Source file hash trong image khớp working tree. Fault injection chỉ bằng PostgreSQL trigger ở test. Image test đang local-only; actual preflight phải xác nhận identity và migration status, không áp dụng theo danh sách test một cách máy móc. Test DB đã restore và test API container đã dừng; D04-21..25 chưa chạy.
