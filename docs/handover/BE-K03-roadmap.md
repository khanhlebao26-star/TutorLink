# BE-K03 — Messaging text, chính sách File và realtime

- **Owner backend:** Khanh
- **Reviewer:** Hoàng
- **Frontend:** Phối hợp người phụ trách FE
- **Branch:** be-k03
- **Phạm vi:** M04; tích hợp File lifecycle M10 và Laravel Reverb/Echo.
- **Contract:** [K03.1 — Contract Messaging API và file theo Conversation](BE-K03-K03.1-contract.md)
- **Trạng thái (30/10/2026):** K03.1 đã chốt phạm vi mới; K03.2–K03.6 giữ evidence phiên bản trước. K03.7a, K03.7b và K03.8 đã kiểm tra kỹ thuật — chờ Hoàng review. K03.9–K03.14 chưa nghiệm thu. K03.7 ClamAV là lịch sử, không phải yêu cầu runtime hiện hành. Frontend chat chưa có để nghiệm thu E2E.

## Nguyên tắc triển khai

- REST API chịu trách nhiệm ghi dữ liệu, kiểm quyền, idempotency, history và resync.
- PostgreSQL là nguồn dữ liệu chính.
- WebSocket thông báo thay đổi sau khi transaction commit.
- Frontend dùng cursor API để đồng bộ tin mới, phục hồi khi mất kết nối và dedupe theo server message ID.
- Hoàn thành và kiểm thử phần lõi trước khi tích hợp realtime.
- K03.1 đã thống nhất; các task còn lại chỉ đánh dấu hoàn tất khi có code và evidence.
- Conversation chỉ text, không đính kèm. Không có antivirus tự động. File bài tập/bài nộp kiểm tra kỹ thuật và scope; CV duyệt nội dung riêng theo phiên bản. K03.7b đã đồng bộ frontend/upload/complete: mọi upload mới tối đa 5.000.000 byte. Điều này không chứng nhận API Task/Submission hoặc CV approval đã triển khai.

## Bảng kế hoạch

| Giai đoạn | Task | Nội dung | Trạng thái |
|---|---|---|---|
| A — Lõi API | K03.1 | Thống nhất contract | Đã thống nhất |
| A — Lõi API | K03.2 | Kiểm tra schema và tạo model | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.3 | Tạo/dùng lại Conversation và membership | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.4 | Inbox, detail, history và polling | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.5 | Gửi text, idempotency và quota | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.6 | Read cursor tăng đơn điệu | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| Lịch sử | K03.7 | Phiên bản ClamAV trước điều chỉnh phạm vi | Giữ evidence lịch sử |
| A — Lõi API | K03.7a | Gỡ ClamAV, kiểm tra kỹ thuật và regression | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.7b | Đồng bộ complete/upload và giới hạn 5.000.000 byte | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.8 | Khóa chức năng attachment chat và bảo toàn legacy | Đã kiểm tra kỹ thuật — chờ Hoàng review |
| A — Lõi API | K03.9 | Đồng bộ Conversation lifecycle | Chưa nghiệm thu |
| A — Lõi API | K03.10 | Nghiệm thu phần lõi | Chưa nghiệm thu |
| B — Realtime | K03.11 | Backend broadcasting và Reverb | Chưa nghiệm thu |
| B — Realtime | K03.12 | Frontend Laravel Echo và resync | Chưa nghiệm thu |
| B — Realtime | K03.13 | Thu hồi quyền và nghiệm thu realtime | Chưa nghiệm thu |
| C — Bàn giao | K03.14 | API/schema delta, evidence và PR | Chưa nghiệm thu |

## Giai đoạn A — Lõi Messaging API

### K03.1 — Thống nhất contract

- [x] Thống nhất Conversation theo cặp, context bất biến, quyền đọc/gửi, request/response, cursor, retry, quota và read cursor; điều chỉnh 29/10: chat chỉ text, gỡ scanner, File kiểm tra kỹ thuật và CV duyệt nội dung riêng.
- [x] Bổ sung định hướng triển khai: dùng Reverb/Echo để thông báo realtime; giữ REST API làm luồng ghi và phục hồi dữ liệu.

**Đầu ra:** [Contract K03.1](BE-K03-K03.1-contract.md) làm căn cứ triển khai và review.

### K03.2 — Kiểm tra schema và tạo model

