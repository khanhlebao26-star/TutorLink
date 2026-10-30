# M04-F05 · Cập nhật trạng thái đã đọc

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant đánh dấu tiến độ đã đọc trong một Conversation để cập nhật unread/read state mà không thể thao tác chéo thread hoặc làm lùi tiến độ.

# 2\. Quy tắc nghiệp vụ

* Read cursor thuộc về một member trong một Conversation; không dùng cursor chung cho mọi cuộc trò chuyện.  
* Message được gửi trong cùng Conversation với member mới được làm mốc read.  
* Cập nhật đơn điệu theo thứ tự (created\_at,id): mốc mới hơn có thể tiến lên; request cũ không được kéo cursor lùi.  
* MessageReadViewState đích: unread/read. Read receipt theo Conversation/member; UI chưa nghiệm thu và không công bố per-message read receipt.

# 3\. Hành vi API đích

PATCH read-state trong Conversation với message ID cuối cùng người dùng đã đọc. Server xác nhận membership và thuộc tính message, khóa/cập nhật member cursor nguyên tử. Response trả cursor hiệu lực sau cập nhật.

# 4\. Tiêu chí chấp nhận

* Đánh dấu message trong đúng Conversation cập nhật cursor của member hiện tại.  
* Message ID thuộc thread khác bị từ chối; không thay đổi cursor.  
* Đánh dấu message cũ hơn giữ nguyên cursor hiện tại.  
* Hai cập nhật cạnh tranh kết thúc tại cursor xa nhất đã đọc, không mất tiến độ.  
* Member khác không thể cập nhật read cursor của người dùng hiện tại.

# 5\. Trạng thái triển khai và giới hạn (K03.14)

* `PATCH /api/v1/conversations/{conversation}/read` đã triển khai. Body nhận `last_read_message_id`, chỉ cập nhật member của actor, không nhận user_id hoặc reset null.
* So sánh `(created_at,id)`; mốc mới hơn cập nhật, mốc cũ/bằng no-op và trả 200 cùng cursor hiệu lực. Thiếu/null/sai kiểu, message không tồn tại hoặc thuộc chat khác trả 422 trung tính; outsider 404. Không có thao tác reset cursor.
* [K03.6](../../handover/BE-K03-K03.6-test-report.md), [K03.10](../../handover/BE-K03-K03.10-test-report.md) và regression [K03.13](../../handover/BE-K03-K03.13-test-report.md) là evidence. ReadCursorConcurrencyTest dùng process PostgreSQL độc lập để kiểm hai cập nhật giữ mốc xa nhất.
* Read receipt theo Conversation/member, không chứng nhận UI đã hiển thị; K03.12 hoãn. Chưa có generic message hide/delete API; dữ liệu legacy cursor bất thường cần xử lý riêng, không tự reset hoặc suy ra semantics mới.

# 6\. Nguồn

Product: Roles & Actors. Domain: Domain Model, Enum Catalog. Thiết kế: PRD Messaging nội bộ, WF-03, ADR-002. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
