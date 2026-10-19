# M10-F03 · Gắn File vào resource

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Liên kết File đã complete với resource đúng owner, purpose và authorization policy.

# 2\. Quy tắc

* Chỉ File owner được đề nghị link; resource owner/member/actor được xác nhận theo resource type.  
* File phải complete, có purpose tương thích và resource phải thuộc scope được phép.  
* Resource type theo Enum Catalog: message, task, task\_submission, announcement, verification\_document; các liên kết avatar dùng profile attachment flow.  
* Verification documents/private task submissions/message/announcement attachments không public.  
* Link retry idempotent; unique resource/file relation không tạo duplicate.  
* Không xóa File hoặc resource làm mất lịch sử/foreign key; attachment scope nằm ở M04/M07/M08 và verification ở M02.

# 3\. Tiêu chí chấp nhận

* File complete, đúng owner/purpose/resource được link thành công.  
* Wrong owner, purpose, resource ID, Program membership hoặc incomplete status bị từ chối.  
* Không thể gắn file verification vào Task/chat hoặc file task vào resource khác.  
* Link response không lộ object key hay dữ liệu private.

# 4\. Implementation hiện tại và câu hỏi mở

* POST /files/{id}/links tồn tại nhưng Controller hiện chỉ thực hiện verification\_document; các business resource type khác trả conflict “not available yet”.  
* Upload purpose whitelist hiện chưa gồm message\_attachment, task\_submission, announcement\_attachment. Cần quyết định link semantics per-resource và transaction/lock owner membership.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit
