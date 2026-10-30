# M10-F03 · Gắn File vào resource

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Liên kết File đã complete với resource đúng owner, purpose và authorization policy.

# 2\. Quy tắc

* Chỉ File owner được đề nghị link; resource owner/member/actor được xác nhận theo resource type.  
* Theo [K03.1 điều chỉnh 29/10](../../handover/BE-K03-K03.1-contract.md), file complete/clean sau kiểm tra kỹ thuật, không yêu cầu antivirus; phải đúng owner/purpose/resource scope. Không cho gắn resource message và không tạo attachment chat. CV cần trạng thái duyệt nội dung của đúng file/version, độc lập complete; Task/Submission không cần duyệt thủ công.
* Resource type theo Enum Catalog: message, task, task\_submission, announcement, verification\_document; các liên kết avatar dùng profile attachment flow.  
* Verification documents/private task submissions/message/announcement attachments không public.  
* Link retry idempotent; unique resource/file relation không tạo duplicate.  
* Không xóa File hoặc resource làm mất lịch sử/foreign key; file ngoài chat theo module M07/M08/M02. Giới hạn mới mục tiêu 5.000.000 byte/file, số file/resource theo module; duplicate file ID bị từ chối. Không xóa enum hoặc link message legacy chỉ để tắt attachment mới.

# 3\. Tiêu chí chấp nhận

* File complete, đúng owner/purpose/resource được link thành công.  
* Wrong owner, purpose, resource ID, Program membership hoặc incomplete status bị từ chối.  
* Không thể gắn file verification vào Task/chat hoặc file task vào resource khác.  
* Link response không lộ object key hay dữ liệu private.

# 4\. Implementation hiện tại và câu hỏi mở

* POST /files/{id}/links tồn tại nhưng Controller hiện chỉ thực hiện verification\_document; các business resource type khác trả conflict “not available yet”.  
* Upload purpose whitelist hiện chưa gồm message\_attachment, task\_submission, announcement\_attachment. Cần quyết định link semantics per-resource và transaction/lock owner membership.
* K03.7b đã đồng bộ frontend/upload/complete tối đa 5.000.000 byte/file; K03.8 vẫn cần kiểm mọi đường chặn attachment chat. Task/Submission và CV approval theo phiên bản chưa được chứng nhận bởi FileController hiện tại. Quota, purpose và quyền download phải được kiểm ở API nghiệp vụ tương ứng; giữ orphan cleanup/storage compensation là công việc riêng.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
