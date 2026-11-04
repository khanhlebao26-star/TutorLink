# M10 — Files

## PRD Index · 5 chức năng · Draft

# Mục tiêu

Quản lý vòng đời File từ upload, complete, resource attachment, authorization download đến xóa File chưa liên kết.

# PRD chức năng

[M10-F01 PRD — Upload File](<M10-F01 PRD — Upload File.md>)  
[M10-F02 PRD — Complete và xác thực File](<M10-F02 PRD — Complete và xác thực File.md>)  
[M10-F03 PRD — Gắn File vào resource](<M10-F03 PRD — Gắn File vào resource.md>)  
[M10-F04 PRD — Xem metadata và tải File](<M10-F04 PRD — Xem metadata và tải File.md>)  
[M10-F05 PRD — Xóa File chưa liên kết](<M10-F05 PRD — Xóa File chưa liên kết.md>)

# Quy tắc đích

* Owner, purpose, visibility, completeness và resource permission phải được kiểm tra tại server.  
* Verification private; avatar chỉ public nếu policy cho phép; task/submission/announcement theo resource scope. Theo [K03.1 điều chỉnh 29/10](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.1-contract.md), chat chỉ text, không upload/link message; CV duyệt nội dung theo file/version riêng.
* Không xóa File làm hỏng FK hoặc historical link; outsider download denied.  
* `clean` và `complete=true` không đồng nghĩa antivirus scan hoặc được duyệt nội dung CV.

# Runtime đã kiểm và phần chưa được chứng nhận (K03.14)

| Luồng | Trạng thái thực tế và evidence |
| --- | --- |
| Upload/complete dùng chung | API hỗ trợ private upload/complete; mọi file không rỗng, tối đa 5.000.000 byte; kiểm owner/purpose, object thật, size, extension/MIME/chữ ký/checksum và archive policy. Branch BE-D04-09 thêm purpose `announcement_attachment` và đã kiểm trên `tutorlink_test`; đã áp dụng trên runtime Docker local ở D04-22 và smoke-test ở D04-23; không xác nhận staging/production. [K03.7a](https://github.com/khanhlebao26-star/TutorLink/blob/0bebbb574ec962dde3c02a54703c96b9a05553a4/docs/handover/BE-K03-K03.7a-test-report.md), [K03.7b](https://github.com/khanhlebao26-star/TutorLink/blob/3d9621d42dadb2f267d0df79aaa49c13290bd1e1/docs/handover/BE-K03-K03.7b-test-report.md), regression [K03.13](https://github.com/khanhlebao26-star/TutorLink/blob/8e7cdaf674f7c2a8927a243eac86a5ce1bf0f004/docs/handover/BE-K03-K03.13-test-report.md), [D04-09](../../handover/evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-09). |
| Antivirus | Không service/client scanner tự động; clean/complete chỉ kiểm tra kỹ thuật. Schema và bằng chứng quét thật lịch sử được giữ; không tạo bằng chứng giả. |
| Attachment Conversation | Không mở upload purpose message_attachment hoặc link resource message; gửi text chỉ bỏ qua file_ids hoặc nhận mảng rỗng. [K03.8](https://github.com/khanhlebao26-star/TutorLink/blob/1205c2fad2330a9ac6902fa4398e5312b9da90e7/docs/handover/BE-K03-K03.8-test-report.md). |
| Link qua endpoint dùng chung | FileController chỉ thực hiện verification_document; Announcement generic link vẫn 409. D04 dùng POST/PATCH Announcement để gắn tập file Draft trong transaction; file Published không được thay/unlink. |
| Metadata/download/delete | D04-09 test policy Announcement: Draft owner-only; Published owner/current active member; membership bị thu hồi, outsider, Admin chung và uploader sai resource bị từ chối. File linked DELETE trả 409; unlink Draft giữ bytes. [D04-09](../../handover/evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-09). Các quyền khác giữ phạm vi chứng nhận K03/D03; runtime Docker local đã được cập nhật ở D04-22 và smoke-test ở D04-23; không xác nhận staging/production. |
| Task/Submission | BE-D03 đã dùng purpose chung `task_attachment`, gắn bằng API nghiệp vụ M07 với `resource_type=task` hoặc `task_submission`; [test tập trung 13/13 ban đầu, 15/15 sau bổ sung](https://github.com/khanhlebao26-star/TutorLink/blob/9c9d7468853aadbc2c32928fa55598e945d04d4c/docs/handover/BE-D03-issue-74-test-report.md) gồm Tutor tải file Submission và outsider bị từ chối. Endpoint `/files/{file}/links` dùng chung vẫn chỉ hỗ trợ `verification_document`. BE-K03 chưa chứng nhận tích hợp nghiệp vụ này; BE-D03 chưa chứng nhận UI, race trên PostgreSQL hoặc toàn bộ quyền download. |
| CV Tutor | profile_document không tự tạo quy trình duyệt CV. Pending/approved/rejected theo đúng file/version, reviewer permission/assignment, audit và thông báo cần triển khai/evidence riêng M02/M10; khác xét duyệt giấy tờ xác minh Tutor. |
| Orphan cleanup/storage compensation | Chưa được BE-K03 chứng nhận; job retention, retry/compensation khi object và DB lỗi là công việc riêng. Retention file linked theo resource; không phá version/lịch sử còn tham chiếu. |

Phạm vi bàn giao đã thống nhất tại [K03.14](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.14-handover.md): backend đã kiểm; Echo/UI hoãn K03.12; các phần nghiệp vụ File còn thiếu không được đánh dấu hoàn tất chỉ vì upload/complete đã chạy.

# Nguồn

Product/Domain docs hoàn tất, Security Design và M04/M07/M08/M02 policies; nguồn cụ thể trong từng PRD.

Nguồn Product/Domain đã hoàn tất:  
Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)  
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)
