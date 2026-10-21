# M01-F01 — Đăng ký tài khoản

Trạng thái: Draft    
Actor: người chưa có tài khoản    
API: POST /api/v1/auth/register

# Mục tiêu

Cho phép người dùng tạo một tài khoản Customer hoặc Tutor với danh tính và ngày sinh hợp lệ. Admin được tạo nội bộ và không xuất hiện trong lựa chọn đăng ký công khai.

# Luồng và quy tắc

1\. Người dùng nhập họ tên, email, mật khẩu, xác nhận mật khẩu, role và ngày sinh; giao diện hiển thị đúng phiên bản điều khoản áp dụng và yêu cầu thao tác đồng ý chủ động, không đánh dấu sẵn.
2\. Backend kiểm tra định dạng, email chưa được sử dụng, role thuộc customer hoặc tutor, và bằng chứng đồng ý phiên bản điều khoản đang áp dụng. Không tin `terms_version` do client tự khai nếu không khớp phiên bản server công bố.
3\. Customer phải từ đủ 15 tuổi; Tutor phải từ đủ 18 tuổi. Mỗi User chỉ có một role cố định.  
4\. Backend tạo tài khoản với `email_verified_at = null` và phát sự kiện gửi email xác minh; không tạo session đăng nhập. Giao diện báo kiểm tra email, sau khi xác minh thành công mới chuyển đến bước đăng nhập. Password không xuất hiện trong response/log.

API đích yêu cầu full\_name, email, password, password\_confirmation, role, date\_of\_birth, `terms_version` của bản điều khoản đang hiển thị và `terms_accepted=true` từ thao tác đồng ý chủ động. Server kiểm đúng phiên bản hiện hành, lưu `terms_version` cùng `terms_accepted_at` trên User để chứng minh phiên bản nào được chấp nhận và lúc nào; giữ bản nội dung điều khoản bất biến theo version để có thể đối chiếu. `terms_accepted` không cần lưu riêng thay phiên bản/thời điểm. Mật khẩu tối thiểu 8 ký tự. Thành công trả 201 cùng User resource. Runtime hiện vẫn cho `terms_version` null, không kiểm `terms_accepted` và chưa lưu `terms_accepted_at`.

# Tiêu chí chấp nhận

* Đăng ký hợp lệ tạo đúng một Customer hoặc Tutor và phát email xác minh.  
* Từ chối role admin, giá trị role khác, email trùng, ngày sinh không hợp lệ, người chưa đủ tuổi theo role và mật khẩu không khớp.  
* Response không chứa password/hash; lỗi validation gắn đúng trường và không tạo tài khoản một phần.  
* Không tạo role Guest hoặc cho phép đổi role qua payload cập nhật tài khoản.
* Không đồng ý điều khoản hoặc gửi sai phiên bản hiện hành trả 422 và không tạo tài khoản; phiên bản hiển thị, phiên bản lưu và bản nội dung tương ứng phải đối chiếu được.
* Response đăng ký không cấp cookie phiên xác thực; người chưa xác minh email không thể đăng nhập. Tài khoản unverified tồn tại để kiểm tra liên kết xác minh và gửi lại thư, không được dùng API riêng tư.

# Phụ thuộc và điểm cần đồng bộ

* Quy tắc tuổi 15/18 cần được phản ánh trong RegisterRequest, OpenAPI và form đăng ký. Hiện validation backend yêu cầu 18 tuổi cho cả hai role.  
* Product đã chốt điều khoản ngày 20/10/2026 như trên; cần cập nhật form, validation/backend, migration `terms_accepted_at`, nội dung điều khoản theo version và OpenAPI sinh từ code trước khi coi đã triển khai.
* Product đã chốt không tự đăng nhập ngày 20/10/2026. Controller hiện vẫn `Auth::login` khi request có session; phải bỏ hành vi đó, kiểm tra cookie thực tế và cập nhật flow frontend sau xác minh. Việc gửi lại email cho tài khoản chưa đăng nhập theo M01-F02 là phụ thuộc bắt buộc.
* Đã xác nhận ngày 18/10/2026: giữ lỗi validation email đã được sử dụng khi đăng ký. Đây là ngoại lệ của cam kết chống dò tài khoản; SP-05 áp dụng login/quên/reset mật khẩu. Không đổi validation email trùng trong đợt này.
