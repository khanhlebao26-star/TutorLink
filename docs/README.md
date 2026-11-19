# TutorLink documentation

The Markdown files are the local working documentation for TutorLink. Modules retains its M01–M10 subfolders, and embedded images are in local `assets/` folders. Read [AGENTS.md](AGENTS.md) for the maintenance workflow.

## Realtime, email queue and cache — November 19, 2026

The frontend now subscribes to the open conversation's session-scoped Reverb channel and resynchronizes messages through the authorized HTTP API. Reconnect, logout, hidden/offline handling and polling fallback are implemented; inbox and badge outside that conversation still poll. See the [frontend configuration](../frontend/README.md#realtime-configuration).

Account verification/password-reset emails use encrypted jobs on `emails`, dispatched after commit, with eligibility/token checks at delivery. Local and production worker configurations include an email worker; use `QUEUE_CONNECTION=database`. Catalog cache TTL is 300 seconds, marketplace TTL remains 60 seconds. Explicit anonymous catalog/listing reads omit credentials via Fetch. A bounded rebuild lock reduces duplicate cache generation while preserving the database invalidation and document-expiry gates.

Implementation evidence and limitations: [optimization report, phase 2](handover/TutorLink-server-optimization-2026-11-19.md#8-realtime-email-queue-and-cache-implementation).

## API documentation

- [Trạng thái tích hợp frontend](../frontend/README.md#trạng-thái-tích-hợp) ghi rõ màn hình nào dùng API, dữ liệu nào được mô phỏng và nội dung nào được viết sẵn.
- Chat hiện gửi được văn bản và tệp đính kèm qua API. Tham khảo [client nhắn tin](../frontend/src/lib/data/messaging.ts) và [quy tắc nhận tin nhắn](../backend/app/Http/Requests/SendTextMessageRequest.php). Quyết định chat chỉ có văn bản trong [bàn giao K03.1](https://github.com/khanhlebao26-star/TutorLink/blob/17cc0df25c7d61313981d38bc9751b80b395a325/docs/handover/BE-K03-K03.1-contract.md) mô tả phạm vi của đợt bàn giao tháng 10.

- [API Route Catalog](<Architecture & Technical Design/API Route Catalog.md>) is the canonical method/path inventory for the target API; all listed routes remain in scope. **Đã đăng ký** means the method/path appears in generated OpenAPI, with runtime-verified exceptions for the Sanctum CSRF bootstrap and `POST /broadcasting/auth` outside `/api/v1`; it does not certify all PRD behavior. **Dự kiến** means the route is planned and may still have policy details to settle before implementation.
- [OpenAPI](api/README.md) liệt kê 139 thao tác đã đối chiếu với [route Laravel](../backend/routes/api.php), gồm yêu thích, hồ sơ học tập, quiz và thông báo. Kết quả đối chiếu xác nhận method và đường dẫn; xem bảng tích hợp frontend để biết màn hình nào gọi API.
- [Business Rules](<Domain Documentation/Business Rules.md>) and the module PRDs define intended behavior. A policy decision can precede backend implementation. The [OpenAPI 0.2.0 draft guide](<Architecture & Technical Design/TutorLink — OpenAPI 0.2.0 draft - hướng dẫn, thay đổi và điểm cần chốt.md>) is a historical target snapshot, not the current generated contract.
- [Decision register](UNRESOLVED_DECISIONS_2026-10-20.md) records the Product rules approved through 21/10/2026 and distinguishes them from implementation gaps. BR-23–BR-27 close the five groups previously listed as open; this does not certify API or migration completion.

**Realtime 30/10/2026:** [K03.13](https://github.com/khanhlebao26-star/TutorLink/blob/8e7cdaf674f7c2a8927a243eac86a5ce1bf0f004/docs/handover/BE-K03-K03.13-test-report.md) kiểm backend bằng session/CSRF và WebSocket thật, gồm channel theo phiên, logout/suspend/restore, reconnect/resync và outage Reverb/worker. Bản OpenAPI thời điểm K03.13 có 70 thao tác. Các mốc sau đó có 89 thao tác trước D04, 105 sau D04, 122 sau H04 và 123 sau khi thêm dashboard H05. [Bàn giao BE-D04](handover/BE-D04-handover.md) tổng hợp kết quả đối chiếu và kiểm thử trên môi trường test và local.

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

- [FE phase 2 — bổ sung code và quality setup](handover/FE-K02-D02-H02-handover.md): Session/Task/notification/trust, Browser/E2E config; chưa nghiệm thu runtime hoặc coverage.

- [Điều phối test FE 09/11/2026](handover/FE-test-coordination-2026-11-09.md): ba lane Tutor/Customer/Admin, runtime PostgreSQL/Mailpit cô lập và evidence kiểm tra chung; xem trạng thái từng case, không suy Gate FE Pass từ readiness hoặc build.

## Docker hiện hành

- `docker-compose.yml`: môi trường local chính (backend/frontend/PostgreSQL/Reverb và công cụ local).
- `compose.workers.yml`: bổ sung outbox worker, Slack worker và scheduler khi cần chạy các job nền.
- `compose.testing.yml` + `backend/Dockerfile.testing`: kiểm thử PostgreSQL cô lập; không dùng database local làm database test.
- Cấu hình browser test cổng 3001/8001 trong [báo cáo điều phối tháng 11](handover/FE-test-coordination-2026-11-09.md) thuộc đợt kiểm thử trước. Các bước E2E trong CI hiện nằm ở [workflow frontend](../.github/workflows/fe-phase2-quality.yml).
- `compose.production.yml` + `backend/Dockerfile.production`, `.dockerignore` riêng và `backend/docker/production/`: bộ deploy/backup/rollback production, còn được runbook sử dụng.
- `backend/Dockerfile`, `backend/.dockerignore`, `backend/docker/uploads.ini`, `docker/pgadmin/servers.json`: file phụ thuộc của các cấu hình trên.

Dọn ngày 05/11/2026: bỏ bốn Compose override theo từng lần chạy BE-D04/BE-D06 (`compose.be-d04.fixtures.testing.yml`, `compose.be-d04.testing.yml`, `compose.d06.runtime.testing.yml`, `compose.d06.testing.yml`) và `frontend/.dockerignore` không có build context sử dụng. Các override cũ chỉ gắn evidence/storage/port của đợt chạy lịch sử, không phải entrypoint runtime hiện tại. Các lệnh/file name trong evidence cũ chỉ ghi nhận cấu hình tại thời điểm tạo báo cáo; không sửa lại evidence. Có thể khôi phục file lịch sử từ Git nếu cần tái hiện chính xác đợt chạy đó.

Việc xóa file cấu hình không xóa container, image hay volume. Các file `.env` local và dữ liệu PostgreSQL/storage được giữ nguyên.
