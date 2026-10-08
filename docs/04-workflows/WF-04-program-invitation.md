# WF04 Draft tới Program Active

Actor: Approved Tutor, Customer target, Customer khác. Tiền điều kiện: schema/state/money mapping được duyệt; disposable PostgreSQL cho race.

1. Tutor tạo Draft đúng target, source Listing và offer; lưu Program ID.
2. Send Invitation tạo snapshot/version bất biến và Pending duy nhất.
3. Mô phỏng send fail sau Draft success: UI giữ Draft, retry send trên cùng Program.
4. Customer mở detail, kiểm snapshot/expiry; accept.
5. Transaction tạo Customer member và Active một lần; notification chỉ xuất hiện sau commit.

Nhánh lỗi: wrong target, expired/rejected/cancelled/accepted lại, đổi Listing không đổi snapshot, đổi Pending offer bị chặn. Race accept/accept, accept/cancel, accept/expire, accept/suspend phải dùng hai connection PostgreSQL và invariant sau commit.

Evidence AC-G2-03/04/05: request replay, DB counts, trạng thái/membership cùng transaction, failure injection và queue retry. Không test lock bằng SQLite rồi tuyên bố PostgreSQL race pass.
