# M02-F03 — Gửi hồ sơ xét duyệt và gửi lại sau chỉnh sửa

## Trạng thái: Draft  |  Actor: Tutor  |  API hiện tại: POST /api/v1/tutor/profile/submit

# Mục tiêu

Chuyển hồ sơ Tutor từ draft sang hàng đợi Admin, hoặc gửi lại nội dung đã được yêu cầu chỉnh sửa.

# Quy tắc sản phẩm

* Với Profile chưa approved, transition hợp lệ: draft → submitted và changes\_requested → submitted. Profile approved có revision riêng theo BR-16; Profile rejected không tự gửi lại.
* Chỉ Tutor sở hữu hồ sơ mới có thể gửi. Backend kiểm tra trạng thái và mọi điều kiện trước khi cập nhật để không nhận hồ sơ dở dang.  
* Khi gửi thành công, hồ sơ xuất hiện trong phạm vi xét duyệt của Admin; quyết định và thay đổi trạng thái phải được ghi nhận theo audit policy.  
* Checklist BR-16: Tutor active/email verified, headline, bio, ít nhất một specialization active, avatar complete đúng owner/purpose và một identity_card hoặc passport complete. Qualification có cấu trúc kéo theo bằng cấp/chứng chỉ tương ứng; `other` không thay định danh.
* Với hồ sơ approved, submit bản revision riêng để xét duyệt; bản approved giữ nguyên và tiếp tục công khai theo gate cho đến khi revision được duyệt. Revision bị request-changes được sửa/gửi lại, bị reject không tự gửi lại.

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

* Active và verified khi submit đã được chốt tại BR-16. Runtime hiện có các gate này và headline/bio/specialization/avatar, nhưng giấy tờ xác minh nullable nên chưa bắt buộc; OpenAPI sinh từ code phải phản ánh runtime cho đến khi sửa controller/test.
* Runtime chưa có revision riêng cho hồ sơ approved và qualification có cấu trúc; cần migration, DTO và test trước khi quảng bá luồng này là khả dụng.
* API hiện biểu diễn submitted bằng approval\_status=pending cùng submitted\_at; domain target dùng TutorProfileStatus=submitted. Đồng bộ OpenAPI, UI và migration theo enum đích.

# Nguồn và phụ thuộc

Business Rules; Roles & Actors; State Lifecycles; Enum Catalog; Domain Model. Phụ thuộc M02-F01 và M02-F02.

F03 — Tác động assignment khi gửi/gửi lại  
Hồ sơ mới gửi có thể vào hàng đợi chưa phân công (assigned\_admin\_id NULL) cho coordinator có admin.tutor\_profiles.assign. Khi Tutor gửi lại sau changes\_requested hoặc gửi revision, giữ assignment hiện tại nếu Admin còn active và đủ quyền xử lý; nếu không, đưa về hàng đợi chưa phân công. Quyền quyết định luôn kiểm tra lại trạng thái hồ sơ/revision và assignment hiện tại trong transaction.
