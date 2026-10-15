# Trạng thái và chuyển trạng thái

## Profile hiện có

UI Draft = `approval_status=pending` và `submitted_at=null`; Submitted = pending và đã submitted; Approved = active. Request Changes = changes_requested, Rejected = rejected. `suspended_at` là trục độc lập, không thay approval_status.

Tutor sửa Draft/Changes Requested; submit đưa về Submitted. Admin quyết định Submitted → Approved/Changes Requested/Rejected với reason/audit. Submitted/Approved/Rejected khóa chỉnh sửa theo baseline. Không mở rộng quyền sửa Approved trong task Marketplace.

## Listing đã chốt W1 closeout

Wire giữ `draft/active/inactive/cancelled`: draft→active publish, active→inactive pause, inactive→active publish; nonterminal→cancelled archive mềm. Cancelled terminal, không edit/restore; draft→inactive trả409. Active→active vẫn kiểm catalog eligibility. `inactive` chỉ là pause, không Admin hide; không thêm Hidden trong W1. Backend trả allowed_actions và kiểm lại từng request; chỉ Tutor verified/account active/profile approved không suspended được quản lý Listing.

## Program và Invitation cần chốt W2

Program đích: Draft → Active (accept), Active → Paused/Completed/Cancelled; Paused → Active/Completed/Cancelled; terminal không reopen. Draft có thể cancel theo contract được duyệt.

Đây là target, không phải trạng thái đã triển khai. Theo quyết định 15/10/2026, migration/check và lifecycle Completed thuộc W3-C3; W1/W2 không nới completed để có review public. GET reviews W1 chỉ đọc theo Completed gate và hiện rỗng. W2 vẫn chốt mapping cho các command Program/Invitation thuộc scope riêng.

Invitation Pending → Accepted/Rejected/Cancelled/Expired. Chỉ đúng actor, đúng target và chưa hết hạn được quyết định; Accepted terminal. Wire cũ `active=Accepted`: giữ hoặc migrate phải ghi ADR, không đổi riêng FE.

## Workspace và Trust

Session UI đích Scheduled/Completed/Cancelled trên hai bảng hiện có; không expose HTTP sessions. Task UI Not Started/Submitted/Completed/Cancelled; mapping `todo/in_progress` và resubmission phải chốt trước W3. Fee đích DECLARED/TUTOR_MARKED_PAID/UNPAID khác schema declared/confirmed/cancelled; Report đích OPEN/RESOLVED/DISMISSED khác pending/reviewing/resolved/dismissed.

Invalid/stale transition trả 409 và không side effect. Enum đích chưa được duyệt wire phải giữ ở open questions, không tự ghi là đã implemented.
