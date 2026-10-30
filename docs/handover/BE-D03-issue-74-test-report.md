# BE-D03 / Issue #74 — Task, submission versions và feedback

Ngày kiểm chứng: 31/10/2026. Phạm vi: backend Laravel trên nhánh hiện tại. Các probe PostgreSQL dùng database/schema test cô lập, không dùng database ứng dụng. Không triển khai lên staging/production.

## API và schema

11 method/path Task và Submission đã có trong API Route Catalog được đăng ký vào `backend/routes/api.php`. OpenAPI được xuất lại từ code, gồm 81 operations; route parity đạt. Các API dùng session auth và kiểm Tutor owner hoặc Customer assignee còn membership active. Task dùng `expected_version`; bài nộp và feedback dùng `expected_submission_version`. `PUT submission` bắt buộc UUID `Idempotency-Key`.

Migration versioning đã được bổ sung backfill: bài nộp cũ thành version 1, Task trỏ tới bài hiện tại, key/hash tổng hợp riêng cho dữ liệu cũ. Key tổng hợp không đại diện cho một request lịch sử và không thể dùng để replay từ client.

## Quy trình đã chạy

Snapshot test `TaskWorkflowApiTest` chạy từ HTTP request tới database trên SQLite test database ở lượt đầu; các regression và toàn backend mới nhất chạy bằng PostgreSQL được ghi riêng bên dưới:

1. Tutor giao Task cho Customer member, đọc danh sách và sửa Task với version đúng.
2. Outsider bị từ chối; version Task cũ trả 409; Customer bắt đầu Task.
3. Customer nộp lần 1; retry cùng key/payload trả đúng ID cũ; đổi payload cùng key trả 409 và không tạo bản thứ hai.
4. Tutor yêu cầu sửa; Customer gửi version cũ bị 409, nộp lần 2 thành công; lịch sử vẫn giữ feedback trên lần 1.
5. Tutor feedback nhầm version bị 409; chấp nhận version 2 thành công; Task completed và bản nộp hiện tại trỏ đúng version 2.
6. File hoàn tất được gắn vào bài nộp; Tutor tải được, outsider bị từ chối. Một thử nghiệm gắn file không hợp lệ và một lỗi ghi `file_links` được tạo có chủ đích đều không để lại bài nộp hoặc trạng thái Task dở dang.
7. Một bài nộp cũ được đưa qua migration trên SQLite độc lập: thành version 1, có key/hash tổng hợp và Task trỏ đúng bài hiện tại.

## Bằng chứng

- [Kết quả kiểm thử tập trung ban đầu trên SQLite](evidence/be-d03/focused-tests.txt): **13/13 test, 116 assertions đạt**; đây là snapshot lịch sử, không phải bằng chứng PostgreSQL.
- [Kiểm tra OpenAPI](evidence/be-d03/openapi-parity.txt): **81 operations, route parity đạt**.
- [Ảnh trình bày kết quả chạy thực tế](evidence/be-d03/focused-tests.png): ảnh được dựng từ hai tệp kết quả lệnh ở trên để dễ đưa vào issue; đây là bằng chứng test backend, không phải ảnh chụp giao diện người dùng.

## Ma trận quyền, trạng thái và cách hiểu CRUD — 31/10/2026

