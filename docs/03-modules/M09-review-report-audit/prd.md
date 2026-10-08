# PRD Review Report and Audit

FR-12, BR-13/15. Customer member tạo Review một lần sau Completed, rating 1–5, body validation. Public rating chỉ tổng hợp Review hợp lệ, không seeded fake rating trong API mode.

User report resource_type allow-list, reason_code/description; Admin permission riêng list/detail/evidence và resolve/dismiss với resolution_note. Đích OPEN/RESOLVED/DISMISSED cần schema/wire mapping từ trạng thái cũ. Audit actor/resource/before/after/reason/time cùng transaction; API không sửa/xóa audit.

AC-G3-06: Active/Cancelled/outsider không review; duplicate Review bị chặn cả race; report cross-resource/private evidence denied; list không trả evidence đầy đủ; quyết định sai state 409; audit failure rollback; public không lộ reporter/verification. File evidence chỉ truy cập trong phạm vi được cấp, không suy ra từ Admin role.
