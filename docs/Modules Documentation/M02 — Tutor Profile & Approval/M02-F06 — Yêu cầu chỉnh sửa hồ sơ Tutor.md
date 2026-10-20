# M02-F06 — Yêu cầu chỉnh sửa hồ sơ Tutor

## Trạng thái: Draft  |  Actor: Admin có quyền quyết định và được giao hồ sơ  |  API hiện tại: POST /api/v1/admin/tutor-profiles/{id}/request-changes

# Mục tiêu

Trả hồ sơ đã submitted cho Tutor để sửa các điểm cần hoàn thiện, sau đó Tutor có thể gửi lại xét duyệt.

# Quy tắc sản phẩm

* Transition hợp lệ: submitted → changes\_requested; Admin cung cấp lý do/ghi chú đủ để Tutor biết nội dung cần sửa.  
* Chỉ Admin có admin.tutor\_profiles.decide và assignment hợp lệ được đưa ra quyết định.  
* Tutor chỉ sửa hồ sơ sau khi nhận trạng thái changes\_requested; gửi lại tạo transition changes\_requested → submitted.  
* Lý do, người xử lý, thời điểm và thay đổi trạng thái phải được lưu trong audit.  
* Request Changes không đồng nghĩa với từ chối và không đình chỉ User hoặc Tutor Profile.

# Luồng

* Admin đọc hồ sơ submitted và bằng chứng theo quyền được cấp.  
* Admin chọn Yêu cầu chỉnh sửa, ghi lý do cụ thể rồi xác nhận.  
* Hồ sơ chuyển changes\_requested; Tutor sửa nội dung được nêu và gửi lại theo M02-F03.

# Tiêu chí chấp nhận

* Chỉ submitted mới nhận được quyết định yêu cầu chỉnh sửa; request stale hoặc lặp không tạo thay đổi ngoài ý muốn.  
* Không có reason hoặc thiếu quyền/assignment thì không thay đổi trạng thái.  
* Sau quyết định, hồ sơ ở changes\_requested và có thể chỉnh sửa/gửi lại; khi gửi lại trở về submitted.  
* Audit phản ánh decision và reason; dữ liệu hồ sơ không bị mất ngoài nội dung Tutor chủ động sửa.

# Điểm cần chốt

* Product/Domain Documents chưa định nghĩa notification, format reason hoặc một danh sách cấu trúc các trường Admin yêu cầu chỉnh sửa. Cần chốt trước khi đóng UI/API contract.  
* Runtime hiện biểu diễn submitted bằng pending \+ submitted\_at; đồng bộ với TutorProfileStatus đích.

# Nguồn và phụ thuộc

Roles & Actors; State Lifecycles; Enum Catalog; Domain Model. Phụ thuộc M02-F03, M02-F04 và audit.

F03 — Kiểm tra assignment khi yêu cầu sửa  
Trong transaction, backend kiểm tra lại admin.tutor\_profiles.decide, assignment hiện tại và trạng thái submitted trước khi chuyển sang changes\_requested. Assignment bị chuyển/thu hồi thì Admin cũ không được ghi quyết định; audit giữ actor, reason và kết quả.
