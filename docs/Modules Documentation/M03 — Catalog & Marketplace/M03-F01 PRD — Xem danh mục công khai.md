# M03-F01 · Xem danh mục công khai

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft

# 1\. Mục tiêu và người dùng

Cho phép người dùng chưa đăng nhập, Customer và Tutor xem cấu trúc danh mục để hiểu và khám phá các dịch vụ trên Marketplace.

# 2\. Phạm vi

## Bao gồm

* Đọc cây Category → Subcategory → Specialization; mỗi Listing được gắn với đúng một Specialization.  
* Chỉ trả về nhánh danh mục đang active; giữ quan hệ cha–con và thứ tự ổn định để giao diện hiển thị.  
* Cung cấp danh sách specialization đang hoạt động để dùng trong form tạo/sửa Listing và bộ lọc tìm kiếm.

## Ngoài phạm vi

* Tạo, sửa, xóa, ẩn hoặc kích hoạt danh mục bằng giao diện Admin; CRUD danh mục được hoãn sau các chức năng cốt lõi.  
* Tự tạo taxonomy mới từ tên người dùng nhập hoặc cho phép Listing thuộc nhiều Specialization.

# 3\. Quy tắc nghiệp vụ

* Danh mục không active không xuất hiện ở trải nghiệm công khai và không được chọn cho Listing mới hoặc khi kích hoạt Listing.  
* Cần ít nhất 3 Category để đáp ứng cổng G1. Dữ liệu khởi tạo phải idempotent và không tạo trùng Languages/English; mapping danh mục mẫu cần được xác nhận theo catalog được duyệt.  
* Việc inactive một nhánh catalog làm các Listing thuộc nhánh đó không còn hiển thị công khai; không tự hủy Program đã tạo.

# 4\. Hành vi và contract

Người dùng mở danh mục hoặc bộ lọc Marketplace. Hệ thống trả cây danh mục công khai; lựa chọn Category/Subcategory giới hạn các lựa chọn cấp dưới. Nếu không có danh mục active, trả danh sách rỗng có cấu trúc hợp lệ.  
API hiện có: GET /api/v1/catalog/categories và GET /api/v1/catalog/specializations. Đây là API đọc công khai; controller hiện lọc các Category, Subcategory và Specialization active.

# 5\. Tiêu chí chấp nhận

* Người chưa đăng nhập gọi được hai endpoint danh mục; không cần tạo phiên Customer/Tutor.  
* Response không chứa Category/Subcategory/Specialization inactive; các node con được gắn đúng node cha.  
* Specialization được trả về có toàn bộ chuỗi cha active và có thể dùng như filter/giá trị form.  
* Giao diện không cung cấp thao tác CRUD catalog trong MVP; trạng thái rỗng và lỗi tải được hiển thị rõ.

# 6\. Khoảng cách triển khai và câu hỏi mở

* Đã có API đọc và seeder trong repo. README M03 cũ nói business API chưa có nên đã lỗi thời; phần ghi chú trạng thái module cần được cập nhật riêng.  
* Chốt danh sách Category/Subcategory/Specialization seed, mapping Languages/English và tiêu chí idempotent với bộ catalog được duyệt.

# 7\. Nguồn

Product: Project Overview, Goals & Non-goals, Business Rules. Domain: Domain Model, Enum Catalog, Domain Overview & Glossary. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Goals & Non-goals](<../../Product Document/Goals & Non-goals.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
