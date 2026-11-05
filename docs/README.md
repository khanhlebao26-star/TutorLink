# TutorLink documentation

The Markdown files are the local working documentation for TutorLink. Modules retains its M01–M10 subfolders, and embedded images are in local `assets/` folders. Read [AGENTS.md](AGENTS.md) for the maintenance workflow.

## API documentation

- **Điều chỉnh 29/10/2026:** [K03.1](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.1-contract.md) thay yêu cầu attachment chat và antivirus tự động trước đây: chat chỉ text; File complete là kiểm tra kỹ thuật, CV duyệt nội dung riêng. [K03.7a](https://github.com/khanhlebao26-star/TutorLink/blob/0bebbb574ec962dde3c02a54703c96b9a05553a4/docs/handover/BE-K03-K03.7a-test-report.md) ghi runtime đã gỡ ClamAV; [K03.7b](https://github.com/khanhlebao26-star/TutorLink/blob/3d9621d42dadb2f267d0df79aaa49c13290bd1e1/docs/handover/BE-K03-K03.7b-test-report.md) đã kiểm tra kỹ thuật giới hạn chung 5.000.000 byte cho frontend/upload/complete. Báo cáo K03.7 cũ chỉ là evidence lịch sử.

- [API Route Catalog](<Architecture & Technical Design/API Route Catalog.md>) is the canonical method/path inventory for the target API; all listed routes remain in scope. **Đã đăng ký** means the method/path appears in generated OpenAPI, with runtime-verified exceptions for the Sanctum CSRF bootstrap and `POST /broadcasting/auth` outside `/api/v1`; it does not certify all PRD behavior. **Dự kiến** means the route is planned and may still have policy details to settle before implementation.
- [Generated OpenAPI](api/README.md) describes the registered `/api/v1` API from backend code. Use it for current integration; regenerate with Scramble after code changes. It does not validate Product/Domain rules by itself.
- [Business Rules](<Domain Documentation/Business Rules.md>) and the module PRDs define intended behavior. A policy decision can precede backend implementation. The [OpenAPI 0.2.0 draft guide](<Architecture & Technical Design/TutorLink — OpenAPI 0.2.0 draft - hướng dẫn, thay đổi và điểm cần chốt.md>) is a historical target snapshot, not the current generated contract.
- [Decision register](UNRESOLVED_DECISIONS_2026-10-20.md) records the Product rules approved through 21/10/2026 and distinguishes them from implementation gaps. BR-23–BR-27 close the five groups previously listed as open; this does not certify API or migration completion.

**Realtime 30/10/2026:** [K03.13](https://github.com/khanhlebao26-star/TutorLink/blob/8e7cdaf674f7c2a8927a243eac86a5ce1bf0f004/docs/handover/BE-K03-K03.13-test-report.md) kiểm backend bằng session/CSRF và WebSocket thật: channel theo phiên, logout/suspend/restore, reconnect/resync và outage Reverb/worker. Artifact tại thời điểm K03.13 có 70 operations; trước D04 có 89 sau 11 route BE-D03, hai route reject/cancel BE-H03 và sáu route Session BE-K04. Snapshot D04 có 105 operations sau khi thêm 16 route BE-D04; artifact hiện hành có **123**: 89 nền + 16 BE-D04 + 17 BE-H04 + dashboard BE-H05. Artifact 122 là snapshot trước dashboard H05. D04-18 ghi nhận export/parity/lint ở test; [BE-D04 handover](handover/BE-D04-handover.md) tổng hợp thêm kết quả local từ D04-21..24. Không có staging/production hoặc UI Workspace acceptance trong scope đã nghiệm thu.

## Folder map

- **Workflow** — 6 Google Docs — [Open folder](<Workflow>)
- **Product Document** — 4 Google Docs — [Open folder](<Product Document>)
- **Architecture & Technical Design** — 4 Google Docs — [Open folder](<Architecture & Technical Design>)
- **Domain Documentation** — 5 Google Docs — [Open folder](<Domain Documentation>)
- **Modules Documentation** — 71 Google Docs — [Open folder](<Modules Documentation>)
- **Non-functional Requirements** — 6 Google Docs — [Open folder](<Non-functional Requirements>)

Architecture also contains `Other source files.md` with links to its five non-Google-Docs files.

## Issue handover

- [BE-H06 — Gate BE](handover/BE-H06-gate-be.md): kiểm thử local ngày 05/11/2026, 832 test, coverage 94,26%; quyết định **Blocked**, FE giữ Blocked tới khi đủ merge/CI/owner confirmation và ba chữ ký.

- [BE-D06 — Contract kiểm chứng và phát hành API](handover/BE-D06-contract.md) · [Plan và tiêu chí hoàn thành](handover/BE-D06-plan.md): baseline `d157a8e`, 123 operations; inventory/metadata, PostgreSQL tests/coverage, fixture FE và release snapshot. Đây là đầu vào triển khai, chưa nghiệm thu D06.

- [BE-D05 + BE-H05 — Phase 5](handover/BE-D05-BE-H05-handover.md): marketplace gate/cache/query và backend quản trị dùng chung; thêm dashboard aggregate, chưa mở UI Filament; xem evidence và giới hạn local trong bàn giao.

- [BE-H04 — Review, Report và Audit](handover/BE-H04-test-report.md): 17 API mới, OpenAPI 122 operations, migration/permission và bằng chứng kiểm thử theo nhóm. Chờ review; không chứng nhận production.

- [BE-K04 — Sessions planned/actual và replay](handover/BE-K04-handover.md): sáu API Session, hai migration, policy/version/replay, 109 test liên quan và 625 test toàn backend với 17 race cases PostgreSQL. K04.7 đồng bộ OpenAPI/Catalog/schema và chuẩn bị bàn giao; chờ Hoàng review, chưa chứng nhận staging/production.

- [BE-H03 — Issue #75: Invitation và lifecycle Program](handover/BE-H03-issue-75-test-report.md): reject/cancel, scheduler expiry, PostgreSQL races/rollback, Task integration và OpenAPI 83 operations. Chờ review; chưa triển khai production.

- [BE-K03 — K03.14 bàn giao backend](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.14-handover.md): code `8e7cdaf`, scope/API-schema delta, evidence và [runbook review](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.14-runbook.md). Đã chuẩn bị — chờ Hoàng review; Echo/UI hoãn K03.12, Task/Submission/CV/cleanup/production chưa được chứng nhận. Publication/review chưa thực hiện tự động.

- [BE-D01 — Issue #36: tổng kết và nghiệm thu database](https://github.com/khanhlebao26-star/TutorLink/blob/3512fd74f27cb1c7f7992d6d319f2706c5308d90/docs/handover/BE-D01-issue-36-handover.md) là báo cáo chính thay cho năm báo cáo Task 1–5. Bản chi tiết cũ vẫn có trong lịch sử Git tại commit `6cc1d0b2dfbea701bae7a5617c9c313c5b75270c`.

- [BE-D04 — Issue #78: Program Workspace Progress, Announcement và Fee](handover/BE-D04-handover.md): test report, schema/API delta, source/build identity, test→local evidence index và dữ liệu nghiệm thu còn giữ; [test report](handover/BE-D04-test-report.md) · [plan](handover/BE-D04-plan.md). Kết quả actual chỉ áp dụng cho local Docker Compose.

- [FE-K01 / FE-D01 / FE-H01 — bàn giao code](handover/FE-K01-D01-H01-handover.md): Program/Invitation/chat, onboarding, Filament/Blade; phiên bản cuối chờ người dùng tự test và reviewer nghiệm thu.
