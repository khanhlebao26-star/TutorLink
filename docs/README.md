# TutorLink documentation

The Markdown files are the local working documentation for TutorLink. Modules retains its M01–M10 subfolders, and embedded images are in local `assets/` folders. Read [AGENTS.md](AGENTS.md) for the maintenance workflow.

## API documentation

- [API Route Catalog](<Architecture & Technical Design/API Route Catalog.md>) is the canonical method/path inventory for the target API; all listed routes remain in scope. **Đã đăng ký** means the method/path appears in generated OpenAPI (or is the Sanctum CSRF bootstrap); it does not certify PRD behavior. **Dự kiến** means the route is planned and may still have policy details to settle before implementation.
- [Generated OpenAPI](api/README.md) describes the registered `/api/v1` API from backend code. Use it for current integration; regenerate with Scramble after code changes. It does not validate Product/Domain rules by itself.
- [Business Rules](<Domain Documentation/Business Rules.md>) and the module PRDs define intended behavior. A policy decision can precede backend implementation. The [OpenAPI 0.2.0 draft guide](<Architecture & Technical Design/TutorLink — OpenAPI 0.2.0 draft - hướng dẫn, thay đổi và điểm cần chốt.md>) is a historical target snapshot, not the current generated contract.
- [Decision register](UNRESOLVED_DECISIONS_2026-10-20.md) records the Product rules approved through 21/10/2026 and distinguishes them from implementation gaps. BR-23–BR-27 close the five groups previously listed as open; this does not certify API or migration completion.

## Folder map

- **Workflow** — 6 Google Docs — [Open folder](<Workflow>)
- **Product Document** — 4 Google Docs — [Open folder](<Product Document>)
- **Architecture & Technical Design** — 4 Google Docs — [Open folder](<Architecture & Technical Design>)
- **Domain Documentation** — 5 Google Docs — [Open folder](<Domain Documentation>)
- **Modules Documentation** — 71 Google Docs — [Open folder](<Modules Documentation>)
- **Non-functional Requirements** — 6 Google Docs — [Open folder](<Non-functional Requirements>)

Architecture also contains `Other source files.md` with links to its five non-Google-Docs files.

## Issue handover

- [BE-D01 — Issue #36: tổng kết và nghiệm thu database](handover/BE-D01-issue-36-handover.md) là báo cáo chính thay cho năm báo cáo Task 1–5. Bản chi tiết cũ vẫn có trong lịch sử Git tại commit `6cc1d0b2dfbea701bae7a5617c9c313c5b75270c`.
