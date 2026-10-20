# TutorLink documentation

The Markdown files are the local working documentation for TutorLink. Modules retains its M01–M10 subfolders, and embedded images are in local `assets/` folders. Read [AGENTS.md](AGENTS.md) for the maintenance workflow.

## API documentation

- [API Route Catalog](<Architecture & Technical Design/API Route Catalog.md>) is the canonical method/path inventory for the target API; all listed routes remain in scope. **Đã đăng ký** means the method/path appears in generated OpenAPI (or is the Sanctum CSRF bootstrap); it does not certify PRD behavior. **Dự kiến** means the route is planned and may still have policy details to settle before implementation.
- [Generated OpenAPI](api/README.md) describes the registered `/api/v1` API from backend code. Use it for current integration; regenerate with Scramble after code changes. It does not validate Product/Domain rules by itself.
- [Business Rules](<Domain Documentation/Business Rules.md>) and the module PRDs define intended behavior. A policy decision can precede backend implementation. The [OpenAPI 0.2.0 draft guide](<Architecture & Technical Design/TutorLink — OpenAPI 0.2.0 draft - hướng dẫn, thay đổi và điểm cần chốt.md>) is a historical target snapshot, not the current generated contract.
- [Unresolved decisions (20/10/2026)](UNRESOLVED_DECISIONS_2026-10-20.md) consolidates remaining Product/technical questions after the cross-document audit; its recommendations are not approved API behavior.

## Folder map

- **Workflow** — 6 Google Docs — [Google Drive source](https://drive.google.com/drive/folders/1k-yDi5blMbdVc2vUlklc-m7t4l8b1TJB)
- **Product Document** — 4 Google Docs — [Google Drive source](https://drive.google.com/drive/folders/1_DFysJNwDGwlpDzmJiADvDctlwLj-Z9G)
- **Architecture & Technical Design** — 4 Google Docs — [Google Drive source](https://drive.google.com/drive/folders/1_mXGrauKG9fPKq0ew2ufWkJKpx-F59wV)
- **Domain Documentation** — 5 Google Docs — [Google Drive source](https://drive.google.com/drive/folders/15R7_-4ivPZvXsuYN_axqkqSvgD3_Ig9n)
- **Modules Documentation** — 71 Google Docs — [Google Drive source](https://drive.google.com/drive/folders/1T_d6ESJJUN5acqykfmrM1B9f3_-SMxxf)
- **Non-functional Requirements** — 6 Google Docs — [Google Drive source](https://drive.google.com/drive/folders/1fx9Zs11olxUf4nVOFG5CNxdpT5xo_VIr)

Architecture also contains `Other source files.md` with links to its five non-Google-Docs files.
