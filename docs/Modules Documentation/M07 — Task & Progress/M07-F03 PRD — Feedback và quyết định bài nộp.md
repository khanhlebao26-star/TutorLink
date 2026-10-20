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

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
