# M06-F01 · Workspace Overview và capability

## PRD chức năng · M06 Session & Workspace · Draft

# 1\. Mục tiêu

Cho Tutor owner và Customer member một không gian Program thống nhất để xem tình trạng, hoạt động sắp tới và các tab workspace theo quyền.

# 2\. Cấu trúc

* Sáu tab: Overview, Session, Task, Progress, Announcement, Fee.  
* Overview read model gồm next\_session, task\_counts, latest\_progress, latest\_announcement và capability fee/review.  
* Dữ liệu lấy từ aggregate Program; không dùng mock để thay API và tránh N+1 query.

# 3\. Quyền và state

* Tutor owner và Customer member được đọc trong phạm vi membership; outsider không truy cập.  
* Active cho phép hoạt động theo quyền; Paused chặn tạo Session/Task/Announcement/Fee mới nhưng vẫn cho đọc và các thao tác tiếp diễn được BR-10 cho phép.  
* Completed/Cancelled readonly, ngoại trừ Customer member đủ điều kiện tạo Review sau Completed.  
* Server trả capability/action eligibility; UI không tự quyết bằng cách so sánh status string.

# 4\. Tiêu chí chấp nhận

* Overview trả đúng Session sắp tới, task counts, latest progress/announcement và capabilities.  
* Không tính planned Session như một buổi đã thực hiện.  
* Tab rỗng hoặc một phần dữ liệu thiếu không làm mất khả năng mở các tab khác.  
* Owner/member/outsider và Active/Paused/terminal matrix được áp dụng nhất quán cho mọi tab.

# 5\. Chênh lệch/câu hỏi mở

* Workspace API/UI chưa có trong repo; hiện mới có schema migration.  
* Chốt shape overview, cách đếm task status, timezone source và capability field names trong OpenAPI.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
