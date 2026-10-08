# WF06 Review và moderation

Actor: Customer member, Tutor, reporter, Admin moderator đúng permission. Tiền điều kiện: Completed Program hoặc reportable resource có thật; permission/evidence policy đã được duyệt.

1. Customer Completed tạo Review rating/body; gửi lại không tạo record thứ hai.
2. Customer Active/Cancelled và outsider bị từ chối Review.
3. Reporter gửi resource_type/reason/description allow-list; Admin list chỉ summary.
4. Admin detail xem evidence trong quyền được cấp; resolve/dismiss kèm note.
5. Kiểm history/audit actor/state/reason, public DTO không lộ reporter/private file.

Nhánh lỗi: rating invalid, duplicate race, report resource người dùng không được phép truy cập, thiếu moderation/evidence permission, stale transition, audit write failure. Không cấp toàn bộ chat/verification chỉ vì đang xử lý report.

Evidence AC-G3-06/AC-G4-01: quyền theo actor, rollback, public exclusion và browser report detail thật.
