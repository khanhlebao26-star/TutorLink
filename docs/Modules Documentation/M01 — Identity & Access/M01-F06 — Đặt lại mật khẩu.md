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
* Sau khi reset thành công, vô hiệu hóa mọi phiên đăng nhập cũ của tài khoản trên các thiết bị; thiết bị khác phải đăng nhập lại bằng mật khẩu mới. Không tự tạo phiên đăng nhập mới từ reset. Giữ việc xoay `remember_token` để không cho phiên ghi nhớ cũ tái xác thực.
* Token sai/hết hạn, email không khớp hoặc mật khẩu không hợp lệ không được thay đổi mật khẩu.

# Tiêu chí chấp nhận

* Token hợp lệ đổi được mật khẩu và response xác nhận hoàn tất.  
* Token sai/hết hạn hoặc thông tin không khớp trả lỗi validation/reset theo contract; mật khẩu cũ vẫn dùng được.  
* Mật khẩu mới phải đạt yêu cầu hiện tại (tối thiểu 8 ký tự) và xác nhận khớp.  
* Sau reset, người dùng đăng nhập được bằng mật khẩu mới; mật khẩu cũ bị từ chối.  
* Cookie của các phiên có trước lần reset không còn truy cập được API riêng tư; reset thất bại không thu hồi phiên hoặc đổi mật khẩu.
* UI không tự điền hoặc log token; lỗi cho phép sửa email/token mà không làm mất dữ liệu mật khẩu chưa gửi.

# Chênh lệch triển khai

Quy tắc thu hồi phiên đã được Product chốt ngày 20/10/2026. Backend hiện đổi mật khẩu và `remember_token` nhưng chưa vô hiệu hóa các session đang lưu; cần cơ chế thu hồi phù hợp session store đang dùng và kiểm thử bằng hai phiên trình duyệt trước khi coi đã triển khai.
