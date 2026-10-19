# M04-F08 · Admin truy cập chat theo phạm vi báo cáo

## PRD chức năng · M04 Messaging · Trạng thái: Draft · Phụ thuộc M09 Reports

# 1\. Mục tiêu và actor

Admin được phân công xử lý một Report có thể truy cập Conversation liên quan trong phạm vi cần thiết để xem evidence; việc truy cập phải được ghi audit.

# 2\. Quy tắc nghiệp vụ

* Admin không có quyền đọc mọi Conversation theo mặc định và không trở thành member.  
* Mỗi lần truy cập phải kiểm tra report assignment/scope hiện tại, giới hạn đúng Conversation/resource được báo cáo và tạo audit record.  
* Không cho Admin gửi message, sửa message, thay read cursor của participant hoặc giả danh người dùng.  
* Tải attachment theo M10 cần đồng thời qua report permission và file/resource policy; quyền đọc chat không tự động cấp tải mọi tệp.

# 3\. Hành vi đích

Từ màn hình Report, Admin mở phần Conversation evidence. Máy chủ xác nhận quyền report được phân công, tải dữ liệu tối thiểu và ghi ai/trường hợp nào/khi nào được truy cập. Endpoint, audit event và retention policy cần được phối hợp với M09.

# 4\. Tiêu chí chấp nhận

* Admin ngoài scope nhận từ chối; không thể đoán Conversation ID để vượt quyền.  
* Admin trong scope chỉ đọc phần evidence cho report; mọi lần truy cập được audit.  
* Admin không thể gửi message hoặc đánh dấu read thay participant.  
* Kết thúc/đổi assignment làm quyền truy cập thay đổi theo scope mới.

# 5\. Chênh lệch/câu hỏi mở

* Roles & Actors cho phép Admin truy cập Conversation khi được phân công Report và phải log; Enum Catalog chưa có permission cụ thể cho Conversation. Cần M09 chốt permission name, assignment model, audit event, data minimization và retention.  
* Chốt Admin có thấy toàn bộ thread hay chỉ đoạn quanh message/report evidence; có cần che dữ liệu cá nhân và file attachments không.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, Domain Model. Thiết kế: workflow Report, PRD File Lifecycle. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit

F03 — Kiểm tra quyền Report hiện tại  
Mỗi lần đọc evidence chat cần admin.reports.view cùng assignment hiện tại của Report, đúng Conversation/resource bị báo cáo, và audit truy cập. Assignment không tạo membership, không cho gửi/sửa message hoặc thay read cursor. Chưa chốt xem toàn bộ thread hay chỉ message liên quan; masking dữ liệu và truy cập sau khi Report đóng vẫn cần nhóm quyết định.
