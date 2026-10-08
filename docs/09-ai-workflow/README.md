# AI working workflow

Đây là quy trình quản lý trợ lý phát triển dùng cho cả Khanh/Dũng/Hoàng. Docs là context theo task, không phải lý do giao AI tự làm toàn bộ backlog.

1. Người giao việc xác định issue/plan slice và điền [task brief](task-template.md).
2. AI đọc [rules](working-rules.md), [context map](context-map.md), baseline và tài liệu module/workflow liên quan.
3. AI báo hiểu scope, delta/open question, files dự kiến và test plan trước code. Chỉ hỏi khi quyết định thực sự đổi phạm vi/hành vi.
4. Implement scoped change khi được yêu cầu; update contract/docs/test cùng slice; giữ dirty worktree.
5. Verify đúng risk/môi trường; ghi limitation và evidence. Không tự nhận Accepted.
6. Reviewer đọc [handoff](handoff-template.md), đối chiếu AC và quyết định Accepted hoặc rework.

[Prompts](prompts.md) có startup/implementation/review/acceptance mẫu. Chưa bật auto-loader root AGENTS/rules trong lượt này; nhóm phải đưa startup prompt hoặc yêu cầu AI đọc docs khi bắt đầu chat. Đây là lựa chọn portable theo yêu cầu folder docs, không đảm bảo mọi công cụ AI tự nạp Markdown.
