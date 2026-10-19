# M04-F04 · Gửi tin nhắn văn bản và retry

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant gửi tin nhắn văn bản trong Conversation active; lỗi mạng và double-click không tạo message trùng.

# 2\. Quy tắc nghiệp vụ

* sender\_id lấy từ phiên xác thực, không tin sender ID trong body.  
* Chỉ participant được gửi; Conversation phải active và sender phải đủ điều kiện account/relationship tại thời điểm ghi.  
* Mỗi request có UUID client\_message\_id; unique(sender\_id,client\_message\_id) bảo đảm retry idempotent.  
* Cùng key và cùng payload trả lại bản ghi đã persist; dùng cùng key với payload khác phải báo conflict theo contract sẽ chốt.  
* Tin nhắn văn bản dùng MessageType=text. MessageType=system chỉ do server ghi. Giới hạn body, quy tắc trim/Unicode và xử lý nội dung rỗng cần được chốt.  
* Áp dụng rate limit; khi vượt hạn trả 429 để client hiển thị và retry có kiểm soát.

# 3\. Hành vi API đích

POST message trong scope Conversation. Server kiểm tra quyền và trạng thái trong transaction, ghi message cùng idempotency key rồi trả server ID, client\_message\_id và created\_at. Không gửi khi Conversation cancelled.

# 4\. Tiêu chí chấp nhận

* Retry cùng key/payload sau timeout trả cùng message, chỉ có một hàng persisted.  
* Hai request đồng thời cùng key tạo một message.  
* Key đã dùng với payload khác không ghi thêm message; response conflict có semantics được OpenAPI quy định.  
* Participant khác có thể nhận message qua polling; thứ tự ổn định theo created\_at/id.  
* Outsider, sender bị khóa hoặc Conversation cancelled không ghi được message.  
* System message không thể được gửi bằng cách client chọn message\_type=system.

# 5\. Chênh lệch/câu hỏi mở

* Migration có unique(sender\_id,client\_message\_id), nhưng chưa có endpoint/service/policy.  
* ADR-002 để response khi cùng key khác payload cần chốt trước W2; cần chốt status/error code và hash/canonicalization payload.  
* Chốt body max length, chống spam/rate limits, và quyền gửi khi Tutor Profile bị suspended nhưng account/session còn tồn tại.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, State Lifecycles, Domain Model. Thiết kế: WF-03, ADR-002. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
