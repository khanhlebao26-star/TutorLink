# M10-F04 · Xem metadata và tải File

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Cho phép đúng owner/member hoặc Admin có permission tải File private qua backend authorization; metadata public/private được allow-list.

# 2\. Quy tắc

* GET /files/{id} chỉ trả metadata đã duyệt, không trả object\_key, storage path, private verification data hoặc signed URL ngoài policy.  
* Download yêu cầu File complete và authorization theo owner/resource membership/purpose/visibility.  
* Public visibility chỉ áp dụng avatar đã được policy cho phép; verification documents luôn private.  
* Chat, Task, Submission, Announcement attachments chỉ truy cập qua đúng resource scope; report evidence cần report permission và scope.  
* Admin role không tự bypass; admin.verification\_documents.download hoặc report/evidence permission được kiểm riêng.

# 3\. Tiêu chí chấp nhận

* Owner tải File hợp lệ của mình; participant đủ quyền tải attachment trong resource liên quan.  
* Outsider, wrong member, incomplete file hoặc permission thiếu bị từ chối.  
* Public resource không chứa object key hay verification information.  
* Missing storage object trả not-found/error phù hợp mà không lộ filesystem path.

# 4\. Implementation hiện tại và câu hỏi mở

* GET metadata owner-only; download hiện cho owner hoặc reviewer có permission khi file thuộc verification\_document.  
* Các Member/resource policies cho message/task/submission/announcement chưa được triển khai trong FileController.  
* Chốt caching, range/stream policy, avatar public URL và permission Admin report evidence.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit

F03 — Tải file evidence của Report  
Admin chỉ tải file thuộc đúng resource được Report giao khi có admin.reports.view, assignment hiện tại, quyền/purpose của Report evidence và chính sách file/resource tương ứng. Assignment Report không mở quyền tải các file khác trong Conversation, hồ sơ Tutor hoặc Program; không trả object key, storage path hay URL không hết hạn. Truy cập sau khi Report đóng còn chờ policy.
