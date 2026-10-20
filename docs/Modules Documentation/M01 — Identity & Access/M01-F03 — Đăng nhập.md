# M01-F03 — Đăng nhập

Trạng thái: Draft    
Actor: Customer hoặc Tutor đã đăng ký    
API: POST /api/v1/auth/login

# Mục tiêu

Tạo phiên xác thực first-party an toàn để người dùng truy cập các chức năng riêng tư theo role, trạng thái và quyền sở hữu của họ.

# Luồng và quy tắc

* Người dùng gửi email, mật khẩu và tùy chọn remember.  
* Khi thông tin hợp lệ và tài khoản được phép hoạt động, backend xác thực credentials, xoay session ID và trả User resource.  
* Email/mật khẩu không đúng trả 401 với thông điệp chung; không phân biệt email không tồn tại với mật khẩu sai.  
* Tài khoản bị đình chỉ/không khả dụng không tạo phiên và trả lỗi theo contract.  
* Dùng cookie Sanctum; không trả access token dài hạn để lưu trong localStorage. Login phải có giới hạn tốc độ.

# Tiêu chí chấp nhận

* Credentials đúng tạo session cookie mới; response chỉ có dữ liệu User được phép công khai cho chính người dùng.  
* Credentials sai, thiếu hoặc định dạng sai không tạo session.  
* UserStatus suspended bị từ chối khi đăng nhập; private API vẫn phải kiểm tra điều kiện trạng thái theo chính sách của module sở hữu API, kể cả khi người dùng còn session cũ.  
* Nhiều lần thử nhanh bị giới hạn và trả 429 cùng thông tin retry nếu contract cung cấp.  
* Cookie/session/CSRF được xác minh qua browser và network, không chỉ qua unit test.

# Điểm cần xác nhận

Product/Domain Documents yêu cầu email verified cho các hoạt động Tutor được quy định nhưng không nói đây là điều kiện đăng nhập; vì vậy PRD không chặn login chỉ vì email chưa xác minh. Route login hiện chưa khai báo rate limit; hai bộ tài liệu hoàn tất không quy định giới hạn này, cần chốt trong Security NFR.
