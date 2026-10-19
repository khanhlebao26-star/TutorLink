# M01-F04 — Phiên bảo mật và đăng xuất

Trạng thái: Draft    
Actor: người dùng ứng dụng; người dùng đang đăng nhập    
API: GET /sanctum/csrf-cookie, POST /api/v1/auth/logout

# Mục tiêu

Khởi tạo cookie CSRF cho SPA và kết thúc phiên hiện tại một cách an toàn.

# Luồng và quy tắc

* Trước mutation cần CSRF, client khởi tạo cookie Sanctum qua /sanctum/csrf-cookie.  
* Browser gửi cookie session và CSRF header theo cấu hình cùng origin/CORS đã chốt.  
* Logout yêu cầu session hợp lệ, hủy session hiện tại và đổi CSRF token; thành công trả 204\.  
* Mutation không có/sai CSRF bị từ chối (419) và không làm thay đổi dữ liệu. Client không tự gửi lại mutation sau lỗi CSRF nếu chưa có hành động retry của người dùng.  
* Private API không được coi việc ẩn nút trên UI là kiểm soát quyền.

# Tiêu chí chấp nhận

* CSRF bootstrap cấp cookie; request mutation hợp lệ hoạt động trong browser đã khởi tạo cookie.  
* Logout kết thúc session; request private tiếp theo trả 401\.  
* Logout khi chưa xác thực trả 401; logout không để lại session có thể tái sử dụng.  
* Mutation thiếu/sai CSRF trả 419 và state không đổi.  
* Không lưu bearer token/session secret vào localStorage hoặc ghi cookie/token vào log.

# Ghi chú

Cookie phải HttpOnly và Secure trong production, dùng HTTPS và cấu hình CORS/stateful domains đúng. Các kiểm tra browser cookie/CSRF thuộc acceptance; suite backend riêng chưa đủ để đóng chức năng.
