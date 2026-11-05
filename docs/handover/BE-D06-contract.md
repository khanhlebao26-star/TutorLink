# BE-D06 — Contract kiểm chứng và phát hành API

- **Issue:** [#86 — Scramble, hợp đồng API và kiểm thử lane Dũng](https://github.com/khanhlebao26-star/TutorLink/issues/86).
- **Owner:** Dũng · **Reviewer:** Khanh · **Branch:** `be-d06`.
- **Ngày đối chiếu:** 04/11/2026, Asia/Ho_Chi_Minh.
- **Baseline source:** `d157a8eeae662c01d6bd6b031ea53c26a5b05688`.
- **Plan thực hiện:** [BE-D06-plan.md](BE-D06-plan.md).
- **Trạng thái:** Contract đầu vào cho triển khai/review; chưa nghiệm thu D06. Các yêu cầu bên dưới là tiêu chí phải kiểm chứng, không phải xác nhận runtime đã đạt.

## 1. Mục tiêu, phạm vi và giả định

BE-D06 chốt một hợp đồng API có thể tái lập từ code, đủ để FE tích hợp và reviewer kiểm tra. Kế thừa Scramble/CI/tests hiện có; tập trung sửa phần thiếu hoặc sai khi các lane đã tích hợp, không cài lại nền tảng chỉ để hoàn tất checkbox.

| Phạm vi | Yêu cầu |
| --- | --- |
| Toàn bộ API đã đăng ký `/api/v1` | Inventory method/path, request/response/auth/errors, export/lint/parity và freshness của artifact; mỗi operation có owner và evidence hoặc gap rõ ràng |
| Lane Dũng | Kiểm sâu Program/Invitation, Task/Submission, Overview/Progress/Announcement/Fee và marketplace/cache |
| Các lane liên quan | Auth/File/Session/outbox và H04/H05 phải giữ contract; phối hợp owner khi endpoint/renderer dùng chung thay đổi |
| FE | Fixture tổng hợp tái lập được, response mẫu, field mapping và hướng dẫn retry/version/errors |
| Bàn giao | Commit/PR, API/schema delta, test report/evidence, release manifest và known limitations |

**Giả định thực hiện:** dùng backend/Compose/test guard hiện có; chưa có yêu cầu đổi nghiệp vụ, dependency hoặc môi trường triển khai. Timebox 5 giờ trong issue là mốc kế hoạch, không cho phép bỏ gate hoặc tự công bố hoàn tất khi còn blocker.

**Ngoài phạm vi:** triển khai đủ 131 operations draft; tự thêm route D23 còn dự kiến; xây UI Workspace/Filament; đổi auth sang token cho SPA; thay policy tiền/file/replay; production deployment, gửi Slack/email thật hoặc chứng nhận tải/SLO production. Sửa metadata và lỗi contract trong lane được đưa vào delta; thay đổi nghiệp vụ ngoài lane cần owner/reviewer xử lý rõ trước release.

## 2. Nguồn có thẩm quyền và dependency

Thứ tự đối chiếu: issue xác định đầu ra → Business Rules/PRD và quyết định mới xác định hành vi đích → Catalog xác định method/path đích → source/tests xác định implementation → OpenAPI/HTTP evidence xác định phiên bản có thể bàn giao. Khi các nguồn khác nhau, ghi gap; không sửa tài liệu để che lỗi runtime hoặc coi target là đã triển khai.

| Nguồn | Cách dùng |
| --- | --- |
| [Business Rules](../Domain%20Documentation/Business%20Rules.md), BR-18–BR-26 | Lifecycle, version, tiền/file, replay, errors và pagination |
| [Decision register](../UNRESOLVED_DECISIONS_2026-10-20.md) | Phân biệt quyết định đã chốt với implementation gap; kiểm điều chỉnh file 29/10 trước khi dùng nội dung cũ |
| [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md) | Method/path và ngoại lệ từng route; trạng thái đăng ký không tự chứng nhận hành vi |
| [Generated API guide](../api/README.md), [OpenAPI](../api/openapi.json) | Quy trình export và snapshot registered API; phải kiểm các ghi chú đã cũ |
| [D04 contract](BE-D04-contract.md), [D04 handover](BE-D04-handover.md) | Payload, trạng thái/quyền, version/no-op/replay, schema/legacy và evidence Workspace |
| [D05/H05 handover](BE-D05-BE-H05-handover.md) | Marketplace gate/cache/query; dashboard và giới hạn runtime |
| [H03 report](BE-H03-issue-75-test-report.md), [K04 handover](BE-K04-handover.md) | Invitation/lifecycle, Session và quan hệ với Program/Task |
| [H04 report](BE-H04-test-report.md) | Trust APIs, Report evidence và renderer errors giới hạn theo path |
| [K06 report](BE-K06-test-report.md) | Baseline test/coverage mới hơn và giới hạn smoke local |
| BE-D01–BE-D03 | Issue [#36](https://github.com/khanhlebao26-star/TutorLink/issues/36), [#69](https://github.com/khanhlebao26-star/TutorLink/issues/69), [#74](https://github.com/khanhlebao26-star/TutorLink/issues/74); source/tests hiện tại và tài liệu lịch sử dưới đây |

Handover D01–D03 không còn trong checkout baseline. Bản lịch sử còn ở Git, ví dụ [D01](https://github.com/khanhlebao26-star/TutorLink/blob/8a34aa2/docs/handover/BE-D01-issue-36-handover.md), [D02](https://github.com/khanhlebao26-star/TutorLink/blob/8a34aa2/docs/handover/BE-D02-phase-A-contract.md), [D03](https://github.com/khanhlebao26-star/TutorLink/blob/8a34aa2/docs/handover/BE-D03-issue-74-test-report.md). D06 phải kiểm lại với source hiện tại trước khi kế thừa kết luận; sửa các link tích hợp đang trỏ tới file đã mất bằng nguồn kiểm chứng được hoặc liên kết Git lịch sử. Không khôi phục toàn bộ tài liệu cũ rồi coi đó là contract hiện hành.

`D03/D07` trong dependency là mã quyết định chuẩn wire/errors và pagination, khác issue **BE-D03**. BR-26 đã chốt chuẩn thay trạng thái technical-proposal trong draft. Dẫn chiếu “System Design §7; §12; Task 4” chưa xác định được bản tương ứng: file architecture trong repo chỉ có tới §9; cần ghi nguồn chính xác khi bàn giao, không tự đoán nội dung §12.

## 3. Baseline đã kiểm tra và gap cần xử lý

| ID | Quan sát tại baseline | Mức bằng chứng / việc tiếp theo |
| --- | --- | --- |
| B01 | Branch `be-d06`, HEAD `d157a8e`; có source H04/H05/K06 | Source local; không dùng ref `origin/main` cũ để kết luận branch chưa pull |
| B02 | OpenAPI 3.1.2, API info 1.0.0, **123 operations**, 60 schemas | Đếm trực tiếp JSON; SHA-256 `83717e94538ac04fa6e4b7ec1865001f1dc5c810b67c5562e446ddce9b1c7940`; chưa xác nhận freshness/parity bằng runtime |
| B03 | Lock có Scramble v0.13.49, Laravel v13.35.0, Pest v4.7.8 | Phiên bản trong lock, không đồng nghĩa vendor/runtime đã cài đủ |
| B04 | CI đã có PostgreSQL export, grouped tests/coverage, lint/parity/freshness | Đọc workflow/scripts; chưa chạy CI mới cho D06 |
| B05 | K06 báo cáo 827 tests/7.227 assertions; coverage 93,58% = 8.317/8.888 dòng | Bằng chứng lịch sử của source test `2ba90470e47629f218da85b99ef59b7361ee8a55`, không phải lượt test D06 |
| G01 | Task GET detail/submission/history trong JSON chỉ có response 200; PATCH Task có 200/422, source có scope/state/version failures | Rà/bổ sung error metadata và shape; kiểm HTTP trước khi khẳng định mã/body thực tế |
| G02 | Exception renderer chuẩn hóa H04/dashboard theo path; không bao phủ mọi lỗi framework của Program/Task | Kiểm riêng auth/validation/abort/throttle và correlation; dùng helper không tự chứng minh mọi lỗi đạt BR-26 |
| G03 | README docs ghi 122, API README ghi 123; API README còn nói cả bảy D23 chưa đăng ký | Sửa ghi chú hiện hành, giữ các con số snapshot lịch sử có ngày/commit |
| G04 | `GET /admin/reports/{report}/evidence` đã trong JSON/Catalog; sáu route D23 lịch sử còn dự kiến | Không ẩn route evidence đã đăng ký. Users list/detail/PATCH, Listing hide/restore-visibility và metrics chưa được quảng bá là callable |
| G05 | Task list/history dùng collection không có page/per_page; BR-26 quy định 20/50 và ngoại lệ theo route | Kiểm giới hạn/ordering/DTO của từng list. Nếu không có ngoại lệ được duyệt, sửa implementation/contract và ghi delta; không tự gắn pagination chỉ trong docs |
| G06 | Các link handover D01–D03 và K03.1 từ tài liệu tích hợp có đích không còn trong checkout | Kiểm/sửa link thuộc hướng dẫn release; truy nguồn lịch sử có commit rõ ràng |
| E01 | Docker local: backend/db/test-db đang exited; broadcast/outbox/Slack workers restarting, scheduler running | Snapshot quan sát 04/11; chưa kiểm HTTP. Cần preflight trước smoke; không suy runtime này giống môi trường K06 |
| E02 | Host PHP 8.3.6 chạy parity bị lỗi bootstrap `Class Laravel\\Telescope\\Telescope not found` | Không có kết quả parity mới; exit code shell không thay cho kiểm nội dung lỗi. Dùng môi trường đủ lock/platform requirements |

Working tree ban đầu có thư mục untracked `docs/handover/evidence/be-d04/test/2026-11-01-2252/`; giữ nguyên, không tính là evidence D06.

## 4. Hợp đồng metadata và export

Mỗi operation cần có method/path, owner/module, trạng thái registered/planned, request body/query/path/header, success status/body, auth/scope và các lỗi thực sự áp dụng. Kiểu dữ liệu phải đủ để FE hiểu required/nullable, enum, array item, format, giới hạn, omission khác null, field server quản lý và pagination.

Inventory release có một dòng cho mỗi operation với các cột tối thiểu: `method,path,owner,source,request_schema,response_schema,auth,errors,version,retry,pagination,test_or_case,status,gap`. Tổng inventory phải khớp operations export; route planned và hai route hạ tầng có bảng riêng.

1. Bổ sung metadata trong code/Request/Resource/type/attributes theo convention hiện có; không bắt buộc tạo class mới nếu cách hiện tại đã suy luận đúng.
2. Export qua `backend/scripts/test-console.php openapi:export` trên PostgreSQL test đã guard. D04 migration yêu cầu PostgreSQL; recipe SQLite cũ không dùng cho schema hiện tại.
3. Redocly đúng phiên bản CI, route parity, artifact freshness đều phải đạt. Phân loại warning còn lại theo operation; warning che thiếu schema/errors bắt buộc phải xử lý dù lint exit 0.
4. Route parity chỉ chứng minh method/path. Đối chiếu response shape/header/status bằng tests qua HTTP kernel và smoke thật cho luồng lane Dũng.
5. `GET /sanctum/csrf-cookie` và `POST /broadcasting/auth` nằm ngoài `/api/v1`: ghi evidence riêng, không cộng vào tổng operations.
6. D23 đánh giá từng route theo BR/PRD mới và source. Route dự kiến không có success giả; route đã đăng ký phải có contract. Không dùng draft 131 hoặc API info version 1.0.0 thay cho định danh commit release.

## 5. Wire, auth, errors và phân trang

- SPA dùng Sanctum session cookie; bootstrap CSRF rồi gửi credentials và `X-XSRF-TOKEN` cho mutation. Security global có thể được kế thừa; thiếu `security` ở operation không tự có nghĩa public. Route public phải được kiểm override đúng.
- Chuẩn BR-26: JSON snake_case; error `{code,message,request_id,errors?}`, correlation `X-Request-ID`; instant UTC RFC 3339 `Z`; tiền VND integer. Response resource theo allow-list, không ép mọi DTO vào một shape chung.
- Collection mặc định page/per_page 20/50, sai query trả 422; ngoại lệ phải có nguồn và test. Message/Notification dùng cursor scoped/opaque theo contract riêng; Tutor embedded Listings tối đa 10 và `listings_meta`; Submission history kiểm ordering theo version.
- Các nhánh validation/auth/abort/throttle phải được quan sát, không chỉ business exceptions. Response lỗi không chứa SQL/trace/secret; private/error response không cache public. Kiểm giữ các header framework như `Retry-After` khi chuẩn hóa lỗi.

| HTTP | Case bắt buộc trong bộ ví dụ release |
| --- | --- |
| 401 | Guest hoặc session không còn hợp lệ trên endpoint protected |
| 403 | Actor active không có quyền mutation/permission tương ứng |
| 404 | ID không tồn tại và truy cập resource ngoài scope, không lộ dữ liệu |
| 409 | Stale Task/Fee, Submission stale/mismatch key, Fee trùng kỳ, Program completion blockers |
| 422 | Payload/query sai, thiếu/sai UUID key hoặc expected version theo từng endpoint |
| 429 | Endpoint có quota thực (ví dụ login/message/report), mã/body và `Retry-After` thực tế; không gán 429 giả cho mọi endpoint |
| 419 | Cookie/CSRF thiếu hoặc hết hạn trong browser mutation; hướng dẫn FE không tự replay mutation |

Mỗi ví dụ gắn case/test, actor giả, request, expected/actual response và safe headers. Nếu runtime chưa đạt BR-26 thì ghi lỗi cần sửa, không phát hành ví dụ tự viết như response đã quan sát.

## 6. Version, retry và side effects

| Resource/action | Hợp đồng phải giữ và minh họa |
| --- | --- |
| Program Draft PATCH/send Invitation | `expected_version` là offer version; Invitation giữ snapshot; pending khóa offer; không tự yêu cầu version cho lifecycle endpoint vốn không có guard đó |
| Task PATCH/start/cancel | Integer `expected_version`; stale 409; quyền và lifecycle theo BR-19 |
| PUT Submission | `expected_submission_version` + UUID Idempotency-Key; bản nộp/version/hash giữ lịch sử; cùng request replay bản cũ, khác payload 409; không đổi thuật toán canonicalization riêng |
| Feedback review/request-revision | Đúng submission hiện hành; UUID key, receipt chung 30 ngày; auth hiện tại trước replay; snapshot không lộ nội dung/file IDs trái contract |
| Fee writes | UUID key, version guard theo request; status và payment mark độc lập; receipt bền theo D04, không ép TTL 30 ngày; replay Fee create giữ status đã lưu (201) |
| Announcement/Progress | Publish có UUID key/receipt 30 ngày; Draft edit/Progress theo version/no-op riêng; không thêm key cho action vốn không yêu cầu |
| Session integration | Giữ contract K04; phân biệt expected_version của Session với Program/Task/Submission |

Ví dụ retry phải có: request đầu → cùng key/payload → khác payload cùng key → stale version với key mới → mất quyền rồi retry. Kiểm snapshot/version/count rows, receipt, audit/outbox/file links trước/sau theo resource; replay/no-op không nhân side effect. Không tự quảng bá Task notification delivery khi nguồn chỉ chứng minh chưa phát event Task.

## 7. Kiểm thử, coverage và fixture FE

Tái sử dụng `backend/scripts/test-suite.php --coverage` và bốn nhóm `d04-preflight`, `regression`, `d04`, `d04-race`; nhóm có chuẩn bị schema/fixture riêng và chạy tuần tự. Script có `migrate:fresh`: chỉ dùng project/DB/storage test cô lập đã guard và connected-identity check. Không chạy trên DB ứng dụng `tutorlink`, không dùng backend runtime để chạy destructive suite.

| Nhóm | Điều kiện cần chứng minh |
| --- | --- |
| Program/Invitation | Owner/member/outsider; immutable snapshot; stale/locked Draft; expiry/accept; complete/cancel blockers Session/Task |
| Task/Submission | Actor/state, nhiều versions, feedback đúng bản, replay/mismatch/stale, backfill, concurrent writers và rollback/file permissions |
| D04 | Overview đúng counts/null/order; Progress version/no-op; Announcement Draft/Published và file; Fee unique kỳ/VND/zero/void/payment mark, replay/rollback |
| Marketplace/cache | Gate email/account/Profile/documents/Listing/catalog/hold; anonymous HIT/MISS; authenticated BYPASS; private no-store; invalidation commit/rollback/expiry; fallback DB; N+1 |
| Tích hợp owners | Auth/CSRF, File, Session, audit/outbox và Trust regression không bị thay đổi ngoài delta |

Lượt cuối phải có logs, exit status, bốn JUnit, Clover và coverage text cùng source identity. Số 827 và 93,58% là baseline tham chiếu; không hardcode số test hoặc tự đặt ngưỡng mới thay yêu cầu issue. Giữ source include, bổ sung test cho gap có ý nghĩa; giải thích giảm coverage hoặc dòng thay đổi chưa được kiểm. Có blocker acceptance chưa được test/sửa thì chưa hoàn tất dù tổng coverage cao.

Fixture FE gồm owner/member/outsider; hai Program để thử cross-scope; Active/Paused/terminal; Task đa trạng thái, ít nhất hai Submission versions/feedback; Fee declared/voided và các payment mark; Draft/Published Announcement, Progress/empty Overview; public Tutor hợp lệ và mất gate, dữ liệu vượt một trang cho route có pagination. ID trả bằng manifest/alias, thời gian có mốc cố định hoặc tham số, không hardcode ID máy khác. Có cách tạo lại, expected DTO và xác nhận replay/no-op. Seed demo không chạy mặc định trên DB có dữ liệu; contract tests và seed FE có thể dùng chung factory/helper, không coi file JSON mẫu là evidence runtime.

## 8. Legacy mapping và giới hạn release

| Mapping phải bàn giao | Quy tắc |
| --- | --- |
| Program `customer_id` / PRD `target_customer_id` | Ghi wire, DB và nghĩa; owner lấy từ actor, không cho client tự nhận owner |
| Program/Invitation legacy statuses | Liệt kê mapping đã có migration/source; không đưa literal cũ vào enum wire mới |
| Submission legacy | Xác minh backfill D03 hiện tại; synthetic key/hash không chứng minh request lịch sử có thể replay |
| Fee `declared_status` → `status` | Tách `tutor_payment_mark`; `confirmed` không mặc nhiên là paid; `cancelled` cần semantics trước khi map voided |
| Fee period/money | Wire YYYY-MM, ngày lưu theo contract D04, timezone Program; VND integer, 0 là miễn phí |
| Session | HTTP `sessions` khác `program_sessions` planned và `lesson_sessions` actual |
| Report | Wire `description`/`reason_code` khác DB `details`/`reason`; không tự đổi API theo tên cột |

Mapping có cột nguồn, wire cũ/mới, DB, status implemented/legacy-only/blocked, ảnh hưởng FE và evidence. Không điền consent/payment/timestamp lịch sử khi thiếu nguồn. D06 mặc định không thêm migration; nếu sửa contract buộc có schema delta thì phải kiểm migration/legacy riêng và ghi vào manifest.

## 9. Điều kiện phát hành và trách nhiệm

Release chỉ đạt khi: inventory đủ registered API; metadata hợp lệ; export/lint/parity/freshness đạt; tests/HTTP evidence khớp payload/auth/errors; có ví dụ retry/version và bộ lỗi; fixtures dùng được; mapping/known limitations có nguồn; final commit/PR và reviewer Khanh xác nhận. Registered operation không chạy hoặc lỗi contract thuộc acceptance là blocker; không gắn nhãn limitation để tự bỏ qua.

Manifest tối thiểu: commit và dirty diff/hash nếu có; OpenAPI SHA-256/count; dependency lock hash, PHP/DB/image; migration identity; test run/coverage; case/evidence links; API/schema delta; planned routes; limitations/owner/next step. Không đóng issue từ kết quả lint/count hoặc báo cáo cũ. Nếu source đổi sau freeze, chạy lại kiểm tra bị ảnh hưởng và tạo manifest mới.

Dũng tích hợp và sở hữu evidence lane Dũng; owner endpoint xác nhận schema/errors/auth của mình; Khanh review contract và delta dùng chung. Khi triển khai đến bước cập nhật GitHub, ghi trạng thái/việc tiếp/blocker tại issue; lượt soạn tài liệu này chưa đăng comment hoặc tạo PR.

## 10. Quyết định và câu hỏi còn lại

| ID | Quyết định | Căn cứ / phương án đã cân nhắc |
| --- | --- | --- |
| C01 | Kế thừa source mới `d157a8e`, kiểm lại trước release | Thay vì làm lại hai task Scramble từ baseline D01 |
| C02 | Metadata toàn API; kiểm sâu lane Dũng, owner xác nhận lane khác | Vừa đáp ứng release chung vừa giữ phạm vi issue; không triển khai mọi route draft |
| C03 | PostgreSQL guard + grouped harness hiện có | D04 không tương thích recipe SQLite/mixed-suite cũ |
| C04 | Count/hash là identity, HTTP/tests là evidence behavior | Không lấy 123 hay 131 operations làm số API đã nghiệm thu |
| C05 | Tài liệu nguồn cũ dùng Git link có ref, ghi rõ lịch sử | Tránh tái xuất bản handover cũ như tài liệu hiện hành |

Còn cần giải quyết trong plan: environment E01/E02; audit lỗi/pagination G01/G02/G05; metadata từng owner; vị trí chính xác System Design §12/Task 4. Performance/scale giữ target NFR hiện có, D06 kiểm cache/query regression, không thêm cam kết tải mới. Privacy dùng dữ liệu tổng hợp và redact credential; reliability chứng minh retry/transaction theo contract; maintenance giữ generated artifact và owner từng operation. Không có giả định về production readiness.
