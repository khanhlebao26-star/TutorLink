# M06-F02 · Lập và quản lý Session

## PRD chức năng · M06 Session & Workspace · Draft

# 1\. Mục tiêu

Tutor owner lập các buổi Session của Program; Tutor và Customer member xem lịch trong workspace.

# 2\. Dữ liệu và quyền

* Target SessionStatus: scheduled/completed/cancelled. API dùng một Session ổn định với ID \= program\_sessions.id. program\_sessions lưu lịch dự kiến; lesson\_sessions lưu kết quả thực hiện, tối đa một bản ghi cho mỗi lịch. Tạo lịch hoặc hủy không tạo actual; chỉ Tutor owner xác nhận Hoàn tất mới tạo lesson\_sessions và cập nhật trạng thái hai bản ghi trong cùng transaction.  
* Chỉ Tutor owner tạo/sửa lịch. Member xem; outsider bị từ chối.  
* Session có start/end, timezone context và meeting URL bên ngoài nếu có; end phải sau start. `Program.timezone` là timezone authoritative, dùng tên IANA và mặc định `Asia/Ho_Chi_Minh`; lưu instant theo UTC, API dùng timestamp RFC 3339, UI hiển thị theo timezone Program.
* Meeting URL tùy chọn, chỉ chấp nhận HTTPS, tối đa 2.048 ký tự và không chứa credentials; đây chỉ là liên kết ngoài, không có video integration.

# 3\. Quy tắc

* Chỉ Program Active được tạo hoặc sửa Session `scheduled`; Paused/terminal không cho tạo. Khi Program Paused, Tutor owner vẫn được hoàn tất/hủy Session đã lên lịch nhưng không đổi thời gian, location hoặc meeting URL.
* Session `completed`/`cancelled` không được sửa. Program Completed/Cancelled readonly.
* PATCH phải gửi `expected_version`; version khởi tạo bằng 1, mỗi mutation thành công tăng một lần. Sai version trả 409 và không ghi dữ liệu.
* Planned Session và actual lesson occurrence là hai khái niệm; không đếm lịch dự kiến như dịch vụ đã thực hiện.

# 4\. Tiêu chí chấp nhận

* Tutor owner tạo/sửa Scheduled Session khi Program Active với end\>start; giờ hiển thị đúng qua UTC round-trip.
* Khi Paused, PATCH bị từ chối; action complete/cancel vẫn được phép cho Session scheduled theo M06-F03.
* PATCH stale trả 409 và không ghi đè version mới hơn.
* Sai ngày/giờ hoặc actor/state bị chặn bằng 422/409 phù hợp, không làm hỏng Program.  
* Customer member đọc được; outsider không xem được.  
* Không yêu cầu attendance, tích hợp video hoặc Session attachment trong MVP.
* URL HTTP, scheme khác HTTPS, có credentials hoặc dài hơn 2.048 ký tự bị từ chối.

# 5\. Chênh lệch/câu hỏi mở

* Đã chốt ID/DTO mapping: API Session ID là program\_sessions.id; program\_sessions là lịch dự kiến, lesson\_sessions là kết quả 0..1. Đây không phải Laravel HTTP session và không còn là câu hỏi mở; migration/runtime vẫn cần kiểm chứng.  
* Timezone authoritative và validation meeting URL được chốt tại BR-20. Quyền sửa Session khi Paused đã được chốt tại BR-19.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
