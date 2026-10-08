# Mô hình thực thể

User có một Customer Profile hoặc Tutor Profile theo role. Tutor Profile gắn specialization, avatar và verification document. Catalog có Category → Subcategory → Specialization; Listing thuộc Tutor và specialization.

Conversation có members và messages; `direct_key` unique theo cặp participant, không chứa Listing ID. `context_listing_id` nullable. Message unique `(sender_id, client_message_id)`.

Program thuộc Tutor, tham chiếu source Listing, lưu offer snapshot/version và cần target Customer. Invitation thuộc Program, gửi đúng target, giữ snapshot riêng. Membership được tạo khi accept, tối đa một Customer trong MVP.

Program chứa Session kế hoạch/thực hiện, Task/Submission, Progress, Announcement, Fee Record và Review đủ điều kiện. File lưu metadata, storage object và resource link; Notification thuộc recipient; Report trỏ resource allow-list; Audit ghi mutation quan trọng.

Tái dùng bảng hiện có. Migration delta chỉ thêm phần thiếu; không tạo lại toàn bộ schema hoặc đổi primary ID sang UUID. Xem [schema delta](../07-architecture/data-model.md).
