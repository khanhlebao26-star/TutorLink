# M10-F03 · Gắn File vào resource

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Liên kết File đã complete với resource đúng owner, purpose và authorization policy.

# 2\. Quy tắc

* Chỉ File owner được đề nghị link; resource owner/member/actor được xác nhận theo resource type.  
* File phải complete và `clean` sau scanner thực tế, có purpose tương thích và resource thuộc scope được phép. Với Message, kiểm member active, Conversation active, sender là owner; tạo Message và FileLink nguyên tử. Chat giới hạn một file/message và 5 MiB/file theo BR-21.
* Resource type theo Enum Catalog: message, task, task\_submission, announcement, verification\_document; các liên kết avatar dùng profile attachment flow.  
* Verification documents/private task submissions/message/announcement attachments không public.  
* Link retry idempotent; unique resource/file relation không tạo duplicate.  
* Không xóa File hoặc resource làm mất lịch sử/foreign key; attachment scope nằm ở M04/M07/M08 và verification ở M02. Một Submission hoặc Announcement được gắn tối đa 5 file; mỗi file tối đa 5 MiB với MIME PDF/JPG/PNG/DOC/DOCX. Duplicate file ID trong một request bị từ chối.

# 3\. Tiêu chí chấp nhận

* File complete, đúng owner/purpose/resource được link thành công.  
* Wrong owner, purpose, resource ID, Program membership hoặc incomplete status bị từ chối.  
* Không thể gắn file verification vào Task/chat hoặc file task vào resource khác.  
* Link response không lộ object key hay dữ liệu private.

# 4\. Implementation hiện tại và câu hỏi mở

* POST /files/{id}/links tồn tại nhưng Controller hiện chỉ thực hiện verification\_document; các business resource type khác trả conflict “not available yet”.  
* Upload purpose whitelist hiện chưa gồm message\_attachment, task\_submission, announcement\_attachment. Cần quyết định link semantics per-resource và transaction/lock owner membership.
* BR-20 chốt Workspace: `task_submission` và `announcement_attachment` tối đa 5 MiB/file, tối đa 5 file/resource. BR-21 chốt Chat: một file/message, tối đa 5 MiB/file. Runtime kiểm quota và MIME theo purpose/resource khi link; quyền download vẫn theo resource membership.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
