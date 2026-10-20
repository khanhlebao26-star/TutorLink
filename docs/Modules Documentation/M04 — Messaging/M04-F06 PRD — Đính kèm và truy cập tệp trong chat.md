# M04-F06 · Đính kèm và truy cập tệp trong chat

## PRD chức năng · M04 Messaging · Trạng thái: Draft · Phụ thuộc M10 Files

# 1\. Mục tiêu

Participant đính kèm và tải tệp trong Conversation theo cùng File lifecycle, ownership, purpose và resource authorization của M10.

# 2\. Quy tắc nghiệp vụ

* Chỉ gắn file đã upload/complete, thuộc sender và có purpose/resource phù hợp với message attachment.  
* Tệp chat là private; chỉ participant đủ quyền trong Conversation được tải xuống. Admin cần thêm report scope và audit nếu truy cập evidence.  
* Incomplete file, sai owner/purpose, resource scope sai hoặc outsider download đều bị từ chối.  
* Không để public API lộ object key, verification document hoặc metadata riêng tư.  
* MVP không có Program file library hoặc đính kèm trực tiếp vào Session; thao tác này thuộc message chat nếu được policy cho phép.

# 3\. Hành vi và API đích

Luồng file theo M10: POST multipart /files → complete → attach vào resource message → tải qua backend authorization. Đính kèm phải liên kết tệp với Message/Conversation, không cấp URL công khai không kiểm quyền.

# 4\. Tiêu chí chấp nhận

* Sender gắn được file complete đúng owner/purpose vào message hợp lệ.  
* Participant trong thread tải được tệp theo policy; outsider hoặc member của thread khác bị từ chối.  
* File chưa complete hoặc file link sai resource không hiển thị như attachment có thể dùng.  
* Attachment được giữ cùng lịch sử message; quy tắc retention/xóa theo M10.

# 5\. Chênh lệch/câu hỏi mở

* Enum Catalog đã có MessageType.file và API Route Catalog đã mô tả gửi text/file; không còn quyết định về việc có MessageType riêng cho file. File chỉ gắn với Message theo File lifecycle M10; giới hạn MIME/kích thước, số attachment, nhiều file trong một message và xử lý file bị mất vẫn cần chốt.  
* Chốt loại MIME/kích thước allow-list, số attachment mỗi message, nhiều attachment trong cùng message và xử lý khi file mất sau khi gửi.  
* Kiểm tra API authorization dùng membership hiện tại và lịch sử Conversation cancelled theo policy M10.

# 6\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Enum Catalog, Domain Model. Thiết kế: PRD File Lifecycle, WF-03. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