- [x] Dùng lại các bảng conversations, conversation_members, messages, files và file_links.
- [x] Tạo model Conversation/Message; bổ sung quan hệ và index (conversation_id, created_at, id).
- [x] Giữ unique direct_key và unique (sender_id, client_message_id).
- [x] Kiểm tra dữ liệu/trạng thái legacy pending, expired, type=program trước khi quyết định migration; database local chưa có dữ liệu chat, migration dừng nếu gặp dữ liệu cần mapping/backfill ở môi trường khác.
- [x] Bổ sung payload_hash CHAR(64), nullable; tính hash và đối chiếu payload khi gửi/replay tiếp tục ở K03.5.

**Xong khi:** Migration áp dụng được, dữ liệu cũ có mapping rõ ràng và không tạo schema trùng.

**Kết quả:** Đã kiểm tra kỹ thuật trên PostgreSQL test riêng: **23 test pass, 108 assertions**, gồm quan hệ model, attachment resource scope, constraint/index và guard bảo toàn dữ liệu legacy. Chờ Hoàng review; chưa xác nhận migration chuyển đổi được dữ liệu legacy thực tế.

**Evidence:** [Báo cáo K03.2](BE-K03-K03.2-test-report.md). Phần schema/model này làm nền cho K03.3.

### K03.3 — Tạo/dùng lại Conversation và membership

- [x] Triển khai POST /api/v1/conversations; đã xuất trong OpenAPI và khớp route Laravel.
- [x] Kiểm actor/peer, vai trò, Tutor eligibility và Listing context, kể cả context được truyền khi dùng lại chat.
- [x] Find-or-create nguyên tử theo cặp; tạo đúng hai member; giữ context gốc; reopen đúng Conversation khi đủ gate, không mất history/read cursor.
- [x] Dùng thứ tự khóa User theo ID tăng dần → TutorProfile → Listing tùy chọn → Conversation → member theo User ID, khớp tiền tố khóa User/Profile của BE-H02 và BE-D02.

**Xong khi:** Cùng cặp luôn trả cùng Conversation; context không bị ghi đè; lỗi không để dữ liệu dở dang.

**Kết quả:** **38 test pass / 201 assertions / exit 0** trên PostgreSQL test riêng, gồm 14 test API K03.3, 1 race test hai process và 23 test schema/model trước đó. OpenAPI có **64 operations**, route parity pass; Redocly xác nhận hợp lệ với **45 warnings** còn tồn tại. Chờ Hoàng review.

**Evidence:** [Báo cáo K03.3](BE-K03-K03.3-test-report.md). K03.3 đã có commit `97748f5`; K03.4 bên dưới tiếp nối phần tạo/dùng lại Conversation.

### K03.4 — Inbox, detail, history và polling

- [x] Triển khai GET /api/v1/conversations; inbox phân trang cursor scoped theo actor.
- [x] Triển khai GET /api/v1/conversations/{conversation}.
- [x] Triển khai GET /api/v1/conversations/{conversation}/messages; các GET đã có trong OpenAPI và khớp route Laravel.
- [x] Scope theo membership trước khi trả dữ liệu/kiểm cursor; có inbox preview, unread count theo tuple và peer tối thiểu.
- [x] History dùng before; polling dùng after; thứ tự ổn định (created_at, id), trang mặc định 20/tối đa 50; giữ checkpoint polling riêng, hỗ trợ chat rỗng và lấy đủ nhiều trang.
- [x] Conversation cancelled và peer suspended áp dụng đúng quyền đọc; actor suspended bị chặn; GET không reopen hoặc cập nhật read cursor.

**Xong khi:** Outsider không đọc được; tin trùng timestamp vẫn được phân trang đầy đủ.

**Kết quả:** **57 test pass / 364 assertions / exit 0** trên PostgreSQL test riêng, gồm 19 test API K03.4 và 38 test K03.2–K03.3. Đã kiểm quyền, unread, 55 tin trùng timestamp, polling nhiều trang, cursor sai scope và timestamp có phần thập phân. OpenAPI có **67 operations**, route parity pass; Redocly hợp lệ, còn **45 warnings** cũ. Không có schema delta. Chờ Hoàng review; K03.4 chưa commit tại thời điểm kiểm tra.

**Evidence:** [Báo cáo K03.4](BE-K03-K03.4-test-report.md), kèm log test/parity, lint, kiểm contract và SHA-256 nguồn đã kiểm tra. Chưa ghi nhận blocker kỹ thuật trong phạm vi K03.4; các giới hạn và phụ thuộc tiếp theo được ghi trong báo cáo.

