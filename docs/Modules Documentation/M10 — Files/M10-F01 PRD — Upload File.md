# M10-F01 · Upload File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Người dùng đã đăng nhập upload một tệp vào storage private và nhận metadata có owner để dùng trong resource được phép.

# 2\. Quy tắc

* Upload qua POST multipart /api/v1/files; máy chủ tạo owner\_id từ phiên hiện tại, object key server-owned, checksum và trạng thái pending.  
* Purpose theo Enum Catalog: avatar, verification\_document, task\_attachment, profile\_document, task\_submission, message\_attachment, announcement\_attachment.  
* File mặc định private. Chỉ avatar có thể public theo policy; verification document luôn private.  
* Điều chỉnh 29/10 theo [K03.1](../../handover/BE-K03-K03.1-contract.md): extension, MIME thực/chữ ký, size/checksum và purpose qua allow-list; mọi upload mới mục tiêu 5.000.000 byte/file. Avatar JPG/JPEG/PNG; tài liệu bài tập/bài nộp/CV PDF/JPG/JPEG/PNG/DOC/DOCX. Không mở upload message_attachment, không đính kèm chat. File pending chưa sử dụng; complete chỉ kiểm tra kỹ thuật, không antivirus. Task/Submission không chờ duyệt nội dung; CV duyệt theo phiên bản riêng. Enum cũ được giữ cho schema/legacy, không tự mở tính năng mới.
* Yêu cầu xác minh email áp dụng theo purpose/business rules.

# 3\. Tiêu chí chấp nhận

* Upload hợp lệ tạo metadata owner đúng, object key không lộ ra response.  
* Loại tệp, size hoặc purpose không cho phép bị từ chối trước khi gắn resource.  
* User khác không đọc được metadata private của File.  
* Dữ liệu upload fail không để metadata chỉ tới object không tồn tại.

# 4\. Implementation hiện tại và câu hỏi mở

* API POST /files đã đồng bộ K03.7b: avatar và tài liệu không rỗng, tối đa 5.000.000 byte/file; kiểm số byte thực, không dùng quy đổi KiB làm tròn. Vượt giới hạn trả 422 trước khi tạo object/FileRecord. [Evidence](../../handover/BE-K03-K03.7b-test-report.md). Giới hạn số file/resource vẫn theo module.
* UploadRequest hiện whitelist avatar/verification\_document/task\_attachment/profile\_document. `task_attachment` là purpose upload chung cho Task và Submission; `resource_type` của FileLink phân biệt `task` và `task_submission`. Các enum message\_attachment/task\_submission/announcement\_attachment không tự mở upload mới; chat attachment vẫn bị chặn theo BR-21.
* BR-21/K03.1 điều chỉnh thay các giới hạn file cũ; [K03.7a](../../handover/BE-K03-K03.7a-test-report.md) đã gỡ scanner. BE-D03 có [bằng chứng gắn và tải file Submission qua API M07](../../handover/BE-D03-issue-74-test-report.md); không suy ra toàn bộ quyền download, CV hay API File link dùng chung đã được triển khai.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
