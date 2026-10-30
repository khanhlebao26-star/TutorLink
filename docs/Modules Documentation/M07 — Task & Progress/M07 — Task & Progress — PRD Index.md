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
* Submission mỗi Task/Customer có một bản hiện tại và tăng version mỗi resubmit; feedback gắn version. Content tối đa 10.000 ký tự và cần text không trắng hoặc file; tối đa 5 file, 5.000.000 byte/file theo BR-21/K03.1. `task_attachment` là purpose upload chung; `resource_type=task` hoặc `task_submission` phân biệt liên kết. Submission retry/hash theo BR-20/D13; feedback tối đa 5.000 ký tự, bắt buộc lý do khi request revision.
* Feedback dùng `expected_submission_version` và UUID `Idempotency-Key` với receipt BR-23; stale trả 409\. Overdue chỉ task còn mở; submitted\_late dựa timestamp.
* Paused cho Customer start/nộp Task đã giao, Tutor feedback; Tutor chỉ sửa todo khi Active và hủy todo/in\_progress khi Active/Paused. Terminal readonly; không tự complete Task khi complete Program. Task dùng `version`/`expected_version`; Submission giữ `expected_submission_version` + Idempotency-Key theo BR-19/D13.

# Implementation gaps

* BE-D03 đã đăng ký 11 route Task/Submission, có migration version/backfill và [báo cáo/evidence](../../handover/BE-D03-issue-74-test-report.md). PostgreSQL backfill, concurrency, rollback, quyền file và side effect replay có test chạy lại; log là kết quả của từng lượt, không thay thế source test. Test toàn backend mới nhất ngày 31/10 đạt 477/477 (3.284 assertions); `TaskWorkflowApiTest` đạt 13/13 (231 assertions), gồm regression khoảng trắng/idempotency và RFC 3339 UTC. Các lần chạy dùng database/schema test cô lập, không phải dữ liệu production-like. Log cũ 468/3.121, 475/3.252 và probe ad hoc được giữ làm lịch sử, không dùng làm trạng thái mới nhất.
* Trong phạm vi BE-D03, quyền tải Task/Submission đã được kiểm tra cho Tutor phụ trách, Tutor khác, Customer assignee active, outsider và membership bị hủy. Replay không thêm version, receipt hay dòng audit/outbox/notification. Chưa tích hợp Task-specific audit/outbox/notification event; Issue #74/M07 không yêu cầu event mới, còn việc phát event phụ thuộc contract deep link BE-K02. UI Task/Submission và Progress API/UI M07-F04 chưa có. `/files/{file}/links` dùng chung vẫn chỉ hỗ trợ `verification_document`; M07 gắn file trong transaction API nghiệp vụ.
* Không suy ra toàn bộ M07 hoặc BE-K03 hoàn tất từ route parity/test backend: CV, orphan cleanup/storage compensation, quá hạn và kiểm thử trên dữ liệu production-like chưa được chứng nhận trong BE-D03.

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
