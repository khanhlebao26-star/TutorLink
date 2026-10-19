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

# 3\. Hành vi đích

Máy chủ tính lại điều kiện theo quan hệ Program/Invitation trong transaction khi lifecycle liên quan thay đổi. Không nhận arbitrary status từ client. Các message mới chỉ được gửi khi Conversation active; cancelled giữ lịch sử đọc theo policy member.

# 4\. Tiêu chí chấp nhận

* Program completed nhưng còn Program active/paused hoặc Invitation pending thì Conversation không bị đóng.  
* Khi không còn relationship tiếp diễn và có điều kiện đóng, chuyển sang cancelled và ngăn gửi tin mới.  
* Trao đổi dịch vụ mới đủ điều kiện dùng lại Conversation cancelled hiện có.  
* Hai lifecycle event cạnh tranh không để trạng thái Conversation hoặc membership ở trạng thái nửa chừng.

# 5\. Chênh lệch/câu hỏi mở

* Migration hiện cho status pending/active/cancelled/expired; target Domain chỉ active/cancelled. Local W1-C1 contract còn mô tả pending/expired terminal và cancelled không reopen, mâu thuẫn với State Lifecycles hiện hành. Cần migration/transition mapping được duyệt.  
* Chốt event chính xác kích hoạt kiểm tra đóng, trigger reopen, và thông điệp system có ghi vào timeline khi đổi status hay không.  
* Chốt quyền đọc lịch sử cancelled sau khi User/Tutor Profile bị suspend hoặc account bị khóa.

# 6\. Nguồn

Product: Business Rules, Roles & Actors. Domain: State Lifecycles, Enum Catalog, Domain Model. Thiết kế: PRD Messaging nội bộ, WF-03. Link nguồn: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
