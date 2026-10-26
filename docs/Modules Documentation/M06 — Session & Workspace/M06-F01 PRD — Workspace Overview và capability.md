# M06-F01 · Workspace Overview và capability

## PRD chức năng · M06 Session & Workspace · Draft

# 1\. Mục tiêu

Cho Tutor owner và Customer member một không gian Program thống nhất để xem tình trạng, hoạt động sắp tới và các tab workspace theo quyền.

# 2\. Cấu trúc

* Sáu tab: Overview, Session, Task, Progress, Announcement, Fee.  
* Overview read model gồm next\_session, task\_counts đủ `todo`/`in_progress`/`submitted`/`completed`/`cancelled`, latest\_progress, latest\_announcement và object `capabilities`. `next_session` là Session `scheduled` gần nhất trong tương lai, sắp theo `(planned_starts_at,id)` tăng dần; latest\_progress sắp theo `(recorded_at,id)` giảm dần; latest\_announcement chỉ lấy bản published mới nhất theo `(published_at,id)` giảm dần. Khi không có bản ghi tương ứng, field trả `null`; mọi TaskStatus đều có count, kể cả count 0.
* Dữ liệu lấy từ aggregate Program; không dùng mock để thay API và tránh N+1 query.

# 3\. Quyền và state

* Tutor owner và Customer member được đọc trong phạm vi membership; outsider không truy cập.  
* Active cho phép hoạt động theo quyền; Paused chặn tạo Session/Task/Fee mới, cho Tutor owner tạo, sửa Draft và publish Announcement, đồng thời vẫn cho đọc và các thao tác tiếp diễn được BR-10 cho phép.
* Completed/Cancelled readonly, ngoại trừ Customer member đủ điều kiện tạo Review sau Completed.  
* Server trả capability/action eligibility trong `capabilities`; Overview chỉ trả capability cấp Program (`can_create_session`, `can_create_task`, `can_record_progress`, `can_create_fee_record`, `can_create_announcement`, `can_create_review`). Action cho Session/Task/Fee/Announcement cụ thể nằm trong resource response. UI không tự quyết bằng cách so sánh status string.

# 4\. Tiêu chí chấp nhận

* Overview trả đúng Session sắp tới, task counts (kể cả 0), latest progress/announcement (null nếu chưa có) và capabilities.
* Không tính planned Session như một buổi đã thực hiện.  
* Tab rỗng hoặc một phần dữ liệu thiếu không làm mất khả năng mở các tab khác.  
* Owner/member/outsider và Active/Paused/terminal matrix được áp dụng nhất quán cho mọi tab.
* Timestamp là RFC 3339; UI hiển thị thời điểm theo `Program.timezone`.

# 5\. Chênh lệch/câu hỏi mở

* Workspace API/UI chưa có trong repo; hiện mới có schema migration.  
* Shape, counts, timezone và capability của Overview được chốt tại BR-20; API/UI chưa có trong repo và cần triển khai theo contract này.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
