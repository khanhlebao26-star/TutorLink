# TutorLink — Hợp đồng OpenAPI toàn hệ thống

> **Bản lưu lịch sử của thiết kế đích ngày 18/10/2026.** Số operation, schema, trạng thái quyết định và liên kết YAML/ZIP dưới đây phản ánh thời điểm lập bản nháp; không dùng làm contract API đang triển khai. Để tích hợp với code hiện tại, xem [OpenAPI sinh bởi Scramble](../api/README.md). [API Route Catalog](<API Route Catalog.md>) hiện là chuẩn method/path API đích và giữ đủ 7 route D23 trong phạm vi. Các quyết định mới hơn tại [Business Rules](../Domain%20Documentation/Business%20Rules.md) và PRD hiện hành có ưu tiên hơn toàn bộ trạng thái D01–D24 bên dưới: BR-10 cho phép publish Announcement khi Program Paused, BR-11 chốt evidence chat 10 trước/5 sau, BR-12 chốt đăng ký/xác minh/ngày sinh/phiên sau reset, BR-13 chốt Customer Profile (thay D09 cũ), BR-14 chốt public Tutor/Listing (thay D06 cũ), BR-15 chốt Listing moderation (thay phần D23 liên quan hide/restore), BR-16 chốt M02/D10 về giấy tờ và sửa hồ sơ approved, BR-17 chốt M04/D11 về DTO peer/context và đọc lịch sử khi đình chỉ, BR-18 chốt M05/D12 về Program version/expiry/cancel và Listing hold, BR-19 chốt D21–D22 về policy Paused và optimistic concurrency, M04-F01 cho cả Customer và Tutor mở liên hệ. Tên trường Report trên API đích là `description`, ánh xạ tới `reports.details`; mục 10 bên dưới vẫn ghi wire `details` theo bản nháp lịch sử.

## 0.2.0-draft · 18/10/2026 · Bản nháp để review

Bộ hợp đồng mô tả toàn hệ thống M01–M10: request, response, xác thực, quyền truy cập, lỗi, phân trang, retry và ràng buộc chuyển trạng thái. Đây là hợp đồng đích được đề xuất, chưa phải xác nhận backend đã triển khai hoặc hợp đồng đã được cả nhóm phê duyệt.

# 1\. Mở và tải tài liệu

[OpenAPI đầy đủ — 131 operation (124 cốt lõi \+ 7 có điều kiện)](<../api/openapi.json>)

[OpenAPI core — 124 operation, dùng để render hoặc thử sinh client](<../api/openapi.json>)

[API Route Catalog](<API Route Catalog.md>)

[TutorLink Database Design.drawio](<TutorLink Database Design.drawio>)

Tải YAML về rồi mở bằng công cụ hỗ trợ OpenAPI 3.1. Bản core loại 7 operation chưa chốt điều kiện; các quyết định còn mở vẫn được ghi rõ trong từng operation. Không dùng việc render hoặc sinh client thành công làm dấu phê duyệt nghiệp vụ.

# 2\. Ban đầu có gì và lần này bổ sung gì?

Ban đầu: trên Drive có API Route Catalog mô tả method/path và ghi chú. Trong repository đã có foundation OpenAPI 3.0.3 với 40 operation và conventions. Chúng được giữ nguyên.

Bổ sung: bản OpenAPI 3.1.2 cho đủ 131 operation trong Catalog, 218 reusable schema, request/response mẫu, header, lỗi, giới hạn dữ liệu công khai, mô tả quyền và transaction. Có mapping tới 61 PRD chức năng, nguồn đối chiếu và mã quyết định; hai route assignment được thêm ở lần đồng bộ F03.

Bổ sung khả năng truy vết: route-coverage.csv ghi từng method/path, PRD và bằng chứng trong source; source-register.csv hiện là snapshot 94 tài liệu Product/Domain/PRD/Workflow/Architecture/NFR, gồm URL, thời gian sửa và SHA-256 văn bản paragraph. Không đưa chính guide và log lịch sử vào nguồn hash để tránh tự tham chiếu; hash không đại diện ảnh nhúng. decisions.vi.md và decision-register.json giữ 24 nhóm quyết định hiện có, không tạo nhóm/module mới.

Bảy API có điều kiện: admin report evidence, Listing hide/restore visibility, danh sách/chi tiết/cập nhật Admin User và admin metrics. Chúng chỉ được giữ chỗ, đánh dấu disabled, chưa có success contract được duyệt; bản core loại chúng ra.

