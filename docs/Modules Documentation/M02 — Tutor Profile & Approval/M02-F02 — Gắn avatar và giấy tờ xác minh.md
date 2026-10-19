# M02-F02 — Gắn avatar và giấy tờ xác minh

## Trạng thái: Draft  |  Actor: Tutor; Admin được phân quyền xem xét  |  API hiện tại: PUT avatar, POST verification-documents

# Mục tiêu

Liên kết tệp đã tải lên với hồ sơ Tutor theo đúng chủ sở hữu, mục đích và quyền riêng tư để hồ sơ có thể được xem xét.

# Quy tắc sản phẩm

* M02 liên kết File với Tutor Profile; upload, lưu trữ, quét và xóa tệp thuộc vòng đời của module File.  
* Avatar phải là tệp của Tutor sở hữu hồ sơ và có purpose avatar. Giấy tờ xác minh phải thuộc Tutor đó và có purpose verification\_document.  
* Chỉ liên kết tệp đã hoàn tất. Tệp chưa hoàn tất, sai chủ sở hữu hoặc sai purpose bị từ chối; không tạo liên kết một phần.  
* Giấy tờ xác minh là private, không được đưa vào hồ sơ công khai hay trả cho người chưa đăng nhập.  
* Admin chỉ truy cập bằng chứng trong phạm vi hồ sơ được phân công. Quyền xem hồ sơ và quyền tải giấy tờ được tách biệt; tải/đọc bằng chứng phải được ghi nhật ký.  
* VerificationDocumentStatus là vòng đời độc lập với TutorProfileStatus; tài liệu domain xác định pending, approved, rejected và expired theo điều kiện hết hiệu lực.

# Luồng

* Tutor tải tệp qua module File, đợi trạng thái hoàn tất, rồi chọn file để gắn avatar hoặc giấy tờ với profile của mình.  
* Backend kiểm tra owner, purpose, completeness và quyền sửa profile trước khi lưu liên kết.  
* Admin được giao xem hồ sơ mở metadata/bằng chứng theo quyền. Tải file yêu cầu quyền download riêng.

# Tiêu chí chấp nhận

* Tệp của người khác, sai purpose hoặc chưa complete không thể được gắn vào profile.  
* Không thể đọc/tải giấy tờ xác minh qua đường public hoặc chỉ nhờ có role Admin.  
* Tài khoản thiếu quyền xem, thiếu quyền download hoặc không thuộc phạm vi được giao không nhận được nội dung riêng tư hay download URL.  
* Lượt xem/tải bằng chứng tạo audit theo quy tắc quản trị; lỗi liên kết không để lại quan hệ dữ liệu dở dang.

# Điểm cần chốt

* Danh sách document\_type, số lượng tối thiểu/tối đa, định dạng, dung lượng và quy tắc hết hạn phải khớp Enum Catalog và module File.  
* State Lifecycles có trạng thái riêng cho giấy tờ; cần chốt quan hệ giữa trạng thái document và quyết định Profile. API Route Catalog có placeholder POST /api/v1/admin/verification-documents/{document}/approve và /reject, cả hai đang “Cần bổ sung”; WF01 mô tả đây là luồng dự kiến, chưa phải success contract được duyệt. Cần Product chốt permission/reason/notification, expiry/replacement, document\_type/count và điều kiện giấy tờ bắt buộc trước khi đồng bộ Enum/DB/OpenAPI. Không tự đổi VerificationDocumentStatus theo quyết định Profile.

# Nguồn và phụ thuộc

Roles & Actors; Enum Catalog; Domain Model; State Lifecycles. Phụ thuộc upload/complete File và M02-F01.
