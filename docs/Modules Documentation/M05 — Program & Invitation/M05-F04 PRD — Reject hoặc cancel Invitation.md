# M05-F04 · Reject hoặc cancel Invitation

## PRD chức năng · M05 Program & Invitation · Draft

# 1\. Mục tiêu

Cho Customer đích từ chối offer hoặc Tutor owner thu hồi Invitation pending; giữ lại audit trail và cho phép tiếp tục offer bằng một Invitation mới.

# 2\. Quy tắc

* Customer đúng target thực hiện reject; Tutor owner thực hiện cancel. Chỉ Invitation pending được quyết định.  
* Reject/cancel là trạng thái terminal của Invitation; snapshot cũ không sửa/xóa.  
* Khi Invitation pending bị reject/cancel, Program vẫn Draft, không tạo membership và có thể chỉnh offer theo version đã chốt.  
* Nếu Invitation đã accepted, không hủy Invitation; dùng ProgramAction cancel theo lifecycle Program.

# 3\. Tiêu chí chấp nhận

* Đúng actor thay đổi pending sang rejected hoặc cancelled; actor sai bị từ chối.  
* Quyết định lặp hoặc request stale không gây side effect thứ hai.  
* Snapshot và lý do/thời điểm quyết định được bảo toàn.  
* Program không tự Active và không có Customer membership sau reject/cancel.

## Runtime BE-H03 — 31/10/2026

Hai POST reject/cancel đã đăng ký theo API Route Catalog. Request chỉ có `reason` tùy chọn/null, tối đa 2.000 ký tự; lưu vào audit, không đưa vào notification payload. Active target Customer reject, active Tutor owner cancel; pending phải còn hạn và Program Draft. Actor sai trả 404; terminal hoặc tại/qua expires_at trả 409, không thêm side effect. Program.version/snapshot giữ nguyên và không tạo membership. Luồng ghi trạng thái + audit + outbox cho bên còn lại + đồng bộ chat trong một transaction. Requiredness của reason vẫn chờ Product review; đây là contract runtime hiện tại. [Báo cáo BE-H03](../../handover/BE-H03-issue-75-test-report.md).

# 4\. Chênh lệch/câu hỏi mở

* Chốt bắt buộc resolution/rejection reason và trường audit cho từng action.  
* Schema cũ có state vocabulary khác target; không dùng status pending của Program làm Invitation state.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)
