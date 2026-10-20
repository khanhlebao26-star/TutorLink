# M01-F06 — Đặt lại mật khẩu bằng token

Trạng thái: Draft    
Actor: người nhận liên kết reset    
API: POST /api/v1/auth/reset-password

# Mục tiêu

Thay mật khẩu sau khi người dùng cung cấp token hợp lệ, email tương ứng và mật khẩu mới được xác nhận.

# Luồng và quy tắc

* Form nhận token từ liên kết email, email tài khoản, mật khẩu mới và xác nhận mật khẩu.  
* Backend kiểm tra token còn hạn, chưa dùng và thuộc email đã nhập.  
* Thành công cập nhật password hash và làm mất hiệu lực token reset đã dùng.  
* Token sai/hết hạn, email không khớp hoặc mật khẩu không hợp lệ không được thay đổi mật khẩu.

# Tiêu chí chấp nhận

* Token hợp lệ đổi được mật khẩu và response xác nhận hoàn tất.  
* Token sai/hết hạn hoặc thông tin không khớp trả lỗi validation/reset theo contract; mật khẩu cũ vẫn dùng được.  
* Mật khẩu mới phải đạt yêu cầu hiện tại (tối thiểu 8 ký tự) và xác nhận khớp.  
* Sau reset, người dùng đăng nhập được bằng mật khẩu mới; mật khẩu cũ bị từ chối.  
* UI không tự điền hoặc log token; lỗi cho phép sửa email/token mà không làm mất dữ liệu mật khẩu chưa gửi.

# Điểm cần xác nhận

PRD/module hiện có chưa chốt việc reset mật khẩu có thu hồi mọi session đang hoạt động trên thiết bị khác hay không. Không mở rộng thành chức năng quản lý thiết bị nếu chưa có quyết định.
