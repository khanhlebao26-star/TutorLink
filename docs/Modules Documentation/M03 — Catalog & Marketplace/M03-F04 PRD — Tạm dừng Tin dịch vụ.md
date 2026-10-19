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

# 3\. Hành vi và API hiện có

Tutor chọn Tạm dừng trong quản lý Listing; máy chủ xác nhận quyền sở hữu, cập nhật status và phản hồi trạng thái mới. API hiện có: PATCH /api/v1/me/listings/{listing}; controller cho phép status inactive.

# 4\. Tiêu chí chấp nhận

* Chủ sở hữu chuyển active → inactive thành công; public search và direct detail đều không trả nội dung Listing.  
* Tutor khác không thể pause Listing; request sai transition không làm thay đổi trạng thái.  
* Program đã dùng Listing tiếp tục giữ snapshot và không tự chuyển trạng thái.  
* UI phân biệt rõ Tạm dừng với Lưu trữ; có đường thao tác kích hoạt lại Listing inactive.

# 5\. Khoảng cách triển khai và câu hỏi mở

* API hiện hỗ trợ PATCH status=inactive. Cần xác nhận contract lỗi thống nhất cho trạng thái archived/draft và thao tác lặp lại.

# 6\. Nguồn

Product: Roles & Actors, Business Rules, Project Overview. Domain: State Lifecycles, Domain Model, Enum Catalog. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
