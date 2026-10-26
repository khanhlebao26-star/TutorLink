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

# Implementation gaps

* B1 Program create/list/detail/update và B2 send/read Invitation, Program lifecycle, Accept đã đăng ký. Public reject/cancel Invitation API, expiry worker/scheduler và moderation hold writer chưa được triển khai.
* `ProgramApiTest`, `InvitationAcceptTest` và `NotificationOutboxWriterTest` đã chạy cùng nhau: 33 test, 169 assertion pass trên môi trường test hiện tại. Chưa có bằng chứng kiểm thử cạnh tranh PostgreSQL cho accept-vs-expire/cancel/suspend/hold hoặc complete/cancel-vs-child-write.

Kiểm tra trạng thái repository ngày 27/10/2026: B1 và Accept có route, controller/action, request/resource và feature test trong backend. Việc route được đăng ký không xác nhận các luồng Invitation/lifecycle còn lại hoặc kiểm thử concurrency PostgreSQL đã hoàn tất.

# Nguồn chuẩn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)
