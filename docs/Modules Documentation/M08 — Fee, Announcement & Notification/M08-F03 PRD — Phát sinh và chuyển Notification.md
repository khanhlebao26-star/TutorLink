# M08-F03 · Phát sinh và chuyển Notification

## PRD chức năng · M08 Fee, Announcement & Notification · Draft

# 1\. Mục tiêu

Phát notification trong ứng dụng tới đúng recipient sau khi sự kiện nghiệp vụ đã commit, có thể retry an toàn.

# 2\. Sự kiện MVP

* Invitation nhận được → Customer được mời; accepted/rejected → Tutor owner; cancelled → Customer; expired → Tutor owner.  
* Task assigned → Customer assignee; submitted → Tutor owner; feedback received/revision requested → Customer assignee.  
* Announcement published → các Program member còn lại.  
* Program paused/resumed/completed/cancelled → member còn lại của Program.  
* Ma trận MVP theo NotificationType hiện có trong Enum: tutor\_profile\_submitted → coordinator có quyền phân công; tutor\_profile\_approved/changes\_requested/rejected/suspended/restored → Tutor sở hữu hồ sơ; invitation\_received → Customer được mời, accepted/rejected → Tutor owner, cancelled → Customer, expired → Tutor owner; task\_assigned → Customer assignee, submitted → Tutor owner, feedback\_received/revision\_requested → Customer assignee; announcement\_published → member còn lại; program\_paused/resumed/completed/cancelled → member còn lại; report\_resolved/dismissed → người gửi Report. Type mới phải cập nhật Enum và ma trận này.

# 3\. Quy tắc reliability

* Notification thuộc một recipient; chỉ người nhận đọc được.  
* Trong transaction mutation nghiệp vụ và audit bắt buộc, ghi một notification\_outbox row cho từng recipient/type/event\_key. Worker chỉ thấy row đã commit; khóa bằng FOR UPDATE SKIP LOCKED, tạo notification và đặt processed\_at trong cùng transaction. UNIQUE(user\_id,type,event\_key) và insert an toàn bảo đảm retry không nhân đôi. Payload chỉ gồm resource/deep link và preview được allow-list, không chép chat/evidence.  
* Transaction business rollback thì không tạo notification.  
* Worker trong Docker poll PostgreSQL outbox theo available\_at. Khi xử lý lỗi, rollback việc tạo notification rồi ghi attempt\_count, mã lỗi an toàn và available\_at cho lần thử sau; backoff tăng dần. Đề xuất giới hạn 10 lần hoặc 60 phút cần được nhóm chốt; sau ngưỡng đó chuyển failed\_at để vận hành kiểm tra và replay. Notification là eventual; Telescope hỗ trợ quan sát request/job/lỗi, không thay cơ chế lưu bền. Realtime không thay REST.  
* MVP dùng in\_app; email/SMS provider không mặc định và Mailpit local không phải worker giao thư thật.

# 4\. Tiêu chí chấp nhận

* Mỗi event nghiệp vụ hợp lệ tạo notification đúng recipient và deep link đúng resource.  
* Replay cùng event\_key chỉ có một bản ghi notification.  
* Commit nghiệp vụ luôn có outbox row trong cùng transaction; rollback không để outbox/notification mồ côi. Worker retry/replay cùng recipient/type/event\_key không tạo notification thứ hai; lỗi tạo notification không đánh dấu outbox đã xử lý.  
* Recipient khác không đọc được notification bằng ID/deep link.

# 5\. Chênh lệch/câu hỏi mở

* Schema notification đã có; thiết kế này bổ sung outbox/event writer và worker chạy qua Docker. Đây là target thiết kế, chưa phải runtime đã triển khai.  
* Còn cần chốt retention outbox/notification, ngưỡng cảnh báo hàng failed và quy trình replay vận hành. MVP dùng payload allow-list tối thiểu; không lưu nội dung message hoặc evidence riêng tư.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
