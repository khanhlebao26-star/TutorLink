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

# 4\. Chênh lệch/câu hỏi mở

* Thiết kế transaction đã chốt: đổi trạng thái lịch và tạo lesson\_sessions completed cùng transaction với audit bắt buộc; replay không tạo actual/audit lần hai. Chưa có bằng chứng runtime/kiểm thử thì giữ trạng thái chưa xác minh, không ghi lại đây là câu hỏi chọn mô hình.  
* Đã được Dũng xác nhận ngày 18/10/2026: Tutor owner được hoàn tất/hủy Session đã lên lịch khi Program Active hoặc Paused; Paused không tạo mới, edit lịch còn mở. Program terminal cấm chuyển trạng thái mới; chỉ replay tương đương được trả kết quả đã lưu, không ghi lần hai.

# 5\. Nguồn

Product: Business Rules, Roles & Actors. Domain: State Lifecycles, Domain Model, Enum Catalog. Workflow: WF-05. Link nguồn: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
