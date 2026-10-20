# M03 — Catalog & Marketplace

## PRD Index · 10 chức năng · Draft · Cập nhật 17/10/2026

# Mục tiêu module

Xây dựng catalog đọc công khai và luồng Marketplace để Tutor quản lý Listing, người dùng khám phá dịch vụ, xem hồ sơ/chi tiết, và quản lý availability tham khảo.

# Danh sách PRD chức năng

[M03-F01 PRD — Xem danh mục công khai](https://docs.google.com/document/d/1tDPsXY1KgTspuNMi3ZglR1x6FsBdsd-hl937zHVj8mw/edit)  
[M03-F02 PRD — Quản lý Tin dịch vụ của tôi](https://docs.google.com/document/d/1moB8sea1sxCX9FFxlaaq_pRrpVeCKixHQ4PjC5825Yc/edit)  
[M03-F03 PRD — Đăng và kích hoạt lại Tin dịch vụ](https://docs.google.com/document/d/1OahSxMUYkfC07bjpatO9V_0xLkNR71PkDcs30VR4jfs/edit)  
[M03-F04 PRD — Tạm dừng Tin dịch vụ](https://docs.google.com/document/d/130ulDoY42mtoH0ZtU8551CCQkpNxTU9hTqCeZt4ZVHQ/edit)  
[M03-F05 PRD — Lưu trữ Tin dịch vụ](https://docs.google.com/document/d/12sOhiSVbczhYwn7A3afb4MFTyZUBAMKZQCQkR2e6eYo/edit)  
[M03-F06 PRD — Tìm kiếm và lọc Marketplace](https://docs.google.com/document/d/1eyBVPoGqGJbDl8Tr_7BTuBU_OITSX2WhVoexA8mArks/edit)  
[M03-F07 PRD — Xem chi tiết Tin dịch vụ công khai](https://docs.google.com/document/d/1-WGzE24F-z-sgMDJrJHDUaIdWykIN1DkhE7hVUQRZbs/edit)  
[M03-F08 PRD — Xem hồ sơ Gia sư công khai](https://docs.google.com/document/d/1vtXyxOuEt3DCYzXM5H6clBccyXRZ7eC9PXxfRfJjwYc/edit)  
[M03-F09 PRD — Quản lý khung giờ tham khảo](https://docs.google.com/document/d/1u6ZoA9-YtBob7CRUENXugiCVMMbBSHpuyjUKbmUHp4M/edit)  
[M03-F10 PRD — Quản trị viên ẩn Tin dịch vụ](https://docs.google.com/document/d/1B_5lc-dPLmJEbvPmKoCHdwVqB-dpi3kBd-TjgrthyPo/edit)

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

Project Overview: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit  
Roles & Actors: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit  
Goals & Non-goals: https\://docs.google.com/document/d/1qjHelbP0CIxOj5NMG-GXGjpI0aLYL8-9b\_bgOs\_UcHw/edit  
Business Rules: https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit  
State Lifecycles: https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit  
Enum Catalog: https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit  
Domain Model: https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit  
Domain Overview & Glossary: https\://docs.google.com/document/d/1RKRVvidOXFZh4KrbxF5csnqj5Od7-SqdtWg7KdoG554/edit