Ở lần tạo draft ban đầu không sửa backend/frontend/database/migration/PRD/Enum/Catalog. Đợt đồng bộ F01/F02/F03 ngày 18/10 đã cập nhật tài liệu nguồn Drive được liệt kê ở mục 9; không sửa code hoặc migration. Các schema đích vẫn cần implementation riêng.

# 3\. Session và Conversation được giữ thế nào?

Session giữ quyết định hai bảng: program\_sessions lưu lịch dự kiến; lesson\_sessions lưu kết quả và có unique FK tới lịch. ID trên API là program\_sessions.id. Tạo lịch hoặc hủy không tạo actual row; Tutor xác nhận hoàn tất mới tạo tối đa một lesson\_sessions và đổi trạng thái trong cùng transaction. Gửi lại yêu cầu tương đương trả kết quả đã có, không tạo trùng actual/audit. Không thêm video hoặc suy diễn điểm danh từ giờ dự kiến.

Conversation chưa bị thay đổi trong code hoặc tài liệu gốc. Hợp đồng bám một Conversation cho một cặp Customer–Tutor và trạng thái active/cancelled. Ai được khởi tạo, điều kiện liên hệ, ngữ cảnh và trigger mở lại vẫn được đánh dấu cần chốt. E2EE là đề xuất tương lai, chưa đưa vào phạm vi hiện tại.

# 4\. Cách đọc một operation

operationId nhận diện thao tác. parameters/requestBody mô tả đầu vào; responses mô tả kết quả và lỗi; components tái sử dụng schema. security dùng Sanctum cookie, mutation yêu cầu X-XSRF-TOKEN. x-authorization và x-business-invariants ghi yêu cầu server kiểm tra, không tự thực thi bằng OpenAPI.

x-prd-ids/x-sources trỏ nguồn; x-catalog-status giữ tình trạng từ Catalog. x-implementation-evidence chỉ là bằng chứng trong source và runtime\_verified=false. x-decision-refs là ghi chú liên quan, còn x-blocking-decision-refs cho biết điểm đang chặn operation. x-contract-status phân biệt draft-for-review, blocked-on-policy và conditional-blocked.

Client không tự gửi status/owner/reviewer hoặc timestamp do server quản lý. ID giữ integer int64 theo conventions; frontend cần xử lý bigint chính xác. Tiền VND là integer không âm, exponent 0\. Mỗi mutation phải kiểm session, scope và quyền trước khi trả cả kết quả retry.

# 5\. Kết quả kiểm tra và giới hạn

Đã kiểm tra lại full/core ngày 18/10/2026 bằng openapi-spec-validator 0.9.0: 0 lỗi chuẩn OpenAPI cho mỗi bản. Custom checks đạt 2.729 assertions, 1.770 references, 1.304 ví dụ và 34 negative examples; coverage giữ 131 method/path, 61 PRD, 218 schema. validation-report.json trong ZIP ghi passed cho kiểm tra tài liệu; chưa kiểm thử runtime/backend/database. Core gồm 65 draft-for-review và 59 blocked-on-policy; full giữ thêm 7 conditional-blocked. Không dùng kết quả cấu trúc này để suy mọi policy đã được duyệt.

Đã đối chiếu source: 41 operation khớp Catalog trong routes/api.php; Sanctum CSRF bootstrap dự kiến do framework đăng ký. Catalog ghi Có route cho GET /api/v1/tutors/{slug}/reviews nhưng không thấy route này trong checkout. Nhãn Có route không xác nhận implementation đáp ứng hợp đồng đích.

Chưa kiểm thử runtime: lệnh Artisan không chạy được vì thiếu backend/vendor/autoload.php. Kiểm tra schema không chứng minh authorization, transaction/race, email/queue, storage, hiệu năng hoặc khả năng tích hợp thật. Các nghiệp vụ còn mở dưới đây phải được giải quyết trước phát hành.

# 6\. Nhật ký quyết định chi tiết

confirmed-and-open/scope-confirmed: phần đã xác nhận được giữ, phần mở nêu trong nội dung. compatibility: giữ quy ước cũ. technical-proposal: đề xuất kỹ thuật chưa áp dụng runtime. source-conflict: nguồn cần đồng bộ. blocking/conditional: còn chặn operation liên quan. Không phải mọi operation tham chiếu một Dxx đều bị chặn; xem x-blocking-decision-refs.

## D01 — OpenAPI 3.1.2

