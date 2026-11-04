# BE-D06 — Plan thực hiện và tiêu chí hoàn thành

- **Contract:** [BE-D06-contract.md](BE-D06-contract.md), baseline `d157a8eeae662c01d6bd6b031ea53c26a5b05688`, ngày 04/11/2026.
- **Issue:** [#86](https://github.com/khanhlebao26-star/TutorLink/issues/86) · **Owner:** Dũng · **Reviewer:** Khanh · **Branch:** `be-d06`.
- **Trạng thái:** D06-01–11 và D06-13 đã PASS theo evidence dưới đây. Local export/lint/parity của D06-12 đã PASS; remote CI đang chờ PR. D06-14–15 đang hoàn thiện và chờ snapshot/PR review.
- **Cách sử dụng:** Làm theo dependency; mỗi task cập nhật `Pending/In progress/Pass/Fail/Blocked`, actual result và evidence. Chỉ tick khi đạt đầy đủ tiêu chí của task. Blocker phải có owner và việc tiếp theo.

## 1. Trình tự và nguyên tắc

**Chốt baseline → dựng test → inventory/gap → sửa source metadata/contract → kiểm theo lane → fixture/examples → regression/coverage → export và runtime parity/smoke → release/review.**

Các task 01–03 lập baseline; 04–09 hoàn thiện contract trong source; 10–13 kiểm chứng; 14–15 freeze/bàn giao. Các lượt test focused có thể chạy sớm khi sửa phần liên quan; bộ cuối chạy sau thay đổi source/fixture có ảnh hưởng.

Không reset/seed DB ứng dụng. `test-suite.php` có migrate:fresh; dùng test project/DB/storage riêng. Môi trường local hiện dừng/worker restart và host vendor thiếu Telescope là blocker kiểm runtime, không phải lý do trì hoãn viết inventory/source metadata. Không coi evidence K06 hoặc lời “đã pull mới nhất” là bằng chứng runtime khớp source.

## 2. Evidence và kết quả mỗi task

Root dự kiến `docs/handover/evidence/be-d06/<run-id>/`, run ID `YYYY-MM-DD-HHMMSS` theo Asia/Ho_Chi_Minh. Chưa tạo artifact PASS trước khi chạy. Log/coverage HTML lớn có thể giữ `outputs/be-d06/` và đưa bản tổng hợp/manifest/checksum cùng evidence reviewer cần đọc vào repo.

Mỗi run lưu commit, diff/hash nếu dirty, thời gian có offset, image/PHP/DB identity, command, exit status và actual result. Case API lưu actor giả, method/path/query/body, expected/actual status/body/safe headers, `X-Request-ID`; version/replay có DB snapshot/count trước/sau. Redact cookies, CSRF/session/token/password/secret. Mock HTTP không được ghi là live smoke.

## 3. Task triển khai

- [x] **D06-01 — Chốt source và dependency baseline.** — **Pass**

  **Phụ thuộc:** Không. **Issue:** cả bốn TODO. **Contract:** §2–3.

  **Thực hiện:** Ghi HEAD/branch/status; kiểm ancestry hoặc nội dung dependency D01–D05, K04/H03/H04/H05/K06; đếm/hash OpenAPI; ghi lock version. Kiểm lại nguồn D01–D03 qua Git và các link integration bị mất. Tách source hiện tại khỏi ref remote cũ và evidence runtime cũ.

  **Đầu ra:** `baseline.md`, `source-manifest.json`, danh sách dependency/source refs và gap register.

  **Hoàn thành khi:** Nhận diện chính xác source sẽ làm; baseline count/hash tái tính được; dependency thiếu được ghi Blocked; thư mục untracked D04 ban đầu được giữ nguyên. 123 operations chỉ được ghi là số artifact. Mỗi kết luận có đường dẫn/ref kiểm chứng được.

  **Actual result (04/11/2026):** HEAD/branch/status, OpenAPI count/hash và lock versions đã ghi; các source/implementation refs D01–D05, K04, H03/H04/H05, K06 đều là ancestor của HEAD. Handover D01–D03 đọc lại được từ Git nhưng không có trong worktree; các link D01/D03/K03.1 hỏng và nguồn System Design chưa tìm thấy được ghi trong gap register, kèm owner/next step cho mục Blocked. Hash năm fixture untracked D04 khớp trước/sau. Evidence: [baseline](evidence/be-d06/2026-11-04-193718/baseline.md), [source manifest](evidence/be-d06/2026-11-04-193718/source-manifest.json), [dependency refs](evidence/be-d06/2026-11-04-193718/dependency-refs.md), [gap register](evidence/be-d06/2026-11-04-193718/gap-register.md). Không chạy test/export/HTTP smoke trong task này.

- [x] **D06-02 — Chuẩn bị môi trường kiểm chứng.** — **Pass**

  **Phụ thuộc:** 01. **Issue:** TODO 2–3. **Contract:** §3 E01/E02, §7.

  **Thực hiện:** Kiểm đủ dependency theo lock/platform trong môi trường test; dùng Compose test hiện có với project riêng như `tutorlink-d06-test`, tách storage/evidence. Chạy guard configured/connected identity trước migrate/reset. Kiểm runtime local riêng trước HTTP smoke; xử lý lỗi thiếu package/platform theo workflow dự án, không sửa lock hoặc tắt provider để né lỗi.

  **Đầu ra:** `environment.md`, guard/platform/runtime logs; kế hoạch chạy smoke vào environment cụ thể.

  **Hoàn thành khi:** Runner bootstrap được, kết nối đúng PostgreSQL test cho phép; DB ứng dụng không dùng cho suite/fixture phá dữ liệu; có baseline failure nếu còn lỗi. Runtime chưa sẵn có thể tiếp tục task source, nhưng 13/14 chưa được PASS. Không tự khởi động production hoặc gửi notification ra dịch vụ thật.

  **Actual result (04/11/2026):** Compose project `tutorlink-d06-test` có PostgreSQL 16 healthy, internal-only, tmpfs; image runner build từ lock thành công, platform checks và Laravel bootstrap đạt. Guard xác nhận đúng `tutorlink_test`/`tutorlink_test_runner` và CREATE privileges trước mọi migrate/reset; không chạy migration, reset, seed, suite hoặc HTTP smoke. Host app vẫn lỗi thiếu Telescope, host DB guard từ chối cấu hình; backend/DB local dừng và workers restart. Smoke target đã chuẩn bị trên `127.0.0.1:18006`; D06-13/14 chưa PASS cho đến khi runtime sẵn sàng và smoke thực chạy. Evidence: [environment report](evidence/be-d06/2026-11-04-195035/environment.md), [manifest](evidence/be-d06/2026-11-04-195035/environment-manifest.json), cùng các guard/platform/runtime logs trong thư mục run.

  **Runtime update (05/11/2026):** Rebuilt the `tutorlink` backend/worker images, found the existing `backend_vendor` named volume masked Telescope from the image, then synchronized dependencies from `composer.lock` into that volume. Backend passed its Compose healthcheck and the four workers stayed running with restart count 0; CSRF bootstrap returned 204. D06-13 HTTP flow has not yet been run. Evidence: [runtime rebuild diagnosis](evidence/be-d06/2026-11-05-034651/runtime-rebuild.md).

- [x] **D06-03 — Lập inventory 123 operations và ma trận gap.** — **Pass (inventory baseline)**

  **Phụ thuộc:** 01; cần 02 khi lấy route Laravel. **Issue:** TODO 1–2. **Contract:** §4.

  **Thực hiện:** So registered routes/OpenAPI/Catalog; mỗi method/path ghi owner, request/response schema, auth, lỗi, pagination, version/key và test/case. Tách planned routes và hai infrastructure routes ngoài `/api/v1`.

  **Đầu ra:** `operation-inventory.csv`, `contract-gap-register.md`.

  **Hoàn thành khi:** Không trùng/mất operation; tổng khớp export tại cùng source; mỗi gap có mức ảnh hưởng/owner/next step. Mỗi operation có evidence HTTP/test hoặc gap cần bổ sung; không chỉ ghi “đã đăng ký” thay evidence. Count thay đổi phải có API delta, không cố giữ 123.

  **Actual result (04/11/2026):** Inventory có 123 method/path duy nhất từ OpenAPI artifact, khớp 123/123 với Laravel `route:list` trong runner D06; không thiếu/dư route. Từng dòng có request/response schema của artifact, auth metadata, errors/version/retry/pagination và gap cho hành vi chưa kiểm. 15 Catalog planned routes và hai infrastructure routes được tách riêng. 67 endpoint owner chưa xác nhận có gap `D06-03-OWNER`; cả 123 dòng chưa có focused case/HTTP có gap `D06-03-BEHAVIOR`, phải đóng trước release. Không coi parity là runtime behavior PASS. Evidence: [inventory](evidence/be-d06/2026-11-04-201500/operation-inventory.csv), [gap register](evidence/be-d06/2026-11-04-201500/contract-gap-register.md), [route snapshot/provenance](evidence/be-d06/2026-11-04-201500/provenance.md), [planned/infra](evidence/be-d06/2026-11-04-201500/planned-and-infrastructure-routes.md).

- [x] **D06-04 — Hoàn thiện metadata Program/Invitation.** — **Pass**

  **Phụ thuộc:** 03. **Issue:** TODO 1. **Contract:** §4–6.

  **Thực hiện:** Rà Request/Resource/type/status/errors cho CRUD, send/read/accept/reject/cancel Invitation và lifecycle Program. Kiểm immutable offer snapshot, `customer_id`, offer version, pending lock, expiry và blockers; giữ requiredness reason theo runtime/quyết định hiện hành.

  **Đầu ra:** Source metadata/delta và focused contract test/evidence.

  **Hoàn thành khi:** Payload/default/null/enum và success status đúng; 401/403/404/409/422 theo tình huống khớp body/header thực; stale/pending lock không ghi một phần. Version guard không bị thêm nhầm vào lifecycle. Các gap nghiệp vụ chưa được chốt phải có owner, không tự thay rule.

  **Actual result (04/11/2026):** Rà 14 operations Program/Invitation và source/tests hiện có; bổ sung 403 `account_inactive` cho bốn Program lifecycle actions, làm rõ 403 Invitation list/detail; không đổi business rule hoặc thêm version guard cho lifecycle. Focused case trên PostgreSQL test sau guard đạt 1 test/5 assertions, xác nhận actor `suspended` nhận 403 và Program/version/audit/outbox không đổi. Lượt đầu lỗi fixture `inactive` trái DB check đã được sửa, log thất bại giữ và ghi rõ superseded. Reviewer không có finding source còn mở sau khi evidence được đồng bộ. `reason` reject/cancel vẫn optional/null theo runtime; **Dũng** chịu trách nhiệm lấy quyết định Product và ghi lại trước freeze. Evidence: [source delta](evidence/be-d06/2026-11-04-202539/source-delta.md), [case matrix](evidence/be-d06/2026-11-04-202539/case-matrix.md), [verification/logs](evidence/be-d06/2026-11-04-202539/verification.md). Export, full regression và HTTP smoke thuộc các gate D06-11–13, chưa được claim ở task này.

- [x] **D06-05 — Hoàn thiện metadata Task/Submission/feedback và pagination.** — **Pass (focused regression + generated contract)**

  **Phụ thuộc:** 03. **Issue:** TODO 1, 3. **Contract:** §3 G01/G05, §5–6.

  **Thực hiện:** Kiểm 11 Task/Submission operations, response shape và error branches; UUID header, version guards, current/history/feedback fields. Rà list/history đang trả toàn collection; xác minh ngoại lệ D07/BR-26 hoặc sửa implementation kèm compatibility delta nếu không có ngoại lệ được duyệt.

  **Đầu ra:** Source metadata/fix có regression tests; quyết định rõ pagination/ordering, không còn gap “chưa biết”.

  **Hoàn thành khi:** GET detail/submission/history không chỉ mô tả 200 mà bỏ các lỗi scope/auth thực; PATCH/start/cancel có stale/state errors; Submission replay trả đúng version lịch sử; feedback trỏ đúng bản; quyền kiểm trước replay. Pagination/limit trong source, JSON và hướng dẫn FE thống nhất. Không áp default 20/50 chỉ bằng annotation trong khi runtime vẫn trả tất cả.

  **Actual result (04/11/2026):** Hoàn thiện Scramble metadata cho 11 Task/Submission operations, gồm shape Task/current Submission/history/feedback, status replay (PUT 200/201), UUID `Idempotency-Key`, query parameters, auth/scope/status/error responses và version guards; generated OpenAPI được export từ source, không sửa JSON bằng tay. Catalog/FE API guide thống nhất pagination: `page` mặc định 1; `per_page` mặc định 20, tối đa 50; `page`/`per_page` sai trả 422; Task list tăng dần ID và Submission history giảm dần `submission_version`; response có `data` + `meta(current_page,per_page,total,last_page)`. Không tìm thấy ngoại lệ D07/BR-26 được duyệt nên đã sửa cả hai collection runtime. `TaskWorkflowApiTest`: 14 passed / 286 assertions trên PostgreSQL test runner; test mới xác nhận default, page 2, max 50, invalid limits, current Submission nullable fields và ordering history. OpenAPI export thành công; tổng vẫn 123 operations. Lượt đầu có một assertion test sai về trang 2 với `per_page=50`, đã sửa và rerun đạt; giữ log đầu cùng log rerun. Đây là feature-test evidence, chưa claim browser HTTP smoke. Evidence: [task metadata delta and decision](evidence/be-d06/2026-11-04-204100/task-metadata.md), [test logs](evidence/be-d06/2026-11-04-204100/), [final OpenAPI export log](evidence/be-d06/2026-11-04-204100/openapi-export-final.log).

- [x] **D06-06 — Hoàn thiện metadata Overview/Progress/Announcement/Fee.** — **Pass (focused PostgreSQL regression + generated contract)**

  **Phụ thuộc:** 03. **Issue:** TODO 1, 3. **Contract:** §4–8; contract D04.

  **Thực hiện:** Đối chiếu 16 operations D04 với contract/test; kiểm Request/Resource/type, header, status/error, integer/nullable/pagination, capability/count và file allow-list. Giữ receipt policy khác nhau của Fee và Announcement.

  **Đầu ra:** Metadata/delta, focused D04 results, mapping Fee cập nhật.

  **Hoàn thành khi:** Không mất route nền; response fields/status/headers và version/no-op đúng; `voided` độc lập payment mark; duplicate month và stale 409; Published immutable; member không đọc Draft. Fee replay/zero amount/receipt retention đúng, không suy paid từ `confirmed`.

  **Actual result (04/11/2026):** Đối chiếu đủ 16 operation Overview/Progress/Announcement/Fee. Scramble trước đó suy `AnnouncementResource.files` thành `string[]` dù runtime trả object allow-list, và response type thiếu enum `ProgressRecord.metric_name`, Fee `currency/status/tutor_payment_mark`; đã khai báo response shapes tường minh ở metadata operation và tạo lại OpenAPI. Overview giữ 6 capability booleans, 5 Task counts và nullable latest summaries; Progress `value` là integer, `note` nullable và list có page metadata; Announcement `state` enum và `files[]` chỉ gồm `id/original_name/mime_type/size_bytes/complete`; Fee trả integer `amount_minor`/VND, Fee status và payment mark độc lập. Không đổi implementation/route business behavior. Fee map ghi riêng `period` tháng → `period_start/period_end`, amount/currency, status/payment mark, version, payment/void timestamps/reasons và disclaimer; Fee write receipts không có time expiry, Announcement publish receipt 30 ngày. Feature tests xác nhận Published bất biến, member không đọc Draft, file allow-list, duplicate month/stale, zero amount, no-op/replay và Fee receipt retention. Sáu focused test files đạt 18 tests / 534 assertions trên PostgreSQL runner; OpenAPI có 123 operations và các schema assertions đạt. Evidence: [metadata delta/Fee map](evidence/be-d06/2026-11-04-205328/d04-metadata.md), [focused test logs](evidence/be-d06/2026-11-04-205328/d04-focused-tests-final.log), [final export](evidence/be-d06/2026-11-04-205328/openapi-export-final.log), [OpenAPI schema checks](evidence/be-d06/2026-11-04-205328/openapi-schema-check-final.log). Không claim full D04 group, HTTP smoke hoặc Redocly lint.

- [x] **D06-07 — Rà auth/errors và endpoint các owner khác.** — **Pass (review confirmed)**

  **Phụ thuộc:** 03, phối hợp 04–06. **Issue:** TODO 1, 4. **Contract:** §4–5.

  **Thực hiện:** Rà security global/override public, session cookie/CSRF và các response lỗi framework trên lane Dũng. Phối hợp Khanh/Hoàng cho Auth/File/Session/Messaging/outbox/Trust/dashboard; giữ policy/scope/action riêng. Nếu sửa renderer dùng chung phải kiểm lại các lane chịu ảnh hưởng.

  **Đầu ra:** Error/auth matrix theo operation, source fix nếu cần, kết quả xác minh owner còn chờ/đã nhận.

  **Hoàn thành khi:** 401/403/404/409/422/429 và 419 có case thực phù hợp; BR-26 envelope/correlation đúng cả validation/abort/throttle thuộc phạm vi release; không mất `Retry-After`, không lộ SQL/trace/secret; không biến route protected thành public. Owner chưa phản hồi ghi Pending/Blocked, không tự ghi đã được duyệt.

  **Actual result (05/11/2026):** Installed and read the official Laravel Boost `testing-best-practices` skill (see `~/.codex/skills/testing-best-practices`; rendered source also exists under `backend/.agents/skills/testing-best-practices`). Broadened `backend/bootstrap/app.php` framework rendering to the API namespace: authentication/validation/HTTP errors use the shared `code/message/request_id/errors` envelope; 403/404/405/409/419/429 codes are mapped; exception headers such as `Retry-After` are retained; API errors remain `no-store`. Regression coverage exercises 401/403/404/409/419/422/429, request-ID header/body correlation, validation errors, `Retry-After`, and existing API auth/Trust behavior. Rebuilt `compose.testing.yml` from workspace, then six focused files passed 58 tests / 488 assertions on isolated PostgreSQL. No protected route was made public. Reviewer confirmed D06-07 review is OK. Evidence: [focused D06-07/08 log](evidence/be-d06/2026-11-05-0438/focused-d06-07-08.log), [skill source](https://github.com/laravel/boost/blob/main/.ai/laravel/skill/testing-best-practices/SKILL.blade.php), [Laravel Boost docs](https://laravel.com/framework/docs/boost).

- [x] **D06-08 — Kiểm lại marketplace/cache contract D05.** — **Pass**

  **Phụ thuộc:** 03, 02 để test. **Issue:** TODO 1, 3. **Contract:** §5, §7.

  **Thực hiện:** Kiểm DTO Tutor/card/listings_meta, Admin list default/max/sort, gate eligibility và cache headers. Chạy các regression D05 về commit/rollback/expiry, fallback, public/private/no-store, N+1; chỉ bổ sung test khi có gap thực.

  **Đầu ra:** Cache/query matrix và focused report, benchmark so sánh nếu có sửa query/cache.

  **Hoàn thành khi:** Anonymous cache không đưa Tutor mất gate ra list/detail; cookie/auth requests BYPASS và private no-store; namespace chỉ có hiệu lực sau commit, expiry không trả cache lỗi thời; fallback đọc DB. Header/DTO trong docs đúng runtime. Không dùng microbenchmark cũ để tuyên bố đạt load/SLO production.

  **Actual result (05/11/2026):** Reused the current D05 regression suite; no cache/query source change was needed. Nine auth/error and Marketplace feature files passed 125 tests / 765 assertions on the isolated PostgreSQL runner. `PhaseFiveMarketplaceTest` covered eligibility gates after account/Profile/document changes, cache generation and expiry, rollback isolation, unavailable-cache DB fallback, public Tutor/card DTO and `listings_meta`, public/private cache headers, and bounded query count; `MarketplaceCacheTransactionTest` covered old-cache visibility before commit and immediate denial after commit. The earlier focused set covered Admin max `per_page` and query-count behavior but lacked default/sort assertions, which are covered in the follow-up below. No benchmark claim is made because no query/cache code changed. Evidence: [focused regression log](evidence/be-d06/2026-11-05-033404/focused-auth-cache.log).

  **Actual result (05/11/2026):** Added `AdminApprovalAuditTest` coverage for the default submitted queue, `per_page=20`, `submitted_desc` ordering with ID tie-break, alternate `id_desc`, and rejection of `per_page=51`. This ran in the fresh-image focused PostgreSQL pass above. Reused the earlier D05 regression evidence for cache generations/expiry, rollback isolation, DB fallback, public/private headers, DTOs, eligibility gates and bounded query counts: [focused D05 regression log](evidence/be-d06/2026-11-05-033404/focused-auth-cache.log). No cache/query implementation changed, so no benchmark claim is made.

- [x] **D06-09 — Đồng bộ tài liệu nguồn, D23 và legacy mapping.** — **Pass (docs/routes/mapping)**

  **Phụ thuộc:** 04–08. **Issue:** TODO 2, 4. **Contract:** §2–3, §8.

  **Thực hiện:** Sửa các ghi chú hiện hành count 122/123, recipe export, D03/D07 và link handover mất đích trong hướng dẫn release. D23 lập bảng từng route: Report evidence đã registered, sáu route còn planned theo baseline; giữ con số lịch sử trong tài liệu lịch sử. Lập wire/DB/legacy mapping kèm tác động FE, không tự backfill dữ liệu mơ hồ.

  **Đầu ra:** `field-mapping.md`, `planned-routes.md`, docs delta và source references.

  **Hoàn thành khi:** Không còn hướng dẫn release coi cả bảy D23 là absent, hoặc quảng bá planned route callable; mapping Program/Invitation/Submission/Fee/Session/Report có nguồn và trạng thái; ghi rõ nguồn System Design chưa tìm thấy nếu chưa giải quyết được. Link mới mở được; không sửa JSON sinh ra bằng tay.

  **Actual result (05/11/2026):** Current docs now state 123 operations and explain that 122 was the pre-H05 snapshot; the API export recipe already used the guarded PostgreSQL flow and was retained. D23 status is split accurately: Report evidence is registered; the six Admin users/listing/metrics routes remain planned. Added [field mapping](evidence/be-d06/2026-11-05-034651/field-mapping.md) for Program, Invitation, Submission, Fee, Session and Report, including legacy conversions, ambiguous values that must not be inferred/backfilled, and FE handling; [route status table](evidence/be-d06/2026-11-05-034651/planned-routes.md) records current route status. Updated API guide and affected M07/M10 references to commit-pinned historical reports where handover files are absent from the worktree. Checked 15 affected docs: zero broken local links. The unavailable System Design source remains recorded in the baseline gap register and mapping. No generated OpenAPI JSON was edited.

- [x] **D06-10 — Chuẩn bị fixture và ví dụ FE.** — **Pass**

  **Phụ thuộc:** 04–09. **Issue:** TODO 3–4. **Contract:** §5–8.

  **Thực hiện:** Tái sử dụng factories/TestActors/ProgramApiFixtures/D04 fixture phù hợp. Tạo manifest alias→ID và kịch bản owner/member/outsider, lifecycle, multi-version, Fee marks, cache gate, empty/paginated data. Thu examples success/errors/retry/version từ request/test thực, ghi hướng dẫn auth/419 và stale reload.

  **Đầu ra:** Fixture entry point/hướng dẫn an toàn và `fe-fixtures.md`, `examples/` có case index.

  **Hoàn thành khi:** Có thể tạo lại trên DB demo/test tách biệt; chạy lại không nhân dữ liệu trái policy; FE không cần hardcode ID/money unit/enum. Mỗi example có provenance actual và đã redact; khác biệt status replay (ví dụ Fee create 201) được ghi đúng. JSON mẫu được phân biệt với transcript. Không phụ thuộc dữ liệu cá nhân hoặc acceptance records D04 local.

  **Actual result (05/11/2026):** Added a guarded fixture-manifest entry point reusing the synthetic D04 seeder. On the isolated `tutorlink_test` database it exposes aliases for 8 actors, 4 Programs, 59 Fees, 55 Progress rows, 4 Announcements, 5 Files, 3 Sessions, 5 Tasks and 2 Submissions. The FE guide documents safe reset/seed, alias resolution, pagination and scope; the examples index distinguishes real cookie/CSRF HTTP transcripts from API feature-test examples. The live transcript redacts credentials, cookies, CSRF values and tokens and records actual status, safe response fields and request IDs. Evidence: [fixture guide](evidence/be-d06/2026-11-05-0443/fe-fixtures.md), [fixture manifest](evidence/be-d06/2026-11-05-0443/fixture-manifest-final.json), [example index](evidence/be-d06/2026-11-05-0443/examples/index.md), [HTTP transcript](evidence/be-d06/2026-11-05-0443/http-smoke.json).

- [x] **D06-11 — Chạy regression/coverage cuối và đóng gap test.** — **Pass**

  **Phụ thuộc:** 02, 04–10. **Issue:** TODO 3. **Contract:** §7.

  **Thực hiện:** Chạy grouped harness với coverage trên source cuối; đọc các dòng/nhánh gap lane Dũng; bổ sung test cho failure mode đáng kể. Kiểm race bằng connection/process PostgreSQL độc lập, rollback và side effects. Giữ logs thất bại và rerun đúng nhóm khi cần.

  **Đầu ra:** Bốn JUnit, logs/exit, Clover/text, `test-report.md` và requirement→case matrix.

  **Hoàn thành khi:** Bốn nhóm đạt, không failure/error/skip che acceptance; tổng test không đếm trùng rerun; source include không bị thu hẹp. Báo toàn backend và lane Dũng từ cùng Clover với file mapping minh bạch; giải thích chênh lệch baseline K06 827/93,58%. Gap acceptance có case và actual result; coverage cao không thay race/HTTP evidence.

  **Actual result (05/11/2026):** The final grouped PostgreSQL runner passed 831 tests / 7,366 assertions across preflight (7/27), regression (746/5,369), D04 API (77/1,077) and race (1/893); no failures, errors or skips. Backend line coverage is 93.59% (8,336/8,907), methods 79.97% and classes 61.78%. The Dũng lane map is 56 operations across 14 controller files, 1,805/1,895 statements (95.25%) from the same Clover artifact. Compared with K06, this is +4 tests and +19 covered executable lines (93.58% to 93.59%). Evidence: [test report](evidence/be-d06/2026-11-05-0455/test-report.md), [four JUnit files/Clover/text](evidence/be-d06/2026-11-05-0455/coverage/), [final run log](evidence/be-d06/2026-11-05-0455/full-suite-coverage-final.log), [lane map](evidence/be-d06/2026-11-05-0455/source-coverage-lane-dung.csv), [hashes](evidence/be-d06/2026-11-05-0455/coverage-artifact-sha256.txt).

- [ ] **D06-12 — Export/lint/parity/freshness và kiểm CI.** — **In progress (local checks pass; remote CI pending)**

  **Phụ thuộc:** 04–11. **Issue:** TODO 2. **Contract:** §4.

  **Thực hiện:** Export từ PostgreSQL test đã guard; lint đúng Redocly CI; so routes và inventory; export lại để kiểm artifact ổn định. Kiểm CI workflow thực thi grouped tests/export/lint/parity/freshness. Source metadata đổi sau bước này thì chạy lại export/check liên quan.

  **Đầu ra:** Export/parity/lint/freshness logs, warning triage, operation delta; CI run URL khi có PR.

  **Hoàn thành khi:** Không lỗi schema/reference/missing-extra route/stale JSON; warning còn lại có lý do và không che field/error bắt buộc. Registered routes không bị exclude chỉ để parity xanh. CI của commit PR đạt; nếu chưa có remote run thì ghi Pending, chỉ công bố local checks đã chạy. Total release là số export thực tế, không target 131.

  **Actual result (05/11/2026):** Two guarded exports produced the same OpenAPI SHA-256 `2a75f1d738dd40f5e946d916bbdaa9c14067b10af4253406a2e88ab96d899046`; Laravel route parity passed 123/123. Redocly CLI 2.60.0 lint passed with zero errors and 52 classified warnings. The npm registry lookup failed with `EAI_AGAIN`; the same pinned CLI ran successfully from the local cache. Operation count remains 123; the delta records 34 changed operations, no additions/removals, and three Scramble-flattened resource schemas. Evidence: [export/parity/lint/delta](evidence/be-d06/2026-11-05-0455/). Remote CI remains pending until the branch is pushed and the PR workflow completes.

- [x] **D06-13 — Smoke HTTP thực trên phiên bản đã kiểm chứng.** — **Pass (isolated runtime)**

  **Phụ thuộc:** 02 runtime ready, 10–12. **Issue:** TODO 2–4. **Contract:** §4–7.

  **Thực hiện:** Ghi environment/image/source identity; chạy flow lane Dũng với synthetic actors qua HTTP thật/cookie jar/CSRF. Inventory gắn từng operation với regression HTTP case; smoke thực tập trung chuỗi Program→Task→Submission→feedback và D04 Fee/Announcement/Progress/Overview, cùng cache/auth/stale/replay/errors đại diện. Tái sử dụng công cụ hiện có; không tạo probe trùng với tests chỉ để tăng số evidence.

  **Đầu ra:** `http-cases` transcript, safe headers/body và DB assertions version/history/side effects; coverage map operation→test/smoke.

  **Hoàn thành khi:** Hành vi khớp contract/OpenAPI ở runtime ghi trong manifest; outsider/forbidden/validation/stale/key mismatch và current-scope replay đúng. Không dựa vào health/count thay hành vi; không ghi `actingAs` là browser cookie smoke. Không gửi Slack/email thật; outbox side effects kiểm qua môi trường sink phù hợp. Runtime chưa sẵn hoặc khác image/source thì Blocked, không kế thừa PASS K06.

  **Actual result (05/11/2026):** Ran 26/26 live HTTP checks using cookie jars, CSRF bootstrap, and synthetic actors against Compose project `tutorlink-d06-smoke`. The runtime used image `sha256:955e6d57a4899bb2abb43ccad63755a8f37439c3a66f627497345c19e7aeecf8`, PHP 8.3.36, Laravel 13.35.0 and PostgreSQL 16.15. TestDatabaseGuard confirmed configured and connected identity `test-db/tutorlink_test/tutorlink_test_runner`; the DB was internal-only and the API port bound to loopback. SHA-256 of all nine modified runtime PHP files matched between image and workspace. Smoke covered Overview, Progress, Announcement, Fee, Task, Submission, feedback, auth/CSRF, stale/replay, key mismatch and throttling. The DB snapshot shows one Task-1 Submission, same-ID replay, Task version 4 and one feedback receipt. A 56-operation Dũng-lane map links feature-test files and the nine directly smoked route templates; 67 other operation owners remain unconfirmed and are tracked as a release blocker. Evidence: [runtime identity](evidence/be-d06/2026-11-05-0455/runtime-identity.md), [HTTP transcript](evidence/be-d06/2026-11-05-0443/http-smoke.json), [DB snapshot](evidence/be-d06/2026-11-05-0443/http-smoke-db-snapshot.txt), [operation-to-test/smoke map](evidence/be-d06/2026-11-05-0455/operation-test-smoke-map-dung.csv).

- [ ] **D06-14 — Freeze release snapshot và báo cáo.** — **In progress**

  **Phụ thuộc:** 09–13. **Issue:** TODO 4 và acceptance chung. **Contract:** §9.

  **Thực hiện:** Tập hợp OpenAPI, inventory, mapping, fixture/examples, API/schema delta và test report. Ghi manifest commit/hash/count/lock/image/migrations/environment; đối chiếu từng tiêu chí issue với case/evidence. Ghi planned routes và limitation có owner/next step.

  **Đầu ra:** `release-manifest.json`, `release-notes.md`, `test-report.md`, acceptance matrix và evidence index.

  **Hoàn thành khi:** Reviewer tái lập được phiên bản và kết quả; không có blocker thuộc acceptance bị đổi tên thành limitation; không claim UI/production/load chưa chứng minh; không dùng count 131. Source dirty phải có diff/hash, release được gắn commit cụ thể trước bàn giao. Sửa source sau freeze phải tạo snapshot mới.

  **Actual result (05/11/2026):** Prepared the test report, requirement-to-case matrix, runtime identity, release notes and evidence index in [the 0455 evidence directory](evidence/be-d06/2026-11-05-0455/). The immutable source commit and release manifest will be recorded after commit. Freeze cannot be called complete while the 67 endpoint owners remain unconfirmed and the authoritative System Design source is unavailable; those blockers are explicitly retained in the gap register and release notes.

- [ ] **D06-15 — PR, cập nhật issue và review Khanh.** — **In progress**

  **Phụ thuộc:** 14. **Issue:** Handover. **Contract:** §9.

  **Thực hiện:** Chuẩn bị commit/PR description, issue update với trạng thái/next step/blocker; khi thực hiện publish theo ủy quyền tương ứng, liên kết PR/CI/evidence vào #86, gửi Khanh review. Giải quyết feedback; chạy lại check bị ảnh hưởng nếu sửa code/metadata.

  **Đầu ra:** Commit/PR, CI URL, issue update và reviewer result.

  **Hoàn thành khi:** PR review và CI trên revision cuối đạt; Khanh xác nhận các tiêu chí; issue có tình trạng thực. Chưa đăng hoặc chưa review phải ghi đúng trạng thái, không tự tick toàn issue. Lượt tạo contract/plan hiện tại chưa thực hiện publish/review.

  **Actual result:** PR, CI, issue update and reviewer request are recorded after publication. The task remains open until remote CI passes and Khanh completes the review; no approval is inferred from the earlier D06-07 review.

## 4. Ánh xạ bốn TODO và acceptance

| Yêu cầu issue | Task chính | Bằng chứng để tick |
| --- | --- | --- |
| TODO 1 — Scramble + metadata từng owner | 01, 03–08 | Inventory đầy đủ, source metadata, schema/auth/error checks và xác nhận owner |
| TODO 2 — Export + CI + runtime routes + D23 | 02–03, 09, 12–13 | Guard/export/lint/parity/freshness, CI run và runtime evidence; planned routes đúng |
| TODO 3 — Program/Task/Submission/Fee/cache + coverage + FE fixture | 08, 10–11, 13 | Requirement→case, grouped JUnit/Clover, race/rollback, fixture tái lập |
| TODO 4 — Snapshot + legacy mapping + ví dụ lỗi | 07, 09–10, 14 | Mapping, examples 401/403/404/409/422/429 và 419, retry/version, manifest |
| Docs khớp endpoint chạy được | 03–08, 11–13 | Metadata + schema/body/header/status/auth evidence, không chỉ parity |
| Retry/version/errors có ví dụ | 05–07, 10, 13 | Transcript đầu/replay/mismatch/stale/mất quyền, DB side effects |
| Không lấy 131 operations draft để khai xong | 03, 09, 12, 14 | Count export của release, delta và danh sách planned riêng |
| Handover | 14–15 | Commit/PR, API/schema delta, report/evidence, limitations, review và issue update |

## 5. Lệnh tham chiếu khi môi trường đã sẵn sàng

Chạy bên trong backend **test** đã cấu hình đúng guard, không chạy trên runtime ứng dụng. Chuẩn bị Docker/dependency theo task 02 và workflow hiện hành; không copy credential production vào runner.

```sh
php scripts/test-console.php check
php scripts/test-suite.php --coverage
php scripts/test-console.php openapi:export
npx --yes @redocly/cli@2.60.0 lint ../docs/api/openapi.json --config ../.redocly.yaml
php scripts/verify-openapi-routes.php
```

Trước khi commit artifact: xem diff metadata/JSON và API delta. Sau khi commit đúng snapshot, `git diff --exit-code -- docs/api/openapi.json` từ root phải sạch sau export cùng môi trường. Log có lỗi bootstrap không được coi đạt chỉ vì shell exit 0.

## 6. Handoff và trạng thái hiện tại

D06-01–11 và D06-13 đã PASS theo evidence. D06-12 có local export/lint/parity/freshness pass nhưng chờ CI remote. D06-14–15 đang được hoàn tất qua release snapshot, PR, issue update và yêu cầu review Khanh; không coi CI/review là pass trước khi có kết quả trên revision cuối. D06-07 review đã xác nhận OK; D06-08 đã PASS.
