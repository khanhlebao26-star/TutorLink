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
* Verification private; avatar chỉ public nếu policy cho phép; task/submission/announcement theo resource scope. Theo [K03.1 điều chỉnh 29/10](../../handover/BE-K03-K03.1-contract.md), chat chỉ text, không upload/link message; CV duyệt nội dung theo file/version riêng.
* Không xóa File làm hỏng FK hoặc historical link; outsider download denied.  
* `clean` và `complete=true` không đồng nghĩa antivirus scan hoặc được duyệt nội dung CV.

# Runtime đã kiểm và phần chưa được chứng nhận (K03.14)

| Luồng | Trạng thái thực tế và evidence |
| --- | --- |
| Upload/complete dùng chung | API đã có; mọi upload mới không rỗng, tối đa 5.000.000 byte; owner/purpose, object thật, size, extension/MIME/chữ ký/checksum và archive policy được kiểm. [K03.7a](../../handover/BE-K03-K03.7a-test-report.md), [K03.7b](../../handover/BE-K03-K03.7b-test-report.md), regression [K03.13](../../handover/BE-K03-K03.13-test-report.md). |
| Antivirus | Không service/client scanner tự động; clean/complete chỉ kiểm tra kỹ thuật. Schema và bằng chứng quét thật lịch sử được giữ; không tạo bằng chứng giả. |
| Attachment Conversation | Không mở upload purpose message_attachment hoặc link resource message; gửi text chỉ bỏ qua file_ids hoặc nhận mảng rỗng. [K03.8](../../handover/BE-K03-K03.8-test-report.md). |
| Link qua endpoint dùng chung | FileController hiện chỉ triển khai resource verification_document: file/document/profile đúng owner, purpose, complete và trạng thái cho phép. Có tên trong validation không chứng minh resource đó đã được hỗ trợ. |
| Metadata/download/delete | Đã có các API và kiểm quyền/tham chiếu cho luồng hiện có; race attach/link–delete cho verification document trong [K03.10](../../handover/BE-K03-K03.10-test-report.md). Không suy ra mọi quyền download Task/Submission/CV đã hoàn tất. |
| Task/Submission | BE-D03 đã dùng purpose chung `task_attachment`, gắn bằng API nghiệp vụ M07 với `resource_type=task` hoặc `task_submission`; [test tập trung 13/13 ban đầu, 15/15 sau bổ sung](../../handover/BE-D03-issue-74-test-report.md) gồm Tutor tải file Submission và outsider bị từ chối. Endpoint `/files/{file}/links` dùng chung vẫn chỉ hỗ trợ `verification_document`. BE-K03 chưa chứng nhận tích hợp nghiệp vụ này; BE-D03 chưa chứng nhận UI, race trên PostgreSQL hoặc toàn bộ quyền download. |
| CV Tutor | profile_document không tự tạo quy trình duyệt CV. Pending/approved/rejected theo đúng file/version, reviewer permission/assignment, audit và thông báo cần triển khai/evidence riêng M02/M10; khác xét duyệt giấy tờ xác minh Tutor. |
| Orphan cleanup/storage compensation | Chưa được BE-K03 chứng nhận; job retention, retry/compensation khi object và DB lỗi là công việc riêng. Retention file linked theo resource; không phá version/lịch sử còn tham chiếu. |

Phạm vi bàn giao đã thống nhất tại [K03.14](../../handover/BE-K03-K03.14-handover.md): backend đã kiểm; Echo/UI hoãn K03.12; các phần nghiệp vụ File còn thiếu không được đánh dấu hoàn tất chỉ vì upload/complete đã chạy.

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
