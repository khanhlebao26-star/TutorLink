# M08-F04 · Notification Inbox và trạng thái đã đọc

## PRD chức năng · M08 Fee, Announcement & Notification · Draft

# 1\. Mục tiêu

Recipient xem notification, mở deep link và cập nhật trạng thái đã đọc mà không ảnh hưởng notification của người khác.

# 2\. Quy tắc

* NotificationReadState: unread/read; user chỉ đọc notification có recipient\_id bằng account hiện tại.  
* Inbox có list, unread\_count, deep\_link, mark một notification read và mark-all-read.  
* Read/read-all retry an toàn; badge count phản ánh cùng tập record.  
* Deep link vẫn chạy kiểm quyền trên resource đích; notification không cấp entitlement.
* Trạng thái read/unread thuộc Notification Inbox. Announcement không có read state hoặc unread count riêng trong MVP.

# 3\. Tiêu chí chấp nhận

* List và unread\_count trả đúng notification recipient; outsider không thể truy cập.  
* Mark one/read-all làm trạng thái và count nhất quán; retry không làm count âm hoặc tăng giả.  
* Deep link tới Program/Task/Announcement/Invitation vẫn yêu cầu membership/actor permission.  
* Empty/loading/error state có thể xử lý độc lập với workspace tabs.

# 4\. Chênh lệch/câu hỏi mở

* Chốt pagination, sort order, payload preview và cách lưu route/deep\_link có an toàn với app routing.  
* API/UI hiện chưa có; notification table có nhưng chỉ schema chưa đủ nghiệm thu.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
