# M02-F07 — Từ chối hồ sơ Tutor

## Trạng thái: Draft  |  Actor: Admin có quyền quyết định và được giao hồ sơ  |  API hiện tại: POST /api/v1/admin/tutor-profiles/{id}/reject

# Mục tiêu

Ghi nhận quyết định từ chối hồ sơ đã submitted với lý do có thể truy vết.

# Quy tắc sản phẩm

* Transition hợp lệ: submitted → rejected.  
* Admin cần admin.tutor\_profiles.decide và assignment hợp lệ; mọi quyết định phải ghi actor, thời điểm, reason và audit.  
* Hồ sơ rejected không phải hồ sơ approved và không được xuất hiện công khai như hồ sơ đã duyệt.  
* State Lifecycles không định nghĩa transition ra khỏi rejected. Quy trình khiếu nại, mở lại hoặc nộp hồ sơ mới chưa thuộc phạm vi đã chốt.  
* Từ chối hồ sơ không tự đổi UserStatus; quyết định đình chỉ account là chức năng riêng và cần quyền riêng.

# Luồng

* Admin mở hồ sơ submitted thuộc phạm vi được giao, xem thông tin/bằng chứng trong quyền hạn.  
* Admin chọn Từ chối và nhập lý do.  
* Backend xác minh lại trạng thái và quyền, chuyển sang rejected, ghi reviewer/time/reason và audit.

# Tiêu chí chấp nhận

* Hồ sơ submitted chỉ nhận một decision từ chối hợp lệ.  
* Hồ sơ đã chuyển trạng thái, chưa gửi hoặc bị đình chỉ không bị từ chối bằng dữ liệu stale.  
* Thiếu reason, permission hoặc assignment thì không đổi dữ liệu.  
* Sau rejected, không thể dùng lại endpoint approve/request-changes như thể hồ sơ còn submitted.

# Chênh lệch runtime và điểm cần chốt

* API hiện chỉ quyết định trên approval\_status=pending có submitted\_at; target status là submitted.  
* Cần chốt chính sách khi Tutor muốn nộp lại sau rejected; hiện Domain Documents không có transition đó.

# Nguồn và phụ thuộc

Roles & Actors; State Lifecycles; Enum Catalog; Business Rules. Phụ thuộc M02-F04 và audit.

F03 — Kiểm tra assignment khi từ chối  
Trong transaction, backend kiểm tra lại admin.tutor\_profiles.decide, assignment hiện tại và trạng thái submitted trước khi chuyển sang rejected. Assignment bị chuyển/thu hồi thì Admin cũ không được ghi quyết định; audit giữ actor, reason và kết quả.
