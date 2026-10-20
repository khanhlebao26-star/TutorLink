# M07-F01 · Tạo và giao Task

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Tutor giao công việc cho Customer member trong đúng Program và theo dõi trạng thái xử lý.

# 2\. Quy tắc

* Chỉ Tutor owner tạo/chỉnh nội dung Task và gán cho Customer member của Program.  
* Task mới có trạng thái todo (Chưa bắt đầu); enum đích hỗ trợ todo/in\_progress/submitted/completed/cancelled.  
* Task có nội dung, due\_at tùy chọn và file đính kèm đúng purpose/resource policy.  
* Chỉ Program Active tạo Task mới; Paused/Completed/Cancelled không tạo. Task Completed/Cancelled không tự mở lại.  
* Overdue chỉ áp dụng Task còn mở và due\_at \< thời điểm hiện tại; submitted\_late được xác định từ submitted\_at so với due\_at, không đổi khi thời gian trôi.

# 3\. Tiêu chí chấp nhận

* Owner tạo Task cho đúng Customer member; outsider/wrong assignee/owner khác bị từ chối.  
* Task không tự chuyển completed khi Program hoàn thành.  
* Thay due\_at hoặc nội dung tuân theo state policy; file sai resource không attach.  
* \`todo\` được hiển thị là Chưa bắt đầu; overdue không gắn nhầm Task terminal.

# 4\. Chênh lệch/câu hỏi mở

* Actor todo → in\_progress là Customer assignee theo hình Task & Submission trong Business Rules và WF05. Không cần chốt lại actor; quyền start khi Program Paused và các chính sách edit/cancel còn theo D22, chưa suy rộng từ quyền nộp bài.  
* Migration hiện có tasks; API/UI chưa có, versioning/submission linkage cần được triển khai theo Domain target.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