Trạng thái: technical-proposal. Dùng 3.1.2 cho JSON Schema 2020-12 và validator tương thích đã kiểm tra; 3.2.1 mới hơn nhưng không bắt buộc cho HTTP REST này. File foundation 3.0.3 giữ nguyên. Không thay đổi backend.

## D02 — ID và enum đích

Trạng thái: compatibility. Giữ ID số nguyên int64 theo conventions/contract hiện có, UUID cho notification/client key. Không tự chuyển sang string từ ví dụ chat trước. Frontend phải xử lý bigint chính xác; nếu muốn đổi string cần quyết định version/migration. Enum lấy đủ 51 tập từ Drive; không đưa legacy literal vào target.

## D03 — Chuẩn wire và lỗi

Trạng thái: technical-proposal. UTC RFC3339; JSON snake\_case; envelope data, message/errors \+ code; request\_id tùy chọn trong error, X-Request-ID đề xuất. Hiện handler chưa có code chuẩn. Rate quota/login throttle/error normalization phải triển khai riêng, chưa coi Có route là verified.

## D04 — Tài khoản, terms và tự đăng nhập

Trạng thái: confirmed-and-open. full\_name đã đồng bộ; M01-F07 đã ghi đúng PATCH /me hiện chỉ nhận full\_name. Quyền sửa date\_of\_birth là đề xuất chờ Dũng xác nhận, chưa được đưa vào wire; không còn mô tả PRD hiện hành như đã cho phép DOB. Tuổi đăng ký Customer 15+/Tutor 18+ giữ nguyên. Đăng ký giữ validation email trùng theo xác nhận 18/10; SP-05 chỉ cam kết chống dò cho login/quên/reset. terms\_version, auto-login, thu hồi session sau reset và gate review khi account suspended vẫn mở.

## D05 — Tiền và decimal

Trạng thái: technical-proposal. VND exponent0, integer không âm, không float/FX. progress.value numeric(12,4) đề xuất chuỗi decimal chính xác; cần xác nhận frontend trước triển khai.

## D06 — Listing/public profile

Trạng thái: blocking. Gate active+verified+approved+taxonomy active giữ BR; không public avatar/email/giấy tờ tới khi duyệt allow-list. F18 đề xuất: restore Account/Profile không đổi ListingStatus; visibility tính lại, Listing active hiện lại khi toàn bộ gate thỏa, inactive vẫn ẩn, archived terminal. Đây là đề xuất chờ Product xác nhận. F14 moderation hold riêng và vẫn conditional; chưa thêm Enum/DB/API. Còn chốt slug redirect, sửa Listing active, restore moderation gate và DELETE 204; runtime còn PATCH status/cancelled, không hợp đồng target. Dũng xác nhận 18/10/2026: email verified là public gate của Profile/Listing. Giới hạn/cách lấy tiếp PublicTutor.listings còn chờ D06, GET public Tutor bị chặn cho đến khi chốt; không tự thêm field/filter/endpoint.

## D07 — Phân trang và giới hạn

Trạng thái: technical-proposal. Collection thông thường page/per\_page mặc định20 tối đa50, lỗi422 khi sai. Message cursor tuple(created\_at,id), version history theo submission\_version; cursor scoped/opaque. Notification cursor created\_at/UUID. Availability tối đa50 giữ code. Poll interval, retention và rate quotas chưa chốt; không hứa SLA từ schema.

## D08 — Lưu idempotency bền vững

D08 vẫn blocking cho các create/command dùng replay store chung (gồm assignment), vì chưa chốt canonicalization và retention áp dụng toàn hệ thống. Riêng M07-F02 được xử lý theo D13 bằng idempotency\_key \+ request\_hash cùng từng task\_submissions row trong PostgreSQL; cùng key/payload replay, khác payload 409, giữ theo vòng đời version, kiểm quyền trước replay. Submission không phụ thuộc replay store D08 dùng chung.

## D09 — Customer Profile

Trạng thái: blocking. Catalog có GET/PUT nhưng Customer Profile chưa có PRD đầy đủ. Đề xuất đặt mục Customer Profile riêng trong M01-F07, không gán sang M02 Tutor Profile và không mở module mới; các trường đã có trong DB là city/district/bio/avatar\_file\_id. GET chưa có row trả null; PUT upsert, omission giữ nguyên và explicit null xóa field nullable là đề xuất chưa duyệt. Product cần chốt owner, required/validation, eligibility, avatar lifecycle và PUT semantics trước khi mở contract.

## D10 — Hồ sơ Tutor/checklist/giấy tờ

