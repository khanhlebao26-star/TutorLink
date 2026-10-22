# BE-D01 (Issue #36) — Task 1: Schema inventory và mapping

## Mục tiêu và phạm vi

Task 1 kiểm kê và đối chiếu schema của tám domain: User, Profile, Listing, Program, Invitation, Submission, Fee và Report. Kết quả xác định cấu trúc đích, trạng thái trong repository migrations và database runtime, cùng các chênh lệch cần chuyển sang bước migration.

Nguồn schema đích là [Database Design — TutorLink](Database%20Design%20%E2%80%94%20TutorLink.md), kết hợp [Business Rules](../Domain%20Documentation/Business%20Rules.md), [Enum Catalog](../Domain%20Documentation/Enum%20Catalog.md) và PRD liên quan. Repository migrations cho biết cấu trúc được định nghĩa trong code; runtime được xác minh riêng trên Docker Compose database `tutorlink` (PostgreSQL 16.15) ngày 21/10/2026.

## Kết quả kiểm kê runtime

- Có 39 bảng; `migrate:status`: 14 migration `Ran`, 4 `Pending`.
- 51 users: 3 admin, 25 customer, 23 tutor; tất cả đang `active`.
- 18 tutor profiles: tất cả `approval_status=pending`; 11 có `submitted_at`, 7 chưa có. Có 11 verification documents, tất cả `pending`.
- 24 files: 13 avatar, 11 verification document; tất cả scan `clean`.
- Customer profiles, listings, Programs, Invitations, Program members, Tasks, Submissions, Fee records và Reports đều chưa có rows. Category có 1 row; specialization có 1 row.
- `users.terms_version` đang NULL ở cả 51 users; cột `terms_accepted_at` chưa tồn tại. Không có bằng chứng để tự gán thời điểm đồng ý.

Các số liệu trên là của Docker Compose database đã kiểm tra, không đại diện cho môi trường khác. Không chạy migration hay thay đổi dữ liệu trong Task 1.

## Mapping và chênh lệch chính

| Domain | Bảng và quan hệ chính | Chênh lệch so với schema đích | Runtime |
|---|---|---|---|
| User | `users` — tài khoản, role, trạng thái | Thiếu `terms_accepted_at`; `terms_version` nullable. Trạng thái User đích cần căn chỉnh. | 51 rows; `terms_version` NULL ở cả 51. |
| Profile — Customer | `customer_profiles.user_id` → `users.id`; avatar → `files.id` | Cấu trúc nền tảng phần lớn khớp. Các quy tắc optional/upsert/avatar thuộc behavior API. | Bảng có, 0 rows. |
| Profile — Tutor | `tutor_profiles.user_id` → `users.id`; specialization qua `tutor_specializations`; giấy tờ ở `verification_documents` | Cần status đích, `public_slug`, qualification/assignment fields và các bảng/fields revision. Verification document cần `approved`, `expires_at`, `review_reason`; hiện migration dùng status khác. | 18 profiles, 11 documents; migration status/slug còn Pending hoặc chưa có. |
| Listing | `service_listings.tutor_profile_id` → `tutor_profiles.id`; specialization → `specializations.id` | Status đích có `archived` thay cho `cancelled`; thiếu `listing_moderation_holds`. | Bảng có, 0 rows; CHECK runtime còn theo enum cũ. |
| Program | `programs.owner_tutor_profile_id` → `tutor_profiles.id`; Customer đích qua `customer_id` → `users.id` | Thiếu `customer_id`; status/default hiện tại khác status đích. | Bảng có, 0 rows; chưa có cột `customer_id`. |
| Invitation | `program_invitations.program_id` → Program; `invitee_user_id` → User | Status CHECK dùng `active` thay `accepted`; pending unique constraint khác thiết kế đích. | Bảng có, 0 rows. |
| Submission | `tasks` thuộc Program; `task_submissions` thuộc Task và Customer | Schema hiện chỉ lưu một submission mỗi Task/Customer; target cần nhiều version, idempotency fields và Task version/current-submission pointer. Status `reviewed` khác target `accepted`. | Cả hai bảng 0 rows. |
| Fee | `fee_records.program_id` → Program | Legacy `declared_status` khác target status/payment/void fields; thiếu idempotency receipt và outbox. Không diễn giải `confirmed` thành đã thu tiền. | Bảng có, 0 rows. |
| Report | `reports.reporter_id` → User; resource tham chiếu logic qua `resource_type/resource_id` | Thiếu assignment fields; status CHECK khác target; thiếu partial unique cho một Report đang mở trên cùng resource/người báo cáo. | Bảng có, 0 rows. |

Bảng phụ trợ liên quan: `program_members`, `files`, `verification_documents`, `idempotency_receipts`, `notification_outbox`, `tutor_profile_revisions` và `listing_moderation_holds`. Một số bảng/fields target chưa có migration.

## Delta chuyển tiếp

| Ưu tiên | Việc cần xử lý ở bước migration | Dữ liệu runtime hiện có |
|---|---|---|
| P0 | Bổ sung `programs.customer_id` và chốt status/default cho Program, Invitation. | 0 Programs/Invitations; hiện chưa có rows cần backfill. |
| P0 | Thiết kế Submission append-only theo version, idempotency và current pointer. | 0 Tasks/Submissions. |
| P1 | Cập nhật Fee status/payment/void/version và idempotency/outbox. | 0 Fee records. |
| P1 | Cập nhật Report assignment/status/unique constraint. | 0 Reports. |
| P1 | Hoàn thiện User terms acceptance; Tutor Profile status/assignment/revision; Listing archive/moderation hold. | 51 users thiếu evidence terms; 18 Tutor Profiles; 0 Listings. |

`terms_accepted_at` không được backfill từ `created_at` nếu không có evidence đồng ý. Mapping status và backfill của dữ liệu Tutor Profile cần được review trước khi áp dụng migration.

## Trách nhiệm theo Issue #36 và trạng thái

- Task 1 owner: Dũng. Reviewer: Khanh.
- Issue nêu Assignment — Hoàng, Outbox — Khanh, Submission — Dũng. Owner các delta còn lại chưa được xác định trong inventory.
- Task 1 hoàn tất phần inventory/mapping. Schema runtime vẫn còn chênh lệch với target; migration và backfill thuộc bước triển khai tiếp theo sau khi mapping/decisions được chấp nhận.
