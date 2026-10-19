# M01-F05 — Yêu cầu liên kết đặt lại mật khẩu

Trạng thái: Draft    
Actor: người không nhớ mật khẩu    
API: POST /api/v1/auth/forgot-password

# Mục tiêu

Cho phép người dùng yêu cầu liên kết đặt lại mật khẩu mà không tiết lộ email có đăng ký trên TutorLink hay không.

# Luồng và quy tắc

* Người dùng nhập email và gửi yêu cầu.  
* Với email chưa bị giới hạn tốc độ, API trả thông điệp trung tính giống nhau dù email tồn tại hay không.  
* Nếu email hợp lệ và có tài khoản, hệ thống gửi liên kết reset có token và thời hạn; token được giới hạn tốc độ theo cấu hình.  
* Không đưa token/mật khẩu vào log, analytics hoặc response ngoài luồng email.

# Tiêu chí chấp nhận

* Email đã đăng ký nhận thư; email chưa đăng ký nhận response cùng nội dung và status bình thường.  
* Email sai định dạng bị validation từ chối.  
* Yêu cầu lặp trong thời gian throttle trả 429 và Retry-After; UI thông báo thời gian chờ.  
* UI xác nhận trung tính, không nói rõ email tồn tại.

# Lưu ý về chênh lệch hiện tại

API hiện trả 200 trung tính trong trường hợp thường và 429 sau khi reset request bị throttle. Vì throttle hiện dựa vào password broker theo email, cần bảo đảm phản hồi giới hạn tốc độ không trở thành cách dò email đã đăng ký.