Trạng thái: blocking. Request specialization\_id giữ current; response specialization\_ids biểu diễn bảng join. M02-F02/F03/F05 chưa có checklist được duyệt. Đề xuất checklist theo document\_type: required\_for\_submit, số lượng, format/size, expiry/replacement và review state; mỗi VerificationDocument quyết định riêng, không tự approved theo Profile. API Catalog đánh dấu approve/reject document “Cần bổ sung”; WF01 mô tả placeholder, chưa phải success contract. Product cần chốt specialization count, avatar/document requirement, submit gate (active/email), Profile approval gate, expiry/replacement, permission/reason/notification và sửa Profile approved; giữ D10 blocking.

## D11 — Conversation giữ thiết kế hiện tại

Trạng thái: blocking. Một pair/Conversation active/cancelled, direct\_key nội bộ, không E2EE. Chưa chốt ai khởi tạo (CatalogCustomer/Tutor, PRDCustomer), first-contact eligibility, Listing active, context overwrite, trigger reopen và history sau suspend. peer\_user\_id là đề xuất wire để không mơ hồ user/profile ID. Không coi đề xuất click Liên hệ tự reopen ở chat trước là quyết định đã duyệt.

## D12 — Version và hủy Program

> **Đã được thay thế bởi BR-18 (20/10/2026).** Các trạng thái “blocking” và nội dung đề xuất dưới đây là ghi chép lịch sử, không còn là quyết định đang chờ. Quy tắc hiện hành xem tại [Business Rules](../Domain%20Documentation/Business%20Rules.md) và các PRD M05.

Trạng thái: blocking. customer\_id là wire canonical theo DB; PRD target\_customer\_id là mapping. expected\_program\_version đề xuất cho update/send/action/accept. Expiry boundary đề xuất server\_now\<expires\_at và đúng72h UTC; phải review. Hủy Program có Session/Task mở, lý do Invitation reject/cancel và state-gate resume cần chốt. Snapshot lịch sử không FK tới current version. ADR-09 còn cần owner kỹ thuật xác nhận giao thức khóa/isolation với đình chỉ riêng tài khoản Tutor (users.status) và test PostgreSQL; chưa kết luận race-safe từ khóa Tutor Profile.

## D13 — Submission retry/version

> **D13 được chốt tại BR-20 (20/10/2026).** Nội dung dưới đây là ghi chép trạng thái lịch sử. Quy tắc canonicalization và giới hạn hiện hành xem tại [Business Rules](../Domain%20Documentation/Business%20Rules.md) và M07-F02; runtime vẫn chưa triển khai.

Idempotency-Key UUID, lưu key/hash với UNIQUE(task\_id,customer\_id,idempotency\_key), giữ theo lịch sử version; cùng key/payload trả version gốc, khác payload trả 409; key mới cần expected\_submission\_version hiện hành. D13 dùng canonical JSON RFC 8785 theo BR-20, không phụ thuộc replay store chung D08.

## D14 — File message và atomic attach

Trạng thái: confirmed-and-open. MessageType text/file/system đã đồng bộ trong Enum, M04-F03/F06 và contract; system do server tạo. WF03 mô tả tạo Message gắn file nguyên tử. Còn cần chốt giới hạn attachment và ranh giới generic /files/links để không sửa Message bất biến hoặc bypass quyền; không còn yêu cầu bổ sung MessageType.file vào PRD.

## D15 — File policy/storage

Trạng thái: blocking. Giữ upload multipart hiện có; không presigned flow mới. Avatar2MiB JPG/PNG, file purpose hiện có10MiB allow-list từ request; purpose mới/attachment count/retention/quarantine còn mở. File clean chỉ kiểm tra hiện có. Target download binary stream cần so current handler. Storage/DB không transaction chung, cần compensation và kiểm reference khi xóa.

## D16 — Review/Report allow-list

Trạng thái: confirmed-and-open. F10 ghi mapping PRD → wire → Database tại mục 10\. M09-F03 và Index đã đồng bộ ReportResourceType (tutor\_profile, listing, message, program, review) và ReportReasonCode (spam, harassment, inappropriate\_content, fraud\_suspected, misleading\_information, privacy\_violation, other) theo Enum/Database. message là đối tượng report; conversation không phải ReportResourceType. Quyền xem ngữ cảnh Conversation làm evidence, duplicate report, quyền gửi, giới hạn description, Review requiredness/limits và cách hiển thị identity vẫn mở; Public Review không trả reporter/evidence. Theo WF06, Report reason=other yêu cầu description; wire details phải có ký tự không phải whitespace, schema đã ghi điều kiện. Reason khác giữ details optional/nullable.

