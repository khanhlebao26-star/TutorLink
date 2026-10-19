# M02-F03 — Gửi hồ sơ xét duyệt và gửi lại sau chỉnh sửa

## Trạng thái: Draft  |  Actor: Tutor  |  API hiện tại: POST /api/v1/tutor/profile/submit

# Mục tiêu

Chuyển hồ sơ Tutor từ draft sang hàng đợi Admin, hoặc gửi lại nội dung đã được yêu cầu chỉnh sửa.

# Quy tắc sản phẩm

* Transition hợp lệ: draft → submitted và changes\_requested → submitted. Các trạng thái khác không được gửi lại theo State Lifecycles.  
* Chỉ Tutor sở hữu hồ sơ mới có thể gửi. Backend kiểm tra trạng thái và mọi điều kiện trước khi cập nhật để không nhận hồ sơ dở dang.  
* Khi gửi thành công, hồ sơ xuất hiện trong phạm vi xét duyệt của Admin; quyết định và thay đổi trạng thái phải được ghi nhận theo audit policy.  
* Không suy diễn danh sách trường/tệp bắt buộc từ mỗi timestamp submitted\_at. Checklist phải xác định rõ trong product/API contract.

# Luồng

* Tutor hoàn thiện draft và chọn Gửi xét duyệt.  
* Backend kiểm tra quyền sở hữu, trạng thái cho phép gửi, profile data, avatar và giấy tờ theo checklist đã chốt.  
* Nếu thiếu điều kiện, API trả lỗi phù hợp và giữ nguyên trạng thái/dữ liệu trước đó.  
* Nếu hồ sơ đang changes\_requested, Tutor sửa nội dung được yêu cầu và gửi lại; trạng thái trở lại submitted để Admin xem xét.

# Tiêu chí chấp nhận

* Draft hợp lệ chuyển sang submitted; changes\_requested hợp lệ chuyển lại submitted.  
* Không cho gửi hồ sơ khác chủ sở hữu, hồ sơ suspended, hoặc trạng thái không có transition gửi.  
* Thiếu điều kiện không tạo hồ sơ submitted hoặc một phần trạng thái/audit.  
* Request lặp không tạo lần gửi/decision trùng và không ghi đè dữ liệu mới hơn.

# Chênh lệch runtime và điểm cần chốt

* API hiện yêu cầu Tutor có UserStatus active và email verified khi submit. Product/Domain Documents nêu email verified cho một số hành động Tutor và yêu cầu active cho thao tác cần tài khoản hoạt động, nhưng chưa nói rõ hai điều kiện này áp dụng cho hành động submit hồ sơ. Cần xác nhận gate sản phẩm.  
* Checklist runtime hiện yêu cầu headline, bio, specialization active và avatar thuộc Tutor/đúng purpose/complete/có object trong storage. Code có kiểm tra giấy tờ xác minh nhưng trường validation tương ứng là nullable nên hiện không bắt buộc; mô tả OpenAPI vừa ghi optional trong local testing vừa ghi ít nhất một giấy tờ bắt buộc. Bộ tài liệu hoàn tất chưa định nghĩa checklist; xác nhận trường bắt buộc và điều kiện file trước khi chốt AC.  
* API hiện biểu diễn submitted bằng approval\_status=pending cùng submitted\_at; domain target dùng TutorProfileStatus=submitted. Đồng bộ OpenAPI, UI và migration theo enum đích.

# Nguồn và phụ thuộc

Business Rules; Roles & Actors; State Lifecycles; Enum Catalog; Domain Model. Phụ thuộc M02-F01 và M02-F02.

F03 — Tác động assignment khi gửi/gửi lại  
Hồ sơ mới gửi có thể vào hàng đợi chưa phân công (assigned\_admin\_id NULL) cho coordinator có admin.tutor\_profiles.assign. Khi Tutor gửi lại sau changes\_requested, quyết định giữ assignment hiện tại hay đưa lại hàng đợi chưa phân công còn mở; không tự xóa/chuyển assignment theo phỏng đoán. Dù assignment được giữ, quyền quyết định luôn kiểm tra lại trạng thái hồ sơ và assignment hiện tại.
