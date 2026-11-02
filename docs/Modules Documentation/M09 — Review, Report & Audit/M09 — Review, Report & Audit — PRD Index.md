# M09 — Review, Report & Audit

## PRD Index · 5 chức năng · Draft

# Mục tiêu

Tạo Review đủ điều kiện sau Program Completed, hiển thị Review tin cậy, tiếp nhận Report, xử lý moderation theo permission và lưu audit bất biến.

# PRD chức năng

[M09-F01 PRD — Tạo Review sau Program](<M09-F01 PRD — Tạo Review sau Program.md>)
[M09-F02 PRD — Hiển thị và kiểm duyệt Review](<M09-F02 PRD — Hiển thị và kiểm duyệt Review.md>)
[M09-F03 PRD — Gửi Report](<M09-F03 PRD — Gửi Report.md>)
[M09-F04 PRD — Admin xử lý Report và evidence](<M09-F04 PRD — Admin xử lý Report và evidence.md>)
[M09-F05 PRD — Ghi và tra cứu Audit](<M09-F05 PRD — Ghi và tra cứu Audit.md>)

# Quy tắc đích

* Review một lần bởi Customer member sau Completed; rating 1–5; chỉ Review visible hợp lệ tham gia public aggregate.
* ReportStatus open/resolved/dismissed; ResourceType tutor\_profile/listing/message/program/review; ReasonCode spam/harassment/inappropriate\_content/fraud\_suspected/misleading\_information/privacy\_violation/other; report không tự động hide resource.
* Admin view/decide/review moderation/audit phải có permission cụ thể và scope; evidence allow-list theo resource, không trả PII/document/object key/file không liên quan; chỉ khi Report open + assignment hiện tại. Report/decision và audit giữ 24 tháng.
* Audit cùng transaction, append-only; mutation rollback nếu audit write thất bại.

# Runtime BE-H04 — 02/11/2026

Review/Report/Audit APIs có trong code và OpenAPI. H04 bổ sung visibility/version Review, mapping Report pending/reviewing → open có preflight chặn dữ liệu mơ hồ, partial unique cho Report open; giữ các assignment fields/version có sẵn. Public Review lọc visible và bỏ tên Customer. Evidence có kiểm assignment/open/permission và audit mỗi lần đọc; đóng/reassign thu hồi scope. Audit reader không trả raw JSONB và chỉ mở các scope đã có nguồn assignment/moderation rõ ràng. Retention Report/decision và Audit là 24 tháng, purge có quyền/audit.

[Contract, test report và giới hạn](../../handover/BE-H04-test-report.md). Chưa chứng nhận UI, production hoặc migration trên dữ liệu production. Scoped Audit cho resource không có assignment mapping hiện bị từ chối mặc định.

# Nguồn

Product/Domain docs hoàn tất và WF-06; nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)
