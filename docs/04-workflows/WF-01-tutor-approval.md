# WF01 Tutor đăng ký và được duyệt

Actor: Tutor, Admin có các permission độc lập. Tiền điều kiện: DB test/fixture an toàn, Admin credential riêng, email local Mailpit; chưa được dùng dữ liệu cá nhân thật.

1. Register Tutor ≥18; mở verify link hợp lệ; login bằng cookie.
2. Tạo Draft, chọn specialization visible, upload/complete avatar và verification đúng purpose.
3. Submit; kiểm UI Submitted và backend khóa edit.
4. Admin Request Changes với reason; Tutor thấy reason, sửa và resubmit.
5. Admin Approve với reason; kiểm approval_status active, reviewed_by/time và audit.

Nhánh lỗi: thiếu completeness, file incomplete/wrong owner, reason rỗng, Admin thiếu permission, expired verify, thiếu CSRF qua middleware thực. Fixture riêng cho Reject, suspend/restore và double decision; không reuse Approved để kiểm Reject.

Evidence AC-G1-01/02/05/06: browser network + state trước/sau + audit trong transaction; inject audit failure trên DB test chứng minh rollback. Test chỉ render TokenMismatchException không chứng minh CSRF middleware đã chạy.
