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
* Tutor owner được sửa nội dung Listing đang `active` bằng PATCH theo field allow-list hiện có. Server kiểm lại ownership, eligibility, dữ liệu Listing và taxonomy tại request; nếu hợp lệ, nội dung mới hiện công khai ngay, ghi audit thay đổi. Không thêm trạng thái chờ duyệt hay vòng phê duyệt mới cho lần sửa này. Listing không đủ gate vẫn không công khai dù PATCH thành công ở trạng thái khác. Program/Invitation đã hình thành giữ snapshot cũ.

# 5\. Hành vi và API hiện có

API đích theo Catalog: `GET/POST /api/v1/me/listings` và `GET/PATCH /api/v1/me/listings/{listing}` để xem, tạo Draft và sửa nội dung. Publish/reactivate dùng `POST /api/v1/me/listings/{listing}/publish` (M03-F03); pause dùng `POST /api/v1/me/listings/{listing}/pause` (M03-F04). Các route đang đăng ký yêu cầu đăng nhập và EnsureActiveAccount; controller giới hạn truy vấn theo chủ sở hữu và kiểm tra eligibility khi tạo. API hiện tại còn cho đổi status qua PATCH; đó là hành vi legacy cần chuyển đổi, không là quy tắc API đích.

# 6\. Tiêu chí chấp nhận

* Tutor đủ điều kiện tạo Listing; response mới có status draft và không xuất hiện trong public search/detail.  
* Tutor xem được Listing của mình; thay ID sang Listing của Tutor khác trả lỗi không tiết lộ dữ liệu.  
* Request sai enum, giá âm/không hợp lệ, specialization không tồn tại hoặc currency khác VND bị từ chối bằng lỗi có thể xử lý.  
* Cập nhật Listing không làm thay đổi dữ liệu snapshot của Program/Invitation đã hình thành trước đó.  
* Tài khoản không active, email chưa xác minh, profile chưa approved hoặc đang suspended không thể tạo Listing.

# 7\. Khoảng cách triển khai và câu hỏi mở

* API hiện dùng approval\_status=active làm điều kiện tương thích cho profile approved; cần giữ mapping rõ trong khi schema legacy chưa theo target enum.  
* D06 đã chốt sửa Listing `active` có hiệu lực công khai ngay sau validation và audit, không qua xét duyệt lại. Cần triển khai audit, kiểm tra các gate tại mutation và bảo đảm cache/public search/detail/profile phản ánh nội dung mới. Chống ghi đè do cập nhật đồng thời vẫn thuộc D21–D22, không tự coi đã chốt ở D06.

# 8\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules, Goals & Non-goals. Domain: Domain Model, Enum Catalog. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
