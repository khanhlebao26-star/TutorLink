# M04-F06 · Đính kèm và truy cập tệp trong chat

## PRD chức năng · M04 Messaging · Trạng thái: Draft · Phụ thuộc M10 Files

# 1\. Mục tiêu

Participant đính kèm và tải tệp trong Conversation theo cùng File lifecycle, ownership, purpose và resource authorization của M10.

# 2\. Quy tắc nghiệp vụ

* Chỉ gắn file đã upload/complete, thuộc sender và có purpose/resource phù hợp với message attachment.  
* Tệp chat là private; chỉ participant đủ quyền trong Conversation được tải xuống. Admin cần thêm report scope và audit nếu truy cập evidence.  
* Khi peer bị đình chỉ, participant còn active vẫn được đọc lịch sử và tải tệp đã gắn nếu qua chính sách File/resource M10; người bị đình chỉ bị từ chối truy cập kể cả khi còn session cũ. Khôi phục account cho phép đọc lại theo membership. BR-17 không cấp Admin quyền tải file ngoài evidence BR-11.
* Incomplete, chưa scan clean, sai owner/purpose, resource scope sai hoặc outsider download đều bị từ chối. Gửi file và tạo Message/FileLink phải cùng một giao dịch; lỗi giữa chừng không để lại message thiếu file hoặc link mồ côi.
* Không để public API lộ object key, verification document hoặc metadata riêng tư.  
* MVP không có Program file library hoặc đính kèm trực tiếp vào Session; thao tác này thuộc message chat theo giới hạn BR-21.

# 3\. Hành vi và API đích

Luồng file theo M10: POST multipart /files → complete → attach vào resource message → tải qua backend authorization. Đính kèm phải liên kết tệp với Message/Conversation, không cấp URL công khai không kiểm quyền.

# 4\. Tiêu chí chấp nhận

* Sender gắn được file complete đúng owner/purpose vào message hợp lệ.  
* Participant trong thread tải được tệp theo policy; outsider hoặc member của thread khác bị từ chối.  
* File chưa complete hoặc file link sai resource không hiển thị như attachment có thể dùng.  
* Attachment được giữ cùng lịch sử message; quy tắc retention/xóa theo M10.

# 5\. Chênh lệch/câu hỏi mở

* BR-21 đã chốt một tệp mỗi message, giới hạn 5 MiB, MIME allow-list, malware scan trước khi sử dụng, atomic link, retention orphan và trạng thái unavailable nếu object mất. Runtime File scan và authorization theo resource vẫn chưa triển khai.
* Kiểm tra quyền download phải dùng membership hiện tại và cho member active đọc lịch sử Conversation cancelled theo BR-17/BR-21.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, Domain Model. Thiết kế: PRD File Lifecycle, WF-03. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>)
