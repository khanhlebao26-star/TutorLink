# M04-F03 · Đọc lịch sử tin nhắn và đồng bộ

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant mở Conversation, xem lịch sử theo thứ tự và nhận các tin mới qua REST polling có thể phục hồi sau reconnect.

# 2\. Quy tắc nghiệp vụ

* Chỉ thành viên của Conversation được đọc lịch sử. Admin chỉ theo ngoại lệ có report scope và audit ở M04-F08.  
* Theo BR-17, participant active vẫn đọc lịch sử của Conversation mình tham gia khi peer bị đình chỉ hoặc Conversation cancelled. Participant bị đình chỉ bị từ chối đọc kể cả khi còn session cũ; sau khôi phục, họ đọc lại lịch sử theo membership. Việc đình chỉ không xóa message/cursor hay tự đổi ConversationStatus.
* Cursor có thứ tự kép (created\_at, id) để xử lý nhiều message cùng timestamp; không dùng created\_at đơn lẻ.  
* Client dùng server message ID để dedupe giữa response, polling và sự kiện realtime; K03.13 chứng minh bằng consumer probe, UI/Echo hoãn K03.12.
* REST polling và resync sau reconnect là baseline G2. Backend Reverb đã kiểm K03.13; channel theo Conversation và phiên browser, không thay thế REST hoặc quyền truy cập.
* Client chỉ gửi text theo K03.1 điều chỉnh; file/system trong enum/schema là legacy, không mở quyền gửi attachment hoặc giả system. File Task/Submission/CV đi qua module tương ứng.

# 3\. Hành vi API đích

GET lịch sử trả page message theo cursor, next\_cursor và trạng thái đồng bộ tối thiểu. Poll bằng cursor mới nhất; client không chồng nhiều request polling, có backoff/cleanup và resync sau reconnect. Endpoint, page size và error payload hiện được mô tả trong OpenAPI; xem trạng thái triển khai bên dưới.

# 4\. Tiêu chí chấp nhận

* Hai message có created\_at trùng nhau vẫn được đọc đủ và đúng thứ tự theo (created\_at,id).  
* Phân trang/poll không mất hoặc trả trùng record; client dedupe theo server ID.  
* Outsider không thể đọc lịch sử bằng URL, ID hoặc cursor đoán được.  
* Mất kết nối rồi kết nối lại tiếp tục đồng bộ từ cursor mà không cần realtime.  
* Tin system chỉ xuất hiện từ event do server kiểm soát.

# 5\. Trạng thái triển khai và giới hạn (K03.14)

* Backend đã triển khai `GET /api/v1/conversations/{conversation}/messages`, cursor opaque scoped theo Conversation và thứ tự `(created_at,id)`; index `(conversation_id,created_at,id)` đã có. Không nhận đồng thời before/after; mặc định 20, tối đa 50 message/trang.
* Trang đầu/before trả mới → cũ; after trả cũ → mới. Response có `next_cursor`, `has_more`, `polling_cursor`; timestamp UTC giữ độ chính xác microsecond của DB. API không tự reopen hoặc đổi read cursor.
* [K03.4](../../handover/BE-K03-K03.4-test-report.md)/[K03.10](../../handover/BE-K03-K03.10-test-report.md) kiểm quyền/cursor; [K03.13](../../handover/BE-K03-K03.13-test-report.md) có probe 55 tin cùng timestamp qua ba trang, reconnect và retry/resync dedupe theo server ID.
* Consumer probe chứng minh backend và thứ tự xử lý response/event, chưa chứng nhận UI, polling fallback hoặc cleanup listener. K03.12 hoãn. Writer mới phải giữ tương thích thứ tự commit/cursor; không suy ra generic delete/hide message đã có API.

# 6\. Nguồn

Product: Roles & Actors. Domain: Domain Model, Enum Catalog, State Lifecycles. Thiết kế: WF-03, ADR-002. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)

# Bổ sung K03.13 — phục hồi realtime (30/10/2026)

Backend Reverb hiện đã có channel theo Conversation + phiên theo [contract §13](../../handover/BE-K03-K03.1-contract.md#13-realtime). Notification chỉ là hint; REST after cursor là nguồn dữ liệu. Disconnect/reconnect phải auth socket mới, drain các trang has_more từ checkpoint đã xử lý và dedupe server message ID. Logout/suspend không cho channel cũ nhận publish mới; restore không hồi sinh token. [Evidence](../../handover/BE-K03-K03.13-test-report.md) kiểm 55 tin trùng timestamp qua ba trang, retry, outage worker/Reverb và hai thứ tự xử lý API/event bằng consumer probe. Echo/UI, polling fallback và cleanup listener vẫn hoãn K03.12. Enum/schema file/system legacy không mở quyền gửi: client phiên bản này chỉ gửi text theo K03.1/K03.8.
