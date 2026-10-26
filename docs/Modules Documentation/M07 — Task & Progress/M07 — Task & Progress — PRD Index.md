# M07 — Task & Progress

## PRD Index · 4 chức năng · Draft

# Mục tiêu

Quản lý bài tập và submission có version, feedback chống race, cùng tiến độ dạng metric/value/note trong Program.

# PRD chức năng

[M07-F01 PRD — Tạo và giao Task](<M07-F01 PRD — Tạo và giao Task.md>)  
[M07-F02 PRD — Nộp và cập nhật bài](<M07-F02 PRD — Nộp và cập nhật bài.md>)  
[M07-F03 PRD — Feedback và quyết định bài nộp](<M07-F03 PRD — Feedback và quyết định bài nộp.md>)  
[M07-F04 PRD — Ghi và xem Progress](<M07-F04 PRD — Ghi và xem Progress.md>)

# Quy tắc đích

* TaskStatus: todo/in\_progress/submitted/completed/cancelled; todo hiển thị Chưa bắt đầu.  
* Submission mỗi Task/Customer có một bản hiện tại và tăng version mỗi resubmit; feedback gắn version. Content tối đa 10.000 ký tự và cần text không trắng hoặc file; tối đa 5 file, 5 MiB/file. Submission retry/hash theo BR-20/D13; feedback tối đa 5.000 ký tự, bắt buộc lý do khi request revision.
* Feedback dùng expected version; stale trả 409\. Overdue chỉ task còn mở; submitted\_late dựa timestamp.  
* Paused cho Customer start/nộp Task đã giao, Tutor feedback; Tutor chỉ sửa todo khi Active và hủy todo/in\_progress khi Active/Paused. Terminal readonly; không tự complete Task khi complete Program. Task dùng `version`/`expected_version`; Submission giữ `expected_submission_version` + Idempotency-Key theo BR-19/D13.

# Implementation gaps

* Migration có tasks/task\_submissions/progress\_records; Task API, submission version/race policy, Progress API/UI chưa có.  
* Task policy, Progress metric `progress_percent` 0–100, edit/version/audit/no-delete policy, Submission canonicalization, content/file limits và feedback validation đã chốt tại BR-19/BR-20; API/schema/UI chưa đủ triển khai.

F19 — Bằng chứng current-state (18/10/2026): nội dung mục này là baseline của lượt rà soát trước, chưa được kiểm tra lại repository/commit trong lượt Drive-only này. Owner xác minh: chưa gán; ngày/commit kiểm chứng: chưa có; trạng thái: chưa xác minh. Migration tồn tại không tự chứng minh API, transaction hoặc UI đã được triển khai. Phân biệt ghi nhận này với “Quy tắc đích”; mô tả nháp ADR-09/BR-10 nằm trong Business Rules.

# Nguồn

Product/Domain docs hoàn tất và WF-05; xem nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)  
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)
