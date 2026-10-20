# M01-F02 — Xác minh và gửi lại email

Trạng thái: Draft    
Actor: người dùng đã đăng ký; người nhận liên kết xác minh    
API: GET /api/v1/auth/email/verify/{id}/{hash}, POST /api/v1/auth/email/verification-notification

# Mục tiêu

Xác nhận quyền kiểm soát địa chỉ email bằng liên kết ký số; cho phép người đang đăng nhập yêu cầu gửi lại email khi chưa xác minh.

# Luồng và quy tắc

* Sau đăng ký, hệ thống gửi email chứa liên kết có id, hash email, thời hạn và chữ ký.  
* Mở liên kết không yêu cầu session. Backend chỉ xác minh khi chữ ký, thời hạn, User ID và hash email còn hợp lệ.  
* Liên kết sai, bị sửa hoặc hết hạn trả 403 và không đánh dấu email đã xác minh. Mở lại liên kết hợp lệ đã dùng trước đó có thể trả thành công mà không tạo tác dụng phụ lặp.  
* Gửi lại yêu cầu đăng nhập. Chỉ gửi thư khi địa chỉ chưa xác minh; giới hạn tốc độ và trả thông điệp phù hợp.  
* email\_verified\_at là trạng thái tài khoản. Các thao tác Tutor cần verified email tuân theo điều kiện của module nghiệp vụ tương ứng.

# Tiêu chí chấp nhận

* Liên kết hợp lệ đánh dấu email đã xác minh đúng một lần.  
* Link bị sửa chữ ký/hash, sai User ID hoặc hết hạn không đổi trạng thái.  
* Gửi lại khi chưa xác minh phát một email mới; gửi lại sau khi đã xác minh không gửi thêm thư.  
* Resend từ người chưa đăng nhập trả 401; rate limit trả 429 theo contract.  
* UI thông báo kết quả, cho phép người dùng yêu cầu gửi lại và không hiển thị chữ ký/token trong log.

# API và lưu ý

Route verify hiện dùng middleware signed và throttle:6,1; resend yêu cầu Sanctum session và cũng giới hạn 6/min. Email/provider thất bại không được báo thành công trước khi dispatch được xác nhận.