- Bằng chứng tập trung nằm trong `TaskWorkflowApiTest`: `enforces task visibility and mutation permissions by owner and active assigned membership`, `applies Program state gates to task creation and actions`, và `rejects task edits and submissions in terminal task states while allowing valid transitions`. [Lượt chạy PostgreSQL ngày 31/10](evidence/be-d03/task-permission-state-tests-2026-10-31.txt): **10/10 test, 179 assertions đạt**.
- Ma trận quyền: Tutor owner tạo/sửa/hủy theo gate; Tutor khác bị 403; Customer đúng assignee với membership active xem, bắt đầu và nộp được; Customer là member active của Program khác và outsider không xem được Task (404) hoặc mutate được (403); assignee có membership `cancelled` bị từ chối. Schema chỉ cho một Customer member active trên mỗi Program, nên không thể có thêm active Customer member cùng Program nhưng không phải assignee; test bảo vệ trường hợp tương ứng bằng member của Program khác.
- Ma trận Program: chỉ Active cho tạo/sửa; Active và Paused cho start/nộp/hủy Task đã giao; Completed/Cancelled chặn tạo và mọi mutation Task. Ma trận Task: chỉ `todo` sửa được; `todo`/`in_progress` hủy được; `todo`/`in_progress`/`submitted` nộp hoặc cập nhật được theo version; `in_progress`/`submitted` không sửa được; `submitted` chưa thể hủy; `completed`/`cancelled` không sửa, nộp, start hoặc hủy lại. `expected_version` stale vẫn trả 409.
- “CRUD” trong Issue #74 được bàn giao là create/read/update cùng transition hủy có chủ đích, không phải xóa vật lý. Catalog/routes có `POST /tasks/{task}/cancel` và không có `DELETE /tasks/{task}`; hủy giữ Task và lịch sử Submission theo State Lifecycles.

## Backfill có thể chạy lại và RFC 8785 — 31/10/2026

