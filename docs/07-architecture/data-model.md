# Data model và delta

Nguồn hiện hành: migration trong `backend/database/migrations`; [ERD](../database/erd.md) và [schema notes](../database/w1-a1-database-schema.md) là tài liệu hỗ trợ cần đối chiếu. Bảng tồn tại không chứng minh endpoint/Policy.

## Delta trước từng slice

- W1 đã chốt: giữ Listing `draft/active/inactive/cancelled`; inactive=paused, cancelled=archived mềm terminal; không Hidden. Tutor public_slug đã có; DTO/counts/actions được triển khai, không cần migration enum mới. Settings dùng users.full_name và account fields hiện có, không thêm schema.
- W2-A1: Program target_customer_id, mapping/backfill phục vụ Program/Invitation; invitation active=accepted giữ/migrate; một Pending theo Program thay index program/version/invitee; một Customer member. Target lifecycle được mô tả trước, nhưng CHECK/state `completed` và lifecycle sản sinh Completed defer W3-C3 theo quyết định Hoàng 15/10/2026.
- Messaging: unique direct_key/client_message_id đã có; key derive không Listing; cursor composite/read monotonic cần service policy, không tạo lại tables.
- W3: task submission_version/expected feedback; todo/in_progress mapping; Session read DTO scheduled/completed/cancelled từ hai bảng; không attendance mới.
- W3-C3: thêm completed bằng migration additive được review, triển khai Complete/Review write và visibility/moderation tương ứng. Tái dùng GET public reviews W1; chạy lại hai populated reviews tests PostgreSQL đang skip ở W1, không thay bằng fixture vi phạm CHECK. Chưa có Completed thì GET trả rỗng đúng thiết kế.
- Operations: Fee period YYYY-MM/declared-state mapping; notification event_key unique; Report OPEN mapping/history bằng audit tối thiểu.
- File: complete validation + protect tất cả FK/file_links; visibility/purpose/resource constraints theo slice, không schema public documents mặc định.

Migration additive, có backfill/mapping/rollback và transaction phù hợp; không sửa migration đã shared-applied để giả lịch sử sạch. Up → rollback → up chỉ DB disposable PostgreSQL. Không chạy trên DB dev/shared. SQLite không kiểm được partial index/check/row lock PostgreSQL.
