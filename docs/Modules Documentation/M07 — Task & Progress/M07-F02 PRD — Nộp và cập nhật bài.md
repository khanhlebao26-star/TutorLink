# M07-F02 · Nộp và cập nhật bài

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Customer được giao Task nộp nội dung hoặc tệp và cập nhật bài hiện tại theo version để Tutor phản hồi đúng bản.

# 2\. Quy tắc

* Chỉ Customer member được giao nộp; Tutor không nộp thay và member khác không ghi đè.  
* Mỗi Task/Customer có một submission hiện tại; mỗi lần submit/resubmit tạo version tăng dần.  
* Task todo/in\_progress chuyển sang submitted; Customer assignee cũng được cập nhật bài khi Task đang submitted, tạo Submission version mới và giữ version cũ. Nộp lại sau revision\_requested cũng tạo version mới. Feedback chỉ quyết định version còn hiện hành.
* \`submitted\_late\` dựa vào submitted\_at \> due\_at; due\_at null không bị coi là trễ.  
* Program Paused vẫn cho nộp/cập nhật theo BR-10; Program terminal readonly.  
* Attachment phải complete, thuộc actor, dùng purpose `task_attachment` và được liên kết với `resource_type=task_submission` của đúng Submission.
* Submission phải có `content` không trắng hoặc tối thiểu một tệp; `content` tối đa 10.000 ký tự. Mỗi Submission tối đa 5 tệp, mỗi tệp tối đa 5.000.000 byte theo BR-21/K03.1 và allow-list MIME của M10.
* `expected_submission_version` và Idempotency-Key là concurrency guard của Submission; khi PUT làm đổi Task state, cập nhật Submission, pointer, Task state và `tasks.version` nguyên tử. Mỗi lần state Task đổi tăng `tasks.version` một lần; replay cùng key/payload không tăng version hoặc phát notification lần nữa. Response `submitted_at`/`reviewed_at` dùng RFC 3339 UTC, null được giữ nguyên. Test đếm `audit_logs`, `notification_outbox`, `notifications` quanh request gốc và replay; [bằng chứng chạy PostgreSQL](../../handover/BE-D03-issue-74-test-report.md#auditoutbox-v%C3%A0-side-effect-khi-replay--31102026).
* Request hash tính trên `expected_submission_version`, `content` và file IDs duy nhất được sắp tăng dần; attachment order không có ý nghĩa trong MVP. Thiếu `content` và `null` tương đương `null`; content chỉ gồm whitespace được chuẩn hóa thành `null`, còn khoảng trắng đầu/cuối của content có nội dung được giữ nguyên. Chuẩn hóa CRLF/CR thành LF, không chuẩn hóa Unicode. File IDs trùng bị từ chối. Hash là SHA-256 của JSON canonical theo RFC 8785 sau validation và chuẩn hóa đã nêu.

# 3\. Tiêu chí chấp nhận

* Member đúng assignee nộp bài; version tăng chính xác một lần khi commit.  
* PUT bắt buộc header Idempotency-Key (UUID). Cùng Customer/Task/key và cùng payload đã chuẩn hóa trả lại đúng Submission đã commit, không tạo version, audit hay notification mới; cùng key nhưng payload khác trả 409\.  
* Hai update đồng thời không mất bản mới; version race được phản hồi stale/conflict.  
* Task state, submitted\_at, lateness và file links đồng bộ sau transaction.  
* Outsider, Tutor, wrong Program member và terminal Task không thể nộp.
* Customer được nộp Task đã giao khi Program Paused; stale Submission hoặc sai Task state trả 409, không tạo version một phần.
* Chỉ cho nộp khi có text không trắng hoặc ít nhất một file; quá 10.000 ký tự, hơn 5 file, file lớn hơn 5.000.000 byte hoặc file sai purpose/MIME đều bị từ chối.

# 4\. Chênh lệch/câu hỏi mở

* Đã chốt cho submission: lưu idempotency\_key và SHA-256 của request đã validation/canonicalize trên task\_submissions; phạm vi UNIQUE(task\_id,customer\_id,idempotency\_key), giữ khóa cùng lịch sử version, không TTL khi Submission còn được lưu. Quyền/assignment được kiểm trước replay. Key mới phải gửi expected\_submission\_version hiện tại; stale trả 409 và không tạo version. Task version tăng cùng transaction khi submission làm đổi Task state theo BR-19.
* BE-D03 có schema `idempotency_key`, `request_hash`, unique constraint và API tạo Submission/current pointer/Task state/file links trong một transaction. PostgreSQL backfill được giữ thành test chạy lại được, kiểm tra assignee bị thiếu/đã có và rollback khi dữ liệu legacy lệch; `TaskSubmissionConcurrencyTest` tái lập race submission/retry/review; hash Submission có JCS vector với ký tự Unicode đặc biệt. [Báo cáo/evidence](../../handover/BE-D03-issue-74-test-report.md) phân biệt source test có thể chạy lại với log kết quả của từng lượt. Kiểm chứng dùng dữ liệu test cô lập, chưa chạy trên bản sao production hoặc dữ liệu production-like. D08/BR-23 cho feedback command không thay retention D13 theo vòng đời Submission.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
