# Vòng đời Program

1. Tutor đủ eligibility tạo Draft với Customer target, source Listing tùy ngữ cảnh và thỏa thuận VND.
2. Gửi Invitation khóa snapshot; chỉ một Pending mỗi Program theo thiết kế đích.
3. Customer đúng target accept trước expiry; transaction tạo membership và Active đúng một lần.
4. Active cho tạo nội dung vận hành. Paused vẫn đọc, nộp bài, feedback và progress; không tạo Session/Task/Announcement/Fee mới.
5. Complete yêu cầu Scheduled Session được hoàn thành/hủy, Submitted Task được xử lý feedback; Task Not Started cần xác nhận hủy, không tự đánh dấu hoàn thành.
6. Completed chỉ đọc; Customer đủ điều kiện review một lần. Cancelled chỉ đọc, không review.

Không sửa snapshot khi Pending/Active. Muốn đổi offer chưa được accept: cancel invitation, chỉnh Draft theo version đã chốt rồi gửi lời mời mới; bản cũ bất biến. Listing thay đổi/archive không làm đổi thỏa thuận lịch sử.

Đây là lifecycle đích từ proposal/backlog v3.1. Schema `pending/active/rejected/cancelled/expired` hiện chưa biểu diễn đủ; cần A/C chốt mapping và migration trước API W2.