**Tiếp nối:** K03.4 đã có commit `4c13afe`; K03.5 bên dưới bổ sung luồng gửi text phù hợp với cursor. Attachment, PATCH read và realtime tiếp tục theo các task tương ứng.

### K03.5 — Gửi text, idempotency và quota

- [x] Triển khai POST /api/v1/conversations/{conversation}/messages cho text; đã sinh OpenAPI và đối chiếu route parity.
- [x] Lấy sender từ session, giữ khoảng trắng, chỉ chuẩn hóa newline, kiểm giới hạn 4.000 ký tự; đọc lại account dưới khóa và kiểm trạng thái trong transaction.
- [x] Cùng sender/key/payload trả message cũ; khác payload hoặc Conversation trả 409; giữ unique toàn sender và lưu canonical payload_hash.
- [x] Replay kiểm actor active/membership, không reopen chat, không tạo side effect và không tiêu quota; vẫn dùng được khi peer suspended hoặc chat cancelled.
- [x] Áp dụng 30 tin mới trong cửa sổ trượt 60 giây/người dùng trên mọi Conversation, dưới User lock; vượt hạn trả 429 và Retry-After.
- [x] Chặn client giả system message, sender/created_at/metadata và file message chưa thuộc K03.5.

**Xong khi:** Retry và concurrent send không tạo trùng; gửi-vs-suspend có kết quả nhất quán.

**Kết quả:** **85 test pass / 590 assertions / exit 0** trên PostgreSQL test riêng: 21 test API/schema/middleware mới, 7 race test mới và 57 test K03.2–K03.4. Đã kiểm retry/conflict, quota cạnh tranh giữa hai chat, send-vs-H02 suspend ở cả hai thứ tự và sender object cũ. OpenAPI có **68 operations**, route parity pass; Redocly hợp lệ với **45 warnings** cũ. Schema bổ sung precision 6 cho timestamp message và index `(sender_id,created_at,id)`; local đã áp dụng, có evidence đọc schema. Chờ Hoàng review; K03.5 chưa commit tại thời điểm kiểm tra.

**Evidence:** [Báo cáo K03.5](BE-K03-K03.5-test-report.md), kèm log test/race, export/parity, lint, schema local và SHA-256 nguồn. Chưa ghi nhận blocker kỹ thuật trong phạm vi gửi text; chưa chạy full backend suite, browser end-to-end hoặc nghiệm thu attachment/realtime.

**Tiếp theo:** K03.5 đã commit tại `fa8af47`; gửi Hoàng review cùng evidence. Kết quả K03.6 được ghi ở mục dưới. Khi bổ sung writer file/system/lifecycle, giữ thứ tự khóa và thứ tự timestamp/commit tương thích polling; K03.8 tiếp tục attachment và K03.11–K03.13 tiếp tục realtime.

### K03.6 — Read cursor tăng đơn điệu

- [x] Triển khai PATCH /api/v1/conversations/{conversation}/read.
- [x] Chỉ cập nhật member hiện tại, kiểm message thuộc đúng Conversation và khóa/cập nhật nguyên tử.
- [x] Mốc cũ/bằng giữ nguyên, trả cursor hiệu lực.
- [x] Không cho reset bằng null hoặc đánh dấu thay người khác.

**Xong khi:** Cursor không lùi và hai cập nhật cạnh tranh giữ mốc xa nhất.

**Kết quả:** **107 test pass / 740 assertions / exit 0** trên PostgreSQL test riêng sau format, gồm 15 test API và 7 race test mới của K03.6 cùng 85 test K03.2–K03.5. Đã kiểm tuple có phần thập phân, ID khi timestamp bằng nhau, read-vs-read, hai member, read-vs-H02 suspend ở hai thứ tự, read-vs-send và rollback. OpenAPI có **69 operations**, route parity pass; Redocly hợp lệ với **45 warnings** cũ. Không có schema delta. **Đã kiểm tra kỹ thuật — chờ Hoàng review**; K03.6 chưa commit tại thời điểm lưu evidence.

**Báo cáo/evidence:** [Báo cáo K03.6](BE-K03-K03.6-test-report.md) và [log kiểm thử](evidence/be-k03-k03.6/api-schema-race-tests.txt).

