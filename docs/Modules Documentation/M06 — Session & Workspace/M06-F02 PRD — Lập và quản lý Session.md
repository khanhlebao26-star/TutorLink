# M06-F02 · Lập và quản lý Session

## PRD chức năng · M06 Session & Workspace · Draft

# 1\. Mục tiêu

Tutor owner lập các buổi Session của Program; Tutor và Customer member xem lịch trong workspace.

# 2\. Dữ liệu và quyền

* Target SessionStatus: scheduled/completed/cancelled. API dùng một Session ổn định với ID \= program\_sessions.id. program\_sessions lưu lịch dự kiến; lesson\_sessions lưu kết quả thực hiện, tối đa một bản ghi cho mỗi lịch. Tạo lịch hoặc hủy không tạo actual; chỉ Tutor owner xác nhận Hoàn tất mới tạo lesson\_sessions và cập nhật trạng thái hai bản ghi trong cùng transaction.  
* Chỉ Tutor owner tạo/sửa lịch. Member xem; outsider bị từ chối.  
* Session có start/end, timezone context và meeting URL bên ngoài nếu có; end phải sau start.  
* Persist thời điểm theo UTC; khi hiển thị dùng timezone của Program/User theo contract, mặc định project Asia/Ho\_Chi\_Minh. Meeting URL chỉ là link, không có video integration.

# 3\. Quy tắc

* Chỉ Program Active được tạo Session mới; Paused/terminal không cho tạo.  
* Lịch không được sửa sau khi Session đã completed/cancelled. Quy tắc chỉnh Scheduled Session theo Program Paused cần chốt.  
* Planned Session và actual lesson occurrence là hai khái niệm; không đếm lịch dự kiến như dịch vụ đã thực hiện.

# 4\. Tiêu chí chấp nhận

* Tutor owner tạo/sửa Scheduled Session với end\>start; giờ hiển thị đúng qua UTC round-trip.  
* Sai ngày/giờ hoặc actor/state bị chặn bằng 422/409 phù hợp, không làm hỏng Program.  
* Customer member đọc được; outsider không xem được.  
* Không yêu cầu attendance, tích hợp video hoặc Session attachment trong MVP.

# 5\. Chênh lệch/câu hỏi mở

* Đã chốt ID/DTO mapping: API Session ID là program\_sessions.id; program\_sessions là lịch dự kiến, lesson\_sessions là kết quả 0..1. Đây không phải Laravel HTTP session và không còn là câu hỏi mở; migration/runtime vẫn cần kiểm chứng.  
* Chốt timezone authoritative, quy tắc sửa/đổi lịch khi Program Paused và giới hạn meeting URL.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
