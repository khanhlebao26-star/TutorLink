# M09-F05 · Ghi và tra cứu Audit

## PRD chức năng · M09 Review, Report & Audit · Draft

# 1\. Mục tiêu

Lưu dấu vết bất biến cho các quyết định/moderation quan trọng và cho phép người được cấp quyền đọc trong scope.

# 2\. Audit event

- Record tối thiểu actor, resource, action, old/new state khi phù hợp, reason và timestamp.
- Audit ghi cùng transaction với mutation; nếu audit write fail thì mutation rollback. Khi mutation sinh notification, outbox row cho recipient/type/resource/event_key cũng nằm trong transaction đó; worker không tự ghi audit nghiệp vụ và không đọc private content để dựng thông báo.
- Audit là append-only: API không sửa/xóa sự kiện lịch sử.
- admin.audit_logs.view chỉ đọc theo scope; quyền Admin chung không cấp toàn bộ log.
- Không đưa secret, object key hoặc toàn bộ private content vào audit payload.
- Action catalog MVP: `report.create/resolve/dismiss`, `review.create/hide/restore`, `listing.hide/restore`, `tutor_profile.approve/request_changes/reject/suspend/restore`, `account.suspend/restore`, `program.create/update/pause/resume/complete/cancel`, `invitation.send/accept/expire/cancel`, `assignment.change`, `announcement.publish`, `fee_record.create/update/mark/void`, `session.complete/cancel`, `task.create/update/start/cancel/review`, `file.link/unlink`, `evidence.read`, `audit.read`, `outbox.replay/resolve/purge`, `audit.purge`. Mở rộng action phải cập nhật catalog và kiểm thử tương ứng.
- `admin.audit_logs.view` cho phép operator nội bộ đọc trong scope được giao. DTO allow-list gồm actor, action, resource type/id, status/version trước/sau khi phù hợp, reason, request ID và timestamp; không trả raw JSONB, nội dung message, file hay credentials. Ghi `audit.read` khi truy vấn audit.
- Giữ audit 24 tháng từ `created_at`, sau đó purge theo job có quyền kiểm soát; purge phải tự được audit. Không xóa/sửa thủ công qua API.

# 3\. Tiêu chí chấp nhận

- Report decision, Review moderation và các mutation quan trọng tạo đúng audit record.
- Transaction rollback không để audit orphan; audit failure không để mutation không có trace.
- Người không có scope không đọc được audit; actor hợp lệ đọc được trường cần thiết.
- Read API không sửa lịch sử.

# 4\. Chênh lệch/câu hỏi mở

- Admin approval audit đã có; Report/Review audit API và scoped log reader chưa có.
- Action catalog, masking, retention và scope đã được chốt ngày 21/10/2026. API audit chưa triển khai; pagination tuân mặc định API chung và cần kiểm chứng khi có code.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-06 Review và moderation. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)

F03 — Audit assignment  
Mỗi lần giao, chuyển hoặc thu hồi Tutor Profile/Report tạo audit_logs row append-only trong cùng transaction với cập nhật assignment. Ghi resource_type/id, actor_id, action, before/after tối thiểu gồm assigned_admin_id và assignment_version, reason và created_at; không chép nội dung hồ sơ, tin nhắn hoặc file riêng tư vào audit. Assignment history không được sửa/xóa.


## H02 runtime audit mapping

H02 keeps the existing profile notification/audit identifiers `tutor_profile_approved`, `tutor_profile_changes_requested`, `tutor_profile_rejected`, `tutor_profile_suspended`, `tutor_profile_restored` and the existing `account_suspended`/`account_restored` identifiers for compatibility. Assignment changes use `tutor_profile.assignment`; submit uses `tutor_profile.submit`; per-document decisions use `verification_document.approve`/`verification_document.reject`; reviewer access uses `verification_document.view`/`verification_document.download`. These map to the profile/account/assignment/evidence categories above. Audit payloads contain state, assignment/revision identifiers, version or accessed file identifiers; they never copy document contents or uploaded file names. Request IDs are UUIDs. Existing retention and scoped audit-read rules remain applicable.
