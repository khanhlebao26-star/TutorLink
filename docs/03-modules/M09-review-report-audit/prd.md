# PRD Review Report and Audit

FR-12, BR-13/15. Customer member tạo Review một lần sau Completed, rating 1–5, body validation. Public rating chỉ tổng hợp Review hợp lệ, không seeded fake rating trong API mode.

W1 closeout chỉ triển khai GET `/api/v1/tutors/{slug}/reviews` theo Completed gate, bounded pagination và aggregate toàn tập. PostgreSQL chưa cho Program completed nên API hiện rỗng200; không sửa CHECK ở W1/W2. Hoàng đã chọn defer lifecycle/migration và Review write tới W3-C3. Nhánh populated được kiểm SQLite; hai PostgreSQL tests skip có lý do, không tính là Pass. W3-C3 tái dùng endpoint, thêm visibility/moderation predicate theo schema mới và bỏ skip bằng populated PG tests thật trước khi công khai dữ liệu. [Contract](../../plans/w1-c3-closeout-contract.md).

User report resource_type allow-list, reason_code/description; Admin permission riêng list/detail/evidence và resolve/dismiss với resolution_note. Đích OPEN/RESOLVED/DISMISSED cần schema/wire mapping từ trạng thái cũ. Audit actor/resource/before/after/reason/time cùng transaction; API không sửa/xóa audit.

AC-G3-06: Active/Cancelled/outsider không review; duplicate Review bị chặn cả race; report cross-resource/private evidence denied; list không trả evidence đầy đủ; quyết định sai state 409; audit failure rollback; public không lộ reporter/verification. File evidence chỉ truy cập trong phạm vi được cấp, không suy ra từ Admin role.
