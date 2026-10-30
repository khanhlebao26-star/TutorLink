# M04-F07 · Vòng đời Conversation

## PRD chức năng · M04 Messaging · Trạng thái: Draft · Đối chiếu lifecycle đích

# 1\. Mục tiêu

Giữ trạng thái Conversation đồng bộ với các Program/Invitation đang liên kết giữa cùng một cặp Customer–Tutor.

# 2\. Trạng thái đích

* ConversationStatus chỉ gồm active/cancelled theo Enum Catalog và State Lifecycles.  
* Khi có Program completed, có thể chuyển active → cancelled chỉ khi cặp không còn Program active/paused và không còn Invitation pending còn hiệu lực.  
* Nếu còn quan hệ đang tiếp diễn, Conversation vẫn active.  
* cancelled là read-only; khi có hoạt động dịch vụ mới và cặp đủ điều kiện, tái kích hoạt trên đúng Conversation cũ, không tạo Conversation thứ hai.  
* Không tự xóa Conversation hoặc tin nhắn; giữ lịch sử theo policy.
* Đình chỉ một participant không tự đổi ConversationStatus hoặc membership; người còn active tiếp tục đọc lịch sử, người bị đình chỉ bị từ chối đọc/gửi kể cả khi còn session cũ. Sau khi được khôi phục, participant đọc lại lịch sử theo membership; gửi mới vẫn phải qua status và policy M04-F04.

# 3\. Hành vi đích

Máy chủ tính lại điều kiện theo quan hệ Program/Invitation trong transaction khi lifecycle liên quan thay đổi. Không nhận arbitrary status từ client. Các message mới chỉ được gửi khi Conversation active; cancelled giữ lịch sử đọc theo policy member.

# 4\. Tiêu chí chấp nhận

* Program completed nhưng còn Program active/paused hoặc Invitation pending thì Conversation không bị đóng.  
* Khi không còn relationship tiếp diễn và có điều kiện đóng, chuyển sang cancelled và ngăn gửi tin mới.  
* Trao đổi dịch vụ mới đủ điều kiện dùng lại Conversation cancelled hiện có.  
* Hai lifecycle event cạnh tranh không để trạng thái Conversation hoặc membership ở trạng thái nửa chừng.

# 5\. Trạng thái triển khai và giới hạn (K03.14)

* Migration K03.2 đã align type direct và status active/cancelled, default active; giữ unique direct_key. pending/expired/type=program trong thiết kế cũ không còn là trạng thái runtime được phép. Migration dừng nếu gặp Conversation legacy cần mapping hoặc message cũ cần canonical backfill; chưa có công cụ chuyển đổi legacy tổng quát.
* [K03.9](../../handover/BE-K03-K03.9-test-report.md) đã kiểm đóng chat cuối transaction complete/cancel Program và lazy expiry khi cập nhật Draft. Chỉ đóng khi cặp từng có Program completed, không còn Program active/paused và không còn Invitation pending còn hiệu lực; không có Conversation thì không tự tạo.
* Chỉ POST mở liên hệ mới đủ gate mới reopen Conversation cũ, giữ ID/context. GET, send/accept Invitation, polling và replay không tự reopen; không tự ghi system message. Suspend giữ nguyên Conversation/member/read cursor; restore account không tự đổi status chat.
* [K03.10](../../handover/BE-K03-K03.10-test-report.md)/[K03.13](../../handover/BE-K03-K03.13-test-report.md) có regression và race. Expiry hiện xử lý lazy; chưa có worker tự đóng ngay lúc Invitation hết hạn. Không suy ra reject/cancel Invitation hoặc writer lifecycle tương lai đã triển khai nếu Catalog còn Dự kiến.

# 6\. Nguồn

Product: Business Rules, Roles & Actors. Domain: State Lifecycles, Enum Catalog, Domain Model. Thiết kế: PRD Messaging nội bộ, WF-03. Link nguồn: [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>)
