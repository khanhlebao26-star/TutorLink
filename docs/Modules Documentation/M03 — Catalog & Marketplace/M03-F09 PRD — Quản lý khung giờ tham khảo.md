# M03-F09 · Quản lý khung giờ tham khảo

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft · Điều kiện quyền cần chốt

# 1\. Mục tiêu

Cho Tutor quản lý khung giờ rảnh theo tuần để khách tham khảo trước khi liên hệ. Đây không phải lịch booking và không khóa chỗ.

# 2\. Quy tắc nghiệp vụ

* Khung giờ thuộc Tutor; chỉ chủ sở hữu được đọc/ghi từ vùng quản lý.  
* Mỗi rule gồm weekday 0–6, start\_time, end\_time và timezone hợp lệ; start\_time phải trước end\_time.  
* Các khung giờ của cùng Tutor không được chồng lấn theo quy tắc backend.  
* Availability chỉ là thông tin tham khảo; không đặt lịch, không bảo đảm Tutor còn trống và không tạo Session/Program.  
* Public profile/listing chỉ hiển thị availability của Tutor đủ điều kiện công khai.

# 3\. Hành vi và API hiện có

API hiện có: GET /api/v1/me/availability-rules và PUT /api/v1/me/availability-rules. PUT thay thế toàn bộ tập weekly rules; implementation giới hạn tối đa 50 rule, kiểm tra weekday 0–6, giờ hợp lệ và overlap.

# 4\. Tiêu chí chấp nhận

* Tutor có thể đọc danh sách rule hiện tại và lưu tập rule hợp lệ.  
* Giờ kết thúc bằng hoặc trước giờ bắt đầu, timezone sai, weekday ngoài 0–6 hoặc khung bị chồng lấn bị từ chối.  
* PUT có semantics thay thế toàn bộ; response phản ánh đúng tập rule đã lưu, không tạo trùng sau khi gửi lại.  
* Người khác không thể đọc/sửa rules riêng trong vùng /me.  
* Khách xem thông tin này hiểu rõ đó chỉ là thời gian tham khảo, không phải đặt lịch.

# 5\. Khoảng cách triển khai và câu hỏi mở

* Product chốt availability chỉ mang tính tham khảo nhưng chưa mô tả eligibility cụ thể để Tutor quản lý rules. API hiện yêu cầu Tutor active và profile không suspended; chưa kiểm email verified/profile approved. Cần chốt có áp cùng gate M01/M02 hay không.  
* Giới hạn 50 rule hiện là contract implementation; xác nhận đây có phải giới hạn sản phẩm/API chính thức không.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
