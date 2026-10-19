# M05-F06 · Quản lý lifecycle Program

## PRD chức năng · M05 Program & Invitation · Draft

# 1\. Mục tiêu

Tutor owner quản lý Program sau khi được chấp nhận theo lifecycle đích và không thể mở lại Program terminal.

# 2\. Transition và quyền

* Draft → cancelled.  
* Active ↔ paused; active/paused → completed hoặc cancelled.  
* Completed/cancelled là terminal; chỉ owner được thực hiện ProgramAction phù hợp.  
* Program completed/cancelled giữ membership và lịch sử để đọc; chỉ Program completed đủ điều kiện Review.  
* Paused chặn tạo Session, Task, Announcement và Fee Record mới; vẫn cho đọc, nộp bài, feedback, Progress và xử lý dữ liệu phí sẵn có theo policy.

# 3\. Điều kiện Complete

* Không còn Session ở trạng thái scheduled; lịch có sẵn phải được hoàn tất hoặc hủy theo đúng transition trước khi complete Program.  
* Mọi Task phải ở trạng thái terminal completed hoặc cancelled tường minh. Task submitted chặn hoàn tất cho đến khi Tutor feedback: accept đưa Task sang completed, request revision đưa Task về in\_progress để xử lý tiếp.  
* Task todo hoặc in\_progress không được tự complete; phải đi qua transition hợp lệ để đạt completed hoặc được Tutor owner hủy tường minh.  
* Complete Program không tự chuyển trạng thái Task.

# 4\. Tiêu chí chấp nhận

* Transition hợp lệ chỉ do Tutor owner thực hiện và được server kiểm tra trong transaction.  
* Paused block đúng lệnh tạo mới nhưng cho phép hoạt động tiếp tục được BR-10 cho phép.  
* Program terminal readonly; không resume/reopen.  
* Điều kiện chưa đạt trả 409 code=program\_completion\_blocked với blockers.sessions/blockers.tasks gồm ID, status và required\_action; không đổi Program, không ghi một phần hoặc phát side effect. Sau khi xử lý blocker, client tải lại capability/version trước khi gửi lại.

# 5\. Chênh lệch/câu hỏi mở

* OpenAPI wire customer\_id map với PRD target\_customer\_id; DB programs.customer\_id vẫn là cột đề xuất cần migration trước triển khai. Điều kiện Complete đã được đồng bộ ở mục 3; lock order/version gate vẫn cần chốt trước runtime.  
* Còn chốt UI/UX xác nhận hủy Task todo và cách hiển thị danh sách Session/Task blockers; contract đã định nghĩa nội dung 409\.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: State Lifecycles, Enum Catalog, Domain Model, Domain Overview & Glossary. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edit
