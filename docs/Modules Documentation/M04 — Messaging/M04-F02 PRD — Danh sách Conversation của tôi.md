# M04-F02 · Danh sách Conversation của tôi

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Customer và Tutor xem các Conversation mà tài khoản của mình là thành viên để tiếp tục trao đổi.

# 2\. Quy tắc quyền truy cập

* Chỉ participant là thành viên của Conversation mới được liệt kê/đọc Conversation đó.  
* Không trả Conversation của cặp khác khi thay đổi ID, query hoặc cursor.  
* Admin không xuất hiện như participant và không có inbox chat tổng thể; quyền xem theo report được phân công được mô tả ở M04-F08.  
* Status cancelled vẫn có thể xuất hiện trong lịch sử, ở chế độ chỉ đọc. Status active chưa đủ để cho gửi: cả hai participant phải active và peer không bị đình chỉ; mọi quyền gửi được kiểm lại tại thời điểm ghi.
* Khi một participant bị đình chỉ, membership và ConversationStatus không tự đổi. Participant đó bị từ chối đọc/gửi kể cả khi còn session cũ; participant còn active vẫn được liệt kê và đọc lịch sử chat của chính mình. Sau khi được khôi phục, member cũ đọc lại lịch sử theo membership; gửi tin mới vẫn phải kiểm status và policy M04-F04. Admin không có inbox participant.

# 3\. Dữ liệu tối thiểu và trải nghiệm

Danh sách cần định danh Conversation, peer participant, status, ngữ cảnh Listing nếu có, hoạt động gần nhất và trạng thái chưa đọc để người dùng chọn cuộc trò chuyện. Peer tối thiểu theo BR-17 chỉ gồm `id`/`display_name`; không trả email, điện thoại hoặc thông tin riêng tư. `context_listing_id` là context bất biến từ lúc tạo, không ngầm thay bằng Listing mà người dùng vừa mở. Inbox sắp Conversation có message theo message mới nhất `(created_at,id)` giảm dần; Conversation chưa có message theo `(created_at,id)` giảm dần. Preview chỉ dùng loại/nội dung an toàn của message mới nhất; attachment preview hiển thị nhãn chung, không lộ tên file hay object key. `unread_count` đếm message của peer sau `last_read_message_id`; system message không tăng unread. Read receipt chỉ ở mức Conversation, không công bố đã đọc từng message cho peer. Phân trang dùng cursor ổn định theo khóa sắp xếp và ID Conversation; Wire schema hiện hành đã được sinh từ code trong OpenAPI; frontend chưa nghiệm thu.

# 4\. Tiêu chí chấp nhận

* Customer/Tutor chỉ nhận danh sách Conversation có membership của chính mình.  
* Conversation cancelled không có affordance gửi; lịch sử vẫn có thể xem theo chính sách đọc.  
* Trạng thái unavailable/unauthorized không rò rỉ participant, nội dung tin nhắn hoặc listing context của người ngoài.  
* Danh sách và detail dùng cùng policy membership/account; UI không tự suy quyền từ status label.

# 5\. Trạng thái triển khai và giới hạn (K03.14)

* Backend đã đăng ký `GET /api/v1/conversations` và `GET /api/v1/conversations/{conversation}`. Scope membership/account áp dụng trước dữ liệu; response có peer tối thiểu, context gốc, preview, unread count và cursor inbox. Wire schema hiện hành nằm trong [OpenAPI](../../api/openapi.json).
* [K03.4](../../handover/BE-K03-K03.4-test-report.md), [K03.10](../../handover/BE-K03-K03.10-test-report.md) và regression [K03.13](../../handover/BE-K03-K03.13-test-report.md) là evidence backend. Member active đọc được chat cancelled/peer suspended; actor suspended hoặc outsider bị chặn.
* Chat hiện chỉ text. Mô tả attachment preview chỉ là dữ liệu legacy nếu có; không mở tính năng upload/gửi file mới. UI inbox/chat và Echo chưa được nghiệm thu, K03.12 được team hoãn.

# 6\. Nguồn

Product: Roles & Actors, Project Overview. Domain: Domain Model, State Lifecycles. Thiết kế: PRD Messaging nội bộ, WF-03. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Project Overview](<../../Product Document/Project Overview.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
