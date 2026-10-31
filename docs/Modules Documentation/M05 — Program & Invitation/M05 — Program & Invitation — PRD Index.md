# M05 — Program & Invitation

## PRD Index · 6 chức năng · Draft

# Mục tiêu

Tạo thỏa thuận riêng giữa Tutor và một Customer, gửi Invitation có snapshot bất biến, kích hoạt Program nguyên tử khi đúng Customer accept, rồi quản lý lifecycle Program.

# PRD chức năng

[M05-F01 PRD — Tạo và chỉnh sửa Program Draft](<M05-F01 PRD — Tạo và chỉnh sửa Program Draft.md>)  
[M05-F02 PRD — Gửi Invitation và khóa snapshot](<M05-F02 PRD — Gửi Invitation và khóa snapshot.md>)  
[M05-F03 PRD — Accept Invitation và kích hoạt Program](<M05-F03 PRD — Accept Invitation và kích hoạt Program.md>)  
[M05-F04 PRD — Reject hoặc cancel Invitation](<M05-F04 PRD — Reject hoặc cancel Invitation.md>)  
[M05-F05 PRD — Hết hạn và gửi lại Invitation](<M05-F05 PRD — Hết hạn và gửi lại Invitation.md>)  
[M05-F06 PRD — Quản lý lifecycle Program](<M05-F06 PRD — Quản lý lifecycle Program.md>)

# Quy tắc Product/Domain

* Tutor đủ điều kiện tạo Program; draft có một Customer target và offer VND.  
* Invitation có hạn 72 giờ, snapshot bất biến, tối đa một pending mỗi Program.  
* Accept đúng Customer tạo membership và Program Active trong một transaction; retry không tạo trùng.  
* Program: draft → active/paused/completed/cancelled theo lifecycle; terminal readonly; complete không tự hoàn thành Task.
* BR-18: offer Draft version tăng theo edit, `expected_version` bảo vệ PATCH/send; pending khóa Draft. Expiry UTC chặt tại `expires_at`; Draft cancel thu hồi Invitation pending nguyên tử; Program Active/Paused chỉ cancel sau khi xử lý Session/Task mở. Invitation cũ không accept trong lúc source Listing hold, nhưng có thể accept sau khi gỡ hold trước expiry.

# Runtime và giới hạn kiểm chứng — BE-H03, 31/10/2026

* B1/B2 và Accept có sẵn được kế thừa; BE-H03 đăng ký hai API reject/cancel Invitation và scheduler `invitations:expire` mỗi phút. Quyết định giữ Program Draft, snapshot và lịch sử; không tạo membership. Reason hiện tùy chọn/null, tối đa 2.000 ký tự và lưu audit; việc bắt buộc nhập chưa được Product chốt.
* PostgreSQL tests bao phủ double accept, accept-vs-reject/cancel/expiry/suspend, worker lặp, rollback khi outbox lỗi và complete-vs-create-Task theo cả hai thứ tự. Luồng HTTP accept → Task → Paused → nộp/sửa/feedback → complete giữ membership và Submission history.
* Complete/cancel trả `errors.blockers.sessions/tasks`; Task todo/in_progress/submitted chặn, completed/cancelled cho phép. Program.version vẫn là version offer Draft; lifecycle không tăng version.
* Session HTTP API và Listing moderation writer vẫn ngoài phạm vi hiện tại; không suy rộng race test Task sang writer Session hoặc chứng nhận race moderation từ một ca hold tuần tự. Scheduler/outbox service cần được bật khi triển khai; không có deployment/production evidence trong lượt này.
* [Báo cáo, lệnh tái lập và evidence BE-H03](../../handover/BE-H03-issue-75-test-report.md). OpenAPI hiện có 83 operations trên toàn backend; route registration không tự chứng minh toàn bộ target behavior.

# Nguồn chuẩn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)
