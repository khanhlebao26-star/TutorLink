# Data model và delta

Nguồn hiện hành: migration trong `backend/database/migrations`; [ERD](../database/erd.md) và [schema notes](../database/w1-a1-database-schema.md) là tài liệu hỗ trợ cần đối chiếu. Bảng tồn tại không chứng minh endpoint/Policy.

## Delta trước từng slice

- A3: Listing status/check từ draft/active/inactive/cancelled tới mapping được duyệt cho Paused/Hidden/Archived; Tutor public slug/visibility; Catalog Education mapping từ Languages hiện có; money VND exponent0.
- W2-A1: Program target_customer_id và lifecycle Draft/Active/Paused/Completed/Cancelled; invitation active=accepted giữ/migrate; một Pending theo Program thay index program/version/invitee; một Customer member.
- Messaging: unique direct_key/client_message_id đã có; key derive không Listing; cursor composite/read monotonic cần service policy, không tạo lại tables.
- W3: task submission_version/expected feedback; todo/in_progress mapping; Session read DTO scheduled/completed/cancelled từ hai bảng; không attendance mới.
- Operations: Fee period YYYY-MM/declared-state mapping; notification event_key unique; Report OPEN mapping/history bằng audit tối thiểu.
- File: complete validation + protect tất cả FK/file_links; visibility/purpose/resource constraints theo slice, không schema public documents mặc định.

Migration additive, có backfill/mapping/rollback và transaction phù hợp; không sửa migration đã shared-applied để giả lịch sử sạch. Up → rollback → up chỉ DB disposable PostgreSQL. Không chạy trên DB dev/shared. SQLite không kiểm được partial index/check/row lock PostgreSQL.
