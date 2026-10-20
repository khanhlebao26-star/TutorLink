# M03-F09 · Quản lý khung giờ tham khảo

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft · Gate quản lý đã chốt

# 1\. Mục tiêu

Cho Tutor quản lý khung giờ rảnh theo tuần để khách tham khảo trước khi liên hệ. Đây không phải lịch booking và không khóa chỗ.

# 2\. Quy tắc nghiệp vụ

* Khung giờ thuộc Tutor; chỉ chủ sở hữu được đọc/ghi từ vùng quản lý.  
* Tutor có tài khoản active, email đã xác minh, có Tutor Profile và Profile không suspended được đọc/sửa Availability của mình ngay cả khi Profile chưa approved (ví dụ Draft, Submitted hoặc Changes Requested). Profile approved không phải gate quản lý khung giờ.
* Mỗi rule gồm weekday 0–6, start\_time, end\_time và timezone hợp lệ; start\_time phải trước end\_time.  
* Các khung giờ của cùng Tutor không được chồng lấn theo quy tắc backend.  
* Availability chỉ là thông tin tham khảo; không đặt lịch, không bảo đảm Tutor còn trống và không tạo Session/Program.  
* Các rule đã lưu là dữ liệu riêng của Tutor cho đến khi Tutor đủ điều kiện công khai: User active, email verified, Profile approved, giấy tờ bắt buộc còn hiệu lực theo BR-16 và không suspended. Không công khai rule chỉ vì Tutor đã lưu chúng. Khi Profile được duyệt, hiển thị các rule đã lưu theo public field allow-list; không yêu cầu nhập lại.
* Khi User/Profile bị đình chỉ, giữ nguyên các rule đã lưu, chặn API quản lý trong thời gian đình chỉ và ẩn chúng khỏi bề mặt công khai. Sau khi khôi phục, áp lại gate công khai; không xóa hoặc tự tạo khung giờ.

# 3\. Hành vi và API hiện có

API hiện có: GET /api/v1/me/availability-rules và PUT /api/v1/me/availability-rules. PUT thay thế toàn bộ tập weekly rules; implementation giới hạn tối đa 50 rule, kiểm tra weekday 0–6, giờ hợp lệ và overlap.

# 4\. Tiêu chí chấp nhận

* Tutor có thể đọc danh sách rule hiện tại và lưu tập rule hợp lệ.  
* Tutor có Profile chưa approved nhưng không suspended chuẩn bị/sửa rules được; người ngoài chưa thấy chúng. Tutor bị đình chỉ không quản lý được, rules vẫn còn để dùng sau khi khôi phục.
* Giờ kết thúc bằng hoặc trước giờ bắt đầu, timezone sai, weekday ngoài 0–6 hoặc khung bị chồng lấn bị từ chối.  
* PUT có semantics thay thế toàn bộ; response phản ánh đúng tập rule đã lưu, không tạo trùng sau khi gửi lại.  
* Người khác không thể đọc/sửa rules riêng trong vùng /me.  
* Khách xem thông tin này hiểu rõ đó chỉ là thời gian tham khảo, không phải đặt lịch.

# 5\. Khoảng cách triển khai và câu hỏi mở

* Product đã chốt ngày 20/10/2026: không cần Profile approved để quản lý Availability. API hiện kiểm Tutor active và Profile không suspended; chưa kiểm email verified trực tiếp. Theo BR-12, phiên đăng nhập đích chỉ có sau xác minh email, nhưng backend hiện chưa thực thi BR-12 đầy đủ; cần kiểm gate phiên và public visibility khi triển khai.
* Giới hạn 50 rule hiện là contract implementation; xác nhận đây có phải giới hạn sản phẩm/API chính thức không.

# 6\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog. Link nguồn: https\://docs.google.com/document/d/18cMXfeGZnGCFztw7wJSef-sggf\_JJkUguJvhS2dNBa4/edit · https\://docs.google.com/document/d/13ygl2y4lBb4OC6ReaXTBcaJrKOrqjV2pBX871OXLTik/edit · https\://docs.google.com/document/d/11A70iAzkKyAMpk0ZJg0MK\_rN0\_ZOXIx0Pw4XodHjvXs/edit · https\://docs.google.com/document/d/1sI98J0vYTh5EYdsII1s07\_tWOh8Va3dmJn8g-po5vsw/edit · https\://docs.google.com/document/d/1LaqqMBoK4PKDsYv8tA-LOTMAdxxiL4xJruibKiHwOoY/edit
