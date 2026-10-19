# WF04 Program và Invitation

## 01 Tạo Draft thỏa thuận

**![Sơ đồ image1](assets/wf04-program-va-invitation-image1.png)**

Customer đích được lấy từ cặp trao đổi; Tutor approved tạo Draft. Chat là ngữ cảnh, không điều kiện tạo member.

## 02 Gửi lời mời

**![Sơ đồ image2](assets/wf04-program-va-invitation-image2.png)**

Khóa snapshot, một pending còn hiệu lực; hạn 72 giờ.

## 03 Customer chấp nhận

**![Sơ đồ image3](assets/wf04-program-va-invitation-image3.png)**

Customer đích là actor accept; server đồng thời kiểm Tutor liên quan đủ điều kiện. accepted \+ membership \+ active trong một transaction.

## 04 Customer từ chối

**![Sơ đồ image4](assets/wf04-program-va-invitation-image4.png)**

Program còn draft để Tutor sửa/gửi mới; Customer không tự được cấp quyền GET Program Draft, bước cuối dành Tutor owner.

## 05 Tutor thu hồi để sửa offer

**![Sơ đồ image5](assets/wf04-program-va-invitation-image5.png)**

Tạo Invitation mới với snapshot/hạn mới; không sửa Invitation cũ.

## 06 Lời mời hết hạn

**![Sơ đồ image6](assets/wf04-program-va-invitation-image6.png)**

Hệ thống/scheduler đánh expired, không có expire API public. Customer đọc Invitation; Tutor owner đọc Draft và gửi lời mời mới.

## 07 Đọc danh sách Program

**![Sơ đồ image7](assets/wf04-program-va-invitation-image7.png)**

Server lọc theo owner/member; Invitation detail là điểm xem offer của Customer trước accept.

## 08 Tạm dừng và tiếp tục

**![Sơ đồ image8](assets/wf04-program-va-invitation-image8.png)**

## 09 Hủy Program

**![Sơ đồ image9](assets/wf04-program-va-invitation-image9.png)**

Tutor owner, có reason; terminal chỉ đọc, không Review.
