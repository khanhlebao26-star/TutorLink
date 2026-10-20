Domain Model

| Thực thể | Trách nhiệm và quan hệ chính |
| :---- | :---- |
| User | Danh tính và UserRole customer/tutor/admin; UserStatus active/suspended/deactivated. Có Customer Profile hoặc Tutor Profile tương ứng; trạng thái xác minh email suy ra từ email\_verified\_at. |
| Tutor Profile | Một Tutor có nhiều Listing và Program. TutorProfileStatus gồm draft/submitted/changes\_requested/approved/rejected/suspended; lưu trạng thái trước đình chỉ và thông tin quyết định để restore đúng trạng thái cũ, không tự approved. |
| Catalog | Category → Subcategory → Specialization; Listing thuộc một Specialization. |
| Listing | Dịch vụ của Tutor; ListingStatus draft/active/inactive/archived. Có thể là nguồn tạo nhiều Program; archived giữ lịch sử và không khôi phục. |
| Conversation / Message | Một Conversation duy nhất cho cặp Customer–Tutor, chứa nhiều Message; Listing là ngữ cảnh tùy chọn. Program liên quan qua đúng Tutor/Customer của cặp, không tạo chat riêng mỗi Program. ConversationStatus active/cancelled theo điều kiện đóng/mở lại trong Business Rules. |
| Program (Order) | Thỏa thuận riêng: một Tutor, một Customer đích, source Listing tùy chọn và snapshot/version. ProgramStatus draft/active/paused/completed/cancelled; snapshot đã gửi bất biến. |
| Invitation | Thuộc Program; lưu Customer đích, expires\_at sau 72 giờ và snapshot bất biến. InvitationStatus pending/accepted/rejected/cancelled/expired; tối đa một pending còn hiệu lực mỗi Program. |
| Program Membership | Được tạo khi accept; ProgramMemberRole customer/tutor, tối đa một Customer member. ProgramMemberStatus active/cancelled/expired; Program completed không tự thu hồi membership, để giữ quyền đọc và Review. |
| Session / Task / Submission | Thuộc workspace Program. Session được tách thành program\_sessions (lịch dự kiến) và lesson\_sessions (kết quả thực hiện), quan hệ 1 → 0..1 qua program\_session\_id UNIQUE; ID API là program\_sessions.id. Chỉ Tutor owner hoàn tất lịch scheduled thì transaction mới tạo actual; hủy không tạo actual. SessionStatus scheduled/completed/cancelled. TaskStatus todo/in\_progress/submitted/completed/cancelled. SubmissionStatus submitted/accepted/revision\_requested; mỗi version và feedback gắn version được bảo toàn, chỉ version mới nhất được feedback. |
| Progress / Announcement | Tiến độ và thông báo nội bộ Program. AnnouncementViewState draft/published suy ra từ published\_at; Progress lưu metric/value/note, không thêm status enum. |
| Fee Record (Payment) | Thuộc Program, duy nhất theo Program/tháng YYYY-MM, amount VND nguyên không âm. Hai trường độc lập: status dùng FeeRecordStatus declared/voided; tutor\_payment\_mark dùng TutorPaymentMark unmarked/marked\_paid/marked\_unpaid. Mặc định declared \+ unmarked; lưu reason/time khi void hoặc sửa dấu thanh toán. |
| Review / Report | Review theo Program completed đủ điều kiện, ReviewVisibility visible/hidden. Report tham chiếu ReportResourceType, ReportReasonCode trong Enum Catalog; ReportStatus open/resolved/dismissed. |
| File / Notification / Audit | File dùng FilePurpose/FileResourceType/FileLinkPurpose trong Enum Catalog; FileVisibility chỉ private/public, tệp private kiểm quyền tài nguyên hoặc membership. Notification theo recipient/type/read\_at, kênh in\_app; Audit giữ lịch sử thay đổi quan trọng. |

Đây là mô hình nghiệp vụ đích theo Enum Catalog; tái dùng schema khi phù hợp, mapping dữ liệu cũ bằng migration được kiểm chứng. Snapshot bảo toàn thỏa thuận khi Listing thay đổi hoặc archived. Không dùng literal cũ như một lựa chọn đích khác.

F03 — Quan hệ phân công Admin  
Tutor Profile và Report giữ 0..1 current Admin assignment bằng assigned\_admin\_id → users.id; assigned\_by ghi người thực hiện lần thay đổi gần nhất, assigned\_at ghi thời điểm, assignment\_version bảo vệ cập nhật đồng thời. Assignment là scope xử lý hiện tại, còn reviewed\_by/handled\_by vẫn là người ra quyết định cuối. Mọi lần giao, chuyển và thu hồi được giữ trong audit\_logs; không cần bảng assignment-history riêng cho MVP. Quyền phân công không tự cấp quyền đọc hoặc quyết định.
