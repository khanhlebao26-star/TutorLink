# M07-F02 · Nộp và cập nhật bài

## PRD chức năng · M07 Task & Progress · Draft

# 1\. Mục tiêu

Customer được giao Task nộp nội dung hoặc tệp và cập nhật bài hiện tại theo version để Tutor phản hồi đúng bản.

# 2\. Quy tắc

* Chỉ Customer member được giao nộp; Tutor không nộp thay và member khác không ghi đè.  
* Mỗi Task/Customer có một submission hiện tại; mỗi lần submit/resubmit tạo version tăng dần.  
* Task todo/in\_progress chuyển sang submitted; nộp lại sau revision\_requested tạo version mới và giữ version cũ.  
* \`submitted\_late\` dựa vào submitted\_at \> due\_at; due\_at null không bị coi là trễ.  
* Program Paused vẫn cho nộp/cập nhật theo BR-10; Program terminal readonly.  
* Attachment phải complete, thuộc actor, purpose/resource đúng Task Submission.
* Submission phải có `content` không trắng hoặc tối thiểu một tệp; `content` tối đa 10.000 ký tự. Mỗi Submission tối đa 5 tệp, mỗi tệp tối đa 5 MiB theo allow-list purpose/MIME của M10.
* `expected_submission_version` và Idempotency-Key là concurrency guard của Submission; khi PUT làm đổi Task state, cập nhật Submission, pointer, Task state và `tasks.version` nguyên tử. Mỗi lần state Task đổi tăng `tasks.version` một lần; replay cùng key/payload không tăng version hoặc phát notification lần nữa.
* Request hash tính trên `expected_submission_version`, `content` và file IDs duy nhất được sắp tăng dần; attachment order không có ý nghĩa trong MVP. Thiếu `content` và `null` tương đương `null`; content chỉ gồm whitespace được chuẩn hóa thành `null`; chuẩn hóa CRLF/CR thành LF, không chuẩn hóa Unicode. File IDs trùng bị từ chối. Hash là SHA-256 của JSON canonical theo RFC 8785 sau validation.

# 3\. Tiêu chí chấp nhận

* Member đúng assignee nộp bài; version tăng chính xác một lần khi commit.  
* PUT bắt buộc header Idempotency-Key (UUID). Cùng Customer/Task/key và cùng payload đã chuẩn hóa trả lại đúng Submission đã commit, không tạo version, audit hay notification mới; cùng key nhưng payload khác trả 409\.  
* Hai update đồng thời không mất bản mới; version race được phản hồi stale/conflict.  
* Task state, submitted\_at, lateness và file links đồng bộ sau transaction.  
* Outsider, Tutor, wrong Program member và terminal Task không thể nộp.
* Customer được nộp Task đã giao khi Program Paused; stale Submission hoặc sai Task state trả 409, không tạo version một phần.
* Chỉ cho nộp khi có text không trắng hoặc ít nhất một file; quá 10.000 ký tự, hơn 5 file, file lớn hơn 5 MiB hoặc file sai purpose/MIME đều bị từ chối.

# 4\. Chênh lệch/câu hỏi mở

* Đã chốt cho submission: lưu idempotency\_key và SHA-256 của request đã validation/canonicalize trên task\_submissions; phạm vi UNIQUE(task\_id,customer\_id,idempotency\_key), giữ khóa cùng lịch sử version, không TTL khi Submission còn được lưu. Quyền/assignment được kiểm trước replay. Key mới phải gửi expected\_submission\_version hiện tại; stale trả 409 và không tạo version. Task version tăng cùng transaction khi submission làm đổi Task state theo BR-19.
* Schema đích cần idempotency\_key, request\_hash và unique constraint tương ứng. Tạo Submission version mới, current pointer, Task state và liên kết file trong một transaction. Cấu trúc hash và giới hạn đã chốt tại BR-20; D08 đã chốt ở BR-23 cho command khác và không thay retention D13 theo vòng đời Submission.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
