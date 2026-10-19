# M01-F07 — Xem và cập nhật tài khoản của tôi

Trạng thái: Draft    
Actor: người dùng đã đăng nhập    
API: GET /api/v1/me, PATCH /api/v1/me

# Mục tiêu

Cho phép người dùng xem danh tính/role/trạng thái của chính mình và cập nhật các trường cơ bản được cho phép.

# Luồng và quy tắc

* GET /me trả User resource của session hiện tại, có thông tin profile lồng theo role nếu API cung cấp.  
* Allow-list MVP hiện tại của PATCH /me chỉ có full\_name theo API Catalog/OpenAPI. date\_of\_birth là đề xuất đang chờ Product xác nhận ở D04, chưa được gửi qua PATCH; không suy quyền sửa từ cột database.  
* Email, role, status, verification timestamp, permission và ID do server quản lý; client không thể đổi bằng payload.  
* Chỉ truy cập tài khoản của session hiện tại. Private API yêu cầu active account và CSRF cho mutation.  
* Nếu Product cho phép cập nhật ngày sinh trong tương lai, phải đồng bộ allow-list và age gate Customer 15+/Tutor 18+ trước khi mở; hiện chưa đưa date\_of\_birth vào contract PATCH /me. Quy tắc tuổi khi đăng ký vẫn giữ nguyên.

# Tiêu chí chấp nhận

* Session hợp lệ đọc được User hiện tại; không có session trả 401\.  
* PATCH chỉ đổi field được cho phép; field không được phép bị từ chối hoặc không được áp dụng theo contract.  
* Dữ liệu sai định dạng trả 422; mutation thiếu CSRF trả 419 và không đổi dữ liệu.  
* Người dùng không thể đọc/cập nhật tài khoản người khác bằng cách sửa ID hoặc payload.  
* Response không có password/hash, token reset, document riêng tư hoặc permission nội bộ không dành cho User.

# Phụ thuộc

Profile Tutor thuộc M02. Customer Profile là resource riêng (GET/PUT /api/v1/customer/profile, bảng customer\_profiles) nhưng chưa có PRD đầy đủ. Đề xuất bổ sung mục Customer Profile riêng vào M01-F07 để tránh gán nhầm sang M02 Tutor Profile; Product cần chốt owner, required fields/validation, eligibility, avatar và PUT semantics. /me chỉ quản lý User nền, không thay thế API Customer/Tutor Profile.
