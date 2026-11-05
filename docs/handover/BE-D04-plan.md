# BE-D04 — Plan triển khai và nghiệm thu

- **Ngày lập:** 01/11/2026 · **Owner:** Dũng · **Issue:** [#78](https://github.com/khanhlebao26-star/TutorLink/issues/78).
- **Contract thực hiện:** [BE-D04-contract.md](BE-D04-contract.md), đặc biệt mục 13–17.
- **Phạm vi:** 3 API Progress, 5 API Announcement, 7 API Fee và 1 API Overview; schema, policy, transaction, audit/outbox/receipt, file integration và tài liệu liên quan.
- **Nền khảo sát:** [Baseline D04-01](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-01). Baseline đã lưu 89 route và chưa có 16 API đích; phải đọc lại số liệu lúc bắt đầu thực hiện.
- **Trạng thái:** Chưa thực hiện các bước bên dưới. Chỉ đánh dấu `[x]` khi đạt đủ tiêu chí của chính mục đó và đã lưu bằng chứng.

## 1. Trình tự bắt buộc

**Chuẩn bị test → viết case theo contract → triển khai và kiểm chứng trên test → lưu evidence/freeze phiên bản → preflight môi trường thực tế → áp dụng phiên bản đã kiểm chứng → kiểm chứng thực tế → bàn giao.**

Môi trường test phải có database, storage, account và cấu hình worker riêng. Mọi migration thử, seed, race, fault injection, giả lập thời gian và thử cleanup chạy ở test trước. Không chạy test suite hoặc seed/reset database trên môi trường ứng dụng đang có dữ liệu.

“Môi trường thực tế” là runtime ứng dụng đích được ghi chính xác trong manifest ở D04-21: local đang dùng, staging hoặc production. Kết quả của một môi trường không thay thế bằng chứng của môi trường khác. Kế hoạch này không mặc định các môi trường đó đều đã tồn tại.

Các bước D04-01 đến D04-20 thực hiện ở test. Chỉ bắt đầu D04-21 khi D04-20 đạt. Khi phát sinh sửa code/migration sau D04-20, quay lại kiểm thử phần ảnh hưởng, chạy lại bộ bắt buộc và tạo manifest mới trước khi áp dụng tiếp.

## 2. Quy tắc lưu bằng chứng

Root evidence: `docs/handover/evidence/be-d04/`. Bản evidence theo từng run được giữ nguyên đường dẫn bên trong các gói ZIP tại `consolidated/`; manifest cạnh mỗi gói ghi đường dẫn gốc, kích thước và SHA-256. Tra cứu bằng [test index](evidence/be-d04/test/evidence-index.md), [actual index](evidence/be-d04/actual/evidence-index.md) hoặc các [bản tổng hợp theo nhóm](evidence/be-d04/consolidated/).

`run-id` có dạng `2026-11-01-170000` theo Asia/Ho_Chi_Minh. Mỗi lần chạy ghi cả thời gian có offset, commit SHA, trạng thái working tree, image/build ID và environment. Nếu source còn thay đổi chưa commit, lưu thêm patch và hash source; bản chuyển sang thực tế phải định danh được đầy đủ.

Mỗi case API cần lưu: case ID, actor/role (dữ liệu giả), method/path/query, body và Idempotency-Key nếu có, HTTP status, response headers đã lọc, response JSON/body, kết quả mong đợi và kết quả thực tế. Giữ `X-Request-ID` để nối với log; bỏ cookie, CSRF token, Authorization, mật khẩu và secret khỏi artifact/ảnh. File download lưu status/header an toàn và checksum nội dung thử nghiệm để đối chiếu.

Với version/replay/no-op/transaction, lưu thêm snapshot DB trước/sau gồm ID, version và count audit/outbox/receipt/file links liên quan. **Response 200 hoặc một ảnh đơn lẻ không đủ chứng minh không nhân side effect.** Chỉ query các field phục vụ case, dùng dữ liệu giả cho evidence công khai.

Ảnh chụp phải lấy từ thao tác thật trên trình duyệt hoặc công cụ gọi API, thấy được endpoint/màn hình, actor thử nghiệm, kết quả và environment; không dựng ảnh từ JSON mẫu. Có thể dùng response API đã lưu làm bằng chứng chính khi UI Workspace chưa có. Log test bổ sung cho API/ảnh, không thay thế toàn bộ bộ bằng chứng API yêu cầu ở mục 5.

## 3. Chuẩn bị và thực hiện trên test

- [x] **D04-01 — Ghi nhận baseline và giới hạn thay đổi.md**

  **Thực hiện:** Ghi branch/commit, dependency commits, file đang thay đổi, route/OpenAPI và migration status; đối chiếu contract để xác định đúng 16 API đích.

  **Hoàn thành khi:** Có manifest nền và bảng method/path; xác minh dependency có trong branch; giữ được các thay đổi có sẵn; chưa thay đổi database ứng dụng trong bước này. Baseline route không được dùng để khẳng định behavior đã đạt.

  **Kết quả:** 01/11/2026 · branch `be-d04` · HEAD `135efcc8ec5c4c73cff8d58cabc62a17772234bf`. Test config được đọc; PostgreSQL test chạy healthy và kết nối vào `tutorlink_test` thành công; baseline ban đầu là DB rỗng (0 bảng, chưa có migration table); sau đó theo yêu cầu đã copy DB `tutorlink` sang `tutorlink_test`, đối chiếu đủ 43 bảng và số dòng từng bảng khớp nguồn. Runtime Docker local có 89 route entries/120 method atoms, OpenAPI 89 operations, 0 route D04, 25 migration Ran và 7 Pending. Sáu dependency commit có trong ancestry. Chưa thay đổi DB ứng dụng.

  **Bằng chứng:** [Tổng hợp Test D04-01–06](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-01) · [ZIP](evidence/be-d04/consolidated/test-foundation-01-06.zip) · [manifest](evidence/be-d04/consolidated/test-foundation-01-06.manifest.json) · [Evidence baseline actual](evidence/be-d04/consolidated/actual-local.md#d04-01).

- [x] **D04-02 — Dựng môi trường test cô lập và chặn nhầm database.**

  **Ghi chú bảo trì 05/11/2026:** Override D04 theo timestamp đã được dọn; entrypoint test hiện tại là `compose.testing.yml`. Mô tả sau là evidence lịch sử, không phải lệnh dựng môi trường hiện hành.

  **Thực hiện:** Dùng `compose.testing.yml` với override riêng `compose.be-d04.testing.yml`, kết nối DB clone hiện có `tutorlink_test`, `APP_ENV=testing`. Evidence mount riêng D04; storage/app dùng tmpfs. Network internal; mail array, queue sync, broadcast null; Slack credentials bị xóa; không mở API port.

  **Hoàn thành khi:** Runner kiểm environment/cache, host được phép, database/user thực tế và địa chỉ PostgreSQL trước migrate/reset; từ chối DB/host ứng dụng trước khi kết nối. Guard chạy trước test trait truncate. Storage tách biệt và evidence giữ trên host.

  **Kết quả:** 01/11/2026 · preflight identity đạt; guard suite đạt 8 tests/45 assertions. Test DB giữ nguyên 43 bảng, 154 dòng, 25 migration và 51 user như nguồn; không chạy migration/seed/reset. DB ứng dụng không được dùng để thử guard.

  **Bằng chứng:** [Tổng hợp Test D04-01–06](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-02) · [ZIP](evidence/be-d04/consolidated/test-foundation-01-06.zip) · [manifest](evidence/be-d04/consolidated/test-foundation-01-06.manifest.json).

- [x] **D04-03 — Tạo bộ dữ liệu test có kết quả kỳ vọng rõ.**

  **Thực hiện:** Tạo Tutor owner, Tutor khác, Customer member, outsider, Admin chung, account inactive; hai Program để kiểm cross-scope; Active/Paused/Completed/Cancelled; member bị thu hồi và member được thêm sau publish. Tạo file hợp lệ/sai owner/purpose/pending/rejected và dữ liệu Session/Task phục vụ Overview.

  **Hoàn thành khi:** Fixture có ID/role/state/timezone và expected results được ghi rõ; có kỳ activation/current/future, tháng nhuận, dữ liệu tie-break và trên 50 record để kiểm pagination. Không phụ thuộc dữ liệu cá nhân thật hoặc ID của DB ứng dụng.

  **Kết quả:** 01/11/2026 · tạo fixture tổng hợp trên `tutorlink_test`: 8 actors, 4 Programs, 4 memberships, 59 Fees, 55 Progress, 4 Announcements, 5 Files, 3 Program Sessions, 5 Tasks và 2 Task Submissions. Có kỳ activation/current, tháng nhuận, pagination >50, tie-break, Draft, lifecycle, member revoke/late join, file states và Overview oracle. Seed chạy hai lần; snapshot byte-identical. Guard unit tests đạt 8 tests/57 assertions. Không chạy migration/reset; DB nguồn không đổi.

  **Bằng chứng:** [Tổng hợp Test D04-01–06](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-03) · [ZIP](evidence/be-d04/consolidated/test-foundation-01-06.zip) · [manifest](evidence/be-d04/consolidated/test-foundation-01-06.manifest.json).

- [x] **D04-04 — Viết case kiểm chứng contract trước khi hoàn thiện writer.**

  **Thực hiện:** Lập case theo 16 endpoint, quyền/lifecycle, payload/null/query, version/no-op/replay, file, audit/outbox/receipt và migration. Tạo PostgreSQL schema probes; định nghĩa race/rollback process cases cùng expected side effects để chạy tại D04-15/16 sau khi writer/fault seam tồn tại.

  **Hoàn thành khi:** Mỗi yêu cầu contract có case ID/expected status/body/side effect. Lượt đầu trên PostgreSQL chạy đủ route/schema probes và thất bại do thiếu chức năng/schema, không phải lỗi cấu hình; không bỏ/skip case để lấy kết quả pass. Race và rollback có oracle/chốt process/fault test tại ma trận và sẽ được thực thi ở các bước chuyên biệt D04-15/16. Không yêu cầu test cho chi tiết nội bộ không ảnh hưởng contract.

  **Kết quả:** 01/11/2026 · 16 route probes và 13 schema assertions chạy trên PostgreSQL 16.15, tất cả fail đúng do 16 route chưa đăng ký và schema Fee/Progress/Announcement chưa đạt contract. Laravel kết nối đúng `tutorlink_test`; không lỗi cấu hình. Ma trận bao gồm 7 nhóm PostgreSQL constraint, 7 race case và 4 rollback case với expected response/DB side effects. DB giữ 43 bảng/25 migrations và fixture counts không đổi; không chạy migration/reset/truncate/seed.

  **Bằng chứng:** [Tổng hợp Test D04-01–06](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-04) · [ZIP](evidence/be-d04/consolidated/test-foundation-01-06.zip) · [manifest](evidence/be-d04/consolidated/test-foundation-01-06.manifest.json).

- [x] **D04-05 — Hoàn thiện migration/backfill và diễn tập trên test.**

  **Thực hiện:** Thêm migration theo contract §9/§16 cho Progress, Announcement và Fee; fail-fast với legacy data không thể ánh xạ an toàn; diễn tập backup/restore và kiểm tra constraint trên PostgreSQL test.

  **Hoàn thành khi:** Migration chạy trên DB rỗng và legacy hợp lệ, giữ ID/lịch sử/unique kỳ; dữ liệu mơ hồ hoặc sai contract bị từ chối trước cutover; chạy lại không nhân dữ liệu/side effect; CHECK/FK từ chối row sai; có bằng chứng phục hồi test.

  **Kết quả:** 01/11/2026 · migration đạt trên `tutorlink_test`; giữ 59 Fee, 55 Progress, 4 Announcement và 1 file link; 26 migration đã chạy, gồm D04 đúng một lần. DB rỗng chạy đạt toàn chuỗi 33 migration. Fail-fast đạt 7 tests/27 assertions; PostgreSQL constraints 18/33; schema contract 13/19. Restore drill fingerprint khớp; side effects audit/outbox/receipt/notification không đổi. DB `tutorlink` không bị thay đổi.

  **Bằng chứng:** [Tổng hợp Test D04-01–06](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-05) · [ZIP](evidence/be-d04/consolidated/test-foundation-01-06.zip) · [manifest](evidence/be-d04/consolidated/test-foundation-01-06.manifest.json).
- [x] **D04-06 — Làm nền chung cho validation, quyền, khóa và response.**

  **Thực hiện:** Cài D04 request normalization/base validation, Workspace scope/owner guard và transaction helper theo thứ tự khóa contract; dùng chung `ApiErrorResponse`/`X-Request-ID`.

  **Hoàn thành khi:** Owner/member/outsider, revoked member và account state đúng; scope được kiểm trước dữ liệu; integer strict/unknown field/query/JSON body/null/whitespace đúng; stale trả 409 envelope; lock order Users→Profile→Program→member→resource→Files→FileLinks được xác minh trên PostgreSQL.

  **Kết quả:** 02/11/2026 · focused HTTP suite đạt 11 tests/79 assertions trên `tutorlink_test`. Transcript xác nhận 401/403/404/409/422, normalization CRLF/trim có chọn lọc, omission khác null, integer string/float/bool bị từ chối, unknown body/query bị từ chối. PostgreSQL lock trace đạt thứ tự contract. DB row counts và audit/outbox/receipt/notification giữ nguyên. Route D04 sản phẩm vẫn để các bước D04-07 trở đi đăng ký; D04-06 dùng route probe chỉ trong test.

  **Bằng chứng:** [Tổng hợp Test D04-01–06](evidence/be-d04/consolidated/test-foundation-01-06.md#d04-06) · [ZIP](evidence/be-d04/consolidated/test-foundation-01-06.zip) · [manifest](evidence/be-d04/consolidated/test-foundation-01-06.manifest.json).
- [x] **D04-07 — Triển khai 3 API Progress.**

  **Thực hiện:** List/create/PATCH, metric progress_percent, value 0–100, note nullable ≤2.000 ký tự và audit update theo contract.

  **Hoàn thành khi:** Create 201/version1; PATCH đúng version tăng một lần và ghi before/after allow-list. Omission giữ note, null xóa note; no-op không đổi timestamp/version/audit. Recorded_at giữ nguyên khi edit; list/latest đúng recorded_at/id. Active/Paused cho ghi, terminal chặn request mới; Customer chỉ đọc.

  **Kết quả:** 02/11/2026 · GET/POST/PATCH chạy trên `tutorlink_test`; suite đạt 3 tests/64 assertions. Owner ghi khi Program Active/Paused, Customer chỉ đọc; audit update chỉ lưu projection/hash; no-op không tăng version/time/audit/outbox; stale vẫn 409. DB giữ 55 Progress và không phát sinh audit/outbox; migration D04 vẫn đúng 1 lần.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-07) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-08 — Triển khai Draft Announcement và danh sách.**

  **Thực hiện:** Create/list/detail/PATCH Draft, title/body/file_ids theo contract, state suy từ published_at.

  **Hoàn thành khi:** Owner dùng state=draft/all; Customer chỉ thấy Published, không nhận Draft/count Draft. File_ids omission giữ tập, [] tháo tập, null/duplicate/>5 trả 422; title/body đúng giới hạn. PATCH stale 409; no-op không đổi version/time. List Published/Draft/all đúng thứ tự và pagination.

  **Kết quả:** 02/11/2026 · 4 route trước publish đạt 3 tests/115 assertions trên `tutorlink_test`. Owner xem Draft/all; Customer chỉ nhận Published; state/page/order, no-op/stale và file diff đúng contract. PATCH omission giữ link, `[]` tháo link; chỉ file diff ghi audit, không xóa File hay tạo outbox.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-08) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-09 — Hoàn thiện file Announcement từ upload đến download.**

  **Thực hiện:** Bổ sung announcement_attachment vào validation/consumer liên quan; dùng upload/complete hiện có, gắn/thay/tháo qua POST/PATCH Announcement. Bổ sung policy metadata/download và bảo vệ các đường generic-link/delete/cleanup.

  **Hoàn thành khi:** File private đúng owner/purpose/resource, 1..5.000.000 byte và đúng loại được complete/gắn. Pending/rejected/sai owner/purpose/Program không gắn được. Thay tập file nguyên tử, chỉ diff link; không xóa bytes khi unlink. Generic link Announcement vẫn 409. Draft chỉ owner đọc/tải; Published cho member hiện tại; thu hồi membership mất quyền kể cả biết ID. Uploader đọc được metadata pending chưa gắn. Published không bị sửa file/xóa/thay bytes qua API khác.

  **Kết quả:** 02/11/2026 · D04-09 đạt 3 tests/75 assertions; regression D04-07/08/09 đạt 9 tests/254 assertions trên `tutorlink_test`. Upload/complete, metadata, download checksum, scope Draft/Published, membership bị thu hồi, generic-link/delete và giữ bytes khi unlink đều được kiểm tra. Một migration scan-evidence nullable còn thiếu của pipeline K03 được áp dụng riêng trên test DB; 6 migration khác giữ pending. Không kết nối runtime database dự án.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-09) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json) · [Evidence project actual bổ sung](evidence/be-d04/consolidated/actual-local.md#d04-09).

- [x] **D04-10 — Triển khai publish Announcement nguyên tử.**

  **Thực hiện:** Publish với expected_version + UUID key, snapshot recipient và ghi Published/audit/outbox/receipt trong cùng transaction.

  **Hoàn thành khi:** Publish 200/version+1; recipient là member active tại thời điểm publish trừ owner; member thêm sau chỉ đọc, không nhận thông báo hồi tố. Cùng key/request trả snapshot cũ; khác key không publish lại. Nội dung và file Published bất biến. Outbox có key announcement:{id}:published, payload chỉ deep_link, không body/title/file metadata; failure rollback toàn bộ.

  **Kết quả:** 02/11/2026 · Acceptance đạt 3 tests/75 assertions; regression D04-07..10 đạt 12 tests/329 assertions trên `tutorlink_test`. Publish, UUID key/hash/receipt 30 ngày, replay, stale, recipient snapshot, audit/outbox allow-list, Published immutability và rollback do outbox failure đều đạt. DB counts không đổi; 6 migration nền khác giữ pending; không kết nối runtime database dự án thật.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-10) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-11 — Triển khai đủ 7 API Fee.**

  **Thực hiện:** List/create/detail/PATCH/mark-paid/mark-unpaid/void; dùng period YYYY-MM và schema đích.

  **Hoàn thành khi:** Create chỉ Active, tháng activation..current theo timezone, VND integer hợp lệ. Unique Program/tháng kể cả voided. Amount0 giữ unmarked; sửa amount chỉ declared/chưa marked_paid; paid→unpaid cần reason; void giữ mark/kỳ/payment metadata và không mở lại. Version, last_change_reason và disclaimer đúng; Customer chỉ đọc allow-list. Mỗi thay đổi thực tế có audit + fee_record_changed theo record/version; không có payment gateway.

  **Kết quả:** 02/11/2026 · Acceptance Fee đạt 2 tests/143 assertions, schema contract 13/20, regression D04-07..11 đạt 27/492 trên `tutorlink_test`. Cả 7 route, VND/period/amount, quyền, pagination, state transition, version/no-op, idempotency receipt, audit/outbox và rollback đạt. Migration bổ sung CHECK VND sau khi xác nhận 59/59 row hiện có hợp lệ; chỉ chạy migration D04 currency mới trên test DB.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-11) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-12 — Triển khai Overview từ dữ liệu thực.**

  **Thực hiện:** Thêm GET overview theo contract §3.1/§13.6, dùng consistent read snapshot và projection allow-list.

  **Hoàn thành khi:** next_session chọn đúng scheduled tương lai; task_counts có đủ 5 trạng thái kể cả 0; submitted không tính completed. Latest Progress không bị edit record cũ làm thay đổi thứ tự; latest Announcement chỉ Published kể cả owner. Summary thiếu trả null; capabilities đúng actor/Program state; không tạo blocker Program mới.

  **Kết quả:** 02/11/2026 · Acceptance Overview đạt 4 tests/62 assertions; regression API/schema D04-07..12 đạt 63/663 trên `tutorlink_test`. Summary dùng Session tương lai, đủ 5 Task status, Progress theo recorded_at và Published Announcement; capabilities đúng scope/state. Test connection thứ hai chứng minh response nhất quán qua thay đổi đồng thời Program/Task. Chạy riêng migration Session version còn Pending vì resource Overview dùng SessionResource; DB fixture giữ nguyên.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-12) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-13 — Kiểm payload, danh sách và timestamp xuyên 16 API.**

  **Thực hiện:** Chạy ma trận field bắt buộc/null/unknown, integer strict, Unicode/CRLF/whitespace, pagination/filter/order, scope/count và timezone.

  **Hoàn thành khi:** Omission/null/default đúng từng route; JSON string/float/boolean không lọt vào integer. Default20/max50 được áp dụng, vượt50 trả422, trang vượt cuối trả data[]. Filter kết hợp AND; tie-break ổn định; error/success projection đúng; timestamp Z và ngày kỳ theo timezone Program, gồm biên tháng/năm nhuận.

  **Kết quả:** 3 acceptance tests / 144 assertions; regression D04-07..13 đạt 66 tests / 807 assertions. BR-26, filter, stable order, owner Draft visibility, strict integer/unknown field, Unicode/CRLF/whitespace, omission/null và timezone Fee month boundary đã được đối chiếu bằng API trên PostgreSQL `tutorlink_test`.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-13) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-14 — Kiểm idempotency và no-op tách biệt.**

  **Thực hiện:** Kiểm publish/Fee key-required, Progress/Draft không có bảo đảm key; canonical hash, version và side-effect counts.

  **Hoàn thành khi:** Same actor/key/method/path/payload replay đúng status/body; đổi method/path/body cùng key409. Reordered JSON keys/defaults đúng normalization; đổi nội dung có ý nghĩa không được replay nhầm. Replay kiểm quyền hiện tại và có thể trả snapshot cũ khi resource/Program đã đổi. No-op đúng version trả200 không tăng resource/time/audit/outbox; Fee no-op key mới chỉ thêm receipt. Stale dù cùng giá trị vẫn409; published/voided/terminal key mới vẫn conflict.

  **Kết quả:** 4 acceptance tests / 75 assertions; regression D04-07..14 đạt 70 tests / 882 assertions. Same key replay, hash canonical, Fee create replay 201 sau state change, key mismatch, current permission, Fee no-op receipt riêng, stale-same-value, Progress/Draft không dùng key để replay đều được xác nhận.

  **Bằng chứng:** [Tổng hợp Test D04-07–14](evidence/be-d04/consolidated/test-api-implementation-07-14.md#d04-14) · [ZIP](evidence/be-d04/consolidated/test-api-implementation-07-14.zip) · [manifest](evidence/be-d04/consolidated/test-api-implementation-07-14.manifest.json).

- [x] **D04-15 — Kiểm race trên PostgreSQL thật.**

  **Thực hiện:** Dùng process/connection độc lập, chờ khóa có quan sát được; chạy đủ các race contract §15.3 và file replace/delete vs publish. Bao gồm same-key across resources, edit/mark/void cạnh tranh và D04 writers vs pause/complete/cancel Program.

  **Hoàn thành khi:** Kết quả đúng theo từng thứ tự khóa; tối đa một thay đổi với expected_version cũ, một Fee cho mỗi tháng, một publish/event. Lifecycle thắng trước thì mutation mới bị chặn; writer thắng trước giữ lịch sử và terminal không mất cập nhật. Không deadlock do đảo khóa; retry có giới hạn. Case race bắt buộc chạy ít nhất 3 lượt liên tiếp đạt và lưu kết quả từng lượt; không dùng SQLite thay bằng chứng này.

  **Bằng chứng:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-15) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

