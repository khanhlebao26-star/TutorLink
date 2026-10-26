# M06-F03 · Hoàn tất hoặc hủy Session

## PRD chức năng · M06 Session & Workspace · Draft

# 1\. Mục tiêu

Tutor owner ghi nhận kết quả của Scheduled Session mà không xóa mất lịch sử hoặc tạo bản ghi actual trùng.

# 2\. Lifecycle

* SessionStatus: scheduled → completed hoặc scheduled → cancelled; trạng thái cuối chỉ đọc.  
* Tutor owner thực hiện theo Program state/permission. Hủy không hard-delete.  
* Chỉ Tutor owner được bấm Hoàn tất cho Session đang scheduled. Trong cùng transaction, hệ thống chuyển program\_sessions.status sang completed, tạo tối đa một lesson\_sessions có program\_session\_id duy nhất và đồng bộ status; actual times có thể để NULL nếu không được ghi nhận. API Session ID luôn là program\_sessions.id.  
* Không thêm attendance hay attachment vào Session.

# 3\. Tiêu chí chấp nhận

* Owner hoàn tất/hủy Session scheduled; member nhìn thấy trạng thái cập nhật.  
* Không thể hoàn tất Session đã cancelled hoặc hủy Session đã completed.  
* Retry/double-submit không tạo lesson occurrence thứ hai.  
* Program Paused vẫn cho xử lý Session đã lên lịch theo policy; Program Completed/Cancelled readonly.  
* Hủy chỉ chuyển program\_sessions sang cancelled, không tạo lesson\_sessions; lịch sử được giữ và Overview không tính lịch dự kiến hoặc Session bị hủy là buổi đã thực hiện. Replay request tương đương với trạng thái cuối và payload gốc trả lại kết quả hiện có, không tạo thêm actual hoặc audit; transition mới sau khi Program terminal vẫn bị từ chối.
* Complete/cancel request mới phải gửi `expected_version`; kiểm version và trạng thái trong cùng transaction. Mỗi transition thành công tăng `program_sessions.version` một lần. Replay hợp lệ được trả kết quả đã lưu mà không tăng version/audit lần nữa.

# 4\. Chênh lệch/câu hỏi mở

* Thiết kế transaction đã chốt: đổi trạng thái lịch và tạo lesson\_sessions completed cùng transaction với audit bắt buộc; replay không tạo actual/audit lần hai. Chưa có bằng chứng runtime/kiểm thử thì giữ trạng thái chưa xác minh, không ghi lại đây là câu hỏi chọn mô hình.  
* Đã được Dũng xác nhận ngày 18/10/2026 và chốt D21–D22 tại BR-19 ngày 20/10/2026: Tutor owner được hoàn tất/hủy Session scheduled khi Program Active hoặc Paused; Paused không tạo mới và không cho sửa lịch. Program terminal cấm transition mới; chỉ replay tương đương được trả kết quả đã lưu, không ghi lần hai. Versioned write và implementation evidence còn cần đồng bộ.

# 5\. Nguồn

Product: Business Rules, Roles & Actors. Domain: State Lifecycles, Domain Model, Enum Catalog. Workflow: WF-05. Link nguồn: [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>)