## D17 — Admin assignment và evidence

Trạng thái: confirmed-and-open. Đã chọn assigned\_admin\_id/assigned\_by/assigned\_at/assignment\_version trên Tutor Profile và Report, hai quyền assign riêng và audit\_logs append-only. Quyền assignment chỉ trả metadata queue tối thiểu; xem detail/decide/download/evidence cần quyền riêng và assignment hiện tại. Hai PUT assignment nhận assigned\_admin\_id nhưng chưa có API đã chốt để giao diện liệt kê Admin đủ điều kiện; GET /api/v1/admin/users vẫn conditional. Máy chủ có thể kiểm tra ID được gửi, còn luồng chọn target của UI phải chờ chốt permission, scope và DTO của directory. Còn mở: hồ sơ gửi lại sau changes\_requested giữ người phụ trách hay đưa lại queue; evidence và quyền/retention sau khi Report đóng; không thêm gate cho M02-F08. Các route evidence vẫn conditional cho tới khi chốt scope.

## D18 — Admin action vocabulary

Trạng thái: confirmed-and-open. Enum đích AdminTutorProfileAction gồm approve, request-changes, reject, profile-suspend, profile-restore, account-suspend, account-restore; reject khác request-changes, thao tác hồ sơ tách khỏi tài khoản. Schema allowed\_actions dùng chính enum này, nhưng server vẫn chỉ trả tập hợp được phép theo state, permission và assignment/scope hiện hành; enum không tự cấp quyền. Schema/Enum/WF01/API routes được đồng bộ.

## D19 — Notification/outbox

Trạng thái: đã chọn transactional outbox trong PostgreSQL cho thiết kế nhóm 1; chưa triển khai runtime. Outbox theo recipient/type/resource/event\_key được ghi cùng transaction mutation \+ audit bắt buộc. Worker Docker poll row đã commit; tạo notification và đánh dấu processed trong cùng transaction, chống trùng bằng UNIQUE(user\_id,type,event\_key). Dùng queue/database hiện có; không phụ thuộc Redis. Ma trận lấy toàn bộ NotificationType Enum; payload allow-list/deep link nội bộ, không chứa private content. Ngưỡng retry 10 lần/60 phút là đề xuất cần nhóm chốt. Còn mở: retention, cảnh báo hàng failed và thao tác replay vận hành.

## D20 — Audit DTO/retention

Trạng thái: blocking. Append-only, audit mutation cùng transaction; truy cập evidence có audit. Before/after API dùng allow-list nhỏ (status/visibility/mark/amount/version), không pass nguyên JSONB. Chốt action catalog/scope/retention và cấp quyền; không dùng ReportResourceType cho toàn audit.

## D21 — Session hai bảng

> **Phần policy Paused và stale update đã được thay thế bởi BR-19 (20/10/2026).** Nội dung D21 bên dưới là ghi chép lịch sử; xem PRD M06 và API Route Catalog hiện hành.

Trạng thái: confirmed-and-open. Giữ hai bảng program\_sessions \+ lesson\_sessions, API id=planned ID, Tutor complete nguyên tử tạo actual 0..1; cancel không actual. Dũng xác nhận ngày 18/10/2026: khi Program Paused vẫn cho Tutor hoàn tất/hủy lịch đã có, chặn tạo mới; edit lịch khi Paused còn mở. Program terminal cấm transition mới, chỉ cho replay chỉ đọc đã hợp lệ; actual time optional, không thêm attendance/video.

## D22 — Sửa nội dung và stale data

> **Đã được thay thế bởi BR-19 (20/10/2026).** Chính sách sửa Task/Fee/Announcement và `expected_version` hiện hành xem tại Business Rules, PRD M07–M08 và API Route Catalog. Metric Progress vẫn là câu hỏi mở riêng.

Trạng thái: blocking cho PATCH Task ở submitted/paused, start/cancel Task paused, Announcement sau publish/paused, metric validation và optimistic concurrency cho edit chưa có giao thức chống stale, bao gồm PATCH Fee Record. Row lock/status gate không tự ngăn lost update cùng trạng thái; chưa thêm version/ETag vào wire hoặc DB. Riêng Complete Program đã chốt: không còn Session scheduled, mọi Task completed/cancelled tường minh; lỗi program\_completion\_blocked trả blockers.sessions/tasks, không tự chuyển Task.

