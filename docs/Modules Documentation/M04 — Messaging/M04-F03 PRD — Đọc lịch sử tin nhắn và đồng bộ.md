# M04-F03 · Đọc lịch sử tin nhắn và đồng bộ

## PRD chức năng · M04 Messaging · Trạng thái: Draft

# 1\. Mục tiêu

Participant mở Conversation, xem lịch sử theo thứ tự và nhận các tin mới qua REST polling có thể phục hồi sau reconnect.

# 2\. Quy tắc nghiệp vụ

* Chỉ thành viên của Conversation được đọc lịch sử. Admin chỉ theo ngoại lệ có report scope và audit ở M04-F08.  
* Theo BR-17, participant active vẫn đọc lịch sử của Conversation mình tham gia khi peer bị đình chỉ hoặc Conversation cancelled. Participant bị đình chỉ bị từ chối đọc kể cả khi còn session cũ; sau khôi phục, họ đọc lại lịch sử theo membership. Việc đình chỉ không xóa message/cursor hay tự đổi ConversationStatus.
* Cursor có thứ tự kép (created\_at, id) để xử lý nhiều message cùng timestamp; không dùng created\_at đơn lẻ.  
* Client dùng server message ID để dedupe giữa response, polling và sự kiện realtime nếu bổ sung sau.  
* REST polling và resync sau reconnect là baseline G2. Reverb/private channel là tối ưu tùy chọn sau khi REST được nghiệm thu.  
* MessageType đích gồm text, file và system theo Enum Catalog. File message chỉ liên kết attachment đã complete, đúng owner/purpose/resource theo M04-F06/M10. System event do server tạo; client không giả làm system.

# 3\. Hành vi API đích

GET lịch sử trả page message theo cursor, next\_cursor và trạng thái đồng bộ tối thiểu. Poll bằng cursor mới nhất; client không chồng nhiều request polling, có backoff/cleanup và resync sau reconnect. Endpoint, page size và error payload cần được chốt trong OpenAPI.

# 4\. Tiêu chí chấp nhận

* Hai message có created\_at trùng nhau vẫn được đọc đủ và đúng thứ tự theo (created\_at,id).  
* Phân trang/poll không mất hoặc trả trùng record; client dedupe theo server ID.  
* Outsider không thể đọc lịch sử bằng URL, ID hoặc cursor đoán được.  
* Mất kết nối rồi kết nối lại tiếp tục đồng bộ từ cursor mà không cần realtime.  
* Tin system chỉ xuất hiện từ event do server kiểm soát.

# 5\. Chênh lệch/câu hỏi mở

* Migration có index conversation\_id/created\_at nhưng cursor composite (created\_at,id) là policy service/API còn thiếu.  
* Chốt page size, cursor encoding và thứ tự khi clock values lệch. Conversation cancelled đã chốt chỉ đọc theo membership/BR-17; wire response và runtime còn cần triển khai.

# 6\. Nguồn

Product: Roles & Actors. Domain: Domain Model, Enum Catalog, State Lifecycles. Thiết kế: WF-03, ADR-002. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
