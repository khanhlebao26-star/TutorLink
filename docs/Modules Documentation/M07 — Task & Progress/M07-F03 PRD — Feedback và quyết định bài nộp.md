# M07-F03 · Feedback và quyết định bài nộp

## PRD chức năng · M07 Task & Progress · Draft · Race-sensitive

# 1\. Mục tiêu

Tutor owner đánh giá đúng version hiện tại, chấp nhận bài hoặc yêu cầu sửa mà không ghi feedback lên một bản Customer vừa thay thế.

# 2\. Quy tắc

* Chỉ Tutor owner feedback; chỉ version mới nhất của submission được quyết định.  
* Request gồm expected\_submission\_version; version không khớp thì 409 và không ghi feedback.  
* Accept: Submission submitted → accepted và Task submitted → completed.  
* Yêu cầu sửa: Submission submitted → revision\_requested và Task submitted → in\_progress; Customer nộp lại sẽ tạo version mới.  
* Program Paused vẫn cho feedback; Program Completed/Cancelled readonly.  
* Feedback gắn version và được giữ lại qua resubmission; không overwrite lịch sử.
* Payload chỉ gồm `feedback`; tối đa 5.000 ký tự. Với `revision_requested`, feedback bắt buộc không trắng và giải thích lý do; với accept, feedback tùy chọn.
* Review/request-revision phải kiểm `expected_submission_version` và trạng thái hiện tại nguyên tử; mutation thành công cập nhật Task state/version cùng feedback. Replay hợp lệ không tăng version/audit/notification lần nữa.

# 3\. Tiêu chí chấp nhận

* Feedback đúng version cập nhật submission/task nhất quán trong transaction.  
* Stale feedback trong cuộc đua resubmit trả 409, không ảnh hưởng version mới.  
* Mỗi thay đổi Task state tăng `tasks.version` một lần theo BR-19.
* Tutor khác, Customer, outsider hoặc submission không thuộc Program bị từ chối.  
* Task completed/cancelled không nhận feedback hoặc mở lại.

# 4\. Chênh lệch/câu hỏi mở

* Schema version/expected version chưa được triển khai; cần migration additive và kiểm thử race trên PostgreSQL.  
* Payload/độ dài feedback được chốt tại BR-20; feedback của `revision_requested` chính là lý do hiển thị cho Customer.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
