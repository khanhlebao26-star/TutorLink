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

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
