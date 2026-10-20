# M03-F07 · Xem chi tiết Tin dịch vụ công khai

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft

# 1\. Mục tiêu

Cho người chưa đăng nhập hoặc Customer mở trang chi tiết Listing bằng slug, xem mức giá tham khảo và thông tin Tutor cần thiết để quyết định bước tiếp theo.

# 2\. Quy tắc hiển thị

* Trang chi tiết dùng cùng visibility gate với public search: Listing active, User active, email verified, Tutor Profile approved, không suspended và toàn bộ taxonomy active.  
* Nếu Listing draft/inactive/archived, hoặc bất kỳ điều kiện visibility nào không còn đúng, response không tiết lộ nội dung Listing.  
* Giá là giá tham khảo của Listing, không phải báo giá/Program đã được hai bên chấp thuận.  
* Availability, nếu hiển thị, chỉ để tham khảo; không tạo booking.  
* Không trả email, giấy tờ xác minh, trạng thái xác minh nội bộ hoặc dữ liệu riêng tư của Tutor.

# 3\. Hành vi và API hiện có

Public route hiện có: GET /api/v1/listings/{slug}. Tài nguyên Listing công khai được lấy qua visiblePublic scope. Trang chi tiết có thể điều hướng đến hồ sơ Tutor công khai theo public\_slug.

# 4\. Tiêu chí chấp nhận

* Slug hợp lệ của Listing đủ điều kiện trả nội dung công khai và dữ liệu liên quan tối thiểu.  
* Slug không tồn tại hoặc Listing không đủ điều kiện không làm lộ dữ liệu; phản hồi dùng hành vi not-found thống nhất.  
* Thay đổi account/profile/catalog sang trạng thái không đủ điều kiện làm direct detail ngừng công khai như search.  
* Response không chứa dữ liệu riêng tư hoặc tài liệu xác minh.  
* Thông tin giá giữ đúng currency và price\_unit; không diễn giải thành lịch hẹn hay cam kết thanh toán.

# 5\. Khoảng cách triển khai và câu hỏi mở

* API public detail đã tồn tại. Cần đối chiếu OpenAPI và giao diện để xác nhận payload đủ trường và không vượt public allowlist.  
* Public Tutor slug/avatar và payload chính thức chưa được Product chốt; xem M03-F08.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
