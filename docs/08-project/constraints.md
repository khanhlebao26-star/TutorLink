# Constraints

Giữ source proposal/backlog và docs cũ; không xóa/move hoặc reset dirty worktree. Sáu thay đổi local hiện thuộc phiên trước/người dùng, không được AI gộp vào task mới thiếu chủ đích.

Một task một scoped diff/PR; shared files routes/OpenAPI/types/migrations cần phối hợp owner. Không force push/main, không destructive DB/volume cleanup, không GitHub writes/deploy/secret changes khi chưa được yêu cầu.

Feature freeze ngày 17 theo budget; tuần 4 cho nghiệm thu/handoff. Optional features không lấy quỹ E2E. UI mock không Accepted; SQLite không thay PG concurrency; suite pass không bằng toàn bộ AC.

`backend/AGENTS.md` và `frontend/AGENTS.md` vẫn áp dụng khi sửa code dưới chúng. Hướng dẫn docs không override các instruction đó hoặc yêu cầu cài hệ thống tự động; installation/setup ngoài phạm vi cần xử lý đúng authorization.