- [x] **D04-16 — Kiểm rollback bằng fault injection ở test.**

  **Thực hiện:** Gây lỗi có chủ đích tại link/unlink, audit, outbox và receipt sau khi bước ghi trước đã chạy; áp dụng từng writer liên quan.

  **Hoàn thành khi:** Response lỗi, resource/version/timestamp/file links/recipient snapshot/audit/outbox/receipt đều về trạng thái trước command; không có notification từ mutation rollback. Trigger/fault hook được dọn trong finally và không có trong artifact triển khai. Sau khi gỡ lỗi, retry hợp lệ thành công một lần.

  **Kết quả:** 02/11/2026 · 4 test / 137 assertions PASS trên `tutorlink_test`; 8 fault case (file link/unlink, Fee audit/outbox/receipt, Announcement audit/outbox/receipt) đều trả 500 và giữ nguyên state đã ghi nhận trước request. Gỡ từng hook trước retry; cả 8 retry thành công. Không còn trigger/function D04-16; DB snapshot trước/sau trùng khớp.

  **Bằng chứng:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-16) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

- [x] **D04-17 — Kiểm outbox delivery và retention receipt.**

  **Thực hiện:** Chạy worker trên test DB với recipient giả; kiểm pause/resume worker, deduplicate, receipt lazy-expiry/cleanup và thời gian giả lập.

  **Hoàn thành khi:** Chỉ outbox đã commit sinh notification; đúng recipient/type/event_key/payload, một notification mỗi event/recipient dù worker retry. Publish receipt hết30 ngày không cho publish lần hai. Fee receipt expires_at=NULL, trên30 ngày, record voided hoặc Program terminal vẫn giữ và replay khi còn quyền; audit đủ kỳ purge không tự xóa receipt. Consumer/cleanup không xóa nhầm durable receipt. Khi chưa có quy trình purge Fee đủ điều kiện thì receipt tiếp tục giữ. Không gửi Slack/email cho người thật.

  **Kết quả:** 02/11/2026 · 8 test / 157 assertions PASS: D04-17 (3/58), committed-event worker (1/5), Announcement publish regression (3/75), terminal replay/current-owner regression (1/19). Announcement và Fee chỉ phát một notification mỗi event đúng recipient/type/event_key/payload; trì hoãn worker không phát notification. Fee receipts `expires_at=NULL` vẫn tồn tại và replay snapshot sau 31 ngày, Fee voided, Program completed và cleanup audit; publish Announcement hết TTL trả 409, receipt expired được dọn, không tăng publish/audit/outbox. `tutorlink_test` đã restore sau worker regression; snapshot trước/sau khớp.

  **Bằng chứng:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-17) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

