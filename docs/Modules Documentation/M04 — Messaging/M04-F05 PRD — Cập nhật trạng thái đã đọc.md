# M04-F05 · Cập nhật trạng thái đã đọc

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant đánh dấu tiến độ đã đọc trong một Conversation để cập nhật unread/read state mà không thể thao tác chéo thread hoặc làm lùi tiến độ.

# 2\. Quy tắc nghiệp vụ

* Read cursor thuộc về một member trong một Conversation; không dùng cursor chung cho mọi cuộc trò chuyện.  
* Message được gửi trong cùng Conversation với member mới được làm mốc read.  
* Cập nhật đơn điệu theo thứ tự (created\_at,id): mốc mới hơn có thể tiến lên; request cũ không được kéo cursor lùi.  
* MessageReadViewState đích: unread/read. Quy tắc hiển thị read receipt cho đối phương cần thống nhất với privacy/UI contract.

# 3\. Hành vi API đích

PATCH read-state trong Conversation với message ID cuối cùng người dùng đã đọc. Server xác nhận membership và thuộc tính message, khóa/cập nhật member cursor nguyên tử. Response trả cursor hiệu lực sau cập nhật.

# 4\. Tiêu chí chấp nhận

* Đánh dấu message trong đúng Conversation cập nhật cursor của member hiện tại.  
* Message ID thuộc thread khác bị từ chối; không thay đổi cursor.  
* Đánh dấu message cũ hơn giữ nguyên cursor hiện tại.  
* Hai cập nhật cạnh tranh kết thúc tại cursor xa nhất đã đọc, không mất tiến độ.  
* Member khác không thể cập nhật read cursor của người dùng hiện tại.

# 5\. Chênh lệch/câu hỏi mở

* Schema có last\_read\_message\_id và last\_read\_at; service cần kiểm tra quan hệ Conversation và bảo đảm monotonic.  
* Chốt semantics khi message đã bị xóa/ẩn hoặc mốc null; chọn no-op hay lỗi khi request lùi; chốt mức chi tiết read receipt (per message hay per conversation).

# 6\. Nguồn

Product: Roles & Actors. Domain: Domain Model, Enum Catalog. Thiết kế: PRD Messaging nội bộ, WF-03, ADR-002. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