**Tiếp theo:** K03.6 đã commit tại `c97cb08`; gửi Hoàng review cùng evidence. K03.7 bên dưới bổ sung scanner và đã chạy full backend; các giới hạn của lượt K03.6 được giữ trong báo cáo lịch sử. Chưa hoàn tất toàn BE-K03 hoặc realtime.

### K03.7 — Scanner và complete File (lịch sử trước 29/10/2026)

Phần này giữ kết quả phiên bản ClamAV. Các yêu cầu scanner/attachment/rescan bên dưới đã được thay bởi K03.1 mới và K03.7a–K03.8; không dùng để xác nhận runtime hiện tại hoặc bắt buộc rescan 8 avatar.

- [x] Tích hợp ClamAV service trong Docker Compose; backend gọi INSTREAM tại complete.
- [x] Kiểm object private local thật, purpose/size/extension/MIME/magic bytes/SHA-256 và hash không đổi trong lượt scan.
- [x] Scanner sạch mới ghi clean kèm engine/version/hash/time; malware/metadata vi phạm rejected; scanner/storage lỗi giữ pending, lỗi HTTP sau khi commit trạng thái.
- [x] Áp dụng deadline socket tối đa 10 giây và Retry-After 10; bổ sung ZIP/DOCX guard, không chấp nhận clean khi guard từ chối.
- [x] Phân biệt lifecycle clean cũ với scan evidence; kiểm kê 8 avatar, chốt policy/quy trình rescan qua owner, không backfill giả. **Chưa hoàn tất rescan persisted 8 file.**

**Xong khi:** Có evidence scanner thật, scanner lỗi không cho sử dụng file và không diễn giải checksum thành antivirus scan.

**Kết quả:** Full backend **396 pass / 2.412 assertions / exit 0** với ClamAV thật bật; riêng integration source cuối **4 pass / 18 assertions**. Regression storage thiếu/tamper đã khớp 503/pending và 422/rejected. Pint pass; OpenAPI **69 operations**, parity pass, lint hợp lệ với **44 warnings** còn lại. Schema thêm 4 cột nullable lưu evidence; migration local đã được người dùng áp dụng. **Đã kiểm tra kỹ thuật — chờ Hoàng review.**

**Báo cáo/evidence:** [K03.7 report](BE-K03-K03.7-test-report.md), [legacy policy](BE-K03-K03.7-legacy-policy.md), [full tests](evidence/be-k03-k03.7/full-backend-tests.txt) và [ClamAV thật](evidence/be-k03-k03.7/real-clamav-tests.txt).

**Giới hạn/tiếp theo:** 8 avatar vẫn chưa có evidence persisted; lượt quét chỉ đọc không phải owner HTTP complete. Container nhúng chưa hỗ trợ giữ pending; chưa chứng nhận browser end-to-end/NFR hoặc mọi định dạng tài liệu. Người dùng tự push/gửi review; K03.8 triển khai hasVerifiedScan + owner/purpose/Conversation policy, attachment và bảo toàn lịch sử. Không đánh dấu toàn issue hoàn tất.

### K03.7a — Gỡ ClamAV

- [x] Bỏ scanner client/caller, cấu hình kết nối, service Docker và Compose testing riêng.
- [x] Complete kiểm tra kỹ thuật; không tạo scan evidence giả, giữ schema/evidence lịch sử.
- [x] Vi phạm metadata/archive trả 422/rejected; storage/validation tạm lỗi trả 503/pending, cho retry.
- [x] Thay test scanner bằng test validation, cập nhật comment và regression liên quan.
- [x] Backend/test chạy không có ClamAV; không còn scanner_unavailable trong source runtime.
- [x] Sinh OpenAPI, kiểm parity/lint, lưu test report/evidence.

**Kết quả:** Full backend **384 pass / 2.367 assertions / exit 0**, PostgreSQL test riêng, không có ClamAV. Pint pass; OpenAPI **69 operations**, parity pass. Kết quả lint và giới hạn ghi trong [báo cáo K03.7a](BE-K03-K03.7a-test-report.md), [evidence](evidence/be-k03-k03.7a/). **Đã kiểm tra kỹ thuật — chờ Hoàng review**. Người dùng tự commit/push/gửi review.

### K03.7b — Complete và giới hạn 5 MB