- [x] **D04-18 — Hoàn tất regression, OpenAPI và tài liệu kỹ thuật.**

  **Thực hiện:** Chạy bộ D04, các regression liên quan và toàn backend PostgreSQL; sinh OpenAPI từ code trên DB export tạm; kiểm route parity/lint. Đồng bộ Database Design, action catalog M09-F05, API Catalog và PRD/WF05 liên quan. Sau thay đổi code chạy graphify update .

  **Hoàn thành khi:** Test bắt buộc không failure/error/skipped; không có regression chưa xử lý. Có đủ 16 method/path đích và success/error/header schemas, không làm mất route nền. Nếu chỉ thêm D04 vào baseline89 thì tổng105 operations; ưu tiên so sánh tập method/path với baseline thực tế, không chỉ đếm. Lint không lỗi schema/reference; warnings phải so với baseline, ghi rõ nội dung chưa xử lý. Catalog chỉ gắn Đã đăng ký cho route hiện hữu; graph update thành công hoặc có giới hạn được ghi chính xác.

  **Bằng chứng:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-18) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

  **Kết quả:** 02/11/2026 · PASS trên `tutorlink_test`/PostgreSQL: D04 focused 77 test/1.077 assertions; race 1/893; migration preflight trên clone legacy 7/27; full backend còn lại 627/4.545. Tổng 712 test/6.542 assertions, không failure/error/skipped. Full suite reset fixture test; `tutorlink_test` được phục hồi từ checkpoint và snapshot sau phục hồi trùng baseline (29 migrations, D04 batch 11/13); clone legacy đã xóa. OpenAPI Scramble có 105 operation so với baseline 89: đủ 16 D04, 0 route bị mất; response/request/error/header schema check và route parity PASS. Redocly exit 0 cả hai bản; 59 warning fingerprint giữ nguyên, 0 mới/0 resolved. Catalog và Database Design/M09/M06/M07/M08/WF05/API README đã đồng bộ; graphify update PASS, có ghi nhận SQL extractor chưa có.

  **Report và evidence:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-18) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

