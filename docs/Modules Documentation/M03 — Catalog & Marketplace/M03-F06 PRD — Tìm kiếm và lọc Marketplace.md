# M03-F06 · Tìm kiếm và lọc Marketplace

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft

# 1\. Mục tiêu và người dùng

Người chưa đăng nhập và Customer có thể tìm Listing công khai bằng truy vấn, taxonomy, hình thức cung cấp, địa điểm và mức giá tham khảo.

# 2\. Bộ lọc và sắp xếp

* q: tìm theo title/description.  
* category\_id, subcategory\_id, specialization\_id: lọc theo cây catalog.  
* delivery\_mode: online, offline hoặc hybrid; city; min\_price; max\_price; currency=VND; price\_unit=session/hour/course.  
* sort: newest, price\_asc hoặc price\_desc. Khi lọc theo khoảng giá hoặc sort theo giá, yêu cầu price\_unit để so sánh cùng đơn vị.  
* page và per\_page phân trang phía máy chủ; per\_page tối đa 50\. Tổng số kết quả được tính sau tất cả bộ lọc.

# 3\. Quy tắc hiển thị và giá

* Kết quả chỉ gồm Listing active có User active, email verified, Tutor Profile approved và không suspended, cùng Category/Subcategory/Specialization active. Đây là public gate thống nhất với D06 theo xác nhận ngày 18/10/2026.  
* Availability là thông tin tham khảo, không phải chức năng đặt lịch hoặc đảm bảo chỗ trống.  
* Chỉ dùng VND ở MVP; không so sánh giá giữa các PriceUnit khác nhau.  
* Filter whitelist và enum phải theo Enum Catalog; filter không hợp lệ cần trả validation error thay vì âm thầm lọc ở client.

# 4\. Trải nghiệm và API hiện có

API hiện có: GET /api/v1/listings. Tìm kiếm, lọc, sort và pagination chạy ở server. URL trên giao diện cần giữ query khi tải lại/chia sẻ; client không chỉ lọc một page đã tải.

# 5\. Tiêu chí chấp nhận

* Kết hợp q, taxonomy, delivery\_mode, city và giá trả về đúng giao nhau của các điều kiện.  
* Listing không đạt public visibility gate không xuất hiện dù khớp từ khóa hoặc có slug.  
* Thay page hoặc tải lại giữ nguyên query; tổng kết quả và trang dữ liệu tương ứng cùng bộ lọc.  
* min\_price lớn hơn max\_price, enum sai hoặc price filter thiếu price\_unit bị từ chối rõ ràng.  
* Giá 200000 VND round-trip chính xác; không quy đổi hoặc so sánh chéo session/hour/course.  
* Mục tiêu sản phẩm: tìm kiếm dưới 3 giây; mục tiêu API chung p95 dưới 2 giây theo Project Overview.

# 6\. Câu hỏi mở

* Xác nhận whitelist cuối cùng của filter/sort với OpenAPI và UI; quyết định quy tắc tie-break ổn định khi nhiều Listing có cùng giá/thời điểm.

# 7\. Nguồn

Product: Project Overview, Roles & Actors, Goals & Non-goals, Business Rules. Domain: Enum Catalog, State Lifecycles. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