## D23 — Bảy endpoint chưa được duyệt

Trạng thái: conditional. Catalog có 7 route có điều kiện: 6 Admin Listing/support/metrics và 1 Report evidence. Giữ reserved paths; chưa có success DTO/request/permission được duyệt, không đưa vào executable contract. F14 proposal: ListingStatus giữ owner lifecycle; moderation state/record, Admin permission/scope và audit tách riêng; không thêm \`hidden\` enum/DB/route trước Product approval. F18: restore không đổi ListingStatus; active Listing tự hiện lại khi gate thỏa là đề xuất pending. Availability, Conversation first-contact và Announcement khi Program Paused vẫn cần chốt theo M03-F09/M04-F01/M08-F02.

## D24 — Nguồn, E2EE và performance

Trạng thái: scope-confirmed. User yêu cầu toàn hệ thống; E2EE chỉ đề xuất trong doc riêng, không thêm thiết bị/khóa API. Mục tiêu tải/p95 trong NFR không phải SLA đã đo. Bản này là target draft bao phủ Catalog, không triển khai backend/migration/frontend hoặc ghi đè nguồn Drive.

# 7\. Sơ đồ và cách dùng bộ hợp đồng

Sơ đồ có 4 trang: cấu trúc hợp đồng; luồng M01–M10; mutation/retry/transaction; Session, Task, Fee và các cổng trạng thái. File draw.io có thể mở trong diagrams.net; ZIP còn có PNG/SVG và Mermaid.

Trước khi phát hành: duyệt điểm còn chặn → đồng bộ PRD/Enum/DB theo quyết định → triển khai mapping/migration và cập nhật client → kiểm thử integration, ownership và race trên môi trường riêng → xác minh từng operation → chọn version phát hành. Không đánh dấu F02 nghiệm thu hoàn toàn chỉ từ kiểm tra YAML.

# 8\. Rà lại hợp đồng — chỉ làm trên Drive

Nhãn 0.2.0 do trợ lý tự đặt để phân biệt artifact với foundation, chưa phải phiên bản phát hành nhóm đã duyệt. YAML là file hợp đồng cho công cụ và frontend/backend; Google Doc này là phần giải thích. Hai bản full/core và ZIP là cách đóng gói của trợ lý. Giữ nguyên các link hiện tại.

Phạm vi lưu file: đã kiểm tra checksum và đưa đúng 17 file do trợ lý tạo, chưa bị người dùng sửa, ra ngoài project. docs/api không còn thay đổi do trợ lý thêm. Các file và thay đổi có sẵn của người dùng được giữ nguyên. Việc rà và cập nhật đầu ra từ đây chỉ thực hiện trên Drive; thư mục tạm chỉ phục vụ kiểm tra.

Session: bỏ cách nói “retry cùng key” vì complete/cancel không yêu cầu Idempotency-Key. Replay dùng Session ID, trạng thái đích và payload/reason gốc tương đương; không tạo trùng lesson\_sessions, audit hoặc event. Sau kiểm identity/scope/quyền, có thể trả lại kết quả đã ghi200 ngay cả khi Program đã terminal; mọi chuyển trạng thái mới trên Program terminal vẫn409. Giữ hai bảng và Tutor hoàn tất thủ công.

Quyền đình chỉ: mô tả bổ sung conditional\_permissions đúng M02-F08. Ngoài admin.tutor\_profiles.suspend, cần admin.accounts.suspend khi account đích đang active; thiếu quyền trả403, không ghi một phần profile/account/audit.

Điểm còn chặn: Create Listing/Review/Report nay gắn D08 vì yêu cầu replay store. Update Session gắn D21; Delete File gắn D15; List Conversations gắn D11 về policy đọc sau suspend; reject/cancel Invitation gắn D12 về reason policy. Đây là sửa nhãn truy vết, không tự chọn các chính sách còn mở.

Bảy API có điều kiện: tất cả liên kết D23 và conditional-blocked; bản core vẫn loại cả bảy. Bản OpenAPI full hiện có 131 operation; bản core có 124 operation, không chứa 7 route conditional.

Dữ liệu bắt buộc: minLength1 cho title/headline/body/metric\_name tương ứng và password đăng nhập; PATCH gửi các field đó cũng không được chuỗi rỗng. Không đặt thêm giới hạn độ dài mới; khoảng trắng/chuẩn hóa cần server kiểm tra.

Hướng dẫn độc lập: README trong ZIP chạy validator từ ngay thư mục giải nén, không yêu cầu docs/api/target trong project. Sửa nhật ký Session để nói đúng DB đã có hai bảng, lần này làm rõ ID/DTO và thời điểm tạo actual; không tuyên bố có hai thiết kế DB khác nhau.

Kiểm tra lại: full/core parse thành công; 131 operation (124 core \+ 7 conditional), 218 schema, 88 nguồn/61 PRD; 1.769 references, 1.304 examples, 29 negative examples, 2.719 assertions và không có custom-check failure. Kiểm tra chuẩn OpenAPI chưa chạy vì thiếu openapi-spec-validator; ZIP có 17 entry, checksum/CRC đạt. Chưa kiểm thử runtime hoặc database; draft vẫn cần duyệt các điểm nghiệp vụ còn mở.

Chi tiết trước/sau nằm trong review-changes.vi.md của ZIP và Google Doc Lịch sử làm việc. Đợt hiện tại đã đồng bộ tài liệu nguồn Drive cho F01/F02/F03; không đổi chính sách Conversation/E2EE.

9\. Đồng bộ F01/F02 và chọn phương án F03 — 18/10/2026

F01: giữ program\_sessions làm lịch và lesson\_sessions làm kết quả 0..1; API dùng program\_sessions.id. Tutor xác nhận hoàn tất thủ công; hủy không tạo actual. Đồng bộ M06-F02/F03, Domain Model và Business Rules. Không thêm video/attendance.

F03: chọn assignment hiện tại trên tutor\_profiles/reports gồm assigned\_admin\_id, assigned\_by, assigned\_at, assignment\_version; audit\_logs lưu before/after trong cùng transaction. Quyền assign tách riêng; hàng đợi lọc mine/unassigned/all trước phân trang. Quyền assign chỉ thấy metadata tối thiểu; xem chi tiết/evidence, quyết định và tải file cần quyền riêng. Hai PUT assignment nhận assigned\_admin\_id nhưng chưa có API đã chốt để giao diện liệt kê Admin đủ điều kiện; GET /api/v1/admin/users vẫn conditional. Máy chủ kiểm tra ID được gửi; UI cần chờ chốt permission, scope và DTO của directory. Thu hồi bằng assigned\_admin\_id=null; version cũ trả 409\. Còn mở: hồ sơ gửi lại có giữ người phụ trách hay không; evidence/quyền truy cập sau khi Report đóng; không thêm gate assignment cho M02-F08 suspend/restore. Sơ đồ Database Design draw.io đã cập nhật 4 trường, FK users.id và mã quan hệ R56–R59; dấu \+ thể hiện đề xuất chưa migration: [TutorLink Database Design.drawio](<TutorLink Database Design.drawio>) .

F02: API Route Catalog thêm PUT /api/v1/admin/tutor-profiles/{tutorProfile}/assignment và PUT /api/v1/admin/reports/{report}/assignment. OpenAPI draft đồng bộ hai route, request/response, permission, audit, assignment\_version và lỗi 409; hai route vẫn blocked-on-policy D08 cho tới khi có quyết định về durable replay store.

Artifact Drive, cùng IDs: Full YAML <../api/openapi.json> ; Core YAML <../api/openapi.json> ; ZIP 17 file <..> ; API Route Catalog [API Route Catalog](<API Route Catalog.md>) .

Kiểm tra offline đã đạt; chưa triển khai backend, migration hoặc runtime. Danh sách nguồn F03 và thay đổi before/after đã được ghi trong Lịch sử làm việc.

# 10\. Nhóm 2 — F06/F10: điều kiện Complete và field mapping — 18/10/2026

Phạm vi: đã đồng bộ hai phát hiện còn lại của nhóm hợp đồng API trên Drive. Các tên wire đang có trong OpenAPI được giữ làm chuẩn cho bản draft; bảng dưới ghi bí danh PRD và cột database, không đổi schema DB.  
F06 — M05-F06, State Lifecycles, API Route Catalog và operation completeProgram thống nhất: chỉ Tutor owner của Program active/paused có thể complete; không còn Session scheduled và mọi Task phải completed hoặc cancelled tường minh. Task todo/in\_progress cần đi qua transition hợp lệ hoặc bị hủy; submitted cần Tutor feedback. Complete không tự đổi Task.  
OpenAPI 409 code=program\_completion\_blocked có blockers.sessions và blockers.tasks; từng mục trả ID, status và required\_action. Blocker list chỉ trả sau kiểm quyền; không đổi Program hoặc phát side effect một phần. Xung đột version/idempotency khác vẫn dùng 409 chung.  
F10 — ánh xạ PRD → OpenAPI wire → database (type và tính bắt buộc theo hợp đồng hiện tại):  
M05-F01 target\_customer\_id → customer\_id (POST bắt buộc, response bắt buộc, int64) → programs.customer\_id (bigint NOT NULL, FK users.id; Database Design đang ghi cột này là đề xuất thêm).  
M08-F04 recipient\_id → Notification.user\_id (response bắt buộc, int64, server-derived; client không gửi) → notifications.user\_id (bigint NOT NULL, FK users.id).  
M09-F03 reason\_code → Report.reason (POST bắt buộc, enum) → reports.reason (varchar(100) NOT NULL, ReportReasonCode); description → details (request optional/nullable, response bắt buộc nhưng nullable) → reports.details (text NULL).  
M07-F04 metric → metric\_name (POST bắt buộc; PATCH optional; response bắt buộc; string 1–100 ký tự) → progress\_records.metric\_name (varchar(100) NOT NULL).  
M02-F01 experience → experience\_years (request optional, integer ≥0 và Profile PUT tối đa 80; response bắt buộc) → tutor\_profiles.experience\_years (smallint NOT NULL DEFAULT 0).  
M09-F01 body → comment (request optional/nullable; response bắt buộc nhưng nullable) → reviews.comment (text NULL). Tính bắt buộc và giới hạn Review body/comment vẫn là câu hỏi mở; mapping này không tự chốt policy.  
Đã đồng bộ allow-list Report resource/reason trong M09-F03, M09 Index, Enum và schema theo D16; không còn conflict tên loại đối tượng/lý do. Còn mở là giới hạn/requiredness Review comment và các policy Report được nêu ở D16/D17. Cột programs.customer\_id vẫn là thiết kế đề xuất, chưa phải migration; không xác nhận runtime/backend.  
Nguồn: M05-F06 [M05-F06 PRD — Quản lý lifecycle Program](<../Modules Documentation/M05 — Program & Invitation/M05-F06 PRD — Quản lý lifecycle Program.md>) ; State Lifecycles [State Lifecycles](<../Domain Documentation/State Lifecycles.md>) ; API Route Catalog [API Route Catalog](<API Route Catalog.md>) ; Database Design [Database Design — TutorLink](<Database Design — TutorLink.md>) ; contract YAML/ZIP links are at section 9\. F10 PRD sources: M05-F01 [M05-F01 PRD — Tạo và chỉnh sửa Program Draft](<../Modules Documentation/M05 — Program & Invitation/M05-F01 PRD — Tạo và chỉnh sửa Program Draft.md>) ; M08-F04 [M08-F04 PRD — Notification Inbox và trạng thái đã đọc](<../Modules Documentation/M08 — Fee, Announcement & Notification/M08-F04 PRD — Notification Inbox và trạng thái đã đọc.md>) ; M09-F03 [M09-F03 PRD — Gửi Report](<../Modules Documentation/M09 — Review, Report & Audit/M09-F03 PRD — Gửi Report.md>) ; M07-F04 [M07-F04 PRD — Ghi và xem Progress](<../Modules Documentation/M07 — Task & Progress/M07-F04 PRD — Ghi và xem Progress.md>) ; M02-F01 [M02-F01 — Tạo và cập nhật hồ sơ Tutor](<../Modules Documentation/M02 — Tutor Profile & Approval/M02-F01 — Tạo và cập nhật hồ sơ Tutor.md>) ; M09-F01 [M09-F01 PRD — Tạo Review sau Program](<../Modules Documentation/M09 — Review, Report & Audit/M09-F01 PRD — Tạo Review sau Program.md>)  
Đồng bộ một lượt 18/10/2026 — Phạm vi và quản trị: giữ 131 operation/218 schema, không thêm chức năng, endpoint, bảng/cột. Quyết định đã chốt được thể hiện thống nhất; phần chưa duyệt giữ blocking/pending có nêu rõ nội dung còn thiếu. Bộ core chỉ loại 7 conditional, không có nghĩa mọi operation core được phát hành. Đổi rule phải sửa cùng lượt PRD/BR liên quan, Catalog/OpenAPI, bản YAML trong ZIP, sơ đồ bị ảnh hưởng và dòng quyết định hiện có; sau đó kiểm tra/readback trước đóng mục. Dùng Dxx và Lịch sử làm việc hiện có để theo dõi owner/ngày chốt/bằng chứng, không mở thêm tài liệu quản trị.  
.
