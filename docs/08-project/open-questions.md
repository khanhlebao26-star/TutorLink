# Open questions và decision log

Chưa đóng các mục này trong lượt tạo docs. Owner là người cần chốt, không phải cam kết đã làm.

- OQ01 trước A3/B3, Khanh + Dũng + Hoàng: Listing enum/action mapping draft/active/inactive/cancelled ↔ Draft/Active/Paused/Hidden/Archived; draft eligibility, hide/restore owner, archive terminal.
- OQ02 trước A3/B3, Khanh + Dũng: public Tutor slug/avatar visibility và payload tối thiểu; migration collision strategy; không public verification.
- OQ03 trước A3, Khanh: catalog Education mapping giữ Languages/English idempotent, seed ≥3 category; price_unit/filter whitelist và VND sample chốt end-to-end.
- OQ04 trước W2, Khanh + Hoàng: Program lifecycle wire/migration/backfill; target_customer_id; pending uniqueness; invitation active=Accepted giữ hay migrate; snapshot cancel/edit/resend version.
- OQ05 trước W2, Hoàng + Khanh: message key khác payload replay/conflict, pair eligibility và write/read khi Profile suspended; lock order phối hợp Admin suspension.
- OQ06 trước W3, Khanh + Hoàng + Dũng: Session DTO/IDs, Task in_progress/todo mapping, submission_version/feedback; Fee period/state và Report OPEN mapping.
- OQ07 prerequisite, Khanh + Hoàng: DB guard/config-cache safety, PG base harness; full Admin seed/browser/CSRF/reject/restore/audit rollback/completeness.
- OQ08 trước demo, nhóm: host/domain/email/storage/provider quotas, RPO/RTO, reviewer/người ký gate; không chọn vendor từ suy đoán AI.

Mẫu đóng mỗi mục: decision/alternative rejected/reason/người duyệt/ngày/task link/contract-migration-test affected. Không xóa câu hỏi; ghi Closed + link ADR khi đã duyệt.

Drift hiện thấy: contract W1-C1 còn nói users không role/schema nền chưa có, Program states cũ, chat context và session attendance; schema notes có sample money/đề xuất storage cũ. Proposal v3.1 và baseline mới đã ghi delta. Bộ docs mới điều hướng drift, chưa sửa legacy contracts/OpenAPI trong lượt này.
