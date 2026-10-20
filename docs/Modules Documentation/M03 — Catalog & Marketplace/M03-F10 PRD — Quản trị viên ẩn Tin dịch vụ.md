# M03-F10 · Quản trị viên ẩn Tin dịch vụ

## PRD chức năng · M03 Catalog & Marketplace · Draft for contract decision

# 1\. Mục tiêu và actor

Cho Admin được phân công moderation scope ẩn Listing vi phạm chính sách khỏi bề mặt công khai, đồng thời giữ nguyên quyền sở hữu của Tutor và lịch sử liên quan.

# 2\. Yêu cầu từ Product

* Roles & Actors cho phép Admin ẩn Listing khi vi phạm chính sách và chỉ trong moderation scope được giao.  
* Admin không sửa nội dung Listing, không impersonate Tutor và không dùng quyền ẩn để quản lý Listing thay chủ sở hữu.  
* Listing bị ẩn khỏi public search, direct detail và danh sách Listing trên public Tutor profile; không tự hủy Program hoặc snapshot.  
* Thao tác moderation cần có actor, thời điểm, phạm vi và lý do để có thể audit.

# 3\. Mâu thuẫn với target Domain

Enum Catalog hiện chỉ có ListingStatus draft/active/inactive/archived và ListingAction edit/publish/pause/archive. ListingStatusFilter cũng không có hidden. AdminPermission không có permission cho moderation Listing. State Lifecycles không định nghĩa hide/restore transition. Vì vậy không được thêm literal hidden vào target enum như thể đã được phê duyệt.

# 4\. Tình trạng implementation

Repo/runtime hiện chưa có route/API moderation Listing; API Catalog/OpenAPI chỉ giữ reserved paths conditional, chưa có success contract được duyệt. Placeholder này không phải implementation; khi chưa có route, server có thể trả 404/405.

# 5\. Acceptance intent — phụ thuộc quyết định contract

* Admin không có scope nhận lỗi từ chối; Admin có scope chỉ xem/xử lý Listing trong đúng phạm vi.  
* Sau khi ẩn, Listing không xuất hiện trong bất kỳ response public nào kể cả khi truy cập slug trực tiếp.  
* Mọi hành động lưu audit actor, thời điểm và lý do; Tutor vẫn là owner và Program history vẫn được giữ.  
* Tutor không thể tự bỏ trạng thái moderation bằng pause/reactivate hoặc sửa Listing.

# 6\. Quyết định cần chốt trước khi triển khai

* Mô hình visibility/moderation có dùng trạng thái riêng hay cờ moderation tách khỏi ListingStatus? Xác định ảnh hưởng đến filter, public gate và truy vấn.  
* Thêm quyền cụ thể vào AdminPermission và định nghĩa cách cấp moderation scope.  
* Ai được restore/unhide, điều kiện restore và liệu Listing có cần qua lại eligibility gate trước khi public lại.  
* Lý do bắt buộc, audit schema, thông báo Tutor, thời hạn review/appeal và hành vi với Listing đang dùng làm source của Program  
* Phương án đề nghị (chưa chốt)  
* Giữ ListingStatus cho vòng đời do Tutor quản lý; lưu moderation state/record riêng để Tutor không tự bỏ ẩn. Chỉ Admin có permission moderation Listing và scope hợp lệ mới được ẩn/gỡ ẩn; mọi quyết định ghi actor, thời điểm, reason và audit. Public gate gồm Listing active, User active, Profile approved/không suspended, catalog chain active và không có moderation hold. Gỡ moderation chỉ xóa hold, không đổi ListingStatus; archived vẫn terminal. Đây là phương án để Product duyệt, chưa thêm enum, permission, cột DB hoặc success API contract. Còn chốt reason/appeal, notification, thời hạn review, restore gate và cách xử lý Listing đang được Program tham chiếu.  
* .

# 7\. Nguồn

Product: Roles & Actors, Business Rules. Domain: State Lifecycles, Enum Catalog, Domain Model. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
