# M07 — Task & Progress

## PRD Index · 4 chức năng · Draft

# Mục tiêu

Quản lý bài tập và submission có version, feedback chống race, cùng tiến độ dạng metric/value/note trong Program.

# PRD chức năng

[M07-F01 PRD — Tạo và giao Task](https://docs.google.com/document/d/1ZmHvf7sdgHVEh_jnB_8KTkljgivKl_PhYhLdhde1MD0/edit)  
[M07-F02 PRD — Nộp và cập nhật bài](https://docs.google.com/document/d/150i_NzJlO_LBbgftBd5vo_uVfVDGYUVq_GtUVEHvm0U/edit)  
[M07-F03 PRD — Feedback và quyết định bài nộp](https://docs.google.com/document/d/1jwNnQmZ24mgBTjjF-cb_Ui2nNTY_oGLlfB7icrnzJjQ/edit)  
[M07-F04 PRD — Ghi và xem Progress](https://docs.google.com/document/d/1GmsES_DznNX-hYzAHfZWVpvt2-O-UFk9bDroyTrkxpg/edit)

# Quy tắc đích

* TaskStatus: todo/in\_progress/submitted/completed/cancelled; todo hiển thị Chưa bắt đầu.  
* Submission mỗi Task/Customer có một bản hiện tại và tăng version mỗi resubmit; feedback gắn version.  
* Feedback dùng expected version; stale trả 409\. Overdue chỉ task còn mở; submitted\_late dựa timestamp.  
* Paused vẫn cho submit/feedback/Progress; terminal readonly; không tự complete Task khi complete Program.

# Implementation gaps

* Migration có tasks/task\_submissions/progress\_records; Task API, submission version/race policy, Progress API/UI chưa có.  
* Chốt mapping todo/in\_progress và row locking/version strategy trước API.

F19 — Bằng chứng current-state (18/10/2026): nội dung mục này là baseline của lượt rà soát trước, chưa được kiểm tra lại repository/commit trong lượt Drive-only này. Owner xác minh: chưa gán; ngày/commit kiểm chứng: chưa có; trạng thái: chưa xác minh. Migration tồn tại không tự chứng minh API, transaction hoặc UI đã được triển khai. Phân biệt ghi nhận này với “Quy tắc đích”; mô tả nháp ADR-09/BR-10 nằm trong Business Rules.

# Nguồn

Product/Domain docs hoàn tất và WF-05; xem nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
