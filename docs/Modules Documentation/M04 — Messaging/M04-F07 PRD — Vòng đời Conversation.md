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

# 5\. Chênh lệch/câu hỏi mở

* Migration hiện cho status pending/active/cancelled/expired; target Domain chỉ active/cancelled. Local W1-C1 contract còn mô tả pending/expired terminal và cancelled không reopen, mâu thuẫn với State Lifecycles hiện hành. Cần migration/transition mapping được duyệt.  
* BR-21 chốt không tự ghi system message khi lifecycle đổi trạng thái. Server tính lại điều kiện đóng trong cùng giao dịch với sự kiện Program/Invitation làm thay đổi quan hệ; Conversation chỉ được mở lại qua request mở liên hệ mới đủ gate tại M04-F01.
* BR-17 đã chốt quyền đọc lịch sử cancelled và trường hợp participant bị đình chỉ. Trigger đóng/mở, migration và chính sách gửi ở M04-F04 vẫn cần triển khai/kiểm thử.

# 6\. Nguồn

Product: Business Rules, Roles & Actors. Domain: State Lifecycles, Enum Catalog, Domain Model. Thiết kế: PRD Messaging nội bộ, WF-03. Link nguồn: [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>)
