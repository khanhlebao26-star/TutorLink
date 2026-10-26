# BE-D02 — Contract giai đoạn A cho Program và Invitation

**Ngày đối chiếu:** 27/10/2026
**Phạm vi:** Issue [#69](https://github.com/khanhlebao26-star/TutorLink/issues/69), M05 Program. Đây là bản contract cho các quy tắc đã có căn cứ trước code. `GET|POST /api/v1/programs`, `GET|PATCH /api/v1/programs/{program}` và `POST /api/v1/invitations/{invitation}/accept` hiện đã được đăng ký; các route M05 khác vẫn **Dự kiến**. Tài liệu lộ trình BE-D02 là nguồn tham khảo để chia việc, không phải bằng chứng triển khai hay phê duyệt sản phẩm.

## 1. Ranh giới và điều kiện đầu vào

- BE-D01 đã thêm `programs.customer_id`, chuyển vocabulary status đích và ràng buộc PostgreSQL: một Invitation `pending`/Program, một Customer member/Program. [Báo cáo BE-D01](BE-D01-issue-36-handover.md) có bằng chứng trên database test; issue BE-D01 vẫn open và không chứng minh môi trường chạy hiện tại đã được kiểm tra lại.
- BE-D02 sở hữu Program create/list/detail/PATCH, Policy/Request/Resource, các transition Program, endpoint gửi/đọc Invitation và cả action lẫn endpoint HTTP Accept. Chính action Accept chịu trách nhiệm toàn bộ điều kiện nghiệp vụ và thay đổi nguyên tử; HTTP layer không lặp lại logic.
- Method/path theo [API Route Catalog](<../Architecture & Technical Design/API Route Catalog.md>): `GET|POST /api/v1/programs`, `GET|PATCH /api/v1/programs/{program}`, `POST /api/v1/programs/{program}/invitations`, `GET /api/v1/invitations`, `GET /api/v1/invitations/{invitation}`, các POST `/api/v1/programs/{program}/{pause|resume|complete|cancel}` và `POST /api/v1/invitations/{invitation}/accept`.
- Các API reject/cancel Invitation, expiry worker, Workspace/Session/Task và UI không được suy ra là đã nằm trong phần triển khai của Issue #69 chỉ vì Catalog liệt kê chúng. Quy tắc tương tác của chúng dưới đây vẫn là điều kiện của contract M05.

## 2. Field map và dữ liệu trả về

| API/domain | Lưu trữ | Nghĩa và quy tắc |
| --- | --- | --- |
| `customer_id` (`target_customer_id` trong PRD) | `programs.customer_id` | Customer đích duy nhất, bắt buộc khi tạo, cố định trong vòng đời Program. `program_invitations.invitee_user_id` và Customer member phải trùng ID này. |
| Tutor owner từ phiên đăng nhập | `programs.owner_tutor_profile_id` → `tutor_profiles.user_id` | Server xác định; client không gửi owner ID. |
| `source_listing_id` | `programs.source_listing_id` | Tùy chọn; nếu có phải thuộc Tutor owner và không bị moderation hold khi tạo Draft hoặc gửi Invitation mới. Thay đổi Listing về sau không sửa snapshot đã gửi. |
| `version`, `expected_version` | `programs.version`, request guard | `version` khởi tạo 1, PATCH Draft thành công tăng đúng 1. PATCH/send nhận `expected_version`; stale trả 409, không ghi một phần. |
| `status` | `programs.status` | Chỉ `draft`, `active`, `paused`, `completed`, `cancelled`; không trả literal legacy `pending` cho Program. |
| Offer | Cột `programs.title`, `goal`, `level`, `delivery_mode`, `agreed_fee_minor`, `currency`, `fee_unit`, `location_text`, `meeting_url`, `schedule_note`, `timezone`, `planned_session_count` | Request/response dùng snake_case và chỉ các field được phép. `title` tối đa 180, `level` tối đa 80; `delivery_mode` = online/offline/hybrid; `fee_unit` = session/hour/course; tiền là integer không âm, `currency` chỉ VND; `timezone` là IANA, mặc định `Asia/Ho_Chi_Minh`. |
| Invitation snapshot/version | `program_invitations.offer_snapshot`, `program_version`; `programs.offer_snapshot`, `snapshot_locked_at` là bản/cột hỗ trợ | Invitation snapshot và `program_version` là bản lịch sử có thẩm quyền cho Customer; không đọc offer sống từ Program để dựng lại Invitation. Snapshot chứa Customer đích, offer và source context tại thời điểm gửi. |
| Thời gian Invitation | `program_invitations.created_at` (= `sent_at` tại lúc tạo), `expires_at` | Server đặt cùng một thời điểm gửi; `expires_at = sent_at + 72 giờ`. Mọi instant lưu UTC, API trả RFC 3339 UTC. |
| `invitation.status` | `program_invitations.status` | Chỉ `pending`, `accepted`, `rejected`, `cancelled`, `expired`; literal legacy `active` của Invitation đã được migration ánh xạ sang `accepted`. |

`POST /programs` yêu cầu `customer_id`, `title`, `delivery_mode`, `agreed_fee_minor`, `fee_unit`; field còn lại của offer được nhận nếu hợp lệ theo schema/PRD. `currency` vắng mặt dùng VND, nếu gửi chỉ nhận VND. `PATCH` chỉ nhận field offer có thể sửa và `expected_version`; không cho đổi `customer_id`, owner, `status`, `version`, snapshot hoặc timestamps qua PATCH. Response Program chứa ID, owner/Customer/source IDs, status, version, offer và `allowed_actions` tính theo actor/trạng thái hiện tại; không lộ dữ liệu riêng của Customer/Tutor ngoài contract. Response Invitation chứa ID, status, `program_version`, snapshot, `sent_at`, `expires_at`, thời điểm quyết định nếu có, cùng ID Program/Customer tối thiểu. Khi row còn `pending` nhưng đã tới `expires_at`, GET trả `status=expired` theo thời gian server mà không ghi DB; lệnh ghi tiếp theo hoặc worker sẽ chốt transition. Không trả Draft qua DTO Invitation.

## 3. Quyền đọc và ghi

| Actor | Create Draft | List/detail Program Draft | PATCH Draft; send | List/detail Program sau Accept | Đọc Invitation snapshot trước Accept | Program lifecycle |
| --- | --- | --- | --- | --- | --- | --- |
| Tutor owner active, đủ eligibility | Có | Chỉ Program mình sở hữu | Có nếu Draft không bị khóa và version đúng | Có | Invitation mình gửi | Pause/resume/complete/cancel theo transition |
| Tutor khác | Chỉ Draft do chính mình tạo | Không | Không | Không | Không | Không |
| Customer đích chưa là member | Không | Không | Không | Không | Có, qua Invitation inbox/detail | Không |
| Customer member sau Accept | Không | Draft không tồn tại trong quan hệ đã accept | Không | Program của mình, kể cả terminal để xem lịch sử | Có, Invitation gửi cho mình | Không |
| Customer khác/Admin/outsider | Không theo M05 | Không | Không | Không | Không | Không |

Create/send cần Tutor User active, email verified, Profile approved/không suspended, giấy tờ bắt buộc hợp lệ; Customer target phải là User Customer active. Quyền Program detail/list dựa vào owner hoặc Customer member, không dựa vào việc đã nhận Invitation. Account bị đình chỉ không đọc/ghi qua phiên cũ. Đường `GET /invitations` lọc theo Customer đích hoặc Tutor owner; detail kiểm quyền trên từng Invitation. `allowed_actions` là gợi ý UI, mutation luôn kiểm tra lại server-side.

## 4. Version, snapshot và khóa Draft

| Tình huống | Kết quả |
| --- | --- |
| Tạo Draft | `status=draft`, `version=1`; chưa có member, snapshot hay workspace. |
| PATCH Draft với `expected_version` hiện tại, không có Invitation pending | Cập nhật offer nguyên tử, tăng version đúng 1, trả Program version mới. |
| PATCH/send với version cũ | 409; không đổi Program/Invitation, không ghi audit/outbox thành công. |
| Gửi Invitation | Trong một giao dịch kiểm owner/eligibility/Draft/version/Listing hold và uniqueness; lưu Invitation `pending` với snapshot + `program_version` bằng version hiện tại, hạn 72 giờ; khóa sửa Draft, ghi audit/outbox trong cùng giao dịch. Worker chỉ xử lý outbox sau commit. Không tăng offer version chỉ vì gửi. |
| Invitation còn `pending` và chưa đến `expires_at` | Không PATCH offer, không gửi pending thứ hai; partial UNIQUE theo Program bảo vệ race. |
| Invitation lưu `pending` nhưng server time `>= expires_at` | Khi Tutor owner PATCH Draft hoặc gửi Invitation mới, server khóa Program/Invitation, chuyển Invitation cũ sang `expired` và ghi audit/outbox trong cùng giao dịch, rồi tiếp tục lệnh với `expected_version` hiện tại. Nếu lệnh không commit thì cả bước expire trong giao dịch đó rollback; accept vẫn bị chặn tại biên thời gian dù worker chưa chạy. |
| Invitation thành `rejected`, `cancelled` hoặc `expired` | Snapshot cũ giữ nguyên; Program còn Draft, có thể PATCH theo version hiện tại và gửi Invitation mới. |
| Invitation `accepted` | Program chuyển Active, Draft không còn được PATCH. |

Ví dụ: tạo Draft v1, PATCH thành v2, gửi Invitation ghi `program_version=2`. PATCH dùng `expected_version=1` trả 409; PATCH dùng 2 khi Invitation pending cũng bị từ chối do khóa. Mọi retry gửi phải chống tạo trùng Invitation và notification theo Idempotency-Key/BR-23 cùng `UNIQUE(invited_by,idempotency_key)` và pending UNIQUE; cùng key nhưng payload khác trả 409 sau kiểm quyền. Snapshot cũ không được sửa để “gửi lại”.

## 5. Lifecycle Program

| Từ | Action/actor | Sang | Điều kiện |
| --- | --- | --- | --- |
| `draft` | Tutor owner cancel | `cancelled` | Nếu có Invitation pending, chuyển nó sang `cancelled` cùng giao dịch. |
| `draft` | Customer đích accept Invitation | `active` | Qua Accept action tại mục 6; không có đường kích hoạt trực tiếp. |
| `active` | Tutor owner pause | `paused` | Không sửa snapshot/Invitation. |
| `paused` | Tutor owner resume | `active` | Không mở lại terminal. |
| `active`/`paused` | Tutor owner complete | `completed` | Không còn Session `scheduled`; mọi Task là `completed` hoặc `cancelled`. |
| `active`/`paused` | Tutor owner cancel | `cancelled` | Cùng blocker Session/Task như complete. |

`completed` và `cancelled` là terminal, chỉ đọc lịch sử. Complete/cancel không tự đổi Session/Task, không thu hồi Customer membership. Nếu còn blocker, trả `409 program_completion_blocked` hoặc `409 program_cancellation_blocked`, với `errors.blockers.sessions` và `errors.blockers.tasks`; mỗi mục gồm `id`, `status`, `required_action`. Không tiết lộ blocker trước khi kiểm quyền.

## 6. Action và endpoint Accept do BE-D02 triển khai

**Input:** authenticated Customer actor, Invitation ID, Idempotency-Key UUID. HTTP endpoint đích là `POST /api/v1/invitations/{invitation}/accept`; action không tin Customer/Program ID do client khai báo. **Output thành công:** Invitation `accepted`, Program `active`, Customer và Tutor membership active, `accepted_at`/`activated_at` UTC và Program/Invitation DTO theo allow-list; retry cùng key/payload trả kết quả đã commit mà không lặp mutation/audit/outbox.

Trong một giao dịch PostgreSQL, action phải kiểm: actor là đúng `programs.customer_id = invitation.invitee_user_id`; User Customer active; Tutor User active/verified, Profile approved/không suspended và giấy tờ còn hiệu lực; Program `draft`; Invitation `pending`; `invitation.program_version = program.version`; snapshot thuộc đúng Program/Customer; server time **nhỏ hơn** `expires_at`; source Listing không có moderation hold. Listing đã archive/inactive sau khi snapshot được gửi không tự chặn Accept nếu không có hold. Khi đủ điều kiện, đổi Invitation → `accepted`, Program → `active`, tạo đúng một Customer member và Tutor member, ghi audit + outbox trong cùng transaction. Rollback/409 không được để lại membership hoặc notification.

Accept dùng PostgreSQL `READ COMMITTED` và khóa row theo thứ tự: các User liên quan theo ID tăng dần → Tutor Profile → source Listing nếu có → Program → Invitation. Mọi writer cạnh tranh phải giữ cùng thứ tự trên các row mà nó đụng tới: đình chỉ User/Profile, đặt/gỡ Listing hold, send/expire/cancel Invitation và cancel Draft. Sau khi lấy khóa, đọc lại quyền, eligibility, trạng thái, version, hold và expiry rồi mới ghi; moderation phải khóa Listing row trước khi thay đổi hold. `programs` và `program_invitations` không được chỉ dựa vào unique constraint để thay cho eligibility check. Các writer Session/Task phải khóa Program trước khi tạo/sửa trạng thái để complete/cancel không bỏ sót blocker. Giao thức này phải được kiểm chứng bằng test cạnh tranh PostgreSQL cho accept-vs-accept/expire/cancel/suspend/hold và complete/cancel-vs-child-write trước phát hành; ADR-09 cũ chưa phải bằng chứng race-safe.

## 7. Quy ước lỗi, side effect và trạng thái triển khai

- Lỗi JSON theo BR-26: `{ "code": "...", "message": "...", "request_id": "...", "errors": {...} }`, response có `X-Request-ID`. Payload sai trả 422 với lỗi theo field; chưa đăng nhập 401; tài nguyên không thuộc scope actor trả 404 để không lộ tồn tại; actor có quyền đọc tài nguyên nhưng không được thực hiện action trả 403. Stale version, khóa Draft, trạng thái/expiry/hold không còn hợp lệ và blocker trả 409 với mã lỗi cụ thể; không có mutation một phần.
- Mutation thành công ghi audit trong transaction. Chỉ ghi outbox khi mutation phát sinh event đã định nghĩa trong [K02.1](BE-K02-K02.1-contract.md); K02.1 chưa định nghĩa notification cho tạo/sửa Program Draft. Tự expire Invitation trong PATCH ghi `invitation_expired` cho Tutor owner cùng transaction. Với pause/resume/complete/cancel, `event_key` dùng ID audit của từng transition (`program:{id}:{action}:{event_id}`), không dùng offer `Program.version`, để chuỗi pause → resume → pause có hai sự kiện pause riêng và retry không nhân đôi. Worker chỉ phát sau commit; lỗi/rollback không tạo event thành công. `idempotency_receipts` đã có migration và được tích hợp cho Accept; chưa phải runtime idempotency dùng chung cho các module khác.
- Migration BE-D02 bổ sung `listing_moderation_holds`, `idempotency_receipts` và `verification_documents.expires_at` cùng vocabulary trạng thái giấy tờ đích; action Accept kiểm hold/replay trong transaction. Cần xác nhận các migration đã chạy trên môi trường triển khai. B1 Program API và Accept đã đăng ký; các route gửi/đọc/reject/cancel Invitation, lifecycle Program, expiry worker và moderation hold writer chưa được triển khai. OpenAPI phải được sinh lại bằng Scramble; API Route Catalog chỉ đánh route “Đã đăng ký” sau khi xuất hiện trong artifact. Không chỉnh `docs/api/openapi.json` bằng tay.

## Nguồn quyết định

[Issue #69](https://github.com/khanhlebao26-star/TutorLink/issues/69) · [PRD M05](<../Modules Documentation/M05 — Program & Invitation/M05 — Program & Invitation — PRD Index.md>) và F01/F02/F03/F06 cùng thư mục · [BR-18, BR-23, BR-25, BR-26](<../Domain Documentation/Business Rules.md>) · [State Lifecycles](<../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../Domain Documentation/Enum Catalog.md>) · [WF04](<../Workflow/WF04 Program và Invitation.md>) · [Database Design](<../Architecture & Technical Design/Database Design — TutorLink.md>) · [BE-D01](BE-D01-issue-36-handover.md).
