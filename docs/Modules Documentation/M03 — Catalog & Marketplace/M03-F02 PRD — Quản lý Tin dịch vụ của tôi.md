# M03-F02 · Quản lý Tin dịch vụ của tôi

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft

# 1\. Mục tiêu và người dùng

Cho phép Tutor quản lý danh sách Tin dịch vụ của chính mình: xem, tạo bản nháp và cập nhật nội dung. Quyền sở hữu được kiểm tra ở máy chủ cho mọi thao tác.

# 2\. Tiền điều kiện

* Tài khoản Tutor đang active, email đã xác minh, Tutor Profile approved và không bị suspended khi tạo Listing hoặc khi thực hiện hành động cần đủ điều kiện.  
* Tutor chỉ đọc/sửa Listing do chính mình sở hữu. Customer, người chưa đăng nhập và Tutor khác không được truy cập vùng quản lý.

# 3\. Phạm vi và dữ liệu

* Danh sách và chi tiết Listing của Tutor; tạo Listing mới ở trạng thái draft; sửa nội dung Listing theo trạng thái được phép.  
* Các trường hiện có: specialization, title, description, delivery\_mode, city, district, price\_from\_minor, currency=VND, price\_unit.  
* DeliveryMode: online/offline/hybrid. PriceUnit: session/hour/course. Giá VND là số nguyên theo đơn vị minor đã quy ước; ví dụ 200000 VND phải round-trip chính xác.  
* Việc cập nhật nội dung Listing không thay đổi snapshot đã dùng trong Program hoặc Invitation.

# 4\. Quy tắc nghiệp vụ

* Listing mới luôn là draft và chưa xuất hiện ở tìm kiếm công khai.  
* specialization phải thuộc taxonomy hợp lệ. Listing gắn với đúng một Specialization.  
* Không được sửa Listing của người khác bằng cách thay ID/slug trong URL hoặc request body.  
* Quyền sửa nội dung ở trạng thái active và cách cập nhật tức thời ra trang công khai cần được thể hiện nhất quán trong UI; API hiện cho phép PATCH nội dung ở trạng thái hiện tại. Quy tắc kiểm duyệt/phiên bản nội dung chưa được Product/Domain chốt.

# 5\. Hành vi và API hiện có

Routes: GET/POST /api/v1/me/listings, GET/PATCH /api/v1/me/listings/{listing}. Routes yêu cầu đăng nhập và EnsureActiveAccount; controller giới hạn truy vấn theo chủ sở hữu và kiểm tra eligibility khi tạo.

# 6\. Tiêu chí chấp nhận

* Tutor đủ điều kiện tạo Listing; response mới có status draft và không xuất hiện trong public search/detail.  
* Tutor xem được Listing của mình; thay ID sang Listing của Tutor khác trả lỗi không tiết lộ dữ liệu.  
* Request sai enum, giá âm/không hợp lệ, specialization không tồn tại hoặc currency khác VND bị từ chối bằng lỗi có thể xử lý.  
* Cập nhật Listing không làm thay đổi dữ liệu snapshot của Program/Invitation đã hình thành trước đó.  
* Tài khoản không active, email chưa xác minh, profile chưa approved hoặc đang suspended không thể tạo Listing.

# 7\. Khoảng cách triển khai và câu hỏi mở

* API hiện dùng approval\_status=active làm điều kiện tương thích cho profile approved; cần giữ mapping rõ trong khi schema legacy chưa theo target enum.  
* Xác nhận trạng thái nào được sửa nội dung và liệu sửa Listing active có cần kiểm duyệt, versioning hay thông báo ra công khai.

# 8\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: Domain Model, Enum Catalog. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
