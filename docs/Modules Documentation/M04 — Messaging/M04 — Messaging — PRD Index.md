# M04 — Messaging

## PRD Index · 8 chức năng · Draft · Cập nhật 17/10/2026

# Mục tiêu module

Cung cấp Conversation riêng tư giữa Customer và Tutor để trao đổi trước và trong hành trình dịch vụ, với REST-first messaging, kiểm soát membership, retry an toàn, trạng thái đọc và tệp đính kèm theo policy.

# Danh sách PRD chức năng

[M04-F01 PRD — Tạo hoặc dùng lại Conversation](<M04-F01 PRD — Tạo hoặc dùng lại Conversation.md>)  
[M04-F02 PRD — Danh sách Conversation của tôi](<M04-F02 PRD — Danh sách Conversation của tôi.md>)  
[M04-F03 PRD — Đọc lịch sử tin nhắn và đồng bộ](<M04-F03 PRD — Đọc lịch sử tin nhắn và đồng bộ.md>)  
[M04-F04 PRD — Gửi tin nhắn văn bản và retry](<M04-F04 PRD — Gửi tin nhắn văn bản và retry.md>)  
[M04-F05 PRD — Cập nhật trạng thái đã đọc](<M04-F05 PRD — Cập nhật trạng thái đã đọc.md>)  
[M04-F06 PRD — Đính kèm và truy cập tệp trong chat](<M04-F06 PRD — Đính kèm và truy cập tệp trong chat.md>)  
[M04-F07 PRD — Vòng đời Conversation](<M04-F07 PRD — Vòng đời Conversation.md>)  
[M04-F08 PRD — Admin truy cập chat theo phạm vi báo cáo](<M04-F08 PRD — Admin truy cập chat theo phạm vi báo cáo.md>)

# Các quyết định Product/Domain đã hoàn tất

* Một Conversation duy nhất cho cặp Customer–Tutor; Listing chỉ là ngữ cảnh tùy chọn; Program liên quan qua đúng cặp này, không tạo chat riêng cho từng Listing hoặc Program.  
* BR-17: request mở chat nhận `peer_user_id`/`context_listing_id` tùy chọn; response peer tối thiểu, không lộ direct_key/dữ liệu riêng tư. Context chỉ lưu lúc tạo đầu tiên và không ghi đè khi dùng lại/tái mở. Member active vẫn đọc lịch sử khi peer bị đình chỉ; member bị đình chỉ bị từ chối truy cập kể cả khi còn session cũ, khôi phục thì đọc lại theo membership. Đình chỉ không tự đổi ConversationStatus hoặc membership.
* ConversationStatus đích chỉ active/cancelled. Conversation chỉ đóng khi không còn Program active/paused và không còn Invitation pending còn hạn; khi có hoạt động mới đủ điều kiện thì dùng lại Conversation cũ.  
* Chỉ thành viên được đọc/gửi trong Conversation của họ. Admin chỉ truy cập khi được phân công xử lý Report và phải ghi log.  
* Server derive direct\_key từ cặp participant; idempotency message theo (sender\_id, client\_message\_id); cursor theo (created\_at,id), read cursor đơn điệu.  
* REST persistence/polling và resync sau reconnect là baseline G2; Reverb/private channels là tùy chọn sau core.  
* File attachment phải complete, đúng owner/purpose/resource, private theo membership; tệp được phép tải qua backend authorization.

# Các chênh lệch và quyết định còn mở

* Đã có migration messaging, nhưng hiện chưa có messaging routes/controller/API/UI trong repo.  
* Migration cho phép Conversation pending/active/cancelled/expired và MessageType text/file/system; target Domain giữ ConversationStatus active/cancelled và MessageType text/file/system. Như vậy MessageType đã thống nhất; khác biệt ConversationStatus với migration vẫn cần mapping/migration được duyệt.  
* Local W1-C1 contract cũ mô tả cancelled là terminal và Conversation pending/expired, khác State Lifecycles hiện hành; Domain docs đã hoàn tất là target, cần mapping/migration được duyệt.  
* Enum Catalog đã có MessageType.file và API Route Catalog mô tả gửi text/file; không còn câu hỏi có mở loại message file hay không. BR-21 chốt một file/message, 5 MiB, allow-list, scan, retention orphan và xử lý object mất; runtime còn cần thực hiện các kiểm tra này.
* Customer hoặc Tutor đều được mở liên hệ với vai trò còn lại qua gate M04-F01; cancelled chỉ mở lại bằng yêu cầu mở liên hệ mới đủ điều kiện. BR-17 chốt DTO peer/context và lịch sử khi đình chỉ; BR-21 chốt member active không gửi khi peer bị đình chỉ, inbox/unread/read receipt, body/rate limit, conflict idempotency và policy file chat. Runtime, migration và wire DTO vẫn cần triển khai. Admin report scope đã chốt ở BR-11; field masking/retention của evidence vẫn theo D16–D17.

# Implementation evidence

* backend/database/migrations/2026\_10\_07\_000300\_create\_messaging\_tables.php tạo conversations, conversation\_members, messages; có unique direct\_key, unique (sender\_id, client\_message\_id), read cursor FK và index conversation/time.  
* Data model note ghi natural key/cursor service policy còn cần hiện thực. Bảng migration không đồng nghĩa endpoint hoặc permission đã có.

Nguồn chuẩn PRD: các Product/Domain Docs hoàn tất liên kết dưới đây. Module README, WF-03, ADR-002, OpenAPI và migration được dùng để đối chiếu implementation/contract, không ghi đè quyết định Product/Domain.

# Product & Domain source documents

Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)
