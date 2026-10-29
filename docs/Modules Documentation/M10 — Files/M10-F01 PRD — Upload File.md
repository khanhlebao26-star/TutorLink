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

* API POST /files có; runtime hiện giới hạn avatar 2 MiB JPG/PNG và file khác 10 MiB PDF/JPG/PNG/DOC/DOCX. K03.7b phải đồng bộ về 5.000.000 byte/file; chưa được ghi nhận là đã triển khai. Giới hạn số file/resource vẫn theo module.
* UploadRequest hiện chỉ whitelist avatar/verification\_document/task\_attachment/profile\_document; target Enum còn message\_attachment/task\_submission/announcement\_attachment. Cần đồng bộ theo từng module.  
* BR-21/K03.1 điều chỉnh thay các giới hạn file cũ; [K03.7a](../../handover/BE-K03-K03.7a-test-report.md) đã gỡ scanner. Runtime còn cần triển khai các purpose Task/Submission/CV theo module, kiểm allow-list và email verification theo identity policy; không tự áp dụng quyền/resource mới qua File API chung.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
