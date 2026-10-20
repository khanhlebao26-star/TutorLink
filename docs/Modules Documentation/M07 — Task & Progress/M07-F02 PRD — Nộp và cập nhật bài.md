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

# 3\. Tiêu chí chấp nhận

* Member đúng assignee nộp bài; version tăng chính xác một lần khi commit.  
* PUT bắt buộc header Idempotency-Key (UUID). Cùng Customer/Task/key và cùng payload đã chuẩn hóa trả lại đúng Submission đã commit, không tạo version, audit hay notification mới; cùng key nhưng payload khác trả 409\.  
* Hai update đồng thời không mất bản mới; version race được phản hồi stale/conflict.  
* Task state, submitted\_at, lateness và file links đồng bộ sau transaction.  
* Outsider, Tutor, wrong Program member và terminal Task không thể nộp.

# 4\. Chênh lệch/câu hỏi mở

* Đã chốt cho submission: lưu idempotency\_key và SHA-256 của request đã validation/canonicalize trên task\_submissions; phạm vi UNIQUE(task\_id,customer\_id,idempotency\_key), giữ khóa cùng lịch sử version, không TTL khi Submission còn được lưu. Quyền/assignment được kiểm trước replay. Key mới phải gửi expected\_submission\_version hiện tại; stale trả 409 và không tạo version.  
* Schema đích cần idempotency\_key, request\_hash và unique constraint tương ứng. Tạo Submission version mới, current pointer, Task state và liên kết file trong một transaction. Phương án lưu key/hash đã chốt; hàm canonicalize cụ thể (field vào hash, omitted/null, thứ tự file, Unicode/newline và expected\_submission\_version), giới hạn content/attachment vẫn cần chốt ở D13 trước phát hành. Không tự xem SHA-256 là đặc tả đầy đủ của cùng payload.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