- [x] **D04-19 — Chạy demo HTTP và thu evidence API/ảnh trên test.**

  **Thực hiện:** Sau test suite, khởi tạo lại fixture demo riêng trên test DB, chạy ứng dụng qua HTTP với session/CSRF thật và thực hiện ma trận mục 5. Không dựa riêng vào controller/action calls.

  **Hoàn thành khi:** Có request/response cho tất cả16 API; có success,403/404/409/422, replay/no-op và file download đúng quyền. Tất cả dòng bắt buộc trong ma trận evidence có artifact và expected/actual. Ảnh nếu dùng được chụp từ lần thao tác này; test summary/ảnh không thay response body cần đối chiếu contract.

  **Bằng chứng:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-19) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

  **Kết quả:** 02/11/2026 · PASS · 96/96 HTTP cases đúng expected status, bao phủ 16/16 D04 operation với session/CSRF thật trên `tutorlink_test`. Có request/response cho 401/403/404/409/419/422, replay/no-op, upload→complete→attach/replace/detach→publish→download, Fee create→edit→paid→unpaid→void và Overview. Active/Paused/Completed/Cancelled được gọi qua HTTP: Progress được ghi trên Paused; capability ghi bị tắt và Progress/Fee/Announcement write bị chặn trên Completed/Cancelled. Audit before/after, outbox recipient, receipt retention và checksum file được đối chiếu theo resource ID. Race/fault/worker/retention tiếp tục tham chiếu test riêng D04-15/16/17. Sau test, `tutorlink_test` được phục hồi và snapshot khớp D04-18 byte-for-byte; backend-test dừng.

  **Report và evidence:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-19) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