- `SubmissionVersioningBackfillPostgresTest` giữ probe backfill trong repository để có thể chạy lại. Test tạo schema PostgreSQL cô lập, kiểm tra trường hợp assignee được backfill từ submission và trường hợp assignee hiện có được giữ nguyên; row accepted giữ nguyên feedback/reviewer; mismatch bị từ chối và DDL rollback. [Evidence](evidence/be-d03/backfill-rfc8785-tests-2026-10-31.txt): lệnh chạy và kết quả tổng hợp **13/13 test, 203 assertions**.
- `TaskSubmissionRequestHash` tạo hash theo shape hiện tại với property key ASCII được xếp đúng thứ tự RFC 8785, chuỗi UTF-8 không chuẩn hóa/không escape sai U+2028/U+2029, và số nguyên trong miền safe integer. Unit test và HTTP test dùng vector Unicode cố định; hash được đối chiếu theo [RFC 8785 §3.2](https://www.rfc-editor.org/rfc/rfc8785.html#section-3.2). Test này chứng nhận payload shape của Submission hiện tại; nếu thêm kiểu số hoặc cấu trúc lồng nhau cần mở rộng canonicalizer và vectors.
- OpenAPI được xuất lại sau khi giới hạn số nguyên được thêm vào validation; route parity vẫn đạt **81 operations**.
- Backfill vẫn được kiểm trên dữ liệu legacy tổng hợp trong schema cô lập, không phải bản sao production; các trường hợp bổ sung hiện gồm assignee bị thiếu, assignee đã có và mismatch làm migration rollback.

## PostgreSQL concurrency có thể chạy lại — 31/10/2026

- `TaskSubmissionConcurrencyTest` giữ race probes trong test suite: worker PHP độc lập đợi trên cùng khóa Program; test chỉ nhả khóa sau khi `pg_blocking_pids` xác nhận cả hai đã chờ. Test chạy ba lượt liên tiếp đều đạt (**27/28/27 assertions** tùy thứ tự race). [Evidence](evidence/be-d03/task-submission-concurrency-2026-10-31.txt).
- Test tái lập: hai payload/keys khác nhau → một 201 và một 409; retry đồng thời cùng key/payload → 201/200 cùng Submission ID, không nhân đôi; resubmit cạnh tranh Tutor review → một mutation commit, request kia 409 và state cuối nhất quán.
- Worker gọi trực tiếp TaskController bằng Laravel Request trong process riêng để cô lập transaction/row-lock race; test HTTP tuần tự và kiểm quyền vẫn nằm trong `TaskWorkflowApiTest`.

## PostgreSQL transaction rollback có thể chạy lại — 31/10/2026

- Hai fault probes hiện nằm trong `TaskWorkflowApiTest`, dùng trigger/function PostgreSQL tạm để làm lỗi insert FileLink hoặc feedback receipt; mỗi probe kiểm tra response 500 và dữ liệu đã rollback, rồi dọn DDL trong `finally`.
- Có thể chạy riêng hai probe bằng command trong [evidence](evidence/be-d03/postgres-rollback-probes.txt); hai lượt chạy liên tiếp đều đạt **2/2 test, 50 assertions**.

## Ma trận quyền tải file Task/Submission — 31/10/2026

- `TaskWorkflowApiTest` kiểm tra Tutor phụ trách và Customer assignee có membership active tải được file Task; Tutor khác, outsider và assignee sau khi membership chuyển sang `cancelled` bị từ chối. File sai `purpose` cũng bị từ chối.
- Với file Submission, Customer nộp bài và Tutor phụ trách tải được; Tutor khác và outsider bị từ chối. Liên kết dữ liệu xác nhận đúng `purpose=task_attachment`, `resource_type=task` hoặc `task_submission`.
- Kiểm tra membership bị hủy dùng file Task do Tutor sở hữu, nên kết quả xác nhận quyền thành viên đã bị thu hồi. FileController vẫn cho chủ sở hữu tải file của chính họ theo quyền owner chung; ca membership không active không dùng để khẳng định owner bị chặn khi tải file Submission do Customer tự nộp.
- [Kết quả TaskWorkflowApiTest trên PostgreSQL](evidence/be-d03/postgres-file-download-permissions-2026-10-31.txt): **11/11 test, 193 assertions đạt**. Phạm vi quyền tải Task/Submission nêu trên đã được kiểm chứng; test này không chứng nhận quyền CV, orphan cleanup hoặc storage compensation của BE-K03.

## Bổ sung PostgreSQL — 31/10/2026

- [Probe race PostgreSQL](evidence/be-d03/postgres-concurrency-probes.txt): hai PHP worker độc lập chờ trên cùng khóa Program; hai submission cạnh tranh cho kết quả `[201, 409]`, retry đồng thời cùng key cho `[201, 200]` và cùng Submission ID, còn resubmit cạnh tranh với Tutor review chỉ có một thao tác commit; state cuối nhất quán.
- [Probe backfill PostgreSQL](evidence/be-d03/postgres-backfill.txt): hai legacy rows được backfill đúng (assignee, current pointer, version 1, synthetic UUID và SHA-256); row sai assignee bị từ chối và transactional DDL rollback, giữ nguyên row cũ.
- [Test tập trung PostgreSQL](evidence/be-d03/postgres-focused-tests.txt): **16/16 test, 184 assertions đạt** trong lượt chạy trước khi bổ sung assertion side-effect replay.
- [Test toàn backend PostgreSQL trước khi bổ sung các test BE-D03 mới](evidence/be-d03/postgres-full-suite.txt): **468/468 test, 3.121 assertions đạt**; đây là log của lượt chạy trước, được giữ lại làm lịch sử.
- [Test toàn backend PostgreSQL trước hai regression fix mới nhất](evidence/be-d03/postgres-full-suite-current-2026-10-31.txt): **475/475 test, 3.252 assertions đạt**; log này được giữ làm snapshot lịch sử.
- Các tệp `postgres-concurrency-probes.txt` và `postgres-backfill.txt` ghi kết quả probe ad hoc ban đầu; script gốc của các lần đó không còn. Có thể tái lập hành vi tương ứng bằng `TaskSubmissionConcurrencyTest` và `SubmissionVersioningBackfillPostgresTest`. Không dùng hai log ad hoc làm bằng chứng rằng chính script cũ còn chạy được.

## Giới hạn kiểm chứng

- Kết quả toàn backend mới nhất trước các regression fix Task là **475/475, 3.252 assertions**; log 468/468 và 475/3.252 là snapshot lịch sử đã được thay bằng kết quả hiện tại ở phần regression fix bên dưới. Lần chạy cũ có 5 test lỗi cũng đã được thay thế bởi kết quả đạt.
- Các log trong `evidence/be-d03/` là bản ghi đầu ra của từng lần chạy. Có thể tái lập bằng source test: `TaskWorkflowApiTest.php`, `TaskSubmissionConcurrencyTest.php` và `SubmissionVersioningBackfillPostgresTest.php` trong backend; các lệnh Docker tương ứng được ghi ở đầu những tệp evidence có định dạng báo cáo. Probe race/backfill dùng database/schema test cô lập, không chứng nhận trên bản sao PostgreSQL production hoặc dữ liệu production thật.
- Audit/outbox cho sự kiện Task và thông báo người nhận chưa được tích hợp vì deep link Task cho frontend vẫn chưa được chốt trong contract BE-K02.
- API backend đã có; UI Task/Submission và Progress API/UI M07-F04 chưa được triển khai trong phạm vi BE-D03. `/files/{file}/links` chung vẫn chỉ hỗ trợ `verification_document`; Task/Submission file links được tạo qua API nghiệp vụ M07. CV, orphan cleanup/storage compensation và kiểm thử trên dữ liệu production-like chưa được chứng nhận trong #74.

## Bổ sung 31/10/2026 — cập nhật bài khi chờ duyệt và feedback replay

M07/M10, Database Design, Business Rules và API Route Catalog được đối chiếu trước khi sửa. Các câu 5 MiB cũ trong M07/Database Design đã theo quyết định BR-21/K03.1 là **5.000.000 byte/file**; không đổi upload runtime. `task_attachment` là purpose chung, còn FileLink `resource_type` phân biệt Task và Submission. `/files/{file}/links` dùng chung vẫn chỉ hỗ trợ `verification_document`; ghi nhận BE-K03 chưa chứng nhận được giữ lại.

API đã cho Customer assignee tạo version mới ngay khi Task còn `submitted`. Bài nộp cũ được giữ; feedback dùng Task/current pointer lock và `expected_submission_version` để chỉ quyết định bản hiện hành. Hai endpoint feedback yêu cầu UUID `Idempotency-Key`, chuẩn hóa CRLF/CR thành LF và dùng bảng `idempotency_receipts` có sẵn. Receipt 30 ngày ghi trong cùng transaction với quyết định; response snapshot allow-list không chứa nội dung bài nộp hoặc file IDs. Cùng Tutor/key/method/path/payload và còn quyền hiện tại trả lại 200/snapshot; khác path/payload trả 409; key mới trên bản cũ/đã quyết định trả 409; key thiếu/sai trả 422.

- [Kết quả test tập trung ngày 31/10](evidence/be-d03/focused-tests-2026-10-31.txt): **15/15 test, 155 assertions đạt** trên SQLite, gồm version mới khi đang chờ duyệt, stale feedback, replay cùng payload đã chuẩn hóa, mismatch path/payload, key thiếu/sai, kiểm quyền trước replay và rollback khi ghi receipt thất bại.
- OpenAPI được xuất lại từ code; 81 method/path và header UUID bắt buộc trên PUT Submission cùng hai POST feedback được kiểm qua artifact và route parity.
- Đã có bằng chứng race và backfill trên PostgreSQL test cô lập cùng test toàn backend 477/477 (3.284 assertions) sau regression fix mới nhất. Chưa có UI Task. Task-specific audit/outbox/notification event chưa được phát; phần kiểm tra replay chỉ xác nhận không có dòng audit/notification nào phát sinh thêm từ request gốc hoặc retry, không chứng nhận event delivery.

## Audit/outbox và side effect khi replay — 31/10/2026

- `TaskWorkflowApiTest` chụp số dòng `audit_logs`, `notification_outbox` và `notifications` trước/sau lần nộp hoặc feedback đầu tiên, rồi so sánh lại ngay sau replay. Các số đếm không đổi; các assertion hiện bao phủ replay Submission, feedback yêu cầu sửa và feedback chấp nhận.
- Version, Submission và receipt hiện có cũng tiếp tục được kiểm tra để xác nhận replay không tạo bản nộp hoặc receipt trùng. Không thêm Task event/outbox mới: Issue #74/M07 yêu cầu retry không nhân đôi side effect nhưng không yêu cầu tích hợp sự kiện Task mới; luồng phát sự kiện còn phụ thuộc contract deep link BE-K02.
- [Kết quả TaskWorkflowApiTest trên PostgreSQL](evidence/be-d03/postgres-replay-side-effects-2026-10-31.txt): **11/11 test, 199 assertions đạt**.

## Tái lập kiểm thử và phạm vi bàn giao

- Chạy lại luồng Task, permission, download, replay side effect và rollback: `docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest tests/Feature/TaskWorkflowApiTest.php`.
- Chạy lại toàn backend trên PostgreSQL test: `docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest`; kết quả mới nhất **477/477 test, 3.284 assertions**, được lưu trong [log hiện tại](evidence/be-d03/postgres-full-suite-issue74-regressions-2026-10-31.txt).
- Chạy riêng probe tái lập: `docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest tests/Feature/TaskSubmissionConcurrencyTest.php` hoặc `docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest tests/Feature/SubmissionVersioningBackfillPostgresTest.php`. Source test là phương tiện tái lập; log chỉ là snapshot kết quả theo lần chạy. Các log probe ad hoc cũ đã được đánh dấu là lịch sử.
- Phạm vi #74 là API/schema/backend và evidence. UI Task/Submission, Progress API/UI M07-F04 và hỗ trợ `task`/`task_submission` trong `/files/{file}/links` chung nằm ngoài phạm vi hiện tại. CV, orphan cleanup/storage compensation thuộc BE-K03; kiểm thử bằng dữ liệu production-like chưa được thực hiện.
- Không còn blocker cho các acceptance criteria backend của #74 trong phạm vi hiện tại. Task-specific event audit/outbox/notification chưa tích hợp; việc này không bắt buộc theo #74/M07 và chỉ nên xét khi contract deep link BE-K02 được chốt. Issue nên giữ mở đến khi PR được review và merge.
- Bàn giao local hiện nằm trên branch `codex/2w-be-d03`; commit đã tạo nhưng chưa được đẩy lên GitHub. Draft PR chưa mở vì `git push` trong môi trường này không có thông tin xác thực GitHub (`could not read Username`); GitHub integration cũng từ chối ghi blob/bình luận issue với HTTP 403 `Resource not accessible by integration`. Vì vậy trạng thái Issue #74 chưa thể được cập nhật từ môi trường hiện tại. Đây là blocker bàn giao/publish, không phải blocker kiểm thử hay acceptance criteria backend.

## Regression fix: whitespace và timestamp Task API — 31/10/2026

- Nguyên nhân mất khoảng trắng là middleware Laravel `TrimStrings` chạy toàn cục trước `TaskController`. `bootstrap/app.php` nay loại trừ đúng các route `/api/v1/tasks/*` và `/api/v1/programs/{program}/tasks`; các endpoint khác vẫn giữ hành vi trim. Chuẩn hóa trong controller tiếp tục đổi CRLF/CR thành LF, đổi chuỗi toàn whitespace thành `null`, giữ nguyên khoảng trắng đầu/cuối khi chuỗi có nội dung, không Unicode-normalize; hash tính trên đúng payload sau chuẩn hóa.
- Regression HTTP trên PostgreSQL kiểm tra submit padded-content cùng key trả replay 200, payload chỉ khác edge spaces trả 409, DB giữ nguyên nội dung; feedback tương tự, và chuỗi toàn whitespace bị từ chối theo validation hiện hành.
- `taskData`, `submissionData`, `feedbackData` dùng chung serializer UTC RFC 3339: `due_at`, `submitted_at`, `reviewed_at` trả dạng `...Z`; giá trị null vẫn null. Test kiểm tra chuyển `2026-11-02T09:30:00+07:00` thành `2026-11-02T02:30:00Z`, các timestamp Submission/feedback đúng định dạng, và null cho due/review trước khi duyệt. Database timestamp storage không đổi.
- [Regression TaskWorkflowApiTest PostgreSQL](evidence/be-d03/task-http-contract-regressions-2026-10-31.txt): **13/13 test, 231 assertions đạt**. [Toàn backend PostgreSQL sau fix](evidence/be-d03/postgres-full-suite-issue74-regressions-2026-10-31.txt): **477/477 test, 3.284 assertions đạt**.
- [OpenAPI Scramble export và route parity](evidence/be-d03/task-openapi-parity-2026-10-31.txt): export từ source thành công; route parity **81 operations**.
- Giới hạn bàn giao không đổi: UI Task/Submission và mở rộng `/files/{file}/links` vẫn ngoài phạm vi; Issue #74 vẫn chưa có PR hoặc cập nhật GitHub do giới hạn quyền publish đã nêu ở trên.
