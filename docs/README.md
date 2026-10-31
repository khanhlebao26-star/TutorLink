# TutorLink documentation

The Markdown files are the local working documentation for TutorLink. Modules retains its M01–M10 subfolders, and embedded images are in local `assets/` folders. Read [AGENTS.md](AGENTS.md) for the maintenance workflow.

## API documentation

- **Điều chỉnh 29/10/2026:** [K03.1](handover/BE-K03-K03.1-contract.md) thay yêu cầu attachment chat và antivirus tự động trước đây: chat chỉ text; File complete là kiểm tra kỹ thuật, CV duyệt nội dung riêng. [K03.7a](handover/BE-K03-K03.7a-test-report.md) ghi runtime đã gỡ ClamAV; [K03.7b](handover/BE-K03-K03.7b-test-report.md) đã kiểm tra kỹ thuật giới hạn chung 5.000.000 byte cho frontend/upload/complete. Báo cáo K03.7 cũ chỉ là evidence lịch sử.

- [API Route Catalog](<Architecture & Technical Design/API Route Catalog.md>) is the canonical method/path inventory for the target API; all listed routes remain in scope. **Đã đăng ký** means the method/path appears in generated OpenAPI, with runtime-verified exceptions for the Sanctum CSRF bootstrap and `POST /broadcasting/auth` outside `/api/v1`; it does not certify all PRD behavior. **Dự kiến** means the route is planned and may still have policy details to settle before implementation.
- [Generated OpenAPI](api/README.md) describes the registered `/api/v1` API from backend code. Use it for current integration; regenerate with Scramble after code changes. It does not validate Product/Domain rules by itself.
- [Business Rules](<Domain Documentation/Business Rules.md>) and the module PRDs define intended behavior. A policy decision can precede backend implementation. The [OpenAPI 0.2.0 draft guide](<Architecture & Technical Design/TutorLink — OpenAPI 0.2.0 draft - hướng dẫn, thay đổi và điểm cần chốt.md>) is a historical target snapshot, not the current generated contract.
- [Decision register](UNRESOLVED_DECISIONS_2026-10-20.md) records the Product rules approved through 21/10/2026 and distinguishes them from implementation gaps. BR-23–BR-27 close the five groups previously listed as open; this does not certify API or migration completion.

**Realtime 30/10/2026:** [K03.13](handover/BE-K03-K03.13-test-report.md) kiểm backend bằng session/CSRF và WebSocket thật: channel theo phiên, logout/suspend/restore, reconnect/resync và outage Reverb/worker. Artifact tại thời điểm K03.13 có 70 operations; artifact hiện tại có 83 sau 11 route BE-D03 và hai route reject/cancel BE-H03. Echo/UI K03.12 hoãn; consumer probe không thay thế nghiệm thu giao diện.

## Folder map

- **Workflow** — 6 Google Docs — [Open folder](<Workflow>)
- **Product Document** — 4 Google Docs — [Open folder](<Product Document>)
- **Architecture & Technical Design** — 4 Google Docs — [Open folder](<Architecture & Technical Design>)
- **Domain Documentation** — 5 Google Docs — [Open folder](<Domain Documentation>)
- **Modules Documentation** — 71 Google Docs — [Open folder](<Modules Documentation>)
- **Non-functional Requirements** — 6 Google Docs — [Open folder](<Non-functional Requirements>)

Architecture also contains `Other source files.md` with links to its five non-Google-Docs files.

## Issue handover

- [BE-H03 — Issue #75: Invitation và lifecycle Program](handover/BE-H03-issue-75-test-report.md): reject/cancel, scheduler expiry, PostgreSQL races/rollback, Task integration và OpenAPI 83 operations. Chờ review; chưa triển khai production.

- [BE-K03 — K03.14 bàn giao backend](handover/BE-K03-K03.14-handover.md): code `8e7cdaf`, scope/API-schema delta, evidence và [runbook review](handover/BE-K03-K03.14-runbook.md). Đã chuẩn bị — chờ Hoàng review; Echo/UI hoãn K03.12, Task/Submission/CV/cleanup/production chưa được chứng nhận. Publication/review chưa thực hiện tự động.

- [BE-D01 — Issue #36: tổng kết và nghiệm thu database](handover/BE-D01-issue-36-handover.md) là báo cáo chính thay cho năm báo cáo Task 1–5. Bản chi tiết cũ vẫn có trong lịch sử Git tại commit `6cc1d0b2dfbea701bae7a5617c9c313c5b75270c`.
