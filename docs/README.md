# TutorLink Documentation Hub

Bộ tài liệu dùng cho nhóm và AI từ W1 đến G4. Đồng bộ ngày 15/10/2026 theo [W1 closeout](06-acceptance/issue-16-closeout.md): Settings tối thiểu, Auth giữ nguyên và reviews GET theo Completed gate; lifecycle defer W3. Base 48dc7f8 và working-tree changes chưa commit/push; Verified local không thay Accepted hoặc CI remote.

## Bắt đầu một task

1. Đọc [trạng thái hiện tại](08-project/current-status.md), [phạm vi](01-product/product-scope.md) và [quy tắc AI](09-ai-workflow/working-rules.md).
2. Chọn tài liệu theo [context map](09-ai-workflow/context-map.md); đọc PRD module, workflow và các business rule liên quan.
3. Điền [task brief](09-ai-workflow/task-template.md), xác định owner/reviewer, file được sửa và test trước khi code.
4. Đối chiếu code, OpenAPI và migration. Nếu khác thiết kế, ghi delta và xin quyết định khi cần; không âm thầm mở rộng scope.
5. Bàn giao bằng [handoff](09-ai-workflow/handoff-template.md) và [evidence](06-acceptance/evidence-template.md). Chỉ người nghiệm thu mới đổi Accepted.

## Cấu trúc

- [01 Product](01-product/product-overview.md): bài toán, mục tiêu, vai trò và giới hạn MVP.
- [02 Domain](02-domain/domain-overview.md): thực thể, vòng đời, trạng thái, business rules.
- [03 Modules](03-modules/README.md): 10 module TutorLink; mỗi module có README điều hướng và PRD triển khai.
- [04 Workflows](04-workflows/README.md): luồng xuyên module và nhánh lỗi.
- [05 Non functional requirements](05-non-functional-requirements/README.md): bảo mật, tải, phục hồi, khả dụng và triển khai.
- [06 Acceptance](06-acceptance/README.md): requirement → test → evidence → gate.
- [07 Architecture](07-architecture/README.md): kiến trúc, API/data, design system và ADR.
- [08 Project](08-project/current-status.md): baseline, phân công, ràng buộc và câu hỏi cần chốt.
- [09 AI workflow](09-ai-workflow/README.md): cách giao việc, đọc context, thực thi và báo cáo.

## Nguồn và cách xử lý mâu thuẫn

Proposal [v3.1](../outputs/planning-update-2026-10-07/TutorLink_design_proposal_v3_1.docx) là nguồn thiết kế sản phẩm. [Kế hoạch v3.1](../outputs/planning-update-2026-10-07/Ke_hoach_xay_dung_TutorLink_v3_1.docx) và [backlog W1-A3 đến W4](../outputs/planning-update-2026-10-07/TutorLink_Issues_W1_A3_den_W4.docx) là nguồn phân công. Giữ tên file/folder 07/10; nội dung liên quan đã đồng bộ 15/10 theo [quyết định closeout](plans/w1-c3-closeout-contract.md). Các đoạn historical/evidence cũ vẫn giữ thời điểm của chúng.

Yêu cầu trực tiếp đã được nhóm xác nhận quyết định phạm vi task. Proposal/decision được duyệt quyết định hành vi đích; code và migration chứng minh hành vi đã triển khai; test/evidence chứng minh hành vi đã kiểm chứng. Không lấy code sai làm lý do sửa yêu cầu, cũng không coi thiết kế là feature đã chạy.

Giữ nguyên tài liệu cũ tại [API](api/openapi.yaml), [conventions](api/conventions.md), [database](database/w1-a1-database-schema.md), [contracts](contracts/w1-c1-business-contracts.md), [setup](setup.md) và `plans/`. Snapshot cũ có điểm lệch đã ghi trong [open questions](08-project/open-questions.md); không dùng riêng một file cũ để quyết định state mới.

## Quy ước duy trì

BR-xx là business rule; FR-xx là nhóm yêu cầu; AC-Gx-xx là kịch bản nghiệm thu mới trong bộ docs này. Chúng không phải GitHub issue ID và không thay mã test trong backlog Word. Mỗi PR phải liên kết mã tương ứng và test thực tế.

Thư mục docs không tự khiến mọi AI đọc tài liệu. Dùng [startup prompt](09-ai-workflow/prompts.md) khi mở chat mới. Lượt này không thêm root `AGENTS.md`, không sửa `.cursor/rules`, không thay các `AGENTS.md` sẵn có.