- [x] Đồng bộ upload request, complete, cấu hình, frontend và mô tả API: mọi upload mới tối đa 5.000.000 byte, kiểm số byte thực.
- [x] Kiểm allow-list theo purpose và object thật; test đúng ngưỡng/ngoài ngưỡng, định dạng, checksum, storage lỗi và retry.
- [x] Đối soát local: 0 FileRecord vượt 5.000.000 byte. Không xóa/đổi trạng thái/liên kết hàng loạt; môi trường khác phải kiểm kê trước rollout.
- [x] Sinh OpenAPI, parity/lint; lưu evidence vào thư mục được Git theo dõi.

**Kết quả:** Full backend **398 pass / 2.469 assertions / exit 0**; frontend lint hai file thay đổi và TypeScript pass. Kiểm trình duyệt/API: **8 ca UI + 16 ca API, 68 kiểm tra đạt**. OpenAPI **69 operations**, parity pass; Redocly hợp lệ, **43 warnings**. Không có schema delta. **Đã kiểm tra kỹ thuật — chờ Hoàng review.**

**Báo cáo/evidence:** [K03.7b](BE-K03-K03.7b-test-report.md), [log và ảnh](evidence/be-k03-k03.7b/). [Bản cập nhật issue](BE-K03-K03.7b-issue-update.md) để người dùng tự đăng.

**Tiếp theo:** K03.8 chặn toàn bộ đường attachment chat và bảo toàn legacy. Không đánh dấu toàn BE-K03 hoàn tất; người dùng tự commit/push/gửi review.

### K03.8 — Khóa chức năng attachment chat

- [x] Chỉ nhận text; file_ids chỉ omission hoặc mảng rỗng. Test null/chuỗi rỗng/object rỗng/scalar/mảng có phần tử và multipart; không tạo Message/FileLink khi không hợp lệ.
- [x] Không upload purpose message_attachment; không link resource message. Giữ một rule resource_type duy nhất và đồng bộ enum OpenAPI.
- [x] Đối soát local: 0 message, 0 file message_attachment, 0 link message. Test fixture legacy giữ nguyên Message/FileRecord/FileLink và không chuyển file message cũ thành text khi replay key.
- [x] Kiểm source frontend: upload chỉ ở Tutor onboarding; chưa có Conversation page/component/API client để nghiệm thu UI chat E2E. Không xóa upload hồ sơ.
- [x] Giữ policy verification document và chạy full backend regression; không xóa enum/schema/model relation legacy.
- [x] Sinh OpenAPI, kiểm parity/lint và lưu báo cáo/evidence. Ghi rõ orphan cleanup/storage compensation, generic File race và Task/Submission/CV còn thuộc module phụ trách.

**Kết quả:** **416 test pass / 2.579 assertions / exit 0**, gồm 18 ca K03.8. Pint và git diff --check đạt. OpenAPI **69 operations**, parity pass; Redocly hợp lệ với **43 warnings**. Không có schema delta. **Đã kiểm tra kỹ thuật — chờ Hoàng review.**

**Báo cáo/evidence:** [K03.8](BE-K03-K03.8-test-report.md), [log, SQL và source audit](evidence/be-k03-k03.8/), [nội dung cập nhật issue](BE-K03-K03.8-issue-update.md).

**Phạm vi/giới hạn:** Nghiệm thu khóa attachment chat qua API và kiểm source UI; chưa nghiệm thu chat UI E2E hoặc bổ sung download attachment legacy. Generic race link-vs-delete và cleanup/compensation thuộc M10/K03.10, chưa được chứng nhận bởi task này.

**Tiếp theo:** K03.9 đồng bộ Conversation lifecycle; người dùng tự commit/push/yêu cầu review. Không đánh dấu toàn BE-K03 hoàn tất.

### K03.9 — Đồng bộ Conversation lifecycle

- [ ] Tích hợp vào transaction Program/Invitation liên quan.
- [ ] Chỉ đóng chat khi đạt điều kiện lifecycle và không còn quan hệ tiếp diễn.
- [ ] Reopen chỉ qua yêu cầu mở liên hệ hợp lệ.
- [ ] Suspend không đổi membership/status chat; không tự tạo system message.

**Xong khi:** Hoàn tất một Program không đóng chat nếu còn Program active/paused hoặc Invitation pending còn hiệu lực.

### K03.10 — Nghiệm thu phần lõi

