# BE-D04 — Contract API và quyền Workspace

- **Issue:** [#78 — Progress, announcements và fees](https://github.com/khanhlebao26-star/TutorLink/issues/78)
- **Owner:** Dũng · **Reviewer:** Khanh
- **Branch theo issue:** `codex/2w-be-d04` · **Branch kiểm tra hiện tại:** `be-d04` · **HEAD nền:** `135efcc8ec5c4c73cff8d58cabc62a17772234bf`
- **Phạm vi:** API/backend cho Progress, Announcement, Fee và phần Overview cần thiết để ba tab hiển thị dữ liệu đúng.
- **Plan thực hiện:** [BE-D04-plan.md](BE-D04-plan.md) — checklist test trước, áp dụng thực tế sau, tiêu chí hoàn thành và evidence cho từng bước.
- **Trạng thái tài liệu:** Contract dùng làm đầu vào triển khai BE-D04. BR-19/20/21/22/23/25/26 là căn cứ nghiệp vụ; mục 13–17 quy định request/response, truy vấn, idempotency, no-op, transaction, schema, audit và tích hợp file. Trạng thái runtime được ghi riêng tại mục 11.
- **Đối chiếu mới nhất:** 01/11/2026; graphify → tài liệu nguồn → source/commit ancestry → route runtime/OpenAPI và issue GitHub. [Bằng chứng và giới hạn](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-01).
- **Tài liệu liên quan:** [Issue #78](https://github.com/khanhlebao26-star/TutorLink/issues/78), [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md), [Business Rules](../Domain%20Documentation/Business%20Rules.md), [Database Design](../Architecture%20%26%20Technical%20Design/Database%20Design%20%E2%80%94%20TutorLink.md), [WF05](../Workflow/WF05%20Workspace%20Session%20Task%20v%C3%A0%20ph%C3%AD.md), [Progress PRD](../Modules%20Documentation/M07%20%E2%80%94%20Task%20%26%20Progress/M07-F04%20PRD%20%E2%80%94%20Ghi%20v%C3%A0%20xem%20Progress.md), [Announcement PRD](../Modules%20Documentation/M08%20%E2%80%94%20Fee%2C%20Announcement%20%26%20Notification/M08-F02%20PRD%20%E2%80%94%20T%E1%BA%A1o%20v%C3%A0%20c%C3%B4ng%20b%E1%BB%91%20Announcement.md), [Fee PRD](../Modules%20Documentation/M08%20%E2%80%94%20Fee%2C%20Announcement%20%26%20Notification/M08-F01%20PRD%20%E2%80%94%20Qu%E1%BA%A3n%20l%C3%BD%20Fee%20Record.md).

## 1. Quyền chung

- **Tutor owner:** đọc Workspace và thực hiện các thao tác quản lý được phép theo trạng thái Program và trạng thái resource.
- **Customer member hiện tại:** đọc dữ liệu được chia sẻ. Chỉ Customer được giao Task mới được start hoặc nộp bài.
- **Người ngoài Program:** không được đọc hoặc ghi, kể cả khi biết ID hay URL trực tiếp.
- **Admin:** không tự có quyền đọc nội dung Workspace chỉ vì mang role Admin. Truy cập hỗ trợ/evidence phải qua policy riêng.
- Server kiểm quyền ở mọi request, gồm đọc chi tiết, tải file và replay. Giao diện dùng `capabilities`/actions do server trả để hiển thị thao tác; server vẫn là nơi quyết định quyền.
- Kiểm membership hiện tại, account state và scope của Program/resource trước khi trả dữ liệu hoặc kết quả replay.

## 2. Ma trận trạng thái Program

Các quyền ghi dưới đây chỉ dành cho đúng actor của hành động tương ứng.

| Hành động | Active | Paused | Completed/Cancelled |
|---|---|---|---|
| Đọc dữ liệu trong phạm vi quyền | Có | Có | Có |
| Tạo Session/Task/Fee | Có | Không | Không |
| Sửa lịch Session `scheduled` | Có | Không | Không |
| Hoàn tất/hủy Session `scheduled` đã có | Có | Có | Không |
| Sửa nội dung Task `todo` | Có | Không | Không |
| Start/nộp bài/feedback Task hợp lệ | Có | Có | Không |
| Hủy Task `todo`/`in_progress` | Có | Có | Không |
| Tạo/sửa Progress | Có | Có | Không |
| Tạo/sửa Draft/publish Announcement | Có | Có | Không |
| Sửa/mark/void Fee đã có | Theo trạng thái Fee | Theo trạng thái Fee | Không |

Program terminal chặn mutation mới. Replay một lệnh đã commit tuân policy riêng của resource và vẫn phải kiểm quyền hiện tại. Review hợp lệ sau Program Completed là ngoại lệ riêng, ngoài phạm vi #78.

## 3. API và dữ liệu theo tab

Các bảng dưới đây ghi đầy đủ tiền tố `/api/v1`, theo method/path chuẩn trong API Route Catalog. Đây là API đích; trạng thái đăng ký runtime của BE-D04 được đối chiếu tại mục 11. Các API Session và Task được nêu để định nghĩa tích hợp Workspace; chúng không thuộc phần triển khai của issue #78.

### 3.1 Overview — dữ liệu tổng hợp

`GET /api/v1/programs/{program}/overview`

Owner/member được đọc:

- `next_session`: Session `scheduled` gần nhất trong tương lai, sắp theo `(planned_starts_at,id)` tăng dần.
- `task_counts`: đủ `todo`, `in_progress`, `submitted`, `completed`, `cancelled`, kể cả count bằng 0.
- `latest_progress`: Progress mới nhất theo `(recorded_at,id)` giảm dần.
- `latest_announcement`: Announcement Published mới nhất theo `(published_at,id)` giảm dần.
- `capabilities`: `can_create_session`, `can_create_task`, `can_record_progress`, `can_create_fee_record`, `can_create_announcement`, `can_create_review`.

Capability là quyền hiện tại của actor trong scope Program, không phải chỉ báo UI theo role: Tutor owner trên Program `active` có thể tạo Session/Task/Fee/Announcement; owner trên Program `active` hoặc `paused` có thể ghi Progress. Customer member trên Program `completed` có thể tạo Review nếu chưa có Review cho cặp Program/Customer, theo eligibility của M09-F01. Các capability còn lại là `false`; account inactive và actor ngoài scope bị chặn trước khi tạo response.

Summary không có dữ liệu trả `null`. Overview không có API ghi riêng; các tab vẫn đọc độc lập. Không tính Session dự kiến là Session đã diễn ra. `submitted` không đồng nghĩa với Task `completed`.

**Hiện trạng xác minh 01/11/2026:** route Overview chưa có trong `backend/routes/api.php`, danh sách route Laravel đang chạy hoặc `docs/api/openapi.json`. Catalog trước đây gắn “Đã đăng ký” chưa có căn cứ; đã sửa thành **Dự kiến**. Không dùng `GET /api/v1/programs/{program}` thay thế cho Overview vì hai route có contract khác nhau. Chi tiết tại mục 11.

### 3.2 Session — bối cảnh tích hợp, ngoài phạm vi code #78

| API | Actor và hành vi |
|---|---|
| `GET /api/v1/programs/{program}/sessions` | Owner/member đọc danh sách trong scope. |
| `GET /api/v1/sessions/{session}` | Owner/member đọc chi tiết. |
| `POST /api/v1/programs/{program}/sessions` | Owner tạo lịch với title, giờ bắt đầu/kết thúc, delivery mode và location/meeting URL tùy chọn. |
| `PATCH /api/v1/sessions/{session}` | Owner sửa field lịch khi Session `scheduled` và Program Active. |
| `POST /api/v1/sessions/{session}/complete` | Owner hoàn tất Session `scheduled`, tạo tối đa một actual. |
| `POST /api/v1/sessions/{session}/cancel` | Owner hủy Session `scheduled`, giữ lịch sử. |

Status, owner, Program, version, actual do server quản lý. Giờ lưu UTC và hiển thị theo `Program.timezone`; meeting URL nếu có phải HTTPS, tối đa 2.048 ký tự, không credentials. Lịch dự kiến không được tính là buổi đã thực hiện.

### 3.3 Task — bối cảnh tích hợp, ngoài phạm vi code #78

| API | Actor và hành vi |
|---|---|
| `GET /api/v1/programs/{program}/tasks` | Owner/member đọc danh sách trong scope. |
| `GET /api/v1/tasks/{task}` | Owner/assignee đọc chi tiết. |
| `POST /api/v1/programs/{program}/tasks` | Owner giao Task cho Customer member, nhập nội dung, hạn nộp và file hợp lệ. |
| `PATCH /api/v1/tasks/{task}` | Owner sửa nội dung Task `todo` khi Program Active. |
| `POST /api/v1/tasks/{task}/start` | Assignee chuyển `todo` → `in_progress`. |
| `POST /api/v1/tasks/{task}/cancel` | Owner hủy Task `todo`/`in_progress`. |
| `GET /api/v1/tasks/{task}/submission` | Owner/assignee đọc bài hiện tại và feedback. |
| `GET /api/v1/tasks/{task}/submission/versions` | Owner/assignee đọc lịch sử bài nộp. |
| `PUT /api/v1/tasks/{task}/submission` | Assignee nộp/cập nhật content và file, tạo version bài mới. |
| `POST /api/v1/tasks/{task}/submission/review` | Owner chấp nhận đúng version; Task chuyển `completed`. |
| `POST /api/v1/tasks/{task}/submission/request-revision` | Owner yêu cầu sửa; Task chuyển `in_progress`. |

Task `submitted` chưa phải `completed`; Task submitted phải qua feedback trước khi có thể hủy. Không xóa lịch sử Submission. Submission dùng `expected_submission_version` và Idempotency-Key theo contract riêng.

### 3.4 Progress — thuộc #78, 3 API

**Trạng thái 01/11/2026:** cả nhóm là **Dự kiến**, chưa có trong route runtime hoặc OpenAPI; các bảng mô tả contract cần triển khai.

| API | Quyền và dữ liệu |
|---|---|
| `GET /api/v1/programs/{program}/progress-records` | Owner/member đọc Progress thuộc Program. |
| `POST /api/v1/programs/{program}/progress-records` | Owner tạo với `metric_name=progress_percent`, `value`, `note` tùy chọn. |
| `PATCH /api/v1/progress-records/{progressRecord}` | Owner sửa `value`/`note`, bắt buộc `expected_version`. |

- `value` là số nguyên 0–100 do Tutor tự đánh giá; không suy từ số Session, điểm hoặc số Task.
- `recorded_at`, actor, Program ID và version do server đặt.
- Active/Paused cho phép tạo/sửa; terminal chỉ đọc.
- Sửa ghi audit before/after. Không có DELETE.
- Response có metric, value, note, thời gian và version.
- `latest_progress` trong Overview lấy theo `(recorded_at,id)` giảm dần.

### 3.5 Announcement — thuộc #78, 5 API

**Trạng thái 01/11/2026:** cả nhóm là **Dự kiến**, chưa có trong route runtime hoặc OpenAPI; các bảng mô tả contract cần triển khai.

| API | Quyền và dữ liệu |
|---|---|
| `GET /api/v1/programs/{program}/announcements` | Owner đọc Draft/Published; Customer member chỉ đọc Published. |
| `GET /api/v1/announcements/{announcement}` | Kiểm quyền giống API danh sách. |
| `POST /api/v1/programs/{program}/announcements` | Owner tạo Draft: `title`, `body`, `file_ids` tùy chọn. |
| `PATCH /api/v1/announcements/{announcement}` | Owner sửa Draft title/body/file IDs, bắt buộc `expected_version`. |
| `POST /api/v1/announcements/{announcement}/publish` | Owner publish Draft, bắt buộc `expected_version`. |

- Active/Paused cho phép tạo Draft, sửa Draft và publish.
- Title không trắng, 1–180 ký tự; body không trắng, 1–10.000 ký tự; tối đa 5 file.
- Published bất biến cả nội dung lẫn file. Đính chính bằng Announcement mới; không unpublish.
- Author, Program, `published_at` và version do server quản lý.
- Draft chỉ Tutor owner đọc. Published được member hiện tại đọc; membership được kiểm lại cả khi tải file.
- Khi publish, chốt recipient là member hiện tại trừ Tutor owner. Member gia nhập sau đó đọc được Published nhưng không nhận thông báo hồi tố.
- Trạng thái Published, audit và outbox recipient được ghi nguyên tử. Notification sau commit chỉ mang nội dung trung tính và Announcement ID/deep link; không chép body hoặc metadata file.
- Không có read state riêng cho Announcement.
- Danh sách Published mới nhất trước theo `(published_at,id)` giảm dần.

**File:** đúng owner, purpose `announcement_attachment`, resource scope và hoàn tất kiểm tra kỹ thuật theo M10/K03.1; quyền đọc/tải được kiểm ở mỗi request. BR-21/K03.1 quy định giới hạn 5.000.000 byte/file và không yêu cầu antivirus tự động. Một số PRD/WF05 còn ghi “scan clean”; cần đồng bộ câu chữ với K03.1, không coi đây là yêu cầu triển khai scanner antivirus.

### 3.6 Fee — thuộc #78, 7 API

**Trạng thái 01/11/2026:** cả nhóm là **Dự kiến**, chưa có trong route runtime hoặc OpenAPI; các bảng mô tả contract cần triển khai.

| API | Quyền và dữ liệu |
|---|---|
| `GET /api/v1/programs/{program}/fee-records` | Owner/member đọc theo kỳ. |
| `POST /api/v1/programs/{program}/fee-records` | Owner khai báo kỳ tháng và `amount_minor`; chỉ khi Program Active. |
| `GET /api/v1/fee-records/{feeRecord}` | Owner/member đọc chi tiết. |
| `PATCH /api/v1/fee-records/{feeRecord}` | Owner sửa amount, bắt buộc reason và `expected_version`. |
| `POST /api/v1/fee-records/{feeRecord}/mark-paid` | Owner đánh dấu Tutor đã thu, kèm `expected_version`. |
| `POST /api/v1/fee-records/{feeRecord}/mark-unpaid` | Owner đánh dấu Tutor chưa thu, kèm `expected_version`; chuyển từ paid cần reason. |
| `POST /api/v1/fee-records/{feeRecord}/void` | Owner vô hiệu record, bắt buộc reason và `expected_version`. |

Quy tắc bắt buộc:

- Một record mỗi Program/tháng `YYYY-MM`, tính theo lịch `Program.timezone`; `period_start` là ngày đầu tháng, `period_end` là ngày cuối tháng.
- Chỉ khai báo từ tháng kích hoạt tới tháng hiện tại; không tạo kỳ tương lai. Có thể khai báo bù trong khoảng hợp lệ.
- VND là số nguyên không âm. `0` nghĩa miễn phí; không có record nghĩa là chưa khai báo.
- `FeeRecordStatus`: `declared` → `voided`. `TutorPaymentMark` độc lập: `unmarked`, `marked_paid`, `marked_unpaid`.
- Chỉ sửa amount khi status `declared` và payment mark chưa `marked_paid`; không sửa kỳ tháng.
- Amount bằng 0 giữ payment mark `unmarked`, không thể mark paid/unpaid. Chỉ sửa về 0 khi mark hiện tại vẫn unmarked.
- Void giữ nguyên kỳ, payment mark và lịch sử; vẫn chiếm unique tháng; không mở lại hoặc sửa tiếp.
- Customer chỉ đọc; không xác nhận thanh toán và không sửa thay Tutor.
- Customer response có kỳ, amount, status, payment mark, thời điểm cập nhật và lý do được chia sẻ.
- Luôn ghi rõ: **“Do Tutor khai báo, không phải bằng chứng thanh toán.”**
- Mọi Fee mutation dùng UUID `Idempotency-Key`; mỗi mutation có thay đổi thực tế phát `fee_record_changed` cho Customer member qua outbox sau commit. No-op/replay theo mục 14 không phát lại.
- Không gọi cổng thanh toán.

**Dữ liệu legacy:** trước khi đổi schema phải kiểm kê `declared_status` hiện có và xác minh ý nghĩa của `confirmed`; tuyệt đối không tự ánh xạ thành bằng chứng thanh toán. Record voided vẫn giữ unique Program/tháng.

## 4. Version, retry và dữ liệu do server quản lý

- Resource version bắt đầu từ `1`.
- Mutation có version guard gửi `expected_version`; Submission/feedback dùng `expected_submission_version` theo contract riêng.
- Server kiểm state/version và ghi mutation trong cùng transaction. Stale trả `409`, không ghi một phần.
- Mutation có thay đổi thực tế tăng version đúng một lần. Request không đổi dữ liệu và replay được phân biệt tại mục 14; không tăng version/audit/outbox/notification cho hai trường hợp đó.
- Với command dùng Idempotency-Key: cùng actor/key/method/path/canonical payload trả kết quả đã lưu; cùng key nhưng request khác trả `409`.
- Kiểm quyền hiện tại trước khi trả replay; không suy replay chỉ vì trạng thái hiện tại giống kết quả mong muốn.
- Client không ghi owner, Program ID gắn resource, version, timestamp hệ thống hoặc status ngoài action tương ứng.
- Response chỉ chứa field allow-list của resource; không trả nguyên row database, audit nội bộ hay storage key.
- Mutation nguyên tử bao gồm các side effect được yêu cầu: audit/outbox/receipt và liên kết file nếu có. Lỗi phải rollback toàn bộ phần liên quan.

## 5. Quy ước dữ liệu và lỗi

- Timestamp lưu UTC, API serialize RFC 3339 UTC (`Z`); giao diện hiển thị theo `Program.timezone`.
- Fee month được xác định theo timezone của Program, mặc định `Asia/Ho_Chi_Minh`.
- File phải đúng owner/purpose/resource, complete qua kiểm tra kỹ thuật; kiểm quyền tải lại ở mỗi request. Giới hạn mới là 5.000.000 byte/file theo K03.1.
- Error response theo `{code,message,request_id,errors?}` và trả `X-Request-ID`, phù hợp BR-26.

## 6. Tiêu chí nghiệm thu #78

- Có đủ 15 route thuộc scope: 3 Progress, 5 Announcement, 7 Fee. Overview hiện là Dự kiến; triển khai phần tổng hợp cần thiết rồi đăng ký route và sinh OpenAPI trước khi đổi nhãn Catalog.
- Kiểm quyền owner/member/outsider, sai Program, membership đã mất hiệu lực và ma trận Active/Paused/terminal.
- Customer không thấy Draft, không sửa Progress/Fee/Announcement và không tải file ngoài scope.
- Progress chỉ nhận `progress_percent` nguyên 0–100; không có DELETE; Overview latest Progress đúng thứ tự.
- Announcement stale publish/edit trả 409; published không sửa được; recipient snapshot và notification đúng một lần; rollback không để trạng thái/outbox dở dang.
- Fee sai format tháng, tháng ngoài khoảng, số âm/số lẻ/currency khác VND và duplicate month bị chặn; race tạo cùng tháng chỉ có tối đa một record.
- Fee void không tự chuyển thành paid, giữ payment mark và vẫn chiếm kỳ; API không gọi payment gateway.
- Stale edit/concurrent edit không ghi đè; retry không nhân đôi version, audit, receipt, outbox hoặc notification.
- Sinh OpenAPI từ code, kiểm tra route parity và đồng bộ Catalog; không sửa OpenAPI sinh ra bằng tay.
- Bàn giao PR/commit, API/schema delta, test report/evidence, known limitations; cập nhật trạng thái, việc tiếp theo và blocker trong issue.

## 7. Ranh giới và handover

- **Trong #78:** backend API/schema cần thiết cho Progress, Announcement, Fee; tích hợp Overview tối thiểu; policy, versioning, audit/outbox/replay, file scope, OpenAPI/Catalog và evidence kiểm chứng.
- **Ngoài #78:** UI ba tab; thay đổi nghiệp vụ Session/Task; thanh toán trực tuyến; engine chấm điểm/chart nâng cao; nội dung các module Review/Admin ngoài Overview capability.
- Tài liệu phụ thuộc cần đọc: [BE-D03 / issue #74](BE-D03-issue-74-test-report.md) cho Task/Submissions; [BE-H03 / issue #75](BE-H03-issue-75-test-report.md) cho membership/Program lifecycle; [BE-K02 handover](BE-K02-handover.md) và [K02.1 outbox contract](BE-K02-K02.1-contract.md) cho notification/outbox.
- Chưa có handover triển khai BE-D04. Các commit phụ thuộc đã có trong branch hiện tại; BE-H03 có merge commit PR #77. Issue #74/#75/#68 vẫn mở tại lần đọc GitHub 01/11/2026. Báo cáo local cũ không phản ánh đầy đủ ancestry hiện tại; xem hash và phạm vi phụ thuộc ở mục 12.2.

## 8. Các điểm cần đồng bộ tài liệu trong quá trình triển khai

1. Đã sửa nhãn Overview trong Catalog thành Dự kiến theo đối chiếu 01/11/2026 ở mục 11. Chỉ đổi lại Đã đăng ký sau khi có route và OpenAPI sinh từ code.
2. PRD Announcement và WF05 cần thay cụm “scan clean” nếu đang hàm ý antivirus tự động; dùng policy kiểm tra kỹ thuật theo K03.1.
3. Đồng bộ Database Design với field/type/nullability/FK/CHECK đã xác định tại mục 16 trước khi viết migration và xử lý preflight `confirmed`.
4. Chỉ chuyển route Catalog sang “Đã đăng ký” sau khi route có trong OpenAPI được sinh từ source; route registration không tự chứng minh toàn bộ behavior đã đạt.

## 9. Kiểm kê schema và dữ liệu cũ trước migration

### 9.1. Phạm vi và kết quả kiểm tra

Bản contract trước ghi nhận kiểm tra chỉ đọc database PostgreSQL local `tutorlink` ngày **01/11/2026** bằng transaction read-only, với số liệu bên dưới. **Lượt bổ sung qua graphify này không kiểm kê lại database ứng dụng**; giữ số liệu đó như snapshot trước, không coi là evidence DB mới. Kết quả chỉ mô tả local tại lần kiểm tra cũ, không chứng minh dữ liệu staging/production.

| Bảng | Số row local | Schema hiện tại đáng chú ý |
|---|---:|---|
| `progress_records` | 0 | `value numeric(12,4)`; chưa có `version`; index `(program_id, customer_id, recorded_at)` |
| `announcements` | 0 | Đã có `published_at` nullable để phân biệt Draft/Published; chưa có `version` |
| `fee_records` | 0 | `declared_status` cho `declared/confirmed/cancelled`; chưa có payment mark hoặc `version`; đã unique `(program_id, period_start)` |

Kiểm kê phát hiện 0 Progress có metric/value sai, 0 Announcement có nội dung sai hoặc đã publish, 0 Fee có status `confirmed`/`cancelled` hoặc kỳ không trọn tháng. Không có file link thuộc resource/purpose Announcement, Progress hoặc Fee trong truy vấn kiểm kê. Vì các bảng đều rỗng, các số 0 ở phần kiểm định chất lượng không phải bằng chứng rằng những trường hợp đó không tồn tại ở môi trường khác.

Database local đã có `audit_logs`, `notification_outbox` và `idempotency_receipts`. Receipt có actor/key, method/path, request hash, response snapshot và `expires_at`, unique theo `(actor_id, idempotency_key)`. Outbox writer đã hỗ trợ `announcement_published` và `fee_record_changed`; cần nối đúng vào mutation trong cùng transaction. Không tạo bảng thay thế trước khi kiểm tra đầy đủ các consumer và policy retention hiện có.

Migration history local có **25 migration đã ghi nhận**, trong khi checkout có migration mới hơn chưa nằm trong danh sách database đó. Do đó trước khi chạy thay đổi phải đối chiếu `php artisan migrate:status`, migration pending và môi trường đích. Không chạy migration pending ngoài scope một cách mù quáng, không dùng `migrate:fresh` trên database có dữ liệu.

### 9.2. Chênh lệch với Database Design

| Nội dung | Schema local hiện tại | Schema đích | Delta cần xử lý |
|---|---|---|---|
| Progress `value` | Decimal `numeric(12,4)` | Số nguyên nhỏ `smallint`, trong khoảng 0–100 | Xác minh metric/range/integer; chuyển kiểu và thêm CHECK |
| Progress `metric_name` | Chuỗi không có CHECK chỉ cho metric MVP | Chỉ `progress_percent` | Thêm validation Backend và ràng buộc DB phù hợp |
| Progress version | Chưa có | Integer NOT NULL, mặc định 1 | Thêm cột và khởi tạo dữ liệu cũ bằng 1 |
| Announcement version | Chưa có | Integer NOT NULL, mặc định 1 | Thêm cột và khởi tạo dữ liệu cũ bằng 1 |
| Announcement state | `published_at` nullable | Draft/Published tiếp tục suy ra từ `published_at` | Giữ cách biểu diễn state; không cần thêm enum status |
| Fee status | `declared_status`: `declared/confirmed/cancelled` | `status`: `declared/voided` | Đổi tên/chuẩn hóa status sau khi phân loại legacy |
| Fee payment mark | Chưa có | `unmarked/marked_paid/marked_unpaid` độc lập với status | Thêm field, CHECK và metadata actor/time/reason chính xác tại mục 16 |
| Fee lý do lần sửa cuối | Chưa có | `last_change_reason text NULL` theo mục 16 | Lưu cùng mutation/audit để Customer còn đọc được sau retention audit |
| Fee version | Chưa có | Integer NOT NULL, mặc định 1 | Thêm cột và khởi tạo bản ghi cũ bằng 1 |
| Fee kỳ tháng | Chỉ CHECK `period_end >= period_start` | `period_start` là ngày đầu tháng, `period_end` là ngày cuối cùng tháng | Kiểm tra/chuẩn hóa dữ liệu; tăng CHECK để cấm kỳ không trọn tháng |
| Fee tiền 0 | CHECK `amount_minor >= 0` | `amount=0` bắt buộc mark `unmarked` | Thêm CHECK liên cột và validation tương ứng |
| Fee unique tháng | Unique `(program_id, period_start)` đã có | Áp dụng cả record voided; không tạo lại cùng tháng | Giữ constraint; bảo đảm backfill không làm đổi khóa hoặc bỏ unique |

Schema hiện tại đã có các nền tảng có thể dùng lại: Fee amount không âm, currency chỉ VND, period end không trước period start; Announcement có FK Program/author; Progress có FK Program/Customer/recorder. Những constraint này chưa bao phủ đầy đủ điều kiện nghiệp vụ đích.

### 9.3. Phân loại và chuyển dữ liệu cũ

Trước chuyển đổi trên mỗi môi trường, chạy thống kê read-only theo nhóm sau và lưu kết quả trong handover:

1. Progress: metric khác `progress_percent`; value fractional, ngoài 0–100 hoặc NaN/infinity; Customer không phải Customer của Program; recorder không phải Tutor owner; quan hệ Program thiếu hoặc không hợp lệ.
2. Announcement: title/body trắng hoặc vượt giới hạn; file link không đúng owner/purpose/resource; draft/published timestamp bất thường. Giữ nguyên ID, nội dung, author, `published_at`, created/updated time và liên kết file hợp lệ.
3. Fee: phân bố từng `declared_status`; `period_start`/`period_end` lệch tháng; duplicate sau quy đổi theo Program timezone; amount/currency sai hoặc vượt giới hạn safe integer tại mục 13.4; reason legacy vượt giới hạn mới; Program thiếu `activated_at`/timezone; audit cũ có dấu thu/lý do liên quan. Giữ nguyên ID và unique key tháng; không tự cắt reason/amount để vượt preflight.

| Dữ liệu legacy | Chuyển đổi an toàn được phép |
|---|---|
| Progress có metric đúng, value nguyên 0–100, scope hợp lệ | Chuyển sang `smallint`, giữ nguyên value; đặt `version=1` |
| Progress fractional, ngoài range, metric khác hoặc sai actor/scope | Không tự làm tròn, ép range, đổi metric hay sửa ownership. Đưa vào danh sách lỗi cần xác minh; chặn migration destructive cho tới khi có quyết định xử lý |
| Announcement hợp lệ | Giữ nguyên dữ liệu và ID; đặt `version=1`; không phát notification/outbox khi backfill |
| Fee `declared` | Có thể giữ `status=declared`; chỉ đặt mark `unmarked` sau khi kiểm tra audit/dữ liệu cũ không hàm ý đã đánh dấu thu |
| Fee `confirmed` | Không tự chuyển thành `marked_paid`. Xác minh ý nghĩa legacy bằng code/evidence nghiệp vụ và audit. Nếu không đủ bằng chứng thì giữ nguyên trong danh sách backfill cần xác minh và yêu cầu quyết định rõ trước cutover |
| Fee `cancelled` | Chỉ ánh xạ sang `voided` khi xác nhận semantics legacy tương ứng vô hiệu record. Lưu ý voided tiếp tục chiếm Program/tháng và giữ dấu thu đã xác minh |
| Fee có khoảng ngày không khớp tháng | Chỉ chuẩn hóa theo `Program.timezone` khi quy tắc kỳ và nguồn thời gian được xác định; kiểm tra xung đột unique sau chuẩn hóa. Không tự dịch sang tháng kề |
| Mọi Fee cũ không có version | Đặt `version=1`; không tạo audit giả hoặc suy diễn lịch sử mutation |

Không suy `payment_marked_at/by/reason`, `voided_at/by/reason` từ `updated_at`, actor migration hoặc `confirmed` nếu không có bằng chứng. Nếu schema target giữ metadata hiện hành trong bảng Fee, chỉ backfill các field đó khi có nguồn xác minh; audit append-only vẫn là căn cứ lịch sử cho từng mutation.

### 9.4. Trình tự migration/backfill

1. **Snapshot và dry run:** xác nhận backup/snapshot theo quy trình môi trường; ghi database, thời điểm, migration status, row counts và report phân loại; không ghi dữ liệu trong bước này.
2. **Fail-fast preflight:** migration/command kiểm tra các row không chuyển đổi được và xung đột unique theo tháng. Nếu có trường hợp mơ hồ như Fee `confirmed`/`cancelled`, dừng trước khi đổi dữ liệu; không tự chọn nghĩa.
3. **Mở rộng schema:** thêm các cột version và Fee target fields theo Database Design ở trạng thái tương thích; bổ sung CHECK/unique phù hợp sau khi xác nhận dữ liệu. Với bảng lớn, cân nhắc thêm nullable/default theo cách giảm lock, backfill theo batch rồi mới đặt NOT NULL.
4. **Backfill có thể chạy lại:** Progress chuyển kiểu chỉ khi giá trị đã được chứng minh nguyên và trong range; khởi tạo version các resource cũ bằng 1; giữ nguyên ID/timestamp/state hợp lệ; không phát audit, outbox hoặc notification từ job chuyển đổi. Mỗi batch cần điều kiện rõ, có thể chạy lại mà không nhân side effect.
5. **Thắt constraint và cutover:** xác nhận không còn row vi phạm, sau đó đặt NOT NULL, enum/check, period-month check, amount-zero/payment-mark check và giữ unique `(program_id, period_start)` áp dụng cả voided.
6. **Backend và OpenAPI:** chỉ cutover writer/reader khi schema đích sẵn sàng. Tất cả mutation kiểm state/permission/version trong transaction; ghi audit, receipt và outbox theo contract. Lỗi phải rollback cả mutation và side effect bắt buộc.
7. **Xác minh sau chạy:** so sánh row counts và ID sets trước/sau; tổng hợp trạng thái Fee trước/sau; kiểm version khởi tạo, file links, unique tháng, constraint, receipt retention; sinh OpenAPI và đối chiếu route catalog. Ghi evidence và rollback/forward-fix procedure trong handover.

Nếu bảng rỗng ở **một môi trường đã xác minh**, backfill nghiệp vụ có thể không cần chạy row nào, nhưng schema migration và thay đổi Backend vẫn cần. Trạng thái local rỗng không miễn kiểm kê hoặc backfill ở môi trường khác.

## 10. Kết luận: cần migration, backfill hay thay đổi Backend?

| Việc | Kết luận |
|---|---|
| Migration schema | **Bắt buộc**: Progress value/metric/version; Announcement version; Fee status/payment mark/version/metadata/constraints. Tạo migration mới; không sửa migration đã được áp dụng |
| Backfill | **Local: không có row nghiệp vụ để backfill. Môi trường khác: cần preflight; backfill có điều kiện nếu có dữ liệu.** Không tự ánh xạ `confirmed` sang paid hay `cancelled` sang voided |
| Backend | **Bắt buộc**: authorization, field validation, lifecycle, timezone/month eligibility, `expected_version`, Idempotency-Key, audit, outbox, response allow-list và lỗi stale/conflict |
| Hạ tầng DB dùng lại | `audit_logs`, `notification_outbox`, `idempotency_receipts` đã có ở local; Fee receipt phải giữ tối thiểu theo retention của Fee Record theo Database Design, không áp TTL 30 ngày mặc định từ command khác |
| Chỉ đổi logic Backend | **Không đủ**: schema thiếu version/mark và kiểu/ràng buộc Progress chưa đúng contract |

Kết luận này dựa trên schema/migration repository và **một database local rỗng**, không phải kiểm kê staging/production. Trước khi triển khai trên dữ liệu thật, bổ sung output preflight của chính môi trường đó và quyết định rõ semantics legacy `confirmed`/`cancelled`.


## 11. Đối chiếu route đích, source Laravel, runtime và OpenAPI

### 11.1. Nguồn và kết quả xác minh ngày 01/11/2026

- [API Route Catalog](<../Architecture & Technical Design/API Route Catalog.md>) là danh mục method/path đích. Nhãn Dự kiến không có nghĩa endpoint đã gọi được hoặc được loại khỏi scope.
- [Contract này](BE-D04-contract.md) chốt phần cần triển khai cho issue #78; dùng cùng method/path với Catalog, bổ sung payload, quyền và state guard.
- [Laravel routes](../../backend/routes/api.php) là nơi đăng ký ánh xạ method/path tới controller/action. Có bảng database, PRD hoặc một dòng Catalog không tự đăng ký route.
- [OpenAPI](../api/openapi.json) là artifact sinh từ route/code đã đăng ký. [Hướng dẫn API](../api/README.md) quy định sinh bằng Scramble, không sửa JSON bằng tay.
- Đã chạy lại chỉ đọc `docker compose exec -T backend php artisan route:list --path=api/v1 --json` trong lượt bổ sung 01/11/2026 ([output](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-01)): runtime local có **89 route entries**, không có Progress, Announcement, Fee hoặc Overview trong phạm vi đối chiếu. OpenAPI đã commit có **89 operations**, cũng không có cả bốn nhóm này. Số lượng tổng bằng nhau chưa chứng minh parity toàn bộ API; kết luận ở đây dựa trên đối chiếu chính xác 16 method/path được nêu dưới.

| Nhóm | Method/path đích trong Catalog so với contract | Source `api.php` | Runtime `route:list` | OpenAPI | Trạng thái đúng |
|---|---|---|---|---|---|
| Progress | Khớp đủ 3/3 | 0/3 | 0/3 | 0/3 | Dự kiến |
| Announcement | Khớp đủ 5/5 | 0/5 | 0/5 | 0/5 | Dự kiến |
| Fee | Khớp đủ 7/7 | 0/7 | 0/7 | 0/7 | Dự kiến |
| Overview | Khớp `GET /api/v1/programs/{program}/overview` | Chưa có | Chưa có | Chưa có | Dự kiến; đã sửa nhãn Catalog |

Tổng cộng **15 endpoint của ba tab + 1 Overview**. Không có method/path chỉ xuất hiện ở Catalog hoặc chỉ xuất hiện ở contract trong tập này. Trước lần đối chiếu này contract rút gọn tiền tố `/api/v1` trong các bảng và sắp xếp Fee khác Catalog, nên cách trình bày trông khác nhau. Đã chuẩn hóa đường dẫn đầy đủ và thứ tự Fee theo Catalog; đây không phải đổi thiết kế endpoint.

### 11.2. Vì sao chưa đăng ký trong Laravel?

Đây là khoảng trống triển khai của BE-D04, không phải lỗi có thể khắc phục bằng thêm route giả:

- PRD Progress mục 4 ghi endpoint chưa có và schema chưa đồng bộ kiểu value/version.
- PRD Announcement mục 4 ghi baseline đã có migration nhưng API/UI và phần phát sự kiện publish chưa hoàn chỉnh.
- PRD Fee mục 4 ghi API/schema/runtime còn cần đồng bộ; status/payment mark và dữ liệu legacy phải được xử lý trước khi writer mới hoạt động đúng.
- Source `api.php` có các route Program, Task, Session đã được đăng ký theo phần triển khai tương ứng, nhưng chưa có khai báo 15 route BE-D04 hoặc Overview. `ProgramController` hiện không có action Overview; route `GET /api/v1/programs/{program}` chỉ ánh xạ tới `show`, không tự cung cấp summary contract của Overview.

Tài liệu cho thấy hành vi đích đã được mô tả trước khi backend hoàn tất. Không có bằng chứng trong lần kiểm tra này để quy nguyên nhân cho route cache, lỗi deploy, hoặc người phụ trách cố ý hoãn việc. Cần triển khai controller/action, request validation, policy, resource/service, transaction và schema liên quan, sau đó đăng ký route trong middleware phù hợp và sinh OpenAPI.

### 11.3. Sai lệch đã sửa và điều kiện cập nhật tiếp

- Catalog từng gắn Overview là Đã đăng ký dù cả source, runtime và OpenAPI đang thiếu. Đã sửa thành Dự kiến, giữ nguyên method/path và yêu cầu nghiệp vụ.
- Các 15 endpoint Progress/Announcement/Fee vốn đã là Dự kiến trong Catalog; không cần đổi tên hoặc thêm endpoint ngoài danh mục để giải quyết vấn đề đối chiếu.
- Contract trước đây chưa đặt trạng thái runtime ngay cạnh từng nhóm và dùng đường dẫn rút gọn. Đã thêm trạng thái cùng ngày kiểm tra và dùng đường dẫn đầy đủ để người đọc đối chiếu trực tiếp.
- Khi triển khai: đăng ký đúng method/path → kiểm tra quyền/hành vi → xác nhận `route:list` → sinh OpenAPI bằng Scramble và kiểm parity → đổi nhãn Catalog cho route đã xuất hiện trong artifact. Việc đăng ký route không tự xác nhận behavior đã hoàn tất.
- Không dùng Catalog hoặc contract thiết kế làm bằng chứng endpoint khả dụng; không thêm placeholder route chỉ để đủ số lượng; không sửa OpenAPI JSON bằng tay.

## 12. Căn cứ và phạm vi áp dụng ngày 01/11/2026

### 12.1. Cách dùng nguồn

Đã dùng graphify query/explain/path để định vị tài liệu và source, sau đó kiểm chứng raw source tại HEAD. Graph không có node hoặc path không phải bằng chứng chức năng đã/ chưa hoạt động. Query rộng bị cắt theo budget đã được thu hẹp về các nguồn trong [baseline evidence](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-01).

Thứ tự áp dụng: BR và decision đã chốt → PRD/Database Design/K02.1/K03.1 → contract kỹ thuật cụ thể → runtime/evidence cho trạng thái triển khai. Mục 13–17 quy định chi tiết triển khai và ưu tiên khi mô tả tổng quát phía trên chưa đủ rõ. D22 được truy vết qua BR-19 trong [decision register](../UNRESOLVED_DECISIONS_2026-10-20.md).

### 12.2. Commit phụ thuộc thực sự có trong branch

`git merge-base --is-ancestor <hash> HEAD` đạt cho tất cả hash dưới đây, tại branch `be-d04`, HEAD `135efcc8ec5c4c73cff8d58cabc62a17772234bf`:

| Phụ thuộc | Commit nền đã có | Phạm vi có thể dùng lại |
|---|---|---|
| BE-D03 | `c67eae1ff0c141be051f1d44073fd41f54b46628`; regression `9c9d7468853aadbc2c32928fa55598e945d04d4c` | Task/Submission, membership checks, atomic file links; canonical payload/timestamp patterns |
| BE-H03 | `2026c996400137da31d21b1e7064cb4d4823b51e`; merge PR #77 `70125345155cb3c85bd949676701e74967848dac` | Invitation/membership/lifecycle, khóa và recheck Program |
| BE-K02 | writer `a54336c0bba3246c5ba4c73e648ee6e908f25227`; handover `7834e5f42fe9410f2e576c08de0325b6d8b6d8a3` | Outbox writer/worker/retention vận hành; event contract |

Các commit phụ thuộc trên đã có trong branch hiện tại. Ancestry không chứng minh CI hiện tại hoặc integration BE-D04. Issue #74/#75/#68 vẫn open tại lần đọc GitHub trong baseline evidence; trạng thái issue và merge là hai bằng chứng khác nhau.

### 12.3. Quyết định kỹ thuật áp dụng

- Payload: Fee dùng field `period`; allow-list, nullability, normalization và giới hạn theo mục 13.
- List: phân trang mặc định 20/tối đa 50 theo BR-26; scope/filter/thứ tự theo 13.5.
- Retry: key bắt buộc với Announcement publish và mọi Fee mutation; Progress/Draft theo version và chính sách tại mục 14. No-op không tăng version/audit/outbox.
- Transaction: khóa theo mục 15, đọc lại quyền/state/version dưới khóa và ghi nguyên tử các side effect bắt buộc.
- Schema/audit/file: áp dụng metadata Fee, audit projection, deep-link templates và flow file Announcement tại mục 16–17.
- Fee receipt giữ đến khi Fee và lịch sử liên quan đủ điều kiện purge theo Database Design; không áp TTL chung 30 ngày.

## 13. Request/response và truy vấn cụ thể

### 13.1. Quy ước chung của 16 API đích

- Session auth/account active và CSRF theo [API guide](../api/README.md). Scope kiểm trước đọc/đếm/phân trang; outsider/sai Program hoặc Customer truy cập Draft trả 404; member đọc được nhưng gọi mutation của owner trả 403; thiếu auth 401; account inactive 403.
- Body là JSON object; từ chối unknown field, kể cả field server-managed. ID là số nguyên dương; `expected_version` là integer 1..2.147.483.647. Không nhận chuỗi số, boolean hoặc float thay integer. `expected_version` là điều kiện, không phải cột được gán từ request.
- Create không nhận `expected_version`. PATCH phải có ít nhất một field sửa được bên cạnh `expected_version`, nếu không trả 422. Bỏ field PATCH = giữ nguyên; `null` chỉ xóa field nullable được ghi dưới đây. Không dùng mass assignment cả body.
- Chuỗi UTF-8; CRLF/CR chuyển LF, không Unicode-normalize. `title`/`reason` trim hai đầu; body/note giữ khoảng trắng nếu có nội dung. Chuỗi toàn whitespace không hợp lệ, không ngầm đổi `""` thành `null`. Cần cấu hình middleware BE-D04 để validation/hash/storage nhận cùng dữ liệu; source hiện chỉ có ngoại lệ Message/Task.
- POST create mới trả 201; GET/PATCH/action trả 200. Body thành công: `{"data": <resource>}`; list: `{"data": [...], "meta": {"current_page":1,"per_page":20,"total":0,"last_page":1}}`. Không thêm success envelope khác theo từng nhóm. List rỗng là `data:[]`; trang vượt cuối trả `[]` và giữ total/last_page đúng, last_page tối thiểu 1.
- Trả `X-Request-ID` ở mọi response, error body `{code,message,request_id,errors}` theo `ApiErrorResponse`; errors rỗng là `{}`. Request ID của replay là request hiện tại trong header; snapshot thành công không chứa request ID cũ.
- Timestamp API là UTC RFC 3339 `Z`, có thể có microsecond; ngày kỳ Fee là `YYYY-MM-DD`. Nullable timestamp/chuỗi trả `null`, không bỏ key. Create mới có created/updated time; timestamp legacy nullable giữ `null` nếu thiếu.

### 13.2. Progress

| Endpoint | Body nhận chính xác |
|---|---|
| POST `/programs/{program}/progress-records` | Bắt buộc `metric_name` string đúng `progress_percent`, `value` integer 0–100; `note` optional/null, string có nội dung tối đa 2.000 ký tự |
| PATCH `/progress-records/{progressRecord}` | Bắt buộc `expected_version`; ít nhất một trong `value` (không null), `note` (nullable, cùng giới hạn create) |

`note` tối đa 2.000 ký tự; optional hoặc null khi tạo, nullable khi sửa. Không nhận `customer_id`, `recorded_by`, `recorded_at` hoặc metric trong PATCH. Server lấy Customer từ Program, recorder từ owner. PATCH giữ nguyên `recorded_at`, `recorded_by`, metric và quan hệ Program/Customer; `updated_at` chỉ đổi khi dữ liệu thực sự đổi. Vì vậy sửa một record cũ không đẩy nó thành latest.

Response allow-list đầy đủ: `id`, `program_id`, `customer_id`, `metric_name`, `value`, `note`, `recorded_by`, `recorded_at`, `version`, `created_at`, `updated_at`. Không có Progress status.

Ví dụ POST request và response 201:

```json
{"metric_name":"progress_percent","value":35,"note":null}
```

```json
{"data":{"id":501,"program_id":21,"customer_id":8,"metric_name":"progress_percent","value":35,"note":null,"recorded_by":7,"recorded_at":"2026-11-01T08:00:00Z","version":1,"created_at":"2026-11-01T08:00:00Z","updated_at":"2026-11-01T08:00:00Z"}}
```

PATCH `{"expected_version":1,"note":"Đã nắm phần cơ bản"}` giữ value; PATCH `{"expected_version":2,"note":null}` xóa note. `value:null` trả 422.

### 13.3. Announcement

| Endpoint | Body nhận chính xác |
|---|---|
| POST `/programs/{program}/announcements` | `title` bắt buộc 1–180 ký tự; `body` bắt buộc 1–10.000 ký tự; `file_ids` optional, mặc định `[]` |
| PATCH `/announcements/{announcement}` | `expected_version` bắt buộc; ít nhất một trong `title`, `body`, `file_ids` |
| POST `/announcements/{announcement}/publish` | Chỉ `expected_version` bắt buộc; UUID `Idempotency-Key` ở header |

Title/body/file_ids không nullable. `file_ids` là tập ID integer dương không trùng, tối đa 5; thứ tự không có ý nghĩa, serialize tăng dần ID. PATCH bỏ file_ids giữ nguyên tập; `[]` tháo hết liên kết Draft; `null` trả 422. Upload/complete là bước riêng; không nhận bytes, owner, purpose, status hoặc published_at trong body Announcement.

Response allow-list: `id`, `program_id`, `author_id`, `state` (`draft`/`published`, suy ra từ published_at), `title`, `body`, `files`, `published_at`, `version`, `created_at`, `updated_at`. `files` gồm `id`, `original_name`, `mime_type`, `size_bytes`, `complete`; không storage key/disk/checksum. Tải bằng `/api/v1/files/{id}/download`, luôn kiểm lại quyền. Draft và Published dùng cùng projection sau authorization.

Ví dụ create request / response 201, publish request / response 200 (ví dụ không file):

```json
{"title":"Lịch tuần này","body":"Buổi học bắt đầu lúc 19:00.","file_ids":[]}
```

```json
{"data":{"id":601,"program_id":21,"author_id":7,"state":"draft","title":"Lịch tuần này","body":"Buổi học bắt đầu lúc 19:00.","files":[],"published_at":null,"version":1,"created_at":"2026-11-01T08:05:00Z","updated_at":"2026-11-01T08:05:00Z"}}
```

```json
{"expected_version":1}
```

```json
{"data":{"id":601,"program_id":21,"author_id":7,"state":"published","title":"Lịch tuần này","body":"Buổi học bắt đầu lúc 19:00.","files":[],"published_at":"2026-11-01T08:06:00Z","version":2,"created_at":"2026-11-01T08:05:00Z","updated_at":"2026-11-01T08:06:00Z"}}
```

### 13.4. Fee

Field tháng API được chọn là **`period`**, string đúng `YYYY-MM`, không nhận alias `month`, `fee_month` hay client-supplied period_start/end. API lấy timezone/activation từ Program; server tính ngày đầu/cuối tháng. Cả năm và tháng phải tạo được tháng lịch hợp lệ.

| Endpoint | Body nhận chính xác |
|---|---|
| POST `/programs/{program}/fee-records` | Bắt buộc `period`, `amount_minor`; `currency` optional mặc định `VND`, nếu gửi phải đúng `VND`, không null |
| PATCH `/fee-records/{feeRecord}` | Bắt buộc `expected_version`, `amount_minor`, `reason` |
| POST `/fee-records/{feeRecord}/mark-paid` | Bắt buộc `expected_version`; `reason` optional/null |
| POST `/fee-records/{feeRecord}/mark-unpaid` | Bắt buộc `expected_version`; `reason` optional/null nếu unmarked → unpaid, bắt buộc có nội dung khi paid → unpaid |
| POST `/fee-records/{feeRecord}/void` | Bắt buộc `expected_version`, `reason` |

Mọi Fee write bắt buộc UUID `Idempotency-Key`. Amount là JSON integer 0..9.007.199.254.740.991 để round-trip an toàn với frontend/canonical JSON; không nhận float/scientific notation/chuỗi số/boolean/null. Giới hạn này áp dụng đồng nhất tại validation và DB CHECK. Reason khác null phải là string sau trim dài 1–2.000 ký tự; required reason không nhận null, blank hoặc omission. Không có API reset mark về unmarked.

Response allow-list: `id`, `program_id`, `period`, `period_start`, `period_end`, `amount_minor`, `currency`, `status`, `tutor_payment_mark`, `version`, `payment_marked_at`, `payment_mark_reason`, `voided_at`, `void_reason`, `last_change_reason`, `disclaimer`, `created_at`, `updated_at`. `last_change_reason` là reason được chia sẻ của mutation mới nhất tại version hiện tại (null nếu create/mark không reason), giữ trên Fee theo 16.1 để không phụ thuộc audit đã purge; không trả toàn bộ audit, actor metadata hoặc lịch sử private. `payment_mark_reason` mô tả lần đổi mark gần nhất, không bị amount edit/void ghi đè. Disclaimer luôn do server trả câu chuẩn, không lấy chuỗi tùy ý từ request/legacy.

Ví dụ create request / response 201 và các action payload:

```json
{"period":"2026-11","amount_minor":1200000,"currency":"VND"}
```

```json
{"data":{"id":701,"program_id":21,"period":"2026-11","period_start":"2026-11-01","period_end":"2026-11-30","amount_minor":1200000,"currency":"VND","status":"declared","tutor_payment_mark":"unmarked","version":1,"payment_marked_at":null,"payment_mark_reason":null,"voided_at":null,"void_reason":null,"last_change_reason":null,"disclaimer":"Do Tutor khai báo, không phải bằng chứng thanh toán.","created_at":"2026-11-01T08:10:00Z","updated_at":"2026-11-01T08:10:00Z"}}
```

```json
{"expected_version":1,"amount_minor":1000000,"reason":"Điều chỉnh theo thỏa thuận"}
```

```json
{"expected_version":2,"reason":null}
```

```json
{"expected_version":3,"reason":"Tutor điều chỉnh lại dấu thu"}
```

```json
{"expected_version":4,"reason":"Khai báo nhầm kỳ thỏa thuận"}
```

Bốn body cuối lần lượt PATCH amount, mark-paid, mark-unpaid, void; mỗi response 200 dùng đầy đủ projection trên với state/version/time/reason đã commit. Void không sửa period hoặc payment metadata. Free record amount=0 trả mark unmarked; gọi mark trả 409.

Ví dụ response 200 sau cả chuỗi trên, minh họa void giữ nguyên dấu chưa thu và payment metadata:

```json
{"data":{"id":701,"program_id":21,"period":"2026-11","period_start":"2026-11-01","period_end":"2026-11-30","amount_minor":1000000,"currency":"VND","status":"voided","tutor_payment_mark":"marked_unpaid","version":5,"payment_marked_at":"2026-11-01T08:13:00Z","payment_mark_reason":"Tutor điều chỉnh lại dấu thu","voided_at":"2026-11-01T08:14:00Z","void_reason":"Khai báo nhầm kỳ thỏa thuận","last_change_reason":"Khai báo nhầm kỳ thỏa thuận","disclaimer":"Do Tutor khai báo, không phải bằng chứng thanh toán.","created_at":"2026-11-01T08:10:00Z","updated_at":"2026-11-01T08:14:00Z"}}
```

### 13.5. Danh sách, filter và thứ tự

Áp dụng **BR-26**: `page` integer ≥1 (mặc định 1), `per_page` integer 1–50 (mặc định 20); vượt giới hạn/sai kiểu trả 422, không âm thầm clamp. Đây là áp dụng quy tắc đã có tại [Business Rules, BR-26](<../Domain Documentation/Business Rules.md>), không cần quyết định pagination mới. Query parameters truyền qua URL có thể là chuỗi decimal đúng cú pháp; khác với integer trong JSON body.

| List | Filter hỗ trợ ngoài page/per_page | Thứ tự cố định |
|---|---|---|
| Progress | Không filter nghiệp vụ ở MVP | `(recorded_at DESC, id DESC)`; PATCH không đổi recorded_at |
| Announcement | `state=published` (mặc định), `draft`, `all` | published: `(published_at DESC,id DESC)`; draft: `(created_at DESC NULLS LAST,id DESC)`; all: Draft trước theo thứ tự draft, rồi Published theo thứ tự published |
| Fee | `period=YYYY-MM`, `status=declared\|voided`, `tutor_payment_mark=unmarked\|marked_paid\|marked_unpaid`; kết hợp AND | `(period_start DESC,id DESC)` kể cả voided |

Owner xem Draft bằng `?state=draft` hoặc `?state=all`. Customer chỉ dùng published/mặc định; yêu cầu draft/all trả 403, không trả count hoặc nội dung Draft. Filter không hợp lệ/unknown query trả 422; không hỗ trợ search/sort tùy ý/date range trong phiên bản này. Total luôn tính sau scope và filter. Thứ tự xác định không cam kết snapshot giữa các trang khi có ghi đồng thời; client refresh trang 1 khi cần dữ liệu mới.

Ví dụ `GET /api/v1/programs/21/progress-records?page=1&per_page=20`:

```json
{"data":[],"meta":{"current_page":1,"per_page":20,"total":0,"last_page":1}}
```

### 13.6. Overview và lỗi

Overview không có query/filter hoặc pagination. Response `data` có đúng `next_session`, `task_counts`, `latest_progress`, `latest_announcement`, `capabilities`; Session dùng allow-list `SessionResource`, Progress/Announcement dùng projection 13.2/13.3; latest Announcement luôn Published kể cả owner. `next_session` yêu cầu planned_starts_at > server now. Dựng trong một consistent DB read snapshot để counts/latest/capabilities không trộn hai trạng thái Program trong một response.

Ví dụ Program Active chưa có dữ liệu, actor Tutor owner (can_create_review phụ thuộc policy Review hiện hành):

```json
{"data":{"next_session":null,"task_counts":{"todo":0,"in_progress":0,"submitted":0,"completed":0,"cancelled":0},"latest_progress":null,"latest_announcement":null,"capabilities":{"can_create_session":true,"can_create_task":true,"can_record_progress":true,"can_create_fee_record":true,"can_create_announcement":true,"can_create_review":false}}}
```

| HTTP/code | Trường hợp |
|---|---|
| 409 `stale_version` | expected_version khác version đang khóa; errors chỉ current_version sau khi đã kiểm quyền |
| 409 `program_state_conflict` | Request mới không được phép theo Program state |
| 409 `progress_state_conflict` / `announcement_state_conflict` / `fee_record_state_conflict` | Sai trạng thái resource/action; Published edit/publish lại, voided mutation, Fee marked_paid edit… |
| 409 `fee_period_exists` | Key khác tạo cùng Program/tháng, kể cả tháng đã void |
| 409 `idempotency_conflict` | Actor/key có receipt nhưng method/path/hash không khớp |
| 422 `validation_failed` | Field/key/query/format/range/nullable hoặc file selection sai; errors theo field |

Ví dụ stale (header `X-Request-ID` trùng request_id):

```json
{"code":"stale_version","message":"Dữ liệu đã thay đổi. Hãy tải lại trước khi cập nhật.","request_id":"a1d6514b-761c-4fe4-b226-736d4a33e707","errors":{"current_version":3}}
```

## 14. Idempotency và request không đổi dữ liệu

### 14.1. Endpoint nào cần key?

| Nhóm/endpoint | UUID `Idempotency-Key` | Receipt/retry |
|---|---|---|
| Tất cả GET | Không | Đọc hiện tại, không receipt |
| Progress POST/PATCH | Không yêu cầu, không xử lý key | POST retry có thể tạo record mới; PATCH retry bằng version cũ trả 409 sau lần sửa thực sự |
| Announcement create Draft/PATCH Draft | Không yêu cầu, không xử lý key | POST retry có thể tạo Draft mới; PATCH dựa expected_version |
| Announcement publish | **Bắt buộc** | Shared receipt actor/key, giữ 30 ngày theo BR-23 |
| Fee create/PATCH/mark-paid/mark-unpaid/void | **Bắt buộc** | Shared receipt actor/key, `expires_at=NULL`, giữ theo Fee và lịch sử liên quan |

Key gửi cho Progress/Draft không tạo bảo đảm replay; client không tự retry POST sau timeout khi chưa xác minh kết quả. Key thiếu/sai UUID ở endpoint bắt buộc trả 422; UUID được chuẩn hóa lowercase. Không thêm key vào hash payload.

### 14.2. Hash, receipt và replay

1. Validate cấu trúc/allow-list, normalize theo 13.1; lấy canonical path thật gồm ID numeric và tiền tố `/api/v1`, không host/query/trailing slash; method uppercase. Unknown query ở write trả 422.
2. Canonical payload giữ `expected_version`; create Fee chèn currency mặc định VND; optional mark reason bỏ qua được chuẩn hóa thành null. PATCH giữ nguyên **sự hiện diện** field: omission khác explicit null. File IDs nếu có sort tăng dần, không tự lấy giá trị hiện tại của resource để điền field PATCH bị bỏ.
3. JSON canonical theo RFC 8785, không Unicode-normalize. Hash chọn thống nhất với pattern Session: `SHA-256(method + "\n" + canonical_path + "\n" + canonical_json)` dạng lowercase hex 64 ký tự. Payload chỉ gồm fixed ASCII keys/string/null/safe integer/array ID; nếu mở rộng shape phải thêm canonical vectors. Không gọi một `json_encode` tùy ý là canonicalizer tổng quát.
4. Dùng `idempotency_receipts` hiện có: UUID id, actor_id, idempotency_key, method, canonical_path, request_hash, response_status, response_snapshot, created_at, expires_at. Unique `(actor_id,idempotency_key)` áp dụng xuyên resource/endpoint. Receipt chỉ lưu success 200/201 đã commit; snapshot đúng allow-list và HTTP status, không audit/storage key hoặc credentials.
5. Sau khi khóa và **kiểm quyền hiện tại**, đọc receipt trước state/version của request mới. Method/path/hash khớp → trả HTTP status/body cũ (Fee create replay vẫn 201); khác → 409 idempotency_conflict. Replay hợp lệ vẫn trả khi Program terminal/resource voided hoặc version đã tăng, miễn actor còn quyền; không trả snapshot cho actor đã mất quyền. Snapshot là kết quả lịch sử, client có thể GET lại để lấy trạng thái mới nhất.
6. Receipt, mutation, audit, file links và outbox bắt buộc cùng DB transaction/connection. Hai request cùng actor/key được tuần tự hóa qua khóa User actor, UNIQUE là chốt bảo vệ; không bắt unique error rồi tiếp tục transaction PostgreSQL đã lỗi. Rollback không giữ receipt lỗi; request thất bại có thể retry sau khi sửa nguyên nhân.
7. Publish dùng expires_at=created_at+30 ngày. Receipt hết hạn không còn bảo đảm replay; xử lý như request mới sau khi loại receipt expired an toàn dưới khóa. Resource đã Published vẫn chặn publish mới; không phát event lần hai chỉ vì receipt đã hết TTL.

Fee receipt không bao giờ hết hạn do job TTL chung: **giữ đến khi Fee cùng lịch sử liên quan đủ điều kiện purge** theo [Database Design, dòng 1121](<../Architecture & Technical Design/Database Design — TutorLink.md>). Cleanup phải bỏ qua `expires_at=NULL`, nhận diện path Fee và không cascade xóa receipt qua account/resource delete trước điều kiện retention. Chưa có quy trình purge Fee được chứng nhận thì giữ receipt, không tự tạo TTL thay thế. `outbox:purge` hiện chỉ xử lý outbox/notifications/audit; nó không chứng minh receipt cleanup đã được tích hợp. Cần kiểm mọi cleanup/consumer receipt và test cả đường scheduled lẫn lazy-expiry. Dùng canonical_path và ID trong response snapshot để đối chiếu Fee; nếu cần index cho purge, phải đưa vào migration delta, không tự tạo bảng receipt thứ hai.

Nền source đã kiểm thêm: migration `2026_10_26_212524_add_invitation_acceptance_support_tables.php` có FK actor_id **RESTRICT**, expires_at nullable và index expires_at. Các nhánh lazy-expiry trong AcceptInvitation, TaskController feedback, SaveSession và TransitionSession đều kiểm expires_at khác NULL trước khi xóa; giữ nguyên bảo vệ này khi tích hợp D04. Đây là bằng chứng source, chưa phải test durable Fee receipt/cleanup runtime.

### 14.3. No-op khác replay

Thứ tự request mới: kiểm quyền → receipt nếu có → Program state → expected_version → resource/action state → so sánh dữ liệu sau chuẩn hóa. Các guard vẫn bắt buộc dù payload có vẻ giống dữ liệu hiện tại.

| Request mới, không có receipt khớp | Kết quả |
|---|---|
| Progress PATCH cùng value/note; Draft PATCH cùng nội dung/tập file; Fee PATCH cùng amount | 200 projection hiện tại; không đổi version, updated_at, recorded_at, audit hoặc outbox |
| Fee mark-paid khi đã marked_paid; mark-unpaid khi đã marked_unpaid, version hiện tại đúng, declared/Program còn cho ghi/amount >0 | 200 hiện tại; giữ payment metadata cũ; reason gửi lại không sửa lý do lần mark trước |
| Payload cùng dữ liệu nhưng expected_version cũ | 409 stale_version, không biến thành no-op |
| PATCH Published dù giá trị giống; publish lại với key mới; void lại với key mới; mark/edit Fee voided | 409 state conflict; trạng thái cuối không tự chứng minh replay |
| Program terminal, PATCH/mark cùng giá trị nhưng key mới/không key | 409 program_state_conflict |
| Fee marked_paid PATCH amount dù amount giống | 409 fee_record_state_conflict; quyền sửa amount vẫn bị chặn |
| Key có receipt khớp, dữ liệu hiện tại đã khác kết quả cũ | Replay snapshot cũ, không phải no-op so với DB hiện tại |

No-op vẫn phải có payload hợp lệ: Fee PATCH reason bắt buộc ngay cả khi amount giống; PATCH trống trả 422. Không có API reason-only update. Fee no-op thành công với **key mới** ghi một receipt 200 trong transaction để key đó được replay/mismatch đúng; không ghi audit nghiệp vụ/outbox/version. Progress/Draft không receipt. Replay receipt có sẵn không tạo thêm receipt. Cụm “mỗi mutation thành công” trong PRD được áp dụng cho mutation **có thay đổi thực tế**.

## 15. Transaction và phối hợp lifecycle Program

### 15.1. Thứ tự khóa bắt buộc

Theo [TransitionProgram](../../backend/app/Actions/Programs/TransitionProgram.php), [SaveSession](../../backend/app/Actions/Sessions/SaveSession.php), [H03 handover](BE-H03-issue-75-test-report.md) và [K04.1](BE-K04-K04.1-contract.md):

**Users tăng dần ID (owner/Customer/actor, loại trùng) → TutorProfile → Listing nếu cần → Program → ProgramMember tăng ID nếu cần → Progress/Announcement/Fee → Files tăng ID → FileLinks tăng ID → receipt/audit/outbox.**

- Có thể bỏ khóa không cần thiết, nhưng không đảo thứ tự. Hints đọc trước transaction/khóa (route binding, Program ID từ resource, profile/customer/owner) chỉ dùng để tìm row khóa; sau khóa phải xác minh lại quan hệ. Không khóa resource/File rồi quay lại khóa User/Profile/Program.
- Writer D04 luôn khóa Program bằng `FOR UPDATE`, cả create và update/publish/mark/void. Khóa row resource khi đã có; với Fee create, Program lock tuần tự hóa ngay cả khi Fee row tháng đó chưa tồn tại; UNIQUE Program/tháng vẫn là chốt cuối.
- Listing chỉ cần khi thao tác phải recheck Listing; D04 không thêm điều kiện public Listing/TutorProfile approval ngoài Workspace policy đã có. Khóa Profile để xác minh ownership, không ngầm cấm Workspace lịch sử vì Listing pause.
- Dùng PostgreSQL READ COMMITTED cho writer; đọc/recheck dưới khóa, không dùng Eloquent object hoặc snapshot trước khi chờ khóa để quyết định state/version. Mọi writer thay membership hoặc các quan hệ ảnh hưởng quyền của Program phải tuân cùng Program lock trước khi đổi; kiểm các writer hiện hữu khi tích hợp.
- Complete/validate file dùng User → File ở source hiện có. Luồng Announcement link/metadata/download phải tuân thứ tự tương thích khi cần Program; không thêm nhánh File → Program gây đảo khóa. Delete/cleanup phải kiểm link đang khóa, không xóa File vừa được gắn thành công.

### 15.2. Trình tự command

1. Parse/validate shape, xác thực identity; mở transaction và khóa theo 15.1.
2. Đọc lại account state, owner profile/user, Program/customer relation, membership active và resource scope. Customer/outsider không nhận receipt hoặc version nội bộ.
3. Kiểm receipt/replay theo mục 14. Đừng chặn replay hợp lệ bằng state/version guard của mutation mới.
4. Với request mới, kiểm Program state, expected_version hiện tại và resource/action state; với Fee create, tính server now sau khi chờ khóa, dùng activated_at và timezone đang khóa để kiểm tháng.
5. Với file mutation, khóa hợp của file IDs cũ/mới theo ID; kiểm tất cả file trước khi sửa liên kết. Publish kiểm lại toàn bộ file liên kết hiện tại. So sánh no-op sau các guard; tạo snapshot/receipt no-op nếu có.
6. Ghi thay đổi và version +1 đúng một lần, audit, link/unlink và recipient snapshot/outbox, rồi success receipt. Recipient query lấy membership active trong cùng transaction và trừ Tutor owner; các membership writers phải cùng protocol Program lock. Không query lại recipients sau commit để thêm người mới.
7. Commit rồi mới trả success. Worker gửi notification sau commit; không gọi transport/file upload/delete object storage trong transaction nghiệp vụ D04. Lỗi link/audit/outbox/receipt rollback tất cả; deadlock/serialization retry có giới hạn, không retry vô hạn hoặc nhân notification.

### 15.3. Kết quả race cần kiểm

| Race | Kết quả được phép |
|---|---|
| Progress/Draft/publish/Fee existing vs pause | Một thứ tự commit xác định; sau pause vẫn cho phép theo ma trận, kiểm version trên row mới nhất |
| Fee create vs pause | Create khóa trước có thể commit khi còn Active; pause khóa trước → create đọc Paused và 409 |
| D04 writer vs complete/cancel Program | Lifecycle thắng trước → writer mới 409; writer thắng trước → commit trước terminal, lịch sử còn đọc được; không có mutation mới sau terminal |
| PATCH vs publish một Draft | Edit thắng → publish version cũ 409; publish thắng → edit 409; không có Published với file/nội dung bị sửa sau đó |
| Hai Fee create cùng tháng | Cùng key/request replay một ID; key khác chỉ một 201, request kia 409 fee_period_exists |
| Cùng expected_version, hai edit/mark/void khác nhau | Tối đa một thay đổi commit; request thua 409 stale_version; no-op không được làm mất thay đổi của request khác |

Program completion vẫn chỉ xét blocker Session/Task hiện có; Progress/Announcement/Fee không trở thành blocker mới. Program.version hiện là offer version, không dùng làm guard D04 và không tự tăng khi D04 mutation. Test race phải dùng PostgreSQL và process/connection độc lập chờ khóa thật, theo mẫu H03/D03/K04; SQLite không chứng minh row-lock protocol.

## 16. Schema đích Fee và audit cụ thể

### 16.1. Schema Fee đích

Danh sách sau xác định schema Fee cần triển khai. Đồng bộ Database Design và tạo migration mới; giữ nguyên migration đã áp dụng. Trạng thái schema hiện tại được đối chiếu tại mục 9.

| Field | Type / nullability / default | FK hoặc ý nghĩa |
|---|---|---|
| `id` | bigint NOT NULL PK | Giữ ID cũ |
| `program_id` | bigint NOT NULL | FK programs.id, ON DELETE RESTRICT |
| `period_start`, `period_end` | date NOT NULL | Ngày đầu/cuối cùng một tháng |
| `amount_minor` | bigint NOT NULL | 0..9.007.199.254.740.991, VND nguyên |
| `currency` | char(3) NOT NULL DEFAULT 'VND' | Chỉ VND |
| `status` | varchar(20) NOT NULL DEFAULT 'declared' | declared/voided; thay declared_status sau preflight |
| `tutor_payment_mark` | varchar(20) NOT NULL DEFAULT 'unmarked' | unmarked/marked_paid/marked_unpaid |
| `version` | integer NOT NULL DEFAULT 1 | ≥1 |
| `voided_at` | timestamptz NULL, không default | Server instant void |
| `voided_by` | bigint NULL, không default | FK users.id, ON DELETE RESTRICT |
| `void_reason` | text NULL, không default | Lý do void, 1–2.000 ký tự khi có |
| `payment_marked_at` | timestamptz NULL, không default | Lần thay đổi mark thực tế gần nhất |
| `payment_marked_by` | bigint NULL, không default | FK users.id, ON DELETE RESTRICT |
| `payment_mark_reason` | text NULL, không default | Reason của lần đổi mark gần nhất |
| `last_change_reason` | text NULL, không default | Reason được chia sẻ của mutation thực tế mới nhất; không mất khi audit đủ kỳ purge |
| `disclaimer` | text NULL, giữ cột legacy | Response luôn dùng câu chuẩn server; không cho client sửa |
| `created_at`, `updated_at` | timestamptz NULL, giữ nullability legacy | Writer mới luôn đặt UTC, no-op không đổi |

Không thêm `paid_at`, `customer_confirmed_at`, `payment_status` hoặc một payment transaction table. `last_change_reason` là cột bổ sung ngoài danh sách metadata đang có trong Database Design, với mục đích/transition rõ bên dưới; phải đưa vào migration delta và đồng bộ tài liệu. `voided_by`/`payment_marked_by` là metadata nội bộ, không xuất trong Customer DTO.

### 16.2. Constraint và metadata transition

- Giữ UNIQUE `(program_id,period_start)` cho cả voided; không partial unique chỉ declared.
- CHECK amount trong range trên; currency='VND'; status và mark đúng enum; version≥1.
- CHECK period_start=`date_trunc('month',period_start)::date`; period_end=`(period_start + interval '1 month - 1 day')::date`. Tháng activation/current month phụ thuộc Program/timezone/now nên **service kiểm dưới Program lock**, không CHECK có now hoặc truy vấn bảng khác.
- CHECK amount_minor<>0 OR tutor_payment_mark='unmarked'.
- CHECK status='declared' thì cả voided_at/by/reason đều NULL; status='voided' thì cả ba NOT NULL và reason sau trim có nội dung, tối đa 2.000 ký tự. Không dùng ON DELETE SET NULL làm hỏng metadata bắt buộc của voided.
- CHECK mark='unmarked' thì payment_marked_at/by/reason đều NULL; mark khác unmarked thì marked_at/by NOT NULL; reason có thể NULL, nếu có phải nonblank ≤2.000. Điều kiện **paid → unpaid cần reason** thuộc service + audit vì CHECK row hiện tại không biết state cũ.
- CHECK last_change_reason IS NULL hoặc sau trim có nội dung, dài tối đa 2.000 ký tự. Khi ghi reason vào Fee/audit, chỉ nhận nội dung được phép chia sẻ Customer theo BR-22; client UI phải ghi rõ mục đích này.
- Create: declared/unmarked/version1, toàn bộ void/payment metadata và last_change_reason NULL. Amount edit không thay payment metadata, đặt last_change_reason bằng reason bắt buộc. Mark thay đổi thực tế: cập nhật mark + at/by/reason và last_change_reason, optional reason không gửi → NULL cho lần mark mới. No-op giữ toàn bộ metadata/reason cũ. Void ghi void metadata và last_change_reason, không reset payment metadata. Không transition ngược voided hoặc reset mark.
- Index đọc đích: Progress `(program_id,recorded_at DESC,id DESC)`; Announcement published `(program_id,published_at DESC,id DESC)` và Draft `(program_id,created_at DESC,id DESC) WHERE published_at IS NULL`; Fee unique hiện có dùng đọc theo kỳ. Chỉ tạo index chưa được schema hiện tại bao phủ, ghi DDL thực tế trong migration delta.
- Progress: `smallint value`, CHECK metric_name='progress_percent', value 0..100, version≥1; Announcement version≥1. Giữ FK/identity/timestamp như Database Design. Giới hạn title/body và file count do service kiểm; không tạo Announcement status enum dư thừa.
- Dữ liệu legacy không có metadata xác minh được phải qua phân loại 9.3, không lấy migration actor/updated_at để bịa void/mark history. Constraints chỉ được thắt khi mọi row hợp lệ; nếu không thể đáp ứng thì chặn cutover và ghi row cần xử lý.

### 16.3. Audit action/field và outbox

Mọi row audit có actor_id của owner, resource_type/id đúng, request_id UUID hiện tại, server created_at, reason khi yêu cầu. `before`/`after` là DTO tối thiểu bên dưới. Không lưu toàn bộ private note/body/title, file name, storage key, checksum object hoặc raw request. Các hash nội dung dưới đây là SHA-256 của UTF-8 đã normalize; null khác chuỗi có nội dung. Audit không phải API xem lại toàn văn nội dung cũ.

| Mutation thực tế | Action / resource_type | before/after cho audit | Outbox |
|---|---|---|---|
| Progress create | Không thêm audit bắt buộc; row giữ recorded_by/time | Không | Không |
| Progress update | `progress_record.update` / `progress_record` | program_id, metric_name, value, version; note_present, note_length, note_sha256 nullable để biết note đổi mà không sao chép note | Không |
| Draft create/edit | Không thêm audit nội dung bắt buộc; file diff có audit bên dưới | Không sao chép title/body | Không |
| Announcement publish | `announcement.publish` / `announcement` | program_id, state, version, published_at; title_sha256, body_sha256, tập file_ids đã sort; before=Draft, after=Published | `announcement_published`, key `announcement:{id}:published` |
| Fee create | `fee_record.create` / `fee_record` | before=null; after program_id, period_start/end, amount_minor, currency, status, tutor_payment_mark, version, last_change_reason và void/payment metadata | `fee_record_changed`, key `fee_record:{id}:v1` |
| Fee amount edit | `fee_record.update` / `fee_record` | Snapshot Fee tối thiểu như create trước/sau, reason riêng bắt buộc | `fee_record:{id}:v{new_version}` |
| Fee mark | `fee_record.mark` / `fee_record` | Snapshot trước/sau gồm payment metadata, reason riêng theo policy | Như trên |
| Fee void | `fee_record.void` / `fee_record` | Snapshot trước/sau gồm void metadata và dấu thu được giữ nguyên, reason bắt buộc | Như trên |
| Thêm/tháo file Draft | `file.link` / `file.unlink`, resource_type=`announcement`, resource_id Announcement ID | before/after: program_id, file_id, purpose, announcement version tương ứng; một row cho mỗi link thực sự đổi | Không |

Bổ sung `progress_record.update` vào action catalog M09-F05 khi triển khai; các action publish/Fee/file đã có. Audit append-only; no-op/replay không ghi audit mới. Progress sửa note/value ghi before/after theo projection trên; toàn văn note chỉ nằm ở resource, không sao chép vào audit.

Fee response `last_change_reason` lấy từ cột Fee, được ghi cùng audit/version trong transaction; create hoặc mark không reason trả null. Backfill chỉ lấy reason từ audit Fee phù hợp đã xác minh, không suy từ administrative/file audit hoặc tự bịa khi thiếu nguồn. Audit/read API nội bộ tiếp tục masked theo M09-F05. Fee row và receipt snapshot giữ projection lý do dù audit đã tới kỳ purge; không xóa receipt vì audit chung đã hết 24 tháng.

Outbox dùng writer hiện có cùng transaction và **đúng mẫu key K02.1**, một row/recipient active đã chốt; Announcement trừ Tutor owner, Fee gửi Customer member hiện tại. Announcement payload chỉ `deep_link`; Fee chỉ `deep_link`, `period`. resource_type/id nằm ở cột outbox, không lặp private metadata vào payload. Không copy amount, reason, title, body hoặc file name. Writer hiện đã nhận hai type nhưng D04 chưa tích hợp.

Mẫu deep link: Announcement `/programs/{program}?tab=announcements&announcement_id={id}`; Fee `/programs/{program}?tab=fees&fee_record_id={id}`. Backend dựng URL từ Program/resource ID đã kiểm scope. Frontend dùng query để mở đúng tab/resource và tải dữ liệu qua API kiểm quyền hiện tại; trang đích thuộc phạm vi UI Workspace. Không nhận URL tùy ý từ caller. Worker chỉ deliver sau commit, không cấp quyền đọc resource; người vào Program sau publish không nhận outbox hồi tố.

## 17. File Announcement và việc phải hoàn tất trước triển khai

### 17.1. Khoảng trống hiện tại và flow đích

Source đã có upload/complete nhưng **chưa có luồng Announcement hoàn chỉnh**:

1. `FileUploadRequest` chưa allow `announcement_attachment` → thêm purpose tại validation và kiểm enum/DB CHECK/consumer liên quan; upload private dùng lại pipeline, không tạo uploader khác. Purpose này chỉ dùng bởi Tutor owner khi gắn Announcement.
2. Sau upload, owner complete qua M10/K03.1. Chỉ complete/clean sau kiểm kỹ thuật mới được gắn; `pending`/`rejected` hoặc trạng thái không sẵn sàng đều không hợp lệ. Giới hạn 5.000.000 byte/file, PDF/JPG/PNG/DOC/DOCX, tối đa 5 file/resource; không thêm antivirus hay manual content approval cho Announcement.
3. D04 **POST/PATCH Announcement là đường ghi tập file chính thức**. File phải private, owner khớp Tutor author, purpose đúng; kiểm file chưa gắn resource khác hoặc chỉ đang gắn chính Draft này. Không dùng file đã gắn Program/Announcement khác để lách scope, kể cả cùng uploader. Duplicate IDs trả 422; file IDs sai quyền trả lỗi field chung không lộ metadata.
4. Khóa Program → Announcement → hợp Files cũ/mới tăng ID → link rows. Validate toàn tập trước; insert/delete diff `file_links(resource_type='announcement',resource_id=id,purpose='announcement_attachment')` trong transaction nội dung/version/audit. Giữ unique file/resource có sẵn. PATCH file_ids bỏ qua giữ nguyên; [] unlink tất cả **ở Draft**. Thất bại một link/audit phải rollback cả nội dung/version và tập file.
5. Unlink chỉ tháo quan hệ Draft, không xóa File/object bytes trong request. Các liên kết giữ lại không bị delete/reinsert hoặc tạo audit giả. File đã publish không được link/unlink/delete hoặc thay bytes qua đường khác; object key do server sinh phải bất biến. Publish khóa/kiểm lại files và links rồi ghi Published/audit/receipt/outbox nguyên tử.
6. `/files/{file}/links` hiện chỉ hỗ trợ verification_document. Trong scope D04, giữ chặn generic write cho Announcement bằng 409 và hướng client dùng file_ids của POST/PATCH; không mở một đường link bypass version hoặc Published immutability. Nếu sau này hỗ trợ generic Announcement link/unlink thì phải gọi đúng protocol này và có expected_version; ngoài contract 15 endpoint hiện tại.
7. Bổ sung Announcement policy vào cả file metadata **và download**: owner phải qua resource scope hiện tại đối với file đã gắn Announcement; Draft chỉ owner; Published cho owner/Customer member active; outsider/Admin chung/Customer mất membership bị từ chối. Không để owner bypass chung bỏ qua policy Announcement. File chưa gắn vẫn theo quyền uploader để preview/complete, không cấp Customer quyền qua file ID.
8. Mỗi lần metadata/download kiểm account/membership/resource/purpose hiện tại; file đã gắn Announcement và mọi lượt download phải complete. Uploader vẫn xem được trạng thái metadata của file chưa gắn đang pending để hoàn tất upload. Program terminal vẫn đọc được theo quyền. Metadata file trong response Announcement dùng allow-list 13.3; endpoint metadata File dùng `FileResource` hiện có sau cùng kiểm quyền resource. Download proxy authenticated, không lộ storage key hoặc phát public permanent URL. Những đường mutate/delete/cleanup dùng File phải bảo vệ link Published và không đảo thứ tự khóa.

### 17.2. Checklist triển khai và evidence

- [ ] Đồng bộ Database Design/M09-F05/PRD/WF05 theo mục 13–17; frontend dùng deep-link templates ở 16.3. Áp dụng retention Fee tại 14.2.
- [ ] Preflight schema/dữ liệu/migration status ở môi trường đích; phân loại confirmed/cancelled, fail-fast trường hợp mơ hồ. Thêm migration schema mục 16, không chạy migration ngoài scope chỉ để đủ trạng thái.
- [ ] Triển khai đúng 15 API tab + Overview; authorization/field/null/query/sort/envelope theo 13, receipt/no-op theo 14, lock protocol theo 15. Không thay hành vi Session/Task ngoài phần phối hợp cần thiết.
- [ ] Hoàn thiện upload purpose + atomic file_ids attach/replace/detach + metadata/download Announcement; test direct generic-link/PATCH/file-delete bypass sau publish. Không coi upload/complete dùng chung là acceptance Announcement.
- [ ] Gắn audit/outbox/receipt trong transaction, đồng bộ action progress_record.update; inject lỗi từng link/audit/outbox/receipt và chứng minh rollback không để version, resource, receipt hoặc notification dở dang.
- [ ] Test HTTP: owner/member/outsider/Admin, account inactive/membership bị thu hồi; Active/Paused/terminal; null khác omission, unknown field, integer strict, amount 0, tháng activation/current month qua timezone/month-end/leap-year; pagination 20/50/filter/count/Draft isolation và thứ tự tie-break.
- [ ] Test no-op/version: same-value PATCH, same-mark, reason-only/empty PATCH, stale dù cùng giá trị, state terminal; no-op Fee key mới chỉ có receipt, không audit/outbox/time/version mới. Replay key cũ sau dữ liệu thay đổi trả snapshot cũ; khác key không tự thành replay.
- [ ] Test canonical hash/replay: Unicode, CRLF, whitespace, reordered JSON keys/file IDs, omission/null/default, reuse key đổi path/method/body; current auth trước snapshot; publish receipt 30 ngày và Fee durable receipt độc lập.
- [ ] PostgreSQL race: bảng 15.3, same-key concurrent across resources, PATCH vs publish và file replace/delete vs publish; có chờ khóa/process độc lập, xác minh count/IDs/version/audit/outbox/receipt cuối cùng.
- [ ] Kiểm cleanup: Fee receipt >30 ngày vẫn replay được, expires_at NULL không bị job/lazy-expiry xóa; voided/Program terminal/mất audit cũ không làm receipt đủ điều kiện purge. Chỉ xóa khi Fee và lịch sử đủ điều kiện bằng quy trình purge có kiểm soát; test cleanup không cascade mất receipt sớm. Đây là triển khai/kiểm retention đã có, không cần mở lại TTL.
- [ ] Sinh OpenAPI từ source, kiểm route parity, cập nhật Catalog sau khi route có thật. Lưu API/schema delta, test report, commit/PR, DB preflight và known limitations vào handover/issue; không dùng checklist tài liệu làm bằng chứng đã pass.

Contract này là đầu vào cho triển khai BE-D04 theo checklist trên. Baseline runtime, schema và bằng chứng phụ thuộc được giữ tại mục 9–12; kết quả triển khai và kiểm thử được ghi vào handover khi hoàn tất.
