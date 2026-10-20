# M01-F03 — Đăng nhập

Trạng thái: Draft    
Actor: Customer hoặc Tutor đã đăng ký    
API: POST /api/v1/auth/login

# Mục tiêu

Tạo phiên xác thực first-party an toàn để người dùng truy cập các chức năng riêng tư theo role, trạng thái và quyền sở hữu của họ.

# Luồng và quy tắc

* Người dùng gửi email, mật khẩu và tùy chọn remember.  
* Khi thông tin hợp lệ, email đã xác minh và tài khoản được phép hoạt động, backend xác thực credentials, xoay session ID và trả User resource. Tài khoản vừa đăng ký chưa xác minh không được tạo phiên; đăng ký/xác minh email không tự đăng nhập.
* Email/mật khẩu không đúng trả 401 với thông điệp chung; không phân biệt email không tồn tại với mật khẩu sai.  
* Tài khoản bị đình chỉ/không khả dụng không tạo phiên và trả lỗi theo contract.  
* Dùng cookie Sanctum; không trả access token dài hạn để lưu trong localStorage. Login phải có giới hạn tốc độ.

# Tiêu chí chấp nhận

* Credentials đúng tạo session cookie mới; response chỉ có dữ liệu User được phép công khai cho chính người dùng.  
* Credentials sai, thiếu hoặc định dạng sai không tạo session.  
* UserStatus suspended bị từ chối khi đăng nhập; private API vẫn phải kiểm tra điều kiện trạng thái theo chính sách của module sở hữu API, kể cả khi người dùng còn session cũ.  
* Email chưa xác minh bị từ chối đăng nhập và không được cấp cookie phiên; UI hướng dẫn kiểm tra email hoặc gửi lại email xác minh qua M01-F02, không tiết lộ tài khoản tồn tại qua thông báo đăng nhập.
* Nhiều lần thử nhanh bị giới hạn và trả 429 cùng thông tin retry nếu contract cung cấp.  
* Cookie/session/CSRF được xác minh qua browser và network, không chỉ qua unit test.

# Điểm cần xác nhận

Product đã chốt ngày 20/10/2026: chỉ đăng nhập sau khi xác minh email. Runtime hiện vẫn cho tài khoản unverified đăng nhập; cần thêm gate này ở backend và cập nhật kiểm thử browser/API. Route login hiện chưa khai báo rate limit; ngưỡng cụ thể còn cần chốt trong Security NFR.
