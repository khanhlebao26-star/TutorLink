# M04-F04 · Gửi tin nhắn văn bản và retry

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant gửi tin nhắn văn bản trong Conversation active; lỗi mạng và double-click không tạo message trùng.

# 2\. Quy tắc nghiệp vụ

* sender\_id lấy từ phiên xác thực, không tin sender ID trong body.  
* Chỉ participant được gửi; Conversation phải active và sender phải đủ điều kiện account/relationship tại thời điểm ghi.  
* Mỗi request có UUID client\_message\_id; unique(sender\_id,client\_message\_id) bảo đảm retry idempotent.  
* Payload canonical gồm `conversation_id`, `message_type`, body đã chuẩn hóa CRLF/CR thành LF và file_ids rỗng; cùng key/cùng payload trả message đã persist, cùng key/khác payload trả HTTP 409.
* Tin nhắn văn bản dùng MessageType=text, phải không trắng và tối đa 4.000 ký tự; nội dung được giữ nguyên ngoài chuẩn hóa CRLF/CR thành LF. Client không được gửi system; giữ enum/schema legacy không chứng nhận writer system message đã triển khai.
* Giới hạn 30 tin mới/phút/người dùng; retry của message đã tồn tại không tính quota. Khi vượt hạn trả 429 kèm `Retry-After` để client retry có kiểm soát.

# 3\. Hành vi API đích

POST message trong scope Conversation. Server kiểm tra quyền và trạng thái trong transaction, ghi message cùng idempotency key rồi trả server ID, client\_message\_id và created\_at. Không tạo message mới khi Conversation cancelled; replay message cũ theo quyền đọc không reopen chat.

# 4\. Tiêu chí chấp nhận

* Retry cùng key/payload sau timeout trả cùng message, chỉ có một hàng persisted.  
* Hai request đồng thời cùng key tạo một message.  
* Key đã dùng với payload khác không ghi thêm message; response HTTP 409 theo BR-21.
* Participant khác có thể nhận message qua polling; thứ tự ổn định theo created\_at/id.  
* Outsider, sender bị đình chỉ/khóa, peer bị đình chỉ hoặc Conversation cancelled không ghi được message.
* System message không thể được gửi bằng cách client chọn message\_type=system.

# 5\. Trạng thái triển khai và giới hạn (K03.14)

* `POST /api/v1/conversations/{conversation}/messages` và action SendTextMessage đã có. Sender từ session; transaction kiểm quyền/trạng thái, canonical hóa newline và giữ unique `(sender_id,client_message_id)` trên mọi Conversation của sender.
* Request chỉ text; body không trắng, tối đa 4.000 ký tự; không trim/Unicode-normalize. `file_ids` chỉ được bỏ qua hoặc là mảng rỗng; file/system hoặc attachment request bị 422. Canonical payload có Conversation ID, type text, body đã chuẩn hóa và file_ids rỗng.
* Tạo mới trả 201; cùng key/canonical payload trả message cũ 200; khác payload/Conversation trả 409. Replay kiểm quyền đọc hiện tại, không reopen, không tiêu quota hoặc enqueue thêm notification, kể cả chat cancelled/peer suspended. Actor suspended bị chặn.
* Quota đã triển khai: 30 tin mới trong cửa sổ trượt 60 giây/người gửi trên mọi Conversation; vượt hạn 429 kèm Retry-After, request thất bại không tiêu quota.
* [K03.5](../../handover/BE-K03-K03.5-test-report.md), [K03.10](../../handover/BE-K03-K03.10-test-report.md) và [K03.13](../../handover/BE-K03-K03.13-test-report.md) có API, concurrent send/suspend và after-commit/replay/rollback evidence. Không chứng nhận UI hoặc performance/load production; K03.12 hoãn.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, State Lifecycles, Domain Model. Thiết kế: WF-03, ADR-002. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>)
