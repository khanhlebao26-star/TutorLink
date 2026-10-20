# M04-F04 · Gửi tin nhắn văn bản và retry

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant gửi tin nhắn văn bản trong Conversation active; lỗi mạng và double-click không tạo message trùng.

# 2\. Quy tắc nghiệp vụ

* sender\_id lấy từ phiên xác thực, không tin sender ID trong body.  
* Chỉ participant được gửi; Conversation phải active và sender phải đủ điều kiện account/relationship tại thời điểm ghi.  
* Mỗi request có UUID client\_message\_id; unique(sender\_id,client\_message\_id) bảo đảm retry idempotent.  
* Payload canonical gồm `conversation_id`, `message_type`, body đã chuẩn hóa CRLF/CR thành LF và file IDs; cùng key/cùng payload trả message đã persist, cùng key/khác payload trả HTTP 409.
* Tin nhắn văn bản dùng MessageType=text, phải không trắng và tối đa 4.000 ký tự; nội dung được giữ nguyên ngoài chuẩn hóa CRLF/CR thành LF. MessageType=system chỉ do server ghi.
* Giới hạn 30 tin mới/phút/người dùng; retry của message đã tồn tại không tính quota. Khi vượt hạn trả 429 kèm `Retry-After` để client retry có kiểm soát.

# 3\. Hành vi API đích

POST message trong scope Conversation. Server kiểm tra quyền và trạng thái trong transaction, ghi message cùng idempotency key rồi trả server ID, client\_message\_id và created\_at. Không gửi khi Conversation cancelled.

# 4\. Tiêu chí chấp nhận

* Retry cùng key/payload sau timeout trả cùng message, chỉ có một hàng persisted.  
* Hai request đồng thời cùng key tạo một message.  
* Key đã dùng với payload khác không ghi thêm message; response HTTP 409 theo BR-21.
* Participant khác có thể nhận message qua polling; thứ tự ổn định theo created\_at/id.  
* Outsider, sender bị đình chỉ/khóa, peer bị đình chỉ hoặc Conversation cancelled không ghi được message.
* System message không thể được gửi bằng cách client chọn message\_type=system.

# 5\. Chênh lệch/câu hỏi mở

* Migration có unique(sender\_id,client\_message\_id), nhưng chưa có endpoint/service/policy.  
* BR-21 đã chốt response 409 và canonical payload cho message; migration/service/API vẫn chưa triển khai.
* BR-21 chốt body 4.000 ký tự, quota 30 tin mới/phút/người dùng, 409 khi cùng key khác payload và chặn member còn active gửi khi peer bị đình chỉ. Quota cụ thể vẫn cần được cấu hình/quan sát khi triển khai; M02-F08 yêu cầu khóa account đang active cùng transaction khi đình chỉ Profile.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, State Lifecycles, Domain Model. Thiết kế: WF-03, ADR-002. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
