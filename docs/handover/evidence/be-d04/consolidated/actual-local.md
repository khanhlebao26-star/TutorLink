# Project actual · local runtime

Actual evidence cho TutorLink Docker Compose local. Gồm baseline D04-01, báo cáo bổ sung D04-09, preflight/apply/smoke/stability/handover D04-21–25. Không đại diện staging/production.

- **Gói lưu trữ:** [actual-local.zip](actual-local.zip)
- **Manifest đường dẫn/kích thước/hash từng artifact:** [actual-local.manifest.json](actual-local.manifest.json)
- **SHA-256 ZIP:** `ce67ee675e10594e2302460761807792b675a077ae57af1024fb330dec3276e2`
- **Nội dung:** 155 artifact · 1,633,327 byte trước nén.
- Từng thành viên đã được đối chiếu SHA-256 với nguồn trước khi gom. Để khôi phục cấu trúc gốc, giải nén ZIP vào `docs/handover/evidence/be-d04/`; manifest giữ đường dẫn tương đối ban đầu.

## Evidence actual theo bước

<a id="d04-01"></a>
### D04-01 — Baseline actual
Ghi nhận route, migration và cấu hình runtime local; thao tác D04-01 không thay đổi database ứng dụng. Artifact trong manifest: `actual/D04-01-report.md`, `actual/routes-2026-11-01.json`, `actual/migration-status-2026-11-01.txt`.

<a id="d04-09"></a>
### D04-09 — Báo cáo bổ sung file integration
Báo cáo này ghi nhận trạng thái project tại thời điểm D04-09 và tích hợp file đã kiểm ở test. Trạng thái áp dụng runtime và smoke actual được ghi ở D04-22/23 bên dưới.

<a id="d04-21"></a>
### D04-21 — Preflight môi trường đích và sao lưu.
02/11/2026 · PASS cho môi trường Docker local TutorLink. PostgreSQL target `tutorlink` được xác nhận từ cả Laravel và kết nối trực tiếp; backend/DB khỏe; PHP 8.3.36, Laravel 13.35.0, PostgreSQL 16.15. Read-only inventory không có Fee, Progress, Announcement hoặc file link; tất cả legacy checks D04 đều bằng 0. Backup custom-format ngoài repo có checksum và đã được pg_restore đọc/giải nén kiểm tra. Có 9 migration Pending (7 nền + 2 D04), đã phân loại; hai migration nền thêm so với test checkpoint được nêu rõ cần diễn tập ở D04-22. D04-21 không chạy migration, deploy hay thay đổi dữ liệu.

<a id="d04-22"></a>
### D04-22 — Áp dụng migration và artifact đã kiểm chứng.
02/11/2026 · PASS trên local Docker Compose. Candidate image `sha256:8f1589bfc233765bedce705b2c802fb319f9d10a821ed1a46b57577c2e605411` khớp image của backend/worker/scheduler; 9 migration Pending được chạy, kết thúc 34 Ran/0 Pending. Inventory không có thay đổi D04 business rows; 13 D04 constraints hiện diện. Runtime healthy, 105/105 OpenAPI operation khớp route thực tế gồm 16/16 D04; log scan không thấy lỗi fatal. Test DB đã restore byte-identical về checkpoint. Actual smoke API được để cho D04-23.

<a id="d04-23"></a>
### D04-23 — Smoke test và thu bằng chứng API/ảnh thực tế.
02/11/2026 · PASS trên local Docker Compose (`tutorlink`), chỉ dùng ba actor và hai Program synthetic. HTTP evidence bao phủ 16/16 D04 operation; owner/member/outsider, CSRF, stale/no-op/replay, upload→complete→attach→publish→member read/download và đủ bảy Fee route được xác nhận. Fee ID 1 ở trạng thái voided version 5; Program ID 2 cancelled; không sửa hồ sơ người dùng hiện có. Bảy outbox event đã processed đúng một lần, không pending/failed/duplicate; có bảy notification cho recipient nghiệm thu. Fee/Published Announcement/file/receipt được giữ theo chính sách lưu trữ. Trace đầu có một probe tải file trước khi gắn (403); thứ tự đúng đã được chạy tiếp và cho Draft 404, Published 200.

<a id="d04-24"></a>
### D04-24 — Kiểm trạng thái sau áp dụng và khả năng phục hồi.
02/11/2026 · PASS trên local Docker Compose (`tutorlink`), quan sát khỏe mạnh liên tục 622 giây từ 07:18:50 đến 07:29:12 ICT. Backend/worker/scheduler giữ cùng application image đã áp dụng; PostgreSQL giữ cùng container/image và healthy suốt cửa sổ. Migration cuối 34 Ran/0 Pending. Health/member APIs 200; member tải file private 200 đúng checksum, outsider 404; ba replay trả 200/201/200 và khớp JSON gốc. Snapshot đầu/cuối và các checkpoint giống nhau; 8 receipt còn nguyên, 7/7 outbox processed một lần, không pending/failed/duplicate, không orphan file link; log filter có 0 lỗi. Runtime interruption trước cửa sổ và lỗi CSRF của observer đã được ghi nhận, khởi động/kiểm tra lại an toàn; không reset/migrate DB.

<a id="d04-25"></a>
### D04-25 — Bàn giao kết quả và hoàn tất checklist.
02/11/2026 · PASS. D04-01..24 đều PASS; sau gom evidence, từng bước đã được chuyển sang liên kết tổng hợp theo nhóm. Bốn gói ZIP và manifest đã xác minh hash thành viên; index test/actual và các liên kết tài liệu liên quan đã được cập nhật, kiểm tra. Build/runtime và giới hạn nghiệm thu được mô tả trong handover; actual chỉ là Docker local, không có staging/production hoặc UI claim.
