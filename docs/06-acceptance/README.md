# Acceptance hub

Đây là hệ thống tiêu chí, không là test report đã chạy. Đọc [traceability](traceability-matrix.md), [scenarios](test-scenarios.md), [gates](release-gates.md), [evidence template](evidence-template.md).

Task trạng thái Planned → In Progress → Implemented → Verified → Accepted. Implemented cần diff/contract; Verified cần test đúng scope và evidence; Accepted cần reviewer/người nghiệm thu đồng ý. Blocked là trạng thái phụ có lý do, không thay Verified bằng lời hứa.

Backend suite cũ 34/34 và FE checks là evidence lịch sử trong proposal; không được copy sang một commit mới rồi ghi “đã test”. AC-Gx là mã docs mới, không thay 120 mã testcase trong backlog Word; handoff phải map test thực tế hoặc backlog ID khi thực thi.