- [ ] Tổng hợp test API, quyền, cursor, quota, retry, read cursor, file và lifecycle.
- [ ] Chạy race test PostgreSQL bằng process/kết nối độc lập: create-vs-create.
- [ ] Chạy race send-vs-send cùng key.
- [ ] Chạy race read-vs-read.
- [ ] Chạy race send-vs-suspend.
- [ ] Chạy race link-vs-delete.
- [ ] Chạy regression các phần bị ảnh hưởng: File, Tutor suspension và Program/Invitation.

**Xong khi:** Các tiêu chí lõi có test/evidence, kết quả và phần còn thiếu được ghi rõ.

## Giai đoạn B — Realtime với Reverb/Echo

### K03.11 — Backend broadcasting và Reverb

- [ ] Cài/cấu hình Laravel Reverb, service Docker và broadcast worker phù hợp.
- [ ] Tạo private channel theo Conversation; kiểm session, account và membership khi subscribe.
- [ ] Phát sự kiện báo có tin mới sau commit.
- [ ] Payload tối thiểu để frontend biết cần đồng bộ; không phát nội dung/file metadata riêng tư không cần thiết.
- [ ] Retry message không phát thêm sự kiện nghiệp vụ.
- [ ] Broadcast lỗi không làm mất message đã lưu.

**Xong khi:** Chỉ người đủ quyền subscribe được; rollback không phát sự kiện; message đã commit có thể được thông báo realtime.

### K03.12 — Frontend Laravel Echo và resync

- [ ] Tích hợp Echo trong Next.js; quản lý một kết nối phù hợp với phiên đăng nhập.
- [ ] Khi mở chat, subscribe private channel.
- [ ] Nhận sự kiện thì đồng bộ bằng API after cursor và dedupe theo message ID.
- [ ] Khi reconnect, resync từ mốc đã xử lý.
- [ ] Giữ polling fallback có kiểm soát khi realtime không hoạt động.
- [ ] Cleanup subscription khi đổi chat/logout; không tạo nhiều poll hoặc listener trùng nhau.

**Xong khi:** Người nhận thấy tin mới mà không refresh; reconnect không mất hoặc nhân đôi tin.

### K03.13 — Thu hồi quyền và nghiệm thu realtime

- [ ] Xử lý trường hợp người dùng bị suspend/logout khi socket đang mở; xác thực lúc subscribe không phải kiểm tra quyền duy nhất.
- [ ] Outsider không subscribe.
- [ ] Người mất quyền không tiếp tục nhận dữ liệu.
- [ ] Disconnect/reconnect vẫn lấy đủ tin.
- [ ] Replay không tạo tin trùng trên UI.
- [ ] Reverb/worker lỗi, REST và resync vẫn hoạt động.
- [ ] Broadcast và API đến khác thứ tự không làm UI nhân đôi message.

**Xong khi:** Có evidence tích hợp hai tài khoản, test quyền và phục hồi kết nối.

## Giai đoạn C — Bàn giao

### K03.14 — API/schema delta, evidence và PR

- [ ] Sinh lại OpenAPI bằng Scramble, kiểm route parity/lint và cập nhật Catalog đúng trạng thái thực tế.
- [ ] Bàn giao PR/commit và hướng dẫn chạy.
- [ ] Bàn giao API/schema delta.
- [ ] Bàn giao cấu hình validation, hướng dẫn gỡ scanner và cấu hình/service realtime.
- [ ] Bàn giao test report, log và demo hai tài khoản.
- [ ] Bàn giao known limitations và blocker chưa giải quyết.
- [ ] Cập nhật issue theo từng mốc: đã xong gì, evidence ở đâu, việc tiếp theo và blocker.

## Điều kiện hoàn tất issue

- [ ] Các tiêu chí Messaging text và File theo K03.1 điều chỉnh 29/10/2026 đều đạt.
- [ ] Không tạo trùng Conversation hoặc message khi cạnh tranh/retry.
- [ ] Outsider không đọc/gửi/subscribe realtime; không tải file ngoài scope resource. Chat không hỗ trợ attachment.
- [ ] Read cursor và lịch sử đồng bộ đúng sau reconnect.
- [ ] Suspend/logout được áp dụng cho cả REST và kết nối đang mở.
- [ ] Không tuyên bố file đã antivirus-scan khi chưa có kết quả quét thực tế.
- [ ] API/schema, test evidence và known limitations khớp với code bàn giao.

Nếu team chỉ nghiệm thu phần lõi trước, ghi rõ Giai đoạn B chưa hoàn tất; không đánh dấu toàn issue hoàn tất khi realtime vẫn nằm trong phạm vi.
