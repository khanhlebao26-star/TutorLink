Domain Overview & Glossary

TutorLink kết nối Customer với Tutor: tìm dịch vụ → trao đổi → nhận lời mời học → thực hiện Program → đánh giá.

**Thuật ngữ**

| Thuật ngữ | Ý nghĩa |
| :---- | :---- |
| Customer | Người tìm dịch vụ, nhận lời mời và tham gia Program. |
| Tutor | Người cung cấp dịch vụ, quản lý Listing và Program. |
| Admin | Người duyệt hồ sơ và xử lý vi phạm theo quyền được cấp. |
| Tutor Profile | Hồ sơ Tutor có TutorProfileStatus: draft, submitted, changes\_requested, approved, rejected hoặc suspended. Khi đình chỉ phải lưu trạng thái trước đó để khôi phục; UserStatus của tài khoản được quản lý riêng. |
| Listing | Dịch vụ công khai có ListingStatus draft/active/inactive/archived; giá tham khảo, chưa phải thỏa thuận riêng. |
| Conversation | Hội thoại duy nhất của một cặp Customer–Tutor, dùng chung cho các Program; active cho phép trao đổi, cancelled chỉ đọc sau khi đủ điều kiện đóng. |
| Order / Program | Thỏa thuận riêng giữa một Tutor và một Customer; tên nghiệp vụ trong dự án là Program. |
| Invitation | Lời mời tham gia Program, có hạn và bản chụp thỏa thuận. |
| Snapshot | Bản chụp thỏa thuận được khóa khi gửi lời mời; Listing thay đổi không làm đổi bản cũ. |
| Session / Task | Buổi học / nhiệm vụ và bài nộp trong Program. |
| Payment / Fee Record | Bản ghi phí Tutor nhập thủ công. FeeRecordStatus (declared/voided) tách riêng TutorPaymentMark (unmarked/marked\_paid/marked\_unpaid); không phải giao dịch hoặc bằng chứng thanh toán. |
| Review | Đánh giá của Customer sau khi Program hoàn thành. |

**Phạm vi và sử dụng**

MVP gồm hồ sơ, marketplace, chat, Program, workspace, ghi nhận phí và đánh giá; không tích hợp cổng thanh toán. Tên enum và literal dùng đúng Enum Catalog; quy tắc chuyển trạng thái và quyền được mô tả trong Business Rules và State Lifecycles.
