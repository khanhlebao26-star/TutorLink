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
* Vì đăng ký không tạo phiên và tài khoản chưa xác minh không được đăng nhập, gửi lại email xác minh phải dùng được khi chưa đăng nhập. Client gửi địa chỉ email; server chỉ gửi thư nếu có tài khoản còn unverified, nhưng luôn trả thông điệp trung tính, không để lộ email có tồn tại hay không; áp rate limit.
* email\_verified\_at là trạng thái tài khoản. Các thao tác Tutor cần verified email tuân theo điều kiện của module nghiệp vụ tương ứng.

# Tiêu chí chấp nhận

* Liên kết hợp lệ đánh dấu email đã xác minh đúng một lần.  
* Link bị sửa chữ ký/hash, sai User ID hoặc hết hạn không đổi trạng thái.  
* Gửi lại khi chưa xác minh phát một email mới; gửi lại sau khi đã xác minh không gửi thêm thư.  
* Resend từ người chưa đăng nhập được chấp nhận theo contract công khai; email không tồn tại hoặc đã verified cũng trả phản hồi trung tính và không gửi thư. Rate limit trả 429 theo contract.
* Xác minh thành công không tự tạo session; giao diện chuyển đến trang đăng nhập để người dùng nhập credentials. Tài khoản unverified không được đăng nhập hoặc gọi API riêng tư.
* UI thông báo kết quả, cho phép người dùng yêu cầu gửi lại và không hiển thị chữ ký/token trong log.

# API và lưu ý

Route verify hiện dùng middleware signed và throttle:6,1. Route resend hiện yêu cầu Sanctum session và giới hạn 6/min; cần đổi policy/request của chính method/path này để hỗ trợ người chưa đăng nhập, bảo vệ chống dò tài khoản và chống spam, rồi sinh lại OpenAPI. Email/provider thất bại phải được xử lý vận hành, nhưng public response không tiết lộ trạng thái địa chỉ.
