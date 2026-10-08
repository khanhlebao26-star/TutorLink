# PRD Fee Announcement and Notification

FR-10/11, BR-10/12/14/15. Fee một Program/tháng YYYY-MM, amount VND integer không âm, owner nhập thủ công. Chốt mapping DECLARED/TUTOR_MARKED_PAID/UNPAID từ schema cũ trước API; luôn hiện “Do Tutor khai báo, không phải bằng chứng thanh toán”. Không tính tiền từ Session.

Announcement owner tạo/sửa theo Program state, member xem, File complete đúng resource. Notification recipient riêng, list/deep_link/unread_count/read/read-all idempotent; khóa event_key unique. Event thiết yếu: invitation decision/expiry, task assign/submit/feedback, announcement và Program terminal.

AC-G3-04/05: kỳ tháng trùng 409 hoặc replay theo contract; amount/currency validation; Paused cấm Fee/Announcement mới; notification chỉ recipient, badge/read retry đúng; transaction rollback không tạo notification; worker retry một event không trùng; scheduler expiry an toàn với accept. Email provider demo cần cấu hình riêng, không dùng Mailpit làm giao thư thật.
