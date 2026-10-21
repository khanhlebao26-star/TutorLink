# WF03 Chat và tệp đính kèm

## 01 Mở hội thoại đúng cặp

**![Sơ đồ image1](assets/wf03-chat-va-tep-dinh-kem-image1.png)**

Find-or-create theo Customer–Tutor; cùng cặp luôn tái dùng Conversation. BR-17: request nhận `peer_user_id` và `context_listing_id` tùy chọn; server kiểm Listing đầu vào nhưng chỉ lưu context khi tạo chat lần đầu, không ghi đè khi mở từ Listing khác. Response trả peer tối thiểu và context gốc; UI cho biết đây là chat đã có. Các sơ đồ nhúng chưa thể hiện đầy đủ DTO/quy tắc này.

## 02 Danh sách và polling

**![Sơ đồ image2](assets/wf03-chat-va-tep-dinh-kem-image2.png)**

Inbox theo message mới nhất `(created_at,id)` giảm dần; unread đếm message của peer sau read cursor, không tính system message; read receipt chỉ ở mức Conversation. Member active đọc lịch sử khi peer bị đình chỉ hoặc chat cancelled nhưng không gửi khi peer bị đình chỉ. Member bị đình chỉ bị từ chối truy cập kể cả khi còn session cũ; sau khôi phục đọc lại theo membership. Admin không có inbox chat, chỉ xem evidence đúng Report theo BR-11.

## 03 Gửi tin văn bản

**![Sơ đồ image3](assets/wf03-chat-va-tep-dinh-kem-image3.png)**

Body không trắng, tối đa 4.000 ký tự; chuẩn hóa CRLF/CR thành LF. Cùng client\_message\_id và payload canonical trả message cũ; cùng key khác payload trả 409. Tối đa 30 message mới/phút/người dùng; vượt hạn trả 429 và Retry-After. System chỉ backend tạo.

## 04 Gửi tệp trong chat

**![Sơ đồ image4](assets/wf03-chat-va-tep-dinh-kem-image4.png)**

Một file/message, tối đa 5 MiB, PDF/JPG/PNG/DOC/DOCX. Chỉ attach sau complete và malware scan clean; scanner lỗi thì giữ quarantine. Backend kiểm owner/purpose/quyền và gắn link nguyên tử cùng message. File mất về sau giữ lịch sử và hiện unavailable.

## 05 Gắn tệp vào tài nguyên hiện có

**![Sơ đồ image5](assets/wf03-chat-va-tep-dinh-kem-image5.png)**

Chỉ dùng resource\_type/purpose trong catalog và resource đã tồn tại, đúng quyền. Tạo tài nguyên mới nên gắn file nguyên tử tại API của tài nguyên.

## 06 Xóa tệp chưa dùng

**![Sơ đồ image6](assets/wf03-chat-va-tep-dinh-kem-image6.png)**

Chỉ owner; tệp còn liên kết hoặc lịch sử cần giữ bị từ chối. Pending orphan dọn sau 24 giờ; complete chưa link dọn sau 7 ngày; storage cleanup retry/compensation an toàn.

## 07 Đóng và mở lại hội thoại

**![Sơ đồ image7](assets/wf03-chat-va-tep-dinh-kem-image7.png)**

Complete chỉ đóng khi không còn Program active/paused và Invitation pending còn hạn. Bước POST sau chỉ khi bắt đầu dịch vụ mới, đủ điều kiện; không tạo cặp trùng hoặc ghi đè context. Lifecycle không tự chèn system message. Đình chỉ participant không tự đóng chat hay xóa membership/lịch sử. Không có API đóng chat thủ công.