- [x] **D04-20 — Chốt phiên bản đã kiểm chứng, đủ điều kiện chuyển môi trường.**

  **Thực hiện:** Đối chiếu D04-01..19, kiểm file bằng chứng mở được, đóng gói commit/build và hướng dẫn migration/phục hồi.

  **Hoàn thành khi:** D04-01..19 đều đạt; không còn lỗi contract/data-integrity/authorization/race/rollback chưa xử lý; đủ evidence mục5 ở test. Release manifest ghi chính xác SHA, image digest/build ID, migration list, config delta, checksum contract/OpenAPI, test run IDs và evidence index. Source được đóng gói đúng bản đã test, fault hooks không còn. Không chuyển code chưa kiểm chứng sang môi trường thực tế.

  **Bằng chứng:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-20) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

  **Kết quả:** 02/11/2026 · PASS_TEST_RELEASE_CANDIDATE. D04-01..19 đều PASS và có report/evidence được link trong index. Chốt base commit `135efcc8ec5c4c73cff8d58cabc62a17772234bf`, dirty backend build-context SHA-256, image ID/digest `sha256:95916de4c50255c4694cfd2dafa20878a2939f09fe46272101fe113f13a371ad`, 2 D04 migration records + 5 supporting migrations dùng trong HTTP runtime, config delta test, contract/OpenAPI checksum và test run IDs. Source file hash trong image khớp working tree. Fault injection chỉ bằng PostgreSQL trigger ở test. Image test đang local-only; actual preflight phải xác nhận identity và migration status, không áp dụng theo danh sách test một cách máy móc. Test DB đã restore và test API container đã dừng; D04-21..25 chưa chạy.

  **Report và release package:** [Tổng hợp Test D04-15–20](evidence/be-d04/consolidated/test-assurance-release-15-20.md#d04-20) · [ZIP](evidence/be-d04/consolidated/test-assurance-release-15-20.zip) · [manifest](evidence/be-d04/consolidated/test-assurance-release-15-20.manifest.json).

## 4. Áp dụng và xác nhận trên môi trường thực tế

- [x] **D04-21 — Preflight môi trường đích và sao lưu.**

  **Thực hiện:** Ghi environment/URL/database/storage/worker, phiên bản đang chạy và migration pending; inventory read-only dữ liệu theo contract §9. Sao lưu theo runbook đã diễn tập ở test; ghi cách chuyển sang artifact mới khi schema tương thích.

  **Hoàn thành khi:** Manifest nhận diện đúng đích; backup có ID/checksum và có bằng chứng diễn tập restore từ D04-05. Mọi legacy row cần xử lý được thống kê; không còn confirmed/cancelled/scope/metric/month/amount mơ hồ làm migration fail. Pending migration đã phân loại để không chạy ngoài scope. Chỉ có preflight và backup ở bước này; không chạy seed/reset/migrate:fresh hoặc fault/race probes trên dữ liệu thực.

  **Bằng chứng:** [Tổng hợp Actual local D04-21](evidence/be-d04/consolidated/actual-local.md#d04-21) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

  **Kết quả:** 02/11/2026 · PASS cho môi trường Docker local TutorLink. PostgreSQL target `tutorlink` được xác nhận từ cả Laravel và kết nối trực tiếp; backend/DB khỏe; PHP 8.3.36, Laravel 13.35.0, PostgreSQL 16.15. Read-only inventory không có Fee, Progress, Announcement hoặc file link; tất cả legacy checks D04 đều bằng 0. Backup custom-format ngoài repo có checksum và đã được pg_restore đọc/giải nén kiểm tra. Có 9 migration Pending (7 nền + 2 D04), đã phân loại; hai migration nền thêm so với test checkpoint được nêu rõ cần diễn tập ở D04-22. D04-21 không chạy migration, deploy hay thay đổi dữ liệu.

  **Điều kiện D04-22:** Runtime hiện tại là local với debug bật; image đang chạy không phải image D04-20 dù 359 file source khớp checksum. Chỉ chọn artifact đã kiểm chứng; kiểm tra chênh lệch hai migration Pending thêm so với test trước khi áp dụng. Chưa nhận diện remote staging/production trong bước này.

  **Report và bằng chứng:** [Tổng hợp Actual local D04-21](evidence/be-d04/consolidated/actual-local.md#d04-21) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

- [x] **D04-22 — Áp dụng migration và artifact đã kiểm chứng.**

  **Thực hiện:** Áp dụng đúng migration/backfill/config/runtime sequence đã diễn tập; triển khai cùng phiên bản ứng dụng/worker/scheduler, cập nhật route/config cache theo runbook.

  **Hoàn thành khi:** SHA/image khớp chính xác candidate D04-22 đã kiểm chứng trong release manifest; chỉ migration dự kiến được chạy; row counts/ID/history/unique/check/metadata đúng kết quả preflight và backfill. Runtime có đủ16 route, không mất route nền. Worker/scheduler dùng cùng phiên bản và cấu hình đích. Nếu migration/API/constraint sai, dừng bước tiếp theo và dùng phương án phục hồi/forward-fix đã diễn tập; không tự xóa lịch sử hoặc ép dữ liệu để tiếp tục.

  **Bằng chứng:** [Tổng hợp Actual local D04-22](evidence/be-d04/consolidated/actual-local.md#d04-22) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

  **Kết quả:** 02/11/2026 · PASS trên local Docker Compose. Candidate image `sha256:8f1589bfc233765bedce705b2c802fb319f9d10a821ed1a46b57577c2e605411` khớp image của backend/worker/scheduler; 9 migration Pending được chạy, kết thúc 34 Ran/0 Pending. Inventory không có thay đổi D04 business rows; 13 D04 constraints hiện diện. Runtime healthy, 105/105 OpenAPI operation khớp route thực tế gồm 16/16 D04; log scan không thấy lỗi fatal. Test DB đã restore byte-identical về checkpoint. Actual smoke API được để cho D04-23.

  **Report và bằng chứng:** [Tổng hợp Actual local D04-22](evidence/be-d04/consolidated/actual-local.md#d04-22) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

- [x] **D04-23 — Smoke test và thu bằng chứng API/ảnh thực tế.**

  **Thực hiện:** Gọi HTTP qua đúng URL đích bằng account/dữ liệu nghiệm thu riêng, theo ma trận mục5; không dùng account hoặc dữ liệu người dùng thật để thử mutation.

  **Hoàn thành khi:** Có bằng chứng actual cho cả16 API và luồng upload→complete→attach→publish→đọc/tải, Fee create→edit→paid→unpaid→void, Overview. Auth/CSRF/current membership đúng, stale409/no-op/replay đúng contract và không nhân event. Thử terminal và void trên Program/Fee nghiệm thu được đặt tên rõ, không sửa lịch sử thật. Có thể xác nhận notification after-commit trên runtime worker thật với recipient nghiệm thu; race/fault/time-travel/destructive cleanup chỉ dùng evidence test. Không có dữ liệu nghiệm thu bị trộn vào báo cáo người dùng thật.

  **Bằng chứng:** [Tổng hợp Actual local D04-23](evidence/be-d04/consolidated/actual-local.md#d04-23) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

  **Kết quả:** 02/11/2026 · PASS trên local Docker Compose (`tutorlink`), chỉ dùng ba actor và hai Program synthetic. HTTP evidence bao phủ 16/16 D04 operation; owner/member/outsider, CSRF, stale/no-op/replay, upload→complete→attach→publish→member read/download và đủ bảy Fee route được xác nhận. Fee ID 1 ở trạng thái voided version 5; Program ID 2 cancelled; không sửa hồ sơ người dùng hiện có. Bảy outbox event đã processed đúng một lần, không pending/failed/duplicate; có bảy notification cho recipient nghiệm thu. Fee/Published Announcement/file/receipt được giữ theo chính sách lưu trữ. Trace đầu có một probe tải file trước khi gắn (403); thứ tự đúng đã được chạy tiếp và cho Draft 404, Published 200.

  **Report và evidence:** [Tổng hợp Actual local D04-23](evidence/be-d04/consolidated/actual-local.md#d04-23) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

- [x] **D04-24 — Kiểm trạng thái sau áp dụng và khả năng phục hồi.**

  **Thực hiện:** Kiểm error log, outbox pending/failed, worker/scheduler, receipt/file access và thay đổi dữ liệu trong cửa sổ nghiệm thu tối thiểu10 phút từ command đầu ở D04-23.

  **Hoàn thành khi:** Không có lỗi mới liên quan D04; mọi outbox nghiệm thu đã processed hoặc có lỗi được xử lý và chạy lại case đạt. Không có event trùng, orphan link hoặc receipt mất; storage/private access đúng. Có log đầu/cuối cửa sổ và xác nhận version đang chạy. Nếu phát hiện sai quyền/mất dữ liệu/side effect trùng, dừng rollout tiếp, phục hồi theo runbook và quay lại test bản sửa trước khi áp dụng lại; không đánh dấu đạt trong lúc còn sự cố.

  **Bằng chứng:** [Tổng hợp Actual local D04-24](evidence/be-d04/consolidated/actual-local.md#d04-24) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

  **Kết quả:** 02/11/2026 · PASS trên local Docker Compose (`tutorlink`), quan sát khỏe mạnh liên tục 622 giây từ 07:18:50 đến 07:29:12 ICT. Backend/worker/scheduler giữ cùng application image đã áp dụng; PostgreSQL giữ cùng container/image và healthy suốt cửa sổ. Migration cuối 34 Ran/0 Pending. Health/member APIs 200; member tải file private 200 đúng checksum, outsider 404; ba replay trả 200/201/200 và khớp JSON gốc. Snapshot đầu/cuối và các checkpoint giống nhau; 8 receipt còn nguyên, 7/7 outbox processed một lần, không pending/failed/duplicate, không orphan file link; log filter có 0 lỗi. Runtime interruption trước cửa sổ và lỗi CSRF của observer đã được ghi nhận, khởi động/kiểm tra lại an toàn; không reset/migrate DB.

  **Report và evidence:** [Tổng hợp Actual local D04-24](evidence/be-d04/consolidated/actual-local.md#d04-24) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json).

- [x] **D04-25 — Bàn giao kết quả và hoàn tất checklist.**

  **Thực hiện:** Tạo BE-D04 handover/test report với API/schema delta, source/build, test→actual manifests, evidence index, dữ liệu nghiệm thu đã tạo và trạng thái vận hành.

  **Hoàn thành khi:** D04-01..24 đều đạt; mỗi checkbox dẫn tới evidence thật. Báo cáo phân biệt test/actual, failure lịch sử/kết quả cuối, giới hạn backend/UI và environment đã áp dụng. Liên kết commit/PR nếu đã có; nội dung cập nhật issue sẵn dùng. Giữ receipt/lịch sử nghiệm thu theo policy, không hard-delete Fee/Published chỉ để dọn demo. Không gắn nhãn hoàn tất nếu còn case bắt buộc hoặc bằng chứng chưa có.

  **Kết quả:** 02/11/2026 · PASS. D04-01..24 đều PASS; sau gom evidence, từng bước đã được chuyển sang liên kết tổng hợp theo nhóm. Bốn gói ZIP và manifest đã xác minh hash thành viên; index test/actual và các liên kết tài liệu liên quan đã được cập nhật, kiểm tra. Build/runtime và giới hạn nghiệm thu được mô tả trong handover; actual chỉ là Docker local, không có staging/production hoặc UI claim.

  **Bằng chứng:** [Tổng hợp Actual local D04-25](evidence/be-d04/consolidated/actual-local.md#d04-25) · [ZIP](evidence/be-d04/consolidated/actual-local.zip) · [manifest](evidence/be-d04/consolidated/actual-local.manifest.json) · [test index](evidence/be-d04/test/evidence-index.md) · [actual index](evidence/be-d04/actual/evidence-index.md) · [archive/hash/link validation](evidence/be-d04/consolidated/archive-packages-validation.json).

## 5. Ma trận bằng chứng API/ảnh bắt buộc

Mỗi dòng có ít nhất một bộ **request/response API thật** ở test (D04-19) và một bộ smoke tương ứng ở actual (D04-23), trừ các case race/fault/retention đã ghi rõ chỉ chạy test. Screenshot là bằng chứng trực quan bổ sung hoặc lựa chọn thay cho phần trình bày thao tác; vẫn giữ JSON/status để đối chiếu field/version.

| Case | Thao tác và kết quả phải thấy | Evidence bổ sung cần có |
|---|---|---|
| E01 — Progress | Cả3 route: create201/version1, list đúng metric/value/note/time, PATCH200; value ngoài0..100 trả422 | No-op/stale; audit before-after; ảnh/API Progress sau ghi và sửa |
| E02 — Draft | Create201; list owner Draft/all; detail và PATCH200; Customer không đọc Draft404, query draft/all403 | File_ids omission/[]/null; ảnh/API owner và Customer cùng resource |
| E03 — File | Upload201 đúng purpose, complete200, attach/replace/detach đúng; owner đọc Draft file, member chỉ đọc Published file | Metadata/download responses; checksum file test; sai owner/purpose/pending/membership bị thu hồi |
| E04 — Publish | Publish200, state Published/version+1; replay giữ snapshot; edit/publish key mới409 | Count audit/outbox/receipt và recipient; payload trung tính; ảnh/API Published ở owner/member |
| E05 — Fee | Cả7 route và chuỗi create201→PATCH200→paid200→unpaid200→void200 | Version/reason/metadata/disclaimer; void giữ mark và chiếm unique tháng; ảnh/API Customer thấy khai báo |
| E06 — Validation Fee | Duplicate month409; sai tháng/amount/currency422; state không cho tạo/mark/edit409 | Free amount0/unmarked; biên timezone/activation/tháng nhuận chạy test |
| E07 — Overview | GET200 với counts đủ5 trạng thái, summary null/đúng latest/next và capabilities | Snapshot dữ liệu dùng đối chiếu; không lộ Draft qua latest; ảnh/API Overview |
| E08 — Quyền/lifecycle | Outsider/sai scope404; member mutation403; inactive403; Active/Paused/terminal đúng contract | Case thiếu auth401/CSRF419 qua HTTP; ảnh/API bị từ chối không chứa private body |
| E09 — Retry/no-op | Cặp request gốc/replay, key conflict409, same-value/same-mark200, stale409 | Resource/version/timestamp/audit/outbox/receipt counts; chứng minh no-op khác replay |
| E10 — Transaction/race | Kết quả đúng cho race, lỗi ghi bắt buộc rollback toàn bộ | Chỉ test: process/lock logs + before-after DB; action test không thay evidence HTTP E01–E09 |
| E11 — Delivery/retention | Notification đúng một lần; Fee receipt durable, publish receipt theo TTL | Test: giả lập thời gian/cleanup; actual: worker xử lý event nghiệm thu, không time-travel hoặc purge dữ liệu thật |

Không cần xây UI mới để hoàn tất scope backend. Nếu UI hiện có chưa gọi các API này, dùng công cụ HTTP/session thật và ghi rõ artifact là API evidence. Không đánh dấu UI Workspace đã nghiệm thu chỉ từ ảnh Postman hoặc JSON.

## 6. Lệnh test tham chiếu và cách ghi kết quả

Lệnh dưới chỉ dành cho môi trường test đã kiểm guard ở D04-02. Khi chạy thực tế, lưu stdout/stderr và exit code; thay danh sách test bằng các tệp D04 đã tạo, không ghi kết quả dự kiến thành kết quả thực tế.

```sh
# Chạy các file D04 sau khi chúng đã được tạo, bằng --filter phù hợp hoặc path thực.
docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest --filter=BE_D04 --display-warnings --fail-on-warning

# Toàn bộ backend trên PostgreSQL test.
docker compose -f compose.testing.yml run --build --rm backend-test vendor/bin/pest --display-warnings --fail-on-warning
```

`BE_D04` là quy ước tên/filter cần dùng cho nhóm test mới; phải kiểm số test được chọn >0. Nếu dùng tên khác, cập nhật command theo source thật. D04 override khi có phải được thêm vào lệnh bằng `-f` và ghi đường dẫn trong manifest. Compose test build source vào image; phải rebuild sau thay đổi code vì không có source bind mount. Dùng volume evidence riêng BE-D04 hoặc lưu output trên host; không dựa vào dữ liệu còn trong container `--rm`.

OpenAPI export/lint/parity theo [API guide](../api/README.md), dùng DB export tạm riêng. Không copy các biến môi trường export hoặc lệnh migrate sang shell/container môi trường thực tế. Không chỉnh `openapi.json` bằng tay.

## 7. Quy tắc cập nhật tiến độ

Mỗi lần hoàn tất một mục, đổi đúng checkbox đó thành `[x]` và bổ sung ngay dưới mục: `Kết quả: <ngày giờ + environment + SHA/build + kết luận>; Evidence: <liên kết artifact có thật>`. Mục chưa đủ bằng chứng giữ `[ ]` và ghi việc còn thiếu. Case từng fail phải giữ log lịch sử và chỉ rõ lần chạy cuối thay thế; không xóa failure để tạo cảm giác đã pass từ đầu.

Các chặng chuyển tiếp: **D04-20 đạt → bắt đầu actual; D04-24 đạt → tổng hợp bàn giao; D04-25 đạt → hoàn tất BE-D04 theo scope trong plan.**
