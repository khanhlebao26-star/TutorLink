# M03-F04 · Tạm dừng Tin dịch vụ

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft

# 1\. Mục tiêu và người dùng

Tutor chủ sở hữu có thể tạm dừng Listing đang hoạt động để Listing ngừng xuất hiện công khai nhưng vẫn giữ lại để chỉnh sửa hoặc kích hoạt lại.

# 2\. Quy tắc nghiệp vụ

* Chỉ chủ sở hữu mới được pause Listing; Admin không thao tác bằng danh tính hoặc quyền của Tutor.  
* Transition hợp lệ: active → inactive. Không dùng pause để xóa hoặc archive Listing.  
* Listing inactive không xuất hiện trong public search, public detail hoặc danh sách Listing trên hồ sơ Tutor công khai.  
* Pause Listing không tự hủy, kết thúc hoặc thay đổi Program/Invitation đã tạo; snapshot và lịch sử được giữ.  
* Listing có thể được kích hoạt lại theo PRD M03-F03 nếu Tutor và catalog còn đủ điều kiện.
* Nếu Listing đã `inactive` vì Admin đặt moderation hold, yêu cầu Tạm dừng tường minh từ Tutor không đổi status nhưng phải ghi lại ý định tạm dừng của Tutor. Khi Admin gỡ hold, hệ thống giữ Listing `inactive`; Tutor tự publish lại. Chỉ lặp lại request pause mà không có quyết định mới thì không tạo audit/notification trùng.

# 3\. Hành vi và API

Tutor chọn Tạm dừng trong quản lý Listing; máy chủ xác nhận quyền sở hữu, cập nhật status và phản hồi trạng thái mới. API đích theo Catalog: `POST /api/v1/me/listings/{listing}/pause`. `PATCH /api/v1/me/listings/{listing}` chỉ là contract đích để sửa nội dung. API hiện đã đăng ký vẫn dùng PATCH với `status=inactive`; frontend phải chuyển sang POST khi route đích được triển khai, kiểm thử và sinh vào OpenAPI Scramble.

# 4\. Tiêu chí chấp nhận

* Chủ sở hữu chuyển active → inactive thành công; public search và direct detail đều không trả nội dung Listing.  
* Tutor khác không thể pause Listing; request sai transition không làm thay đổi trạng thái.  
* Program đã dùng Listing tiếp tục giữ snapshot và không tự chuyển trạng thái.  
* UI phân biệt rõ Tạm dừng với Lưu trữ; có đường thao tác kích hoạt lại Listing inactive.

# 5\. Khoảng cách triển khai và câu hỏi mở

* Chưa có route POST `/pause` trong OpenAPI hiện tại. API hiện hỗ trợ PATCH status=inactive; cần ghi rõ giai đoạn tương thích và thời điểm bỏ nhánh legacy. Cần xác nhận contract lỗi thống nhất cho trạng thái archived/draft và thao tác lặp lại.

# 6\. Nguồn

Product: Roles & Actors, Business Rules, Project Overview. Domain: State Lifecycles, Domain Model, Enum Catalog. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Project Overview](<../../Product Document/Project Overview.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
