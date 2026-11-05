# M10-F01 · Upload File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Người dùng đã đăng nhập upload một tệp vào storage private và nhận metadata có owner để dùng trong resource được phép.

# 2\. Quy tắc

* Upload qua POST multipart /api/v1/files; máy chủ tạo owner\_id từ phiên hiện tại, object key server-owned, checksum và trạng thái pending.  
* Purpose theo Enum Catalog: avatar, verification\_document, task\_attachment, profile\_document, task\_submission, message\_attachment, announcement\_attachment.  
* File mặc định private. Chỉ avatar có thể public theo policy; verification document luôn private.  
* Điều chỉnh 29/10 theo [K03.1](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.1-contract.md): extension, MIME thực/chữ ký, size/checksum và purpose qua allow-list; mọi upload mới mục tiêu 5.000.000 byte/file. Avatar JPG/JPEG/PNG; tài liệu bài tập/bài nộp/CV/Announcement PDF/JPG/JPEG/PNG/DOC/DOCX. `announcement_attachment` dùng pipeline private hiện có; complete chỉ kiểm tra kỹ thuật, không antivirus. Không mở upload message_attachment, không đính kèm chat. Task/Submission không chờ duyệt nội dung; CV duyệt theo phiên bản riêng.
* Yêu cầu xác minh email áp dụng theo purpose/business rules.

# 3\. Tiêu chí chấp nhận

* Upload hợp lệ tạo metadata owner đúng, object key không lộ ra response.  
* Loại tệp, size hoặc purpose không cho phép bị từ chối trước khi gắn resource.  
* User khác không đọc được metadata private của File.  
* Dữ liệu upload fail không để metadata chỉ tới object không tồn tại.

# 4\. Implementation hiện tại và câu hỏi mở

* API POST /files đã đồng bộ K03.7b: avatar và tài liệu không rỗng, tối đa 5.000.000 byte/file; kiểm số byte thực, không dùng quy đổi KiB làm tròn. Vượt giới hạn trả 422 trước khi tạo object/FileRecord. [Evidence](https://github.com/khanhlebao26-star/TutorLink/blob/3d9621d42dadb2f267d0df79aaa49c13290bd1e1/docs/handover/BE-K03-K03.7b-test-report.md). Giới hạn số file/resource vẫn theo module.
* Branch BE-D04 thêm `announcement_attachment` vào UploadRequest và CompleteFile; đã kiểm upload/complete trên `tutorlink_test` trong [D04-09](../../handover/evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-09). Sau đó đã áp dụng trên runtime Docker local ở D04-22 và smoke-test ở D04-23; không xác nhận staging/production. `task_attachment` vẫn dùng chung cho Task và Submission; `resource_type` của FileLink phân biệt hai resource. Message attachment vẫn bị chặn theo BR-21.
* BR-21/K03.1 điều chỉnh thay các giới hạn file cũ; [K03.7a](https://github.com/khanhlebao26-star/TutorLink/blob/0bebbb574ec962dde3c02a54703c96b9a05553a4/docs/handover/BE-K03-K03.7a-test-report.md) đã gỡ scanner. BE-D03 có [bằng chứng gắn và tải file Submission qua API M07](https://github.com/khanhlebao26-star/TutorLink/blob/9c9d7468853aadbc2c32928fa55598e945d04d4c/docs/handover/BE-D03-issue-74-test-report.md); không suy ra toàn bộ quyền download, CV hay API File link dùng chung đã được triển khai.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
