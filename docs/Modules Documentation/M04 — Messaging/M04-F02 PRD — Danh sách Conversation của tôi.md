# M04-F02 · Danh sách Conversation của tôi

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Customer và Tutor xem các Conversation mà tài khoản của mình là thành viên để tiếp tục trao đổi.

# 2\. Quy tắc quyền truy cập

* Chỉ participant là thành viên của Conversation mới được liệt kê/đọc Conversation đó.  
* Không trả Conversation của cặp khác khi thay đổi ID, query hoặc cursor.  
* Admin không xuất hiện như participant và không có inbox chat tổng thể; quyền xem theo report được phân công được mô tả ở M04-F08.  
* Status cancelled vẫn có thể xuất hiện trong lịch sử, ở chế độ chỉ đọc; status active cho phép trao đổi nếu account/relationship policy còn hợp lệ.

# 3\. Dữ liệu tối thiểu và trải nghiệm

Danh sách cần định danh Conversation, peer participant, status, ngữ cảnh Listing nếu có, hoạt động gần nhất và trạng thái chưa đọc để người dùng chọn cuộc trò chuyện. Field allowlist, unread count, preview nội dung và thứ tự sắp xếp cần được thống nhất trong OpenAPI/UI contract.  
Tải danh sách theo quyền ở server. Có thể phân trang nếu dữ liệu vượt giới hạn; quy tắc pagination/order hiện chưa được Product/Domain định nghĩa.

# 4\. Tiêu chí chấp nhận

* Customer/Tutor chỉ nhận danh sách Conversation có membership của chính mình.  
* Conversation cancelled không có affordance gửi; lịch sử vẫn có thể xem theo chính sách đọc.  
* Trạng thái unavailable/unauthorized không rò rỉ participant, nội dung tin nhắn hoặc listing context của người ngoài.  
* Danh sách và detail dùng cùng policy membership/account; UI không tự suy quyền từ status label.

# 5\. Chênh lệch/câu hỏi mở

* Chưa có API hoặc UI inbox trong repo. Cần chốt sort theo thời gian hoạt động mới nhất, fields public cho peer, cursor pagination và cách tính unread.  
* Product cần chốt quyền đọc lịch sử của thành viên khi một participant bị suspend/khóa account.

# 6\. Nguồn

Product: Roles & Actors, Project Overview. Domain: Domain Model, State Lifecycles. Thiết kế: PRD Messaging nội bộ, WF-03. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
