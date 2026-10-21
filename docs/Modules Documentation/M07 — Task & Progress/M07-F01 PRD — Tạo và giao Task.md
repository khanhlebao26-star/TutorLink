# M07-F01 · Tạo và giao Task

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Tutor giao công việc cho Customer member trong đúng Program và theo dõi trạng thái xử lý.

# 2\. Quy tắc

* Chỉ Tutor owner tạo/chỉnh nội dung Task và gán cho Customer member của Program.  
* Task mới có trạng thái todo (Chưa bắt đầu); enum đích hỗ trợ todo/in\_progress/submitted/completed/cancelled.  
* Task có nội dung, due\_at tùy chọn và file đính kèm đúng purpose/resource policy. `due_at` là instant, lưu UTC và hiển thị theo `Program.timezone`; API dùng RFC 3339.
* Chỉ Program Active tạo Task mới; Paused/Completed/Cancelled không tạo. Task Completed/Cancelled không tự mở lại.  
* Khi Program Paused, Customer assignee vẫn có thể start Task `todo` đã giao; Customer có thể nộp và Tutor feedback theo M07-F02/F03. Tutor chỉ sửa title/description/due_at/attachment của Task `todo` khi Program Active; không sửa `in_progress` hoặc `submitted`.
* Tutor được hủy Task `todo` hoặc `in_progress` khi Program Active/Paused; Task `submitted` phải được xử lý qua feedback trước khi có thể hủy sau yêu cầu sửa. Lịch sử Submission được giữ.
* Task có integer `version` bắt đầu từ 1. PATCH/start/cancel yêu cầu `expected_version`; mismatch trả 409 và không ghi dữ liệu. Mỗi mutation thành công tăng version một lần; Submission/feedback version guard theo M07-F02/F03 và D13.
* Overdue chỉ áp dụng Task còn mở và due\_at \< thời điểm hiện tại; submitted\_late được xác định từ submitted\_at so với due\_at, không đổi khi thời gian trôi.

# 3\. Tiêu chí chấp nhận

* Owner tạo Task cho đúng Customer member; outsider/wrong assignee/owner khác bị từ chối.  
* Task không tự chuyển completed khi Program hoàn thành.  
* Thay due\_at hoặc nội dung chỉ được khi Task `todo` và Program Active; file sai resource không attach.
* Task `todo` được start khi Active/Paused; `in_progress` không được sửa nội dung; `submitted` không được sửa/hủy trước khi Tutor feedback.
* Stale PATCH/start/cancel trả 409; Task state và version không đổi.
* \`todo\` được hiển thị là Chưa bắt đầu; overdue không gắn nhầm Task terminal.

# 4\. Chênh lệch/câu hỏi mở

* Actor todo → in\_progress là Customer assignee theo hình Task & Submission trong Business Rules và WF05; start khi Paused và edit/cancel đã được chốt tại BR-19.
* Migration hiện có tasks; API/UI và cột `version` chưa có bằng chứng triển khai.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
