# M01-F01 — Đăng ký tài khoản

Trạng thái: Draft    
Actor: người chưa có tài khoản    
API: POST /api/v1/auth/register

# Mục tiêu

Cho phép người dùng tạo một tài khoản Customer hoặc Tutor với danh tính và ngày sinh hợp lệ. Admin được tạo nội bộ và không xuất hiện trong lựa chọn đăng ký công khai.

# Luồng và quy tắc

1\. Người dùng nhập họ tên, email, mật khẩu, xác nhận mật khẩu, role và ngày sinh.  
2\. Backend kiểm tra định dạng, email chưa được sử dụng và role thuộc customer hoặc tutor.  
3\. Customer phải từ đủ 15 tuổi; Tutor phải từ đủ 18 tuổi. Mỗi User chỉ có một role cố định.  
4\. Backend tạo tài khoản và phát sự kiện gửi email xác minh. Password không xuất hiện trong response/log.

Request hiện yêu cầu full\_name, email, password, password\_confirmation, role, date\_of\_birth; terms\_version hiện nullable. Mật khẩu tối thiểu 8 ký tự. Thành công trả 201 cùng User resource.

# Tiêu chí chấp nhận

* Đăng ký hợp lệ tạo đúng một Customer hoặc Tutor và phát email xác minh.  
* Từ chối role admin, giá trị role khác, email trùng, ngày sinh không hợp lệ, người chưa đủ tuổi theo role và mật khẩu không khớp.  
* Response không chứa password/hash; lỗi validation gắn đúng trường và không tạo tài khoản một phần.  
* Không tạo role Guest hoặc cho phép đổi role qua payload cập nhật tài khoản.

# Phụ thuộc và điểm cần đồng bộ

* Quy tắc tuổi 15/18 cần được phản ánh trong RegisterRequest, OpenAPI và form đăng ký. Hiện validation backend yêu cầu 18 tuổi cho cả hai role.  
* Product documents mới chưa làm rõ terms\_version có bắt buộc hay không; giữ theo contract hiện tại cho đến khi quyết định được chốt.  
* Controller hiện có thể đăng nhập người dùng ngay sau đăng ký nếu request có session. Cần xác nhận hành vi mong muốn và điều kiện xác minh email trước khi thay đổi luồng này  
* Đã xác nhận ngày 18/10/2026: giữ lỗi validation email đã được sử dụng khi đăng ký. Đây là ngoại lệ của cam kết chống dò tài khoản; SP-05 áp dụng login/quên/reset mật khẩu. Không đổi response đăng ký hoặc thêm nhánh UX mới. Auto-login sau đăng ký vẫn chưa được Product chốt.  
* .
