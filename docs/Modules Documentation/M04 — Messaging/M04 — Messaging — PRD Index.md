# M04 — Messaging

## PRD Index · 8 chức năng · Draft · Cập nhật 17/10/2026

# Mục tiêu module

Cung cấp Conversation riêng tư giữa Customer và Tutor với REST-first messaging, membership, retry an toàn và trạng thái đọc. [K03.1 điều chỉnh 29/10](../../handover/BE-K03-K03.1-contract.md) chốt chỉ text, không đính kèm; backend Reverb thông báo sau commit, quyền theo phiên và REST resync đã có evidence K03.13; Echo/UI K03.12 hoãn.

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
* REST persistence/polling và resync là baseline; Reverb/private channels thuộc K03.11–K03.13, chưa nghiệm thu và không được bỏ khỏi tiêu chí hoàn tất issue.
* Không upload/gửi/link file vào message. File Task/Submission/CV đi qua module tương ứng; không dùng scan evidence làm điều kiện mở attachment chat.

# Các chênh lệch và quyết định còn mở

* Bảy route Conversation K03.3–K03.6/K03.13 đã đăng ký trong [Catalog](../../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md); OpenAPI toàn API có 70 operations. Evidence trước điều chỉnh không chứng nhận toàn bộ K03.1 mới, frontend hoặc realtime.
* K03.2 đã align Conversation active/cancelled và direct; giữ MessageType/file schema legacy không có nghĩa client được gửi file/system.
* Local W1-C1 mô tả pending/expired và cancelled terminal là lịch sử. K03.2 đã align direct, active/cancelled; môi trường có legacy phải lập mapping/backfill riêng trước migration.
* Yêu cầu attachment/scanner cũ đã được K03.1 mới thay thế. K03.7a gỡ ClamAV; K03.7b đã kiểm tra kỹ thuật giới hạn upload 5.000.000 byte; [K03.8](../../handover/BE-K03-K03.8-test-report.md) đã kiểm tra kỹ thuật file_ids, chặn link/upload message và bảo toàn legacy; frontend chat chưa có để nghiệm thu E2E.
* Customer hoặc Tutor đều được mở liên hệ với vai trò còn lại qua gate M04-F01; cancelled chỉ mở lại bằng yêu cầu mở liên hệ mới đủ điều kiện. BR-17 chốt DTO peer/context và lịch sử khi đình chỉ; BR-21 chốt member active không gửi khi peer bị đình chỉ, inbox/unread/read receipt, body/rate limit, conflict idempotency và policy file chat. Backend Messaging đã có code/OpenAPI/evidence K03.2–K03.13; UI hoãn K03.12. Các route Admin report evidence còn theo trạng thái Catalog, chưa được BE-K03 chứng nhận. Admin report scope đã chốt ở BR-11; field masking/retention của evidence vẫn theo D16–D17.

# Implementation evidence

* backend/database/migrations/2026\_10\_07\_000300\_create\_messaging\_tables.php tạo conversations, conversation\_members, messages; có unique direct\_key, unique (sender\_id, client\_message\_id), read cursor FK và index conversation/time.  
* Schema/natural key/cursor và permission đã có evidence theo từng task backend; bảng migration hoặc route parity riêng lẻ không chứng minh toàn bộ hành vi. [K03.14 handover](../../handover/BE-K03-K03.14-handover.md) tổng hợp scope, runbook và phần chưa chứng nhận.

Nguồn chuẩn PRD: các Product/Domain Docs hoàn tất liên kết dưới đây. Module README, WF-03, ADR-002, OpenAPI và migration được dùng để đối chiếu implementation/contract, không ghi đè quyết định Product/Domain.

# Product & Domain source documents

Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)

# Bổ sung realtime 30/10/2026

[K03.13](../../handover/BE-K03-K03.13-test-report.md) kiểm backend với channel theo phiên, quyền publish sau logout/suspend/restore, job sau commit và reconnect/resync khi Reverb/worker lỗi. POST Conversation realtime cấp channel cho đúng session; /broadcasting/auth nằm ngoài OpenAPI. Đã có consumer probe hai participant và Admin/outsider; chưa có nghiệm thu Echo/UI. Team hoãn K03.12 theo phạm vi backend-only; xem [contract §13](../../handover/BE-K03-K03.1-contract.md#13-realtime).
