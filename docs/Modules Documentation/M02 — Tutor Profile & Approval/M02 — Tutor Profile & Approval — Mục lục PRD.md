# M02 — Hồ sơ Tutor & Xét duyệt

## Bộ PRD theo chức năng  |  Trạng thái: Draft  |  Cơ sở: Product Documents và Domain Documents đã hoàn thành

# Mục tiêu và phạm vi

M02 đặc tả việc Tutor tạo, duy trì và gửi Hồ sơ Tutor; Admin được phân quyền xem hồ sơ, đánh giá bằng chứng, đưa ra quyết định xét duyệt, yêu cầu chỉnh sửa, từ chối, đình chỉ hoặc khôi phục hồ sơ. Mọi trạng thái và quyền bên dưới theo tài liệu sản phẩm/domain hoàn tất; API hiện tại chỉ được dùng để ghi nhận chênh lệch triển khai.

# Danh sách PRD

[M02-F01 — Tạo và cập nhật hồ sơ Tutor](<M02-F01 — Tạo và cập nhật hồ sơ Tutor.md>)  
[M02-F02 — Gắn avatar và giấy tờ xác minh](<M02-F02 — Gắn avatar và giấy tờ xác minh.md>)  
[M02-F03 — Gửi hồ sơ xét duyệt và gửi lại sau chỉnh sửa](<M02-F03 — Gửi hồ sơ xét duyệt và gửi lại sau chỉnh sửa.md>)  
[M02-F04 — Hàng đợi xét duyệt và xem hồ sơ](<M02-F04 — Hàng đợi xét duyệt và xem hồ sơ.md>)  
[M02-F05 — Duyệt hồ sơ Tutor](<M02-F05 — Duyệt hồ sơ Tutor.md>)  
[M02-F06 — Yêu cầu chỉnh sửa hồ sơ Tutor](<M02-F06 — Yêu cầu chỉnh sửa hồ sơ Tutor.md>)  
[M02-F07 — Từ chối hồ sơ Tutor](<M02-F07 — Từ chối hồ sơ Tutor.md>)  
[M02-F08 — Đình chỉ và khôi phục hồ sơ Tutor](<M02-F08 — Đình chỉ và khôi phục hồ sơ Tutor.md>)

# Vòng đời chuẩn theo Domain Documents

* TutorProfileStatus: draft → submitted → approved, changes\_requested hoặc rejected; changes\_requested → submitted.  
* Hồ sơ ở trạng thái suspended lưu trạng thái trước đình chỉ. Khôi phục trả về đúng trạng thái đã lưu, không tự chuyển thành approved.  
* VerificationDocumentStatus có vòng đời riêng: pending → approved/rejected; approved → expired khi có căn cứ hết hiệu lực. BR-16 yêu cầu giấy tờ bắt buộc approved và còn hạn trước quyết định Profile; revision của Profile approved có vòng đời xét duyệt riêng.

# Quyền và riêng tư

* Admin là tài khoản nội bộ; role Admin không cấp toàn bộ quyền. Quyền xem, quyết định, tải giấy tờ xác minh và đình chỉ hồ sơ/tài khoản được tách riêng.  
* Chỉ hồ sơ approved mới đủ điều kiện xuất hiện công khai. Giấy tờ xác minh là dữ liệu riêng tư; chỉ người được giao xử lý hồ sơ được truy cập, tải xuống cần quyền riêng và hoạt động phải được ghi nhật ký.  
* Tutor chỉ đủ điều kiện quản lý Listing, tạo Program hoặc gửi Invitation khi UserStatus active, email verified, TutorProfileStatus approved, hồ sơ không bị đình chỉ và giấy tờ bắt buộc còn hiệu lực theo BR-16. Chỉ Customer đích được accept Invitation; khi accept, server kiểm lại điều kiện của Customer và Tutor.

# Chênh lệch triển khai cần đồng bộ

* Domain dùng TutorProfileStatus draft/submitted/changes\_requested/approved/rejected/suspended. API hiện dùng approval\_status pending/active/rejected/changes\_requested kết hợp submitted\_at và suspended\_at; cần đồng bộ contract, enum, response và migration. Không coi active/pending là enum đích.  
* Domain yêu cầu khi đình chỉ hồ sơ của account đang active thì khóa account trong cùng transaction và cần cả quyền đình chỉ profile lẫn account. API hiện có endpoint suspend profile riêng và chưa thực hiện thay đổi account cùng transaction.  
* Product Roles yêu cầu giới hạn Admin theo hồ sơ/vụ việc được phân công. API hiện kiểm tra permission chung; cần xác nhận và thực thi phạm vi assignment ở backend.  
* BR-16 đã chốt checklist, quyết định từng VerificationDocument, revision approved, rejected terminal và assignment khi gửi lại. Runtime hiện yêu cầu headline, bio, specialization active và avatar hợp lệ nhưng giấy tờ vẫn nullable; chưa có quyết định document riêng, revision, gate expiry hay qualification có cấu trúc. Hai route Admin document trong Catalog vẫn Dự kiến; xem M02-F02/F03.

# Nguồn

Project Overview; Roles & Actors; Goals & Non-goals; Business Rules; State Lifecycles; Enum Catalog; Domain Model. Tham chiếu triển khai hiện tại: routes/api.php, TutorProfileController, AdminController, AdminPermissions và AdminDecisionRequest. Nguồn sản phẩm/domain quyết định yêu cầu; mã/API chỉ ghi nhận trạng thái triển khai và khoảng trống cần đồng bộ.
