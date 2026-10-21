# M10-F04 · Xem metadata và tải File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Cho phép đúng owner/member hoặc Admin có permission tải File private qua backend authorization; metadata public/private được allow-list.

# 2\. Quy tắc

* GET /files/{id} chỉ trả metadata đã duyệt, không trả object\_key, storage path, private verification data hoặc signed URL ngoài policy.  
* Download yêu cầu File complete, scan status clean và authorization theo owner/resource membership/purpose/visibility. Nếu object mất sau khi gắn, giữ history và trả attachment unavailable; không lộ storage path.
* Public visibility chỉ áp dụng avatar Tutor gắn vào hồ sơ đủ điều kiện công khai theo D06/M03-F08: File complete, đúng owner/purpose `avatar`, User active/email verified, Profile approved và không suspended. Quyền tải công khai được kiểm lại khi truy cập, không trao object key hoặc URL vĩnh viễn. Avatar Customer thuộc Customer Profile riêng và chưa có đường công khai theo BR-13; verification documents luôn private.
* Chat, Task, Submission, Announcement attachments chỉ truy cập qua đúng resource scope; report evidence cần report permission và scope.  
* Admin role không tự bypass; admin.verification\_documents.download hoặc report/evidence permission được kiểm riêng.

# 3\. Tiêu chí chấp nhận

* Owner tải File hợp lệ của mình; participant đủ quyền tải attachment trong resource liên quan.  
* Với attachment của Conversation, BR-17 cho member active tiếp tục đọc/tải lịch sử đúng membership và policy file khi peer bị đình chỉ hoặc Conversation cancelled; member bị đình chỉ bị từ chối truy cập kể cả khi còn session cũ. Admin vẫn chỉ tải evidence đúng Report theo BR-11.
* Outsider, wrong member, incomplete file hoặc permission thiếu bị từ chối.  
* Public resource không chứa object key hay verification information.  
* Missing storage object trả not-found/error phù hợp mà không lộ filesystem path.

# 4\. Implementation hiện tại và câu hỏi mở

* GET metadata owner-only; download hiện cho owner hoặc reviewer có permission khi file thuộc verification\_document.  
* Các Member/resource policies cho message/task/submission/announcement chưa được triển khai trong FileController.  
* D06 đã chốt điều kiện avatar Tutor công khai; cần triển khai quyền tải và invalidation khi gate mất. Chi tiết URL hết hạn, caching và range/stream vẫn là thiết kế kỹ thuật cần hoàn thiện. Permission Admin report evidence theo BR-11 ở dưới.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit

F03 — Tải file evidence của Report  
Admin chỉ tải file thuộc đúng evidence của Report `open` khi có `admin.reports.view`, assignment hiện tại, quyền/purpose của Report evidence và chính sách file/resource tương ứng. Với Report về message, file phải gắn với message nằm trong cửa sổ BR-11 (message bị báo cáo, tối đa 10 trước/5 sau). Assignment Report không mở quyền tải các file khác trong Conversation, hồ sơ Tutor hoặc Program; không trả object key, storage path hay URL không hết hạn. Khi Report `resolved`/`dismissed` hoặc assignment đổi/thu hồi, quyền tải evidence chấm dứt từ request tiếp theo.
