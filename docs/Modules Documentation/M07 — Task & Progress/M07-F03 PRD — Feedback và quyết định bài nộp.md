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
* Ngoài `expected_submission_version`, body chỉ gồm `feedback` tối đa 5.000 ký tự. Với `revision_requested`, feedback bắt buộc không trắng và giải thích lý do; với accept, feedback tùy chọn. Response của mutation mới chứa `version` của Task và `submission_version` đã quyết định.
* Review/request-revision yêu cầu UUID `Idempotency-Key`; sau khi kiểm quyền Tutor owner hiện tại, dùng receipt BR-23 theo Tutor + key + method + path + payload chuẩn hóa (`expected_submission_version`, `feedback`). Feedback chuẩn hóa CRLF/CR thành LF, feedback bỏ qua/null/whitespace chỉ tương đương null khi accept; request-revision bắt buộc lý do không trắng. Receipt có hạn 30 ngày, lưu response snapshot cùng mutation. Cùng key/yêu cầu trả lại 200 và snapshot cũ; khác path/payload trả 409; key mới trên version cũ hoặc đã quyết định trả 409; key thiếu/sai UUID trả 422. Test kiểm tra replay không tăng version/receipt và không thêm dòng `audit_logs`, `notification_outbox` hoặc `notifications`; [báo cáo/evidence](../../handover/BE-D03-issue-74-test-report.md#auditoutbox-v%C3%A0-side-effect-khi-replay--31102026).

# 3\. Tiêu chí chấp nhận

* Feedback đúng version cập nhật submission/task nhất quán trong transaction.  
* Stale feedback trong cuộc đua resubmit trả 409, không ảnh hưởng version mới.  
* Mỗi thay đổi Task state tăng `tasks.version` một lần theo BR-19.
* Tutor khác, Customer, outsider hoặc submission không thuộc Program bị từ chối.  
* Task completed/cancelled không nhận feedback hoặc mở lại.

# 4\. Chênh lệch/câu hỏi mở

* BE-D03 có schema version, API feedback và receipt/replay 30 ngày; [test PostgreSQL chạy lại được](../../handover/BE-D03-issue-74-test-report.md) kiểm tra cạnh tranh resubmit/review và side effect khi replay trên database test cô lập. Task-specific audit/outbox event chưa được tích hợp; assertion replay xác nhận không tạo thêm audit/notification rows, không chứng nhận event delivery.
* Payload/độ dài feedback được chốt tại BR-20; feedback của `revision_requested` chính là lý do hiển thị cho Customer.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
