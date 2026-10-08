# Thuật ngữ miền

- User: principal đăng nhập; role không thay đổi trong MVP.
- Tutor Profile: hồ sơ người cung cấp, approval và suspension riêng biệt.
- Service Listing: dịch vụ công khai, giá tham khảo; không phải Program.
- Conversation: direct chat của cặp Customer–Tutor; Listing là context tùy chọn.
- Program: một Tutor owner và một Customer target/member; thỏa thuận riêng tư.
- Invitation: lời mời có expiry, snapshot bất biến; `active` trong schema cũ nghĩa Accepted.
- Session: buổi hoạt động; dùng `program_sessions`/`lesson_sessions`. `sessions` là HTTP session Laravel.
- Task/Submission: nhiệm vụ và bài nộp hiện tại có version; overdue/submitted_late là dữ liệu suy ra.
- Fee Record: phí Tutor khai báo, không phải payment/invoice.
- Audit: lịch sử actor/action/before/after/reason/time; không có API sửa/xóa cho người dùng.
- Gate: mốc nghiệm thu có evidence; không phải kết quả một lệnh test.

UI dùng tiếng Việt; payload theo snake_case đã chốt. Không tự đồng nhất nhãn Approved với wire `approved` hoặc Accepted với wire `accepted`.
