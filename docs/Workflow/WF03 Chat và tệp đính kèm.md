# WF03 Chat và tệp đính kèm

## 01 Mở hội thoại đúng cặp

**![Sơ đồ image1](assets/wf03-chat-va-tep-dinh-kem-image1.png)**

Find-or-create theo Customer–Tutor; cùng cặp luôn tái dùng Conversation.

## 02 Danh sách và polling

**![Sơ đồ image2](assets/wf03-chat-va-tep-dinh-kem-image2.png)**

Polling bằng cursor, read cursor không lùi hoặc trỏ sang hội thoại khác.

## 03 Gửi tin văn bản

**![Sơ đồ image3](assets/wf03-chat-va-tep-dinh-kem-image3.png)**

Retry cùng client\_message\_id và payload, không tạo tin trùng; system chỉ backend tạo.

## 04 Gửi tệp trong chat

**![Sơ đồ image4](assets/wf03-chat-va-tep-dinh-kem-image4.png)**

Gửi message với file\_ids; backend kiểm và gắn link nguyên tử cùng message.

## 05 Gắn tệp vào tài nguyên hiện có

**![Sơ đồ image5](assets/wf03-chat-va-tep-dinh-kem-image5.png)**

Chỉ dùng resource\_type/purpose trong catalog và resource đã tồn tại, đúng quyền. Tạo tài nguyên mới nên gắn file nguyên tử tại API của tài nguyên.

## 06 Xóa tệp chưa dùng

**![Sơ đồ image6](assets/wf03-chat-va-tep-dinh-kem-image6.png)**

Chỉ owner; tệp còn liên kết hoặc lịch sử cần giữ bị từ chối.

## 07 Đóng và mở lại hội thoại

**![Sơ đồ image7](assets/wf03-chat-va-tep-dinh-kem-image7.png)**

Complete chỉ đóng khi không còn Program active/paused và Invitation pending còn hạn. Bước POST sau chỉ khi bắt đầu dịch vụ mới, đủ điều kiện; không tạo cặp trùng. Không có API đóng chat thủ công.
