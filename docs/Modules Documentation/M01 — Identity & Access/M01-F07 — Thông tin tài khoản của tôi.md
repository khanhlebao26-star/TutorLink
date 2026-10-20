# M01-F07 — Xem và cập nhật tài khoản của tôi

Trạng thái: Draft    
Actor: người dùng đã đăng nhập    
API: GET /api/v1/me, PATCH /api/v1/me; GET/PUT /api/v1/customer/profile (Dự kiến)

# Mục tiêu

Cho phép người dùng xem danh tính/role/trạng thái của chính mình và cập nhật các trường cơ bản được cho phép.

# Luồng và quy tắc

* GET /me trả User resource của session hiện tại; Customer Profile có resource và API riêng ở mục dưới.
* Product đã chốt: ngày sinh nhập khi đăng ký; sau đăng ký, chủ tài khoản chỉ sửa ngày sinh từ màn hình hồ sơ của mình. Màn hình này gọi `PATCH /api/v1/me` cho trường User `date_of_birth`, không ghi ngày sinh vào Customer Profile hay Tutor Profile riêng. API vẫn kiểm quyền chủ tài khoản ở server; giới hạn UI không thay thế kiểm quyền API. `full_name` cũng được sửa tại đây. Backend/OpenAPI hiện đã nhận `date_of_birth`, nhưng backend áp ngưỡng đủ 18 tuổi cho cả Customer và Tutor, cần đổi theo role.
* Email, role, status, verification timestamp, permission và ID do server quản lý; client không thể đổi bằng payload.  
* Chỉ truy cập tài khoản của session hiện tại. Private API yêu cầu active account và CSRF cho mutation.  
* Khi sửa ngày sinh, backend kiểm định dạng và tính tuổi tại ngày request theo role cố định: Customer từ đủ 15 tuổi, Tutor từ đủ 18 tuổi; không cho sửa thành ngày sinh khiến tài khoản dưới tuổi tối thiểu. Quy tắc áp dụng giống đăng ký, kể cả khi gọi API trực tiếp. Cần đồng bộ backend, form hồ sơ, kiểm thử và OpenAPI sinh lại sau thay đổi code.

# Tiêu chí chấp nhận

* Session hợp lệ đọc được User hiện tại; không có session trả 401\.  
* PATCH chỉ đổi field được cho phép; field không được phép bị từ chối hoặc không được áp dụng theo contract.  
* Dữ liệu sai định dạng trả 422; mutation thiếu CSRF trả 419 và không đổi dữ liệu.  
* Người dùng không thể đọc/cập nhật tài khoản người khác bằng cách sửa ID hoặc payload.  
* Response không có password/hash, token reset, document riêng tư hoặc permission nội bộ không dành cho User.

# Phụ thuộc

Profile Tutor thuộc M02. Customer Profile thuộc M01-F07 và bảng `customer_profiles`; `/me` chỉ quản lý User nền, không thay thế API Customer/Tutor Profile.

# Customer Profile — D09 đã chốt ngày 20/10/2026

* Chỉ Customer đã đăng nhập với UserStatus `active` được GET/PUT Customer Profile của chính mình; owner lấy từ session, không nhận `user_id` từ client. Mutation dùng cookie phiên và CSRF. Tutor/Admin không dùng hai route này.
* Không bắt buộc điền Customer Profile để sử dụng tài khoản. `city`, `district`, `bio`, `avatar_file_id` đều tùy chọn và nullable. `full_name` và `date_of_birth` thuộc User, chỉ sửa qua `PATCH /api/v1/me`, không sao chép vào Customer Profile.
* `GET /api/v1/customer/profile` trả `profile: null` nếu chưa có row; không tự tạo row khi đọc. Nếu đã có, trả bốn field trên cùng định danh profile của owner; không đưa dữ liệu riêng của người khác vào response.
* `PUT /api/v1/customer/profile` tạo row ở lần lưu đầu và cập nhật những field được gửi ở các lần sau. Field vắng mặt giữ giá trị hiện có; gửi `null` xóa field nullable tương ứng. Dù dùng method PUT, route này có ngữ nghĩa upsert từng field; client không được hiểu omission là xóa. Khi chưa có row, field vắng mặt có giá trị `null`. Payload chỉ chứa các field cho phép; server từ chối field khác. `city`/`district` là chuỗi tối đa 120 ký tự, `bio` là chuỗi tối đa 2.000 ký tự; giá trị không đúng kiểu hoặc quá dài trả 422. Payload rỗng không tạo profile mới.
* Avatar đi qua module File: Customer upload với purpose `avatar`, hoàn tất tệp, rồi gửi `avatar_file_id` trong PUT. Server chỉ nhận File complete, đúng owner và purpose. Gửi `null` gỡ avatar; thay hoặc gỡ chỉ đổi tham chiếu trên profile, không tự xóa File cũ. Việc xóa File không còn liên kết phải theo M10-F05 và chính sách retention; File còn liên kết không được hard-delete. Customer Profile không tạo đường truy cập avatar công khai mới; quyền tải vẫn theo M10.
* Hai route Customer Profile vẫn ở trạng thái **Dự kiến** trong Catalog và chưa có trong OpenAPI Scramble hiện tại. Backend, DTO, kiểm thử và OpenAPI sinh lại phải theo các quy tắc trên trước khi coi là đã triển khai.
