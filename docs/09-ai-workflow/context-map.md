# Context map cho AI

Luôn đọc: docs/README, 08-project/current-status, 01-product/product-scope, 09-ai-workflow/working-rules; AGENTS.md áp dụng và live git status. Sau đó chọn task-specific context, không nạp cả repo vào prompt.

- Auth/Profile/Admin: M01/M02/M10 PRD; WF01; BR-01…04/14/15; security; Auth/File/Admin controllers/resources/requests/tests và OpenAPI liên quan.
- A3/B3 Marketplace: M03 PRD; WF02; BR-02/05/14; OQ01…03; state/API/data/UI design system; catalog seed/Listing schema/OpenAPI, marketplace pages/services/types.
- C3/G1: release gates/scenarios/evidence; WF01–02; current gaps; Messaging migration/ADR002. Không implement Messaging ngoài task C3 chuẩn bị.
- Messaging: M04/M10; WF03; ADR002; BR-06/07/14/15; cursor/key/member schema + client polling.
- Program/invitation: M05; WF04; lifecycle/state; ADR003; OQ04/05; BR-08/09/15; PG constraints/lock harness.
- Workspace/Task: M06/M07/M08; WF05; BR-10/11/12/14; submission/version and Session DTO delta; UI/capability.
- Review/Report/Notification: M08/M09; WF06; BR-13/15; evidence/permission/audit and queue/scheduler.
- W4/release: NFR toàn bộ, WF01–06, traceability/scenarios/gates, deployment/restore and relevant tests.

Read only relevant installed framework docs before code per AGENTS; phiên bản Next/Laravel phải lấy từ repo. Không browse ví dụ framework khác để thay convention codebase. Nguồn code luôn đối chiếu routes/migrations, không chỉ README cũ.
