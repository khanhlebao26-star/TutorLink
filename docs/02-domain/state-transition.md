# Trạng thái và chuyển trạng thái

## Profile hiện có

UI Draft = `approval_status=pending` và `submitted_at=null`; Submitted = pending và đã submitted; Approved = active. Request Changes = changes_requested, Rejected = rejected. `suspended_at` là trục độc lập, không thay approval_status.

Tutor sửa Draft/Changes Requested; submit đưa về Submitted. Admin quyết định Submitted → Approved/Changes Requested/Rejected với reason/audit. Submitted/Approved/Rejected khóa chỉnh sửa theo baseline. Không mở rộng quyền sửa Approved trong task Marketplace.

## Listing cần chốt A3

Schema/FE cũ: `draft/active/inactive/cancelled`. Thiết kế đích: Draft/Active/Paused/Hidden/Archived. Không dùng `inactive` đồng thời cho Tutor pause và Admin hide. A3 phải chốt enum/mapping, action owner/Admin, migration/check và DTO trong cùng slice trước B3 tích hợp.

## Program và Invitation cần chốt W2

Program đích: Draft → Active (accept), Active → Paused/Completed/Cancelled; Paused → Active/Completed/Cancelled; terminal không reopen. Draft có thể cancel theo contract được duyệt.

Invitation Pending → Accepted/Rejected/Cancelled/Expired. Chỉ đúng actor, đúng target và chưa hết hạn được quyết định; Accepted terminal. Wire cũ `active=Accepted`: giữ hoặc migrate phải ghi ADR, không đổi riêng FE.

## Workspace và Trust

Session UI đích Scheduled/Completed/Cancelled trên hai bảng hiện có; không expose HTTP sessions. Task UI Not Started/Submitted/Completed/Cancelled; mapping `todo/in_progress` và resubmission phải chốt trước W3. Fee đích DECLARED/TUTOR_MARKED_PAID/UNPAID khác schema declared/confirmed/cancelled; Report đích OPEN/RESOLVED/DISMISSED khác pending/reviewing/resolved/dismissed.

Invalid/stale transition trả 409 và không side effect. Enum đích chưa được duyệt wire phải giữ ở open questions, không tự ghi là đã implemented.
