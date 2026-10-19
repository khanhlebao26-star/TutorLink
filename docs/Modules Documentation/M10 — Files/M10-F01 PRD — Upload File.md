# M10-F01 · Upload File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Người dùng đã đăng nhập upload một tệp vào storage private và nhận metadata có owner để dùng trong resource được phép.

# 2\. Quy tắc

* Upload qua POST multipart /api/v1/files; máy chủ tạo owner\_id từ phiên hiện tại, object key server-owned, checksum và trạng thái pending.  
* Purpose theo Enum Catalog: avatar, verification\_document, task\_attachment, profile\_document, task\_submission, message\_attachment, announcement\_attachment.  
* File mặc định private. Chỉ avatar có thể public theo policy; verification document luôn private.  
* Extension, MIME thực, size và purpose phải qua allow-list theo contract; client metadata không đủ làm căn cứ an toàn.  
* Yêu cầu xác minh email áp dụng theo purpose/business rules.

# 3\. Tiêu chí chấp nhận

* Upload hợp lệ tạo metadata owner đúng, object key không lộ ra response.  
* Loại tệp, size hoặc purpose không cho phép bị từ chối trước khi gắn resource.  
* User khác không đọc được metadata private của File.  
* Dữ liệu upload fail không để metadata chỉ tới object không tồn tại.

# 4\. Implementation hiện tại và câu hỏi mở

* API POST /files có; avatar hiện giới hạn 2 MiB JPG/PNG, file khác 10 MiB PDF/JPG/PNG/DOC/DOCX.  
* UploadRequest hiện chỉ whitelist avatar/verification\_document/task\_attachment/profile\_document; target Enum còn message\_attachment/task\_submission/announcement\_attachment. Cần đồng bộ theo từng module.  
* Chốt allow-list size/MIME theo purpose và yêu cầu verified email cho từng purpose.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
