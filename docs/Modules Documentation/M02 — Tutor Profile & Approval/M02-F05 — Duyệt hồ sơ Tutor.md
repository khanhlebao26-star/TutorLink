# M02-F05 — Duyệt hồ sơ Tutor

## Trạng thái: Draft  |  Actor: Admin có quyền quyết định và được giao hồ sơ  |  API hiện tại: POST /api/v1/admin/tutor-profiles/{id}/approve

# Mục tiêu

Ghi nhận quyết định chấp thuận hồ sơ đã gửi, cho phép hồ sơ trở thành hồ sơ Tutor đã duyệt theo các điều kiện công khai.

# Quy tắc sản phẩm

* Chỉ hồ sơ submitted chưa bị đình chỉ mới có thể được approved theo vòng đời đích.  
* Admin phải có admin.tutor\_profiles.decide và assignment hợp lệ; role Admin không tự cấp quyền quyết định.  
* Quyết định lưu người duyệt, thời điểm và lý do vào lịch sử/audit.  
* Sau approved, hồ sơ đủ điều kiện hiển thị công khai; Listing vẫn chỉ công khai nếu ListingStatus active, UserStatus active, email verified và các điều kiện catalog khác đều thỏa.  
* Duyệt hồ sơ không tự kích hoạt Listing, không tạo Program và không cấp quyền truy cập tài nguyên riêng tư.  
* Trạng thái từng VerificationDocument là lifecycle riêng. Tài liệu hoàn tất chưa xác định hồ sơ approved có yêu cầu mọi giấy tờ chuyển approved hay Admin quyết định giấy tờ riêng.

# Luồng

* Admin mở hồ sơ submitted trong phạm vi được giao và xem thông tin/bằng chứng được quyền truy cập.  
* Admin chọn Duyệt và cung cấp lý do theo quy tắc sản phẩm.  
* Backend xác minh lại state, quyền và assignment trong transaction; ghi TutorProfileStatus=approved cùng reviewer, timestamp, reason và audit.

# Tiêu chí chấp nhận

* Hồ sơ submitted hợp lệ chuyển đúng một lần sang approved.  
* Hồ sơ draft, changes\_requested chưa gửi lại, rejected, suspended hoặc đã có decision không thể bị duyệt qua request cũ.  
* Thiếu quyền, sai assignment hoặc trạng thái stale bị từ chối; trạng thái mới hơn không bị ghi đè.  
* Decision có actor/time/reason; lỗi ghi nhận không để profile và audit lệch nhau.

# Chênh lệch runtime và điểm cần chốt

* API hiện ghi approval\_status=active thay cho TutorProfileStatus=approved và decision chỉ nhận pending đã submitted\_at; đây là mapping runtime, không phải enum sản phẩm.  
* API hiện yêu cầu account status active khi quyết định; Product/Domain Documents chưa nói rõ account suspension có chặn Admin review hay chỉ chặn hoạt động Tutor. Cần chốt điều kiện.  
* AdminDecisionRequest hiện yêu cầu reason dài 2–2000 ký tự; xác nhận giới hạn này trong UX/API contract.

# Nguồn và phụ thuộc

Roles & Actors; Business Rules; State Lifecycles; Enum Catalog; Project Overview. Phụ thuộc M02-F04 và M02-F02.

F03 — Kiểm tra assignment khi duyệt  
Trong transaction, backend kiểm tra lại admin.tutor\_profiles.decide, assignment hiện tại, trạng thái submitted và version/state mới nhất trước khi ghi quyết định. Assignment bị chuyển/thu hồi giữa lúc mở hồ sơ và submit quyết định thì request cũ không được áp dụng. Assignment history nằm trong audit; reviewed\_by vẫn ghi Admin duyệt.
