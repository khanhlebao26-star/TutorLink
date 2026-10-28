# M03 — Catalog & Marketplace

## PRD Index · 10 chức năng · Draft · Cập nhật 17/10/2026

# Mục tiêu module

Xây dựng catalog đọc công khai và luồng Marketplace để Tutor quản lý Listing, người dùng khám phá dịch vụ, xem hồ sơ/chi tiết, và quản lý availability tham khảo.

# Danh sách PRD chức năng

[M03-F01 PRD — Xem danh mục công khai](<M03-F01 PRD — Xem danh mục công khai.md>)  
[M03-F02 PRD — Quản lý Tin dịch vụ của tôi](<M03-F02 PRD — Quản lý Tin dịch vụ của tôi.md>)  
[M03-F03 PRD — Đăng và kích hoạt lại Tin dịch vụ](<M03-F03 PRD — Đăng và kích hoạt lại Tin dịch vụ.md>)  
[M03-F04 PRD — Tạm dừng Tin dịch vụ](<M03-F04 PRD — Tạm dừng Tin dịch vụ.md>)  
[M03-F05 PRD — Lưu trữ Tin dịch vụ](<M03-F05 PRD — Lưu trữ Tin dịch vụ.md>)  
[M03-F06 PRD — Tìm kiếm và lọc Marketplace](<M03-F06 PRD — Tìm kiếm và lọc Marketplace.md>)  
[M03-F07 PRD — Xem chi tiết Tin dịch vụ công khai](<M03-F07 PRD — Xem chi tiết Tin dịch vụ công khai.md>)  
[M03-F08 PRD — Xem hồ sơ Gia sư công khai](<M03-F08 PRD — Xem hồ sơ Gia sư công khai.md>)  
[M03-F09 PRD — Quản lý khung giờ tham khảo](<M03-F09 PRD — Quản lý khung giờ tham khảo.md>)  
[M03-F10 PRD — Quản trị viên ẩn Tin dịch vụ](<M03-F10 PRD — Quản trị viên ẩn Tin dịch vụ.md>)

# Các quyết định nguồn đã khóa

* ListingStatus đích: draft / active / inactive / archived. Archived terminal, không sửa hoặc restore.  
* Listing public khi Listing active \+ User active \+ email verified \+ Tutor Profile approved, không suspended \+ toàn bộ catalog chain active. Email verified áp dụng cho public Profile/Listing theo xác nhận ngày 18/10/2026.  
* Quản lý Listing và gửi Program/Invitation cần Tutor active, email verified, profile approved và không suspended.  
* Category → Subcategory → Specialization; mỗi Listing thuộc một Specialization.  
* Availability chỉ là tham khảo, không đặt lịch. Giá Listing là tham khảo và VND là currency của MVP; không so sánh qua PriceUnit.  
* Tutor active, verified và có Profile không suspended được chuẩn bị/sửa Availability trước khi hồ sơ approved; public chỉ thấy khi Profile đã đủ điều kiện hiển thị. Đình chỉ giữ rules nhưng chặn quản lý/ẩn công khai.
* D06 đã chốt: PublicTutor chỉ trả field allow-list và avatar hợp lệ; Listing nhúng tối đa 10/trang trên cùng GET Tutor, có `listings_page` và `listings_meta`. Slug tạo một lần kèm ID, không đổi theo tên và không cần redirect. Tutor sửa Listing active qua PATCH, revalidate/audit rồi hiện công khai ngay, snapshot cũ bất biến. Khôi phục User/Profile không đổi ListingStatus; active đủ gate tự hiện, inactive vẫn ẩn, archived terminal.
* Admin có thể đọc/khởi tạo catalog trong MVP; giao diện CRUD catalog được hoãn sau tính năng cốt lõi.

# Các điểm lệch tài liệu/code cần theo dõi

* BR-15 đã chốt moderation hold riêng và nguồn inactive: Admin hide active → inactive; restore đủ gate tự active nếu Tutor không tự pause; Tutor tự pause thì tự publish lại. Admin active có `admin.listings.moderate` được xử lý toàn bộ Listing trong MVP, không có assignment riêng. Hide/restore cần reason_code/reason/audit và thông báo Tutor sau thành công; restore thiếu gate khi phải tự active trả 409 giữ hold. Appeal qua hỗ trợ thủ công, không có route mới. Không thêm `hidden` vào ListingStatus; hai route Admin vẫn Dự kiến. Theo BR-18, Invitation có trước hold không accept khi hold còn; sau khi gỡ hold vẫn có thể accept trước expiry nếu Customer/Tutor đủ điều kiện vì offer snapshot đã khóa, không cần Listing active lại.
* Target lifecycle dùng archived; API DELETE hiện ghi status legacy cancelled. Cần chốt API/migration mapping trước khi xem archive hoàn tất.  
* Catalog là chuẩn method/path API đích: publish/reactivate dùng POST `/api/v1/me/listings/{listing}/publish`, pause dùng POST `/api/v1/me/listings/{listing}/pause`. Code/frontend hiện còn chuyển status qua PATCH Listing; phải triển khai route mới và chuyển client trước khi bỏ nhánh legacy.
* README M03 trong repo còn ghi business API chưa có; hiện đã có catalog, public Listing/Tutor, owner Listing và availability routes.  
* Availability API hiện chưa kiểm email verified trực tiếp; BR-12 chặn đăng nhập trước xác minh ở đích nhưng chưa triển khai đầy đủ. Product đã chốt không cần Profile approved cho GET/PUT riêng; không bổ sung approval gate vào backend.
* Public Tutor hiện thiếu avatar, email gate trực tiếp, card allow-list và phân trang 10 Listing; phải đồng bộ File policy, API response, cache/invalidation và Scramble trước khi coi D06 đã triển khai.
* Đình chỉ Profile/User làm Listing ẩn; khôi phục không đổi status, active đủ gate tự hiện. Search, detail và profile phải dùng cùng public gate; moderation hold có luồng restore riêng ở M03-F10.

# Phạm vi code/API đã thấy

* Catalog: GET /api/v1/catalog/categories, GET /api/v1/catalog/specializations.  
* Public: GET /api/v1/listings, GET /api/v1/listings/{slug}, GET /api/v1/tutors/{slug}.  
* Tutor hiện đã đăng ký: GET/POST /api/v1/me/listings, GET/PATCH/DELETE /api/v1/me/listings/{listing}; availability GET/PUT /api/v1/me/availability-rules. Hai POST `/publish` và `/pause` là API đích chưa đăng ký.

Contract đang được mô tả theo Product/Domain docs hoàn tất; ghi chú code chỉ là trạng thái implementation hiện có. Khi có khác biệt, không thay target Product/Domain bằng enum hoặc hành vi legacy.

# Tài liệu nguồn

Project Overview: [Project Overview](<../../Product Document/Project Overview.md>)  
Roles & Actors: [Roles & Actors](<../../Product Document/Roles & Actors.md>)  
Goals & Non-goals: [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>)  
Business Rules: [Business Rules](<../../Domain Documentation/Business Rules.md>)  
State Lifecycles: [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)  
Enum Catalog: [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)  
Domain Model: [Domain Model](<../../Domain Documentation/Domain Model.md>)  
Domain Overview & Glossary: [Domain Overview & Glossary](<../../Domain Documentation/Domain Overview & Glossary.md>)
