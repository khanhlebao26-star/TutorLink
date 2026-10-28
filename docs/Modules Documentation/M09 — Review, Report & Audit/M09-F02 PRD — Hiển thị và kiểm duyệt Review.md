# M09-F02 · Hiển thị và kiểm duyệt Review

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Hiển thị đánh giá hợp lệ cho công chúng và cho Admin có quyền moderation ẩn/hiện review có vi phạm.

# 2\. Quy tắc

* Public DTO chỉ chứa Review hợp lệ visible; rating summary chỉ tổng hợp Review visible đủ điều kiện.  
* Không seed rating giả trong API mode và không tính Review từ Program chưa completed.  
* Admin chỉ hide/show khi có permission admin.reviews.moderate; lưu audit actor, old/new visibility, reason/time.  
* ReviewVisibility: visible ↔ hidden; hide không xóa Review hoặc thay Program history.  
* Public payload không tiết lộ reporter, private files, moderation notes hay verification documents.
* Public list và aggregate chỉ tính Review `visible`; hidden không xuất hiện trong hồ sơ Tutor hoặc thống kê. Customer chỉ xem Review của chính mình và trạng thái hidden; không thấy moderation reason. Admin moderator được xem nội dung Review cùng reason/audit trong phạm vi tác vụ.
* Danh sách public sort theo `(created_at,id)` giảm dần; mặc định 20, tối đa 50 mỗi trang. Không trả identity hoặc contact của Customer; hiển thị nhãn trung tính `Customer đã xác minh`.

# 3\. Tiêu chí chấp nhận

* Hidden Review không xuất hiện trong public list hoặc aggregate; visible hợp lệ xuất hiện.  
* Admin thiếu permission không thể moderate; Admin được cấp permission có thể đổi visibility và audit.  
* Race/stale moderation trả conflict, không mất audit hoặc tạo trạng thái sai.  
* Tutor không sửa/ẩn Review của mình bằng quyền owner.

# 4\. Chênh lệch/câu hỏi mở

* Sorting, pagination, hiển thị hidden Review và identity đã chốt ngày 21/10/2026; Review API/UI và public aggregate chưa triển khai.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
