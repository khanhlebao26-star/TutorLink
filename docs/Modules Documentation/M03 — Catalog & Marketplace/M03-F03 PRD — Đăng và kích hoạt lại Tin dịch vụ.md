# M03-F03 · Đăng và kích hoạt lại Tin dịch vụ

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft

# 1\. Mục tiêu

Cho phép Tutor đủ điều kiện đưa Listing ra Marketplace hoặc kích hoạt lại Listing đã tạm dừng.

# 2\. Trạng thái nguồn và đích

* Publish: draft → active.  
* Reactivate: inactive → active.  
* Listing archived là trạng thái kết thúc; không được kích hoạt lại.  
* Yêu cầu publish khi Listing đã active phải đánh giá lại eligibility như quy tắc State Lifecycles; không tạo trạng thái mới.

# 3\. Điều kiện kích hoạt

* UserStatus=active, email đã xác minh, TutorProfileStatus=approved và Tutor không suspended.  
* Specialization, Subcategory và Category gắn với Listing đều active.  
* Dữ liệu Listing hợp lệ theo Enum Catalog: DeliveryMode online/offline/hybrid, PriceUnit session/hour/course, Currency VND.

# 4\. Hành vi và contract

Tutor chọn Đăng hoặc Kích hoạt lại. Máy chủ kiểm tra quyền sở hữu và đánh giá lại toàn bộ điều kiện tại thời điểm gửi yêu cầu; không dựa vào trạng thái nút trên giao diện. Nếu thành công, status là active và Listing đủ điều kiện xuất hiện công khai sau khi qua public visibility gate.  
API hiện có: PATCH /api/v1/me/listings/{listing}; controller cho phép chuyển status sang active và kiểm tra eligibility cùng active catalog chain.

# 5\. Tiêu chí chấp nhận

* Tutor đủ điều kiện publish draft; Listing chuyển sang active.  
* Tutor đủ điều kiện reactivate inactive; Listing chuyển sang active.  
* Unverified, non-active, unapproved, suspended Tutor hoặc Listing thuộc catalog inactive nhận lỗi và Listing không được công khai.  
* Listing archived không thể publish/reactivate; không có đường vòng qua PATCH nội dung.  
* Tạo trực tiếp URL public cho Listing không active không làm lộ nội dung.

# 6\. Khoảng cách triển khai và câu hỏi mở

* API code dùng profile approval\_status=active theo mapping legacy. Cần giữ contract cùng nghĩa với target TutorProfileStatus=approved.  
* Xác nhận thông báo lỗi/field error cụ thể cho từng điều kiện; không đổi status nếu bất kỳ điều kiện nào thất bại.

# 7\. Nguồn

Product: Roles & Actors, Business Rules, Project Overview. Domain: State Lifecycles, Enum Catalog, Domain Model. Link nguồn: https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/1zA9Ft8mWLJmgkbIhfbA1OQx5E5Mue6XqukyIxwBGVJ8/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit
