# WF01 Tài khoản và duyệt hồ sơ Tutor

## 01 Đăng ký và đọc tài khoản

**![Sơ đồ image1](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image1.png)**

Customer từ 15 tuổi; Tutor từ 18 tuổi; role không tự thay đổi. Chuỗi register → GET /me trong hình minh họa trường hợp có phiên. Auto-login sau đăng ký vẫn chờ D04/M01-F01; không coi sơ đồ là quyết định đăng ký luôn tạo phiên và không tự gọi GET /me như một bước thành công bắt buộc khi chưa có session.

## 02 Đăng nhập và đăng xuất

**![Sơ đồ image2](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image2.png)**

## 03 Xác minh email

**![Sơ đồ image3](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image3.png)**

Bước GET được mở từ signed URL trong email.

## 04 Quên mật khẩu

**![Sơ đồ image4](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image4.png)**

Reset nhận token từ link email; bootstrap lại CSRF nếu phiên đã hết.

## 05 Settings và Customer Profile

**![Sơ đồ image5](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image5.png)**

Hai phần độc lập: account fields và Customer Profile của chính mình.

## 06 Tạo hoặc sửa Draft Tutor

**![Sơ đồ image6](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image6.png)**

Chỉ Draft/Changes Requested được sửa; catalog lấy chuyên môn visible.

## 07 Gắn avatar

**![Sơ đồ image7](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image7.png)**

## 08 Gắn giấy tờ và gửi duyệt

**![Sơ đồ image8](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image8.png)**

## 09 Admin xem và review giấy tờ

**![Sơ đồ image9](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image9.png)**

Placeholder workflow only: Admin có thể cần action approve/reject riêng cho từng giấy tờ qua POST /api/v1/admin/verification-documents/{document}/approve và /reject; API Catalog đánh dấu cả hai “Cần bổ sung”, nên đây chưa là success contract đã duyệt. Không tự động approve giấy tờ khi duyệt Tutor Profile; mọi tải giấy tờ vẫn cần permission riêng. Product cần chốt permission, reason, notification, expiry/replacement và gate với quyết định Profile.

## 10 Yêu cầu sửa và gửi lại

**![Sơ đồ image10](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image10.png)**

## 11 Duyệt hoặc từ chối hồ sơ

**![Sơ đồ image11](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image11.png)**

Nhánh từ chối dùng POST /api/v1/admin/tutor-profiles/{tutorProfile}/reject, không gọi cả approve và reject.

## 12 Đình chỉ và khôi phục hồ sơ

**![Sơ đồ image12](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image12.png)**

Suspend hồ sơ kèm khóa account cần đủ hai permission; restore hồ sơ trước, account sau.

## 13 Đình chỉ riêng tài khoản

**![Sơ đồ image13](assets/wf01-tai-khoan-va-duyet-ho-so-tutor-image13.png)**

Chỉ Admin đúng quyền; account-restore không mở deactivated hoặc hồ sơ còn suspended.

F03 — Bước phân công hồ sơ Tutor  
Sau khi hồ sơ được gửi, hồ sơ chưa có người phụ trách có thể hiện trong queue metadata tối thiểu. Coordinator có admin.tutor\_profiles.assign chọn một Admin nội bộ active có quyền review; hệ thống ghi assignment/version/audit. Chỉ Admin có quyền xem và đang được giao mới mở chi tiết; quyết định luôn kiểm tra lại assignment. Khi Tutor gửi lại, giữ hay gỡ assignment cũ vẫn chờ chốt; sơ đồ nhúng cần được cập nhật nếu thể hiện khác.
