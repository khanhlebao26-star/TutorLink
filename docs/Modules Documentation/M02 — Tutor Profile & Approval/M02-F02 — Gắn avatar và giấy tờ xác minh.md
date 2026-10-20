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
* Checklist định danh cần một `identity_card` hoặc `passport` complete; `other` không thay thế. Degree/certificate chỉ bắt buộc khi Tutor khai báo qualification tương ứng bằng trường có cấu trúc. Thay file tạo document mới, giữ bản cũ và lịch sử quyết định.
* Admin active đang được giao hồ sơ cần `admin.verification_documents.decide` để quyết định từng giấy tờ; quyền tải file riêng là `admin.verification_documents.download`. Reject cần reason và audit. Profile approval không tự approve document.
* Giấy tờ có hạn lưu `expires_at`; degree không tự hết hạn nếu không có hạn thực tế. Giấy tờ bắt buộc expired làm mất gate công khai/tạo dịch vụ mới cho đến khi bản thay thế approved; không tự đình chỉ account/Profile hay hủy Program cũ.

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

* MIME/dung lượng và giới hạn tổng số file thuộc D14–D15; checklist nghiệp vụ tối thiểu đã chốt ở BR-16. Qualification phải được biểu diễn bằng dữ liệu có cấu trúc trước khi áp dụng gate degree/certificate, không phân tích headline/bio tự động.
* Hai route Admin document approve/reject trong Catalog vẫn “Dự kiến”, chưa có success DTO/runtime. Đồng bộ permission, reason, expiry và replacement vào schema/API trước triển khai; không tự đổi VerificationDocumentStatus theo quyết định Profile.

# Nguồn và phụ thuộc

Roles & Actors; Enum Catalog; Domain Model; State Lifecycles. Phụ thuộc upload/complete File và M02-F01.
