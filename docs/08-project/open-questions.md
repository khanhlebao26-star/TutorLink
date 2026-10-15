# Open questions và decision log

Cập nhật quyết định15/10/2026; các câu hỏi còn mở giữ owner, không tự coi target là implemented. Verified không thay Accepted.

- OQ01 Closed về scope W1: Hoàng chốt giữ draft/active/inactive/cancelled; inactive=paused, cancelled=archived mềm terminal; không Hidden. Tutor verified/approved/account active/profile không suspended mới quản lý Listing. Counts/actions server và tutor_slug đã Verified local; không thêm enum/migration chỉ để khớp ảnh. [Decision](../plans/w1-c3-closeout-contract.md).
- OQ02 slug/public DTO đã triển khai; private verification/email không public. Public avatar hoặc Customer avatar Settings mở rộng vẫn cần contract riêng, không coi đã được duyệt trong scope W1 tối thiểu.
- OQ03 trước A3, Khanh: catalog Education mapping giữ Languages/English idempotent, seed ≥3 category; price_unit/filter whitelist và VND sample chốt end-to-end.
- OQ04 trước W2, Khanh + Hoàng: mapping/backfill cho Program/Invitation, target_customer_id, pending uniqueness, invitation active=Accepted giữ hay migrate, snapshot/version. Completed CHECK/lifecycle giữ W3-C3 theo quyết định Hoàng; GET public reviews không là lý do nới schema sớm.
- OQ05 trước W2, Hoàng + Khanh: message key khác payload replay/conflict, pair eligibility và write/read khi Profile suspended; lock order phối hợp Admin suspension.
- OQ06 trước W3, Khanh + Hoàng + Dũng: Session DTO/IDs, Task in_progress/todo mapping, submission_version/feedback; Fee period/state và Report OPEN mapping.
- OQ07 prerequisite, Khanh + Hoàng: DB guard/config-cache safety, PG base harness; full Admin seed/browser/CSRF/reject/restore/audit rollback/completeness.
- OQ08 trước demo, nhóm: host/domain/email/storage/provider quotas, RPO/RTO, reviewer/người ký gate; không chọn vendor từ suy đoán AI.
- OQ09 Closed về scope 15/10, Hoàng: Settings chỉ full_name và account read qua /me, chưa password/avatar Settings; Auth hiện có giữ nguyên, Breeze optional sau. Thiếu reference Settings → Blocked phần visual comparison, không tự tạo yêu cầu design mới.
- OQ10 Closed về lịch Reviews 15/10, Hoàng: GET Completed-only ở W1, rỗng tới W3. W3-C3 thêm lifecycle/migration/write/visibility và retest hai populated PG cases đang skip. Không fake review hoặc claim Review write đã xong.

OQ07 có evidence guard/isolation và Admin/browser/audit ở [baseline và closeout](../06-acceptance/issue-16-closeout.md); phần chờ hiện tại là reviewer/gate và CI remote, không phân công lại harness đã Verified. Các chi tiết quyết định chưa ghi Closed vẫn cần owner xác nhận.

Mẫu đóng mỗi mục: decision/alternative rejected/reason/người duyệt/ngày/task link/contract-migration-test affected. Không xóa câu hỏi; ghi Closed + link ADR khi đã duyệt.

Drift lịch sử ngày 07–08/10 ở legacy contracts không được coi là contract runtime mới. Bốn deletion trong docs/contracts ngoài scope closeout được giữ để kiểm tra riêng, không khôi phục hoặc ghi đè. OpenAPI/runtime hiện đối chiếu theo contract closeout; các schema target W2/W3 vẫn cần mapping/backfill trong slice sở hữu.
