# M06 — Session & Workspace

## PRD Index · 3 chức năng · Draft

# Mục tiêu

Tổ chức Workspace của Program và điều phối Session, với quyền đọc/ghi theo owner/member và lifecycle Program.

# PRD chức năng

[M06-F01 PRD — Workspace Overview và capability](<M06-F01 PRD — Workspace Overview và capability.md>)  
[M06-F02 PRD — Lập và quản lý Session](<M06-F02 PRD — Lập và quản lý Session.md>)  
[M06-F03 PRD — Hoàn tất hoặc hủy Session](<M06-F03 PRD — Hoàn tất hoặc hủy Session.md>)

# Quy tắc đích

* Workspace có sáu tab; Overview trả đủ counts năm TaskStatus, next Session scheduled, Progress mới nhất, Announcement published mới nhất và Program-level capabilities, không N+1.
* Program timezone IANA (mặc định `Asia/Ho_Chi_Minh`) là authoritative; lưu UTC, timestamp RFC 3339, giao diện hiển thị theo timezone Program. Session URL chỉ HTTPS, tối đa 2.048 ký tự và không credentials.
* Program Paused chặn tạo/sửa Session nhưng cho Tutor owner hoàn tất/hủy Session scheduled đã có; terminal readonly. PATCH/action dùng `expected_version`; stale trả 409 theo BR-19.
* Không tính planned Session như delivered lesson; không thêm attendance/session attachment/video MVP.

# Implementation gaps

* Cập nhật runtime BE-K04 ngày 01/11/2026: sáu Session API đọc/tạo/sửa/complete/cancel đã đăng ký; planned ID, version, policy, validation, transaction/replay và race PostgreSQL có evidence. Xem [bàn giao BE-K04](../../handover/BE-K04-handover.md) và [báo cáo K04.6](../../handover/BE-K04-K04.6-test-report.md).
* Program lifecycle và Workspace Overview có route trong Catalog/OpenAPI; BE-K04 không chứng nhận toàn bộ hành vi của Overview hoặc giao diện sáu tab. UI và triển khai staging/production chưa được nghiệm thu trong bộ evidence này.
* Metadata terminal legacy có thể null; không coi trạng thái giống nhau là đủ để replay. Session không thêm attendance, video integration hoặc attachment.

F19 — Bằng chứng current-state (18/10/2026): nội dung mục này là baseline của lượt rà soát trước, chưa được kiểm tra lại repository/commit trong lượt Drive-only này. Owner xác minh: chưa gán; ngày/commit kiểm chứng: chưa có; trạng thái: chưa xác minh. Migration tồn tại không tự chứng minh API, transaction hoặc UI đã được triển khai. Phân biệt ghi nhận này với “Quy tắc đích”; mô tả nháp ADR-09/BR-10 nằm trong Business Rules.

# Nguồn

Product & Domain docs hoàn tất; xem danh sách nguồn trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)  
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)
