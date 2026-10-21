# M04-F08 · Admin truy cập chat theo phạm vi báo cáo

## PRD chức năng · M04 Messaging · Trạng thái: Draft · Phụ thuộc M09 Reports

# 1\. Mục tiêu và actor

Admin được phân công xử lý một Report có thể truy cập Conversation liên quan trong phạm vi cần thiết để xem evidence; việc truy cập phải được ghi audit.

# 2\. Quy tắc nghiệp vụ

* Admin không có quyền đọc mọi Conversation theo mặc định và không trở thành member.  
* Mỗi lần truy cập phải kiểm tra Admin active, `admin.reports.view`, assignment hiện tại, Report `open` và đúng Conversation/resource được báo cáo; tạo audit record. Assignment không tự cấp quyền đọc.
* Với Report về message, chỉ trả message bị báo cáo cùng tối đa 10 message trước và 5 message sau trong cùng Conversation, theo thứ tự `(created_at,id)`; không trả toàn bộ thread.
* Không cho Admin gửi message, sửa message, thay read cursor của participant hoặc giả danh người dùng.  
* Tải attachment theo M10 cần đồng thời qua report permission và file/resource policy; quyền đọc chat không tự động cấp tải mọi tệp.

# 3\. Hành vi đích

Từ màn hình Report, Admin mở phần Conversation evidence qua route evidence của Report. Máy chủ xác nhận quyền và trạng thái Report, lấy đúng cửa sổ BR-11 quanh message bị báo cáo, rồi ghi ai/trường hợp nào/khi nào được truy cập. Không nhận Conversation ID tùy ý để duyệt chat. Retention nội dung sau khi hết quyền truy cập vẫn tuân theo chính sách dữ liệu M09/M10 cần hoàn thiện.

# 4\. Tiêu chí chấp nhận

* Admin ngoài scope nhận từ chối; không thể đoán Conversation ID để vượt quyền.  
* Admin trong scope chỉ đọc message bị báo cáo và tối đa 10 trước/5 sau; mọi lần truy cập được audit.
* Admin không thể gửi message hoặc đánh dấu read thay participant.  
* Report `resolved`/`dismissed` hoặc đổi/thu hồi assignment làm quyền đọc evidence chấm dứt từ request tiếp theo.

# 5\. Chênh lệch/câu hỏi mở

* Dùng `admin.reports.view` trong Enum Catalog cùng assignment hiện tại; không tạo permission đọc Conversation toàn cục cho Admin. M09/M10 còn cần chốt thời hạn lưu evidence và field masking cụ thể, nhưng các phần này không mở rộng cửa sổ 10 trước/5 sau hoặc quyền sau khi Report đóng.
* File đính kèm không tự tải cùng message; tải riêng phải thuộc đúng evidence và qua policy file/resource M10.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, Domain Model. Thiết kế: workflow Report, PRD File Lifecycle. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit

BR-11 — Kiểm tra quyền Report hiện tại
Mỗi lần đọc evidence chat cần `admin.reports.view`, assignment hiện tại, Report `open`, đúng message/Conversation được báo cáo và audit truy cập. Phạm vi là message bị báo cáo cùng tối đa 10 tin trước/5 tin sau; không mở toàn bộ thread. Assignment không tạo membership, không cho gửi/sửa message hoặc thay read cursor. Sau khi Report đóng hoặc assignment đổi/thu hồi, không còn quyền đọc evidence; masking và retention vẫn cần chốt riêng.
