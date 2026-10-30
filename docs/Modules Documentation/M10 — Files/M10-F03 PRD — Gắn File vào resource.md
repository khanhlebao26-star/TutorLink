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
* Purpose `task_attachment` dùng chung cho file Task/Submission; FileLink dùng `resource_type=task` hoặc `task_submission`. Không thêm purpose upload riêng cho Submission chỉ để phân biệt resource. Message attachment vẫn bị chặn; Announcement/CV có phạm vi triển khai riêng.
* K03.7b đã đồng bộ frontend/upload/complete tối đa 5.000.000 byte/file; [K03.8](../../handover/BE-K03-K03.8-test-report.md) đã kiểm tra kỹ thuật các đường tạo attachment chat. BE-D03 gắn Task/Submission trong transaction API M07. Test PostgreSQL xác nhận Tutor phụ trách và Customer assignee active tải file Task được; Tutor khác, outsider và assignee không còn membership active bị từ chối. Với Submission, Customer nộp bài và Tutor phụ trách tải được, Tutor khác và outsider bị từ chối; [ma trận và kết quả](../../handover/BE-D03-issue-74-test-report.md#ma-tran-quyen-tai-file-tasksubmission--31102026). FileController `/files/{file}/links` vẫn chỉ hỗ trợ `verification_document`; BE-K03 chưa chứng nhận luồng link Task/Submission qua endpoint chung, quyền CV, orphan cleanup hoặc storage compensation. Owner vẫn có thể tải file của chính họ theo quyền owner chung.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
