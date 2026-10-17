# Acceptance hub

Đây là hệ thống tiêu chí, không là test report đã chạy. Đọc [traceability](traceability-matrix.md), [scenarios](test-scenarios.md), [gates](release-gates.md), [evidence template](evidence-template.md).

Task trạng thái Planned → In Progress → Implemented → Verified → Accepted. Implemented cần diff/contract; Verified cần test đúng scope và evidence; Accepted cần reviewer/người nghiệm thu đồng ý. Blocked là trạng thái phụ có lý do, không thay Verified bằng lời hứa.

Backend suite cũ 34/34 và FE checks là evidence lịch sử trong proposal; không được copy sang một commit mới rồi ghi “đã test”. AC-Gx là mã docs mới, không thay 120 mã testcase trong backlog Word; handoff phải map test thực tế hoặc backlog ID khi thực thi.

Bàn giao theo issue: [#22 — Auth và Tutor onboarding theo Figma](issue-22-figma-layout.md), gồm link/frame Figma, khác biệt MVP, cách dùng component chung và evidence browser/quality của phần thay đổi hiện tại.

Thay đổi layout tiếp theo: [Full-width page layout](full-width-layout.md), theo yêu cầu chuyển các khung trang centered container sang full-width; có evidence riêng cho 360/768/1536/2560px.

Nghiệm thu tích hợp hiện tại: [#16 — W1-C3 G1 và Messaging](issue-16-w1-c3.md), baseline `48dc7f8`, ngày 15/10/2026: luồng thật đã kiểm, gate đề xuất Fail với dependency Blocked; có evidence browser/API/DB, isolation và UI riêng.

Lượt sửa và retest sau baseline: [#16 — W1 closeout](issue-16-closeout.md): search hai driver, reviews GET theo Completed đã defer lifecycle, owner metadata/actions, Tutor links, Settings tối thiểu và responsive. Functional checks đã Verified; G1 tổng còn chờ visual/reviewer và CI remote, chưa Accepted.
