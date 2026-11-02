# BE-H04 — Review, Report và Audit giới hạn dữ liệu

Ngày: 02/11/2026. Branch `codex/2w-be-h04`, base `8a34aa2` sau pull BE-K04/BE-D04. Backend local/test; chưa commit/push/PR, chưa triển khai database ứng dụng hoặc production. Reviewer: Dũng.

## API và schema

Thêm 17 operations theo API Route Catalog; OpenAPI tổng cộng **122**, không mất path nền. Có Review GET/POST theo Program; Admin Review list/detail/hide/restore; Report POST, reporter list/detail, Admin list/detail/assignment/evidence/resolve/dismiss; Audit GET list/detail. Tất cả qua session actor active hiện có; không thêm API sửa/xóa Review hoặc Audit.

- Review chỉ Customer đích còn active membership trong Program completed; một Review/Program/Customer. Create dùng UUID Idempotency-Key/receipt 30 ngày; cùng payload trả snapshot, key khác trên Review đã có trả 409. Rating JSON integer 1–5; comment optional/null, nếu có phải không trắng và tối đa 2.000 ký tự. Moderator cần `admin.reviews.moderate`, expected_version và reason; stale/state conflict 409; giữ nội dung. Visibility/version cùng audit transaction.
- Public Review list và aggregate chỉ tính visible, hợp lệ với Program completed và historical Customer membership. Nhãn `Customer đã xác minh` thay tên Customer; default 20/max 50; sort created_at/id giảm dần. Owner Customer vẫn xem hidden Review của mình, không thấy moderation reason. Admin moderator xem lịch sử hide/restore giới hạn đúng Review.
- Report POST dùng reason_code/description, UUID key và receipt 30 ngày; current scope kiểm trước replay. Resource allow-list tutor_profile/listing/message/program/review; không cấp quyền đọc resource từ việc report. Duplicate open key mới trả 409, quota 10/giờ và 30/ngày trả 429 + Retry-After; replay không tiêu quota. Description tối đa 2.000 ký tự, reason other yêu cầu không trắng.
- Admin Report view/decide/assign là quyền riêng. Assignment nhận assigned_admin_id nullable, assignment_version và reason; target active Admin có view+decide; stale trả 409. GET scope=mine chỉ assigned summary; scope=unassigned/all chỉ coordinator có assign, chỉ ID/status/assignee/version. Resolve/dismiss yêu cầu current assignment, decide, assignment_version/resolution_note; state, decision metadata, audit và neutral reporter outbox nguyên tử. Không tự moderate resource.
- Evidence endpoint kiểm active actor + view + current assignment + open dưới row lock, audit mỗi lần đọc, private/no-store. Message window là tin được báo cáo + tối đa 10 trước/5 sau cùng Conversation, sort created_at/id. Chỉ ID/body/time, không sender identity, file/object key hoặc message ở chat khác. Loại khác project field allow-list và lịch sử moderation liên quan; nguồn mất trả evidence_unavailable. Sau close hoặc reassign, evidence bị từ chối từ request tiếp theo; kết luận vẫn đọc trong scope riêng. Report assignment không mở quyền tải File chung.
- Audit reader cần `admin.audit_logs.view`; scope hiện hỗ trợ assigned Report, assigned Tutor Profile và Review nếu đồng thời có moderate. Resource không có scope mapping bị từ chối mặc định. DTO chỉ actor/action/resource, reason/request_id/time và status/state/visibility enum + version integer hợp lệ; raw JSONB, nested values/private content không được trả. Read append audit.read. Các event H02/D04 hiện có không bị sửa/xóa.
- Migration H04 thêm Review visibility/version/check/index; map Report pending/reviewing → open và đổi default/check; partial UNIQUE reporter/resource WHERE open, index quota. Preflight chặn status lạ/null và duplicate open trước DDL; không tự xóa hoặc đoán dữ liệu. Unique Review đã có được tái sử dụng. Down yêu cầu reverse migration được review để bảo toàn dữ liệu.
- Retention Audit giữ 24 tháng như K02. outbox:purge thêm Report resolved/dismissed quá 24 tháng từ handled_at, không xóa open. Purge cần hai quyền retention cũ và `admin.reports.purge`, ghi report.purge trong transaction; thiếu quyền mới thì bỏ qua Report. Thêm hằng permission không cấp quyền tự động cho Admin hiện có.

## Kiểm thử đã chạy

Các lượt dùng PostgreSQL `tutorlink_test` trên service test-db, có guard environment/database/host/connected identity. Không dùng database `tutorlink` của ứng dụng.

- [Focused H04 + public Review regression](evidence/be-h04/focused-postgres.txt): **30 test tập trung, 263 assertions đạt** ở source cuối (H04, marketplace và token metadata), bao gồm comment whitespace và error envelope. Bao gồm HTTP flow Review/visibility/replay, validation/outsider, Report quota giờ/ngày, assignment stale, evidence đúng cửa sổ/thu hồi/close, coordinator metadata, Audit masking nested JSONB và append-only API, fault audit rollback, retention, migration preflight unknown/null/duplicate, cùng marketplace tests.
- `TrustWorkflowConcurrencyTest`: independent PHP processes, parent giữ row lock và kiểm pg_blocking_pids trước khi nhả. Double Review khác key → 201/409, cùng key → 201/200, một Review/audit; concurrent assignment → 200/409; evidence-vs-reassignment giữ kết quả serial hợp lệ và request kế tiếp của Admin cũ bị từ chối.
- [Regression nền không gồm BeD04](evidence/be-h04/regression-postgres.txt): **636 test, 4.641 assertions đạt**; là snapshot trước một số bổ sung H04 cuối, được bổ sung bằng focused log cuối, không giả làm full-suite sau mọi edit.
- [D04 fixture](evidence/be-h04/d04-fixture.txt) + [D04 current-schema regression](evidence/be-h04/d04-regression-postgres.txt): **77 test, 1.077 assertions đạt**. [D04 race fixture](evidence/be-h04/d04-race-fixture.txt) + [D04 race](evidence/be-h04/d04-race-postgres.txt): **1 test, 893 assertions đạt**.
- [Lượt mixed-suite chưa chuẩn bị D04](evidence/be-h04/full-postgres-unprepared-d04.txt): 56 failure / 665 pass. Phần lớn D04 cần fixture/schema legacy riêng; một marketplace assertion cũ default 5 đã được sửa thành 20 theo BR-24 và kiểm lại focused/regression. Không dùng log này để tuyên bố full suite đạt. Bảy test migration preflight D04 cần schema trước D04 và chưa chạy lại trong lượt H04; không chạy chúng trên schema đã D04/H04 rồi coi là regression nghiệp vụ.
- Error envelope riêng H04 chuẩn BR-26 cho validation/403/404/405/419/500, giữ request_id và không trả debug SQL/trace. Route ID giới hạn số; API khác giữ renderer hiện có.
- [Export](evidence/be-h04/openapi-export.txt), [parity](evidence/be-h04/openapi-parity.txt), [lint](evidence/be-h04/openapi-lint.txt). Lint hợp lệ, 59 warnings như baseline; không khẳng định zero-warning. Artifact được sinh từ source, không sửa JSON tay. Pint tracked/new files logs nằm cùng thư mục evidence.

## Export và tái lập

D04 migration yêu cầu PostgreSQL; SQLite export cũ hiện lỗi ngay ở migration D04. H04 cho phép guarded PostgreSQL trong test-console openapi:export và đổi CI export sang test PostgreSQL; vẫn kiểm toàn bộ safety/connected identity, không mở export trên app/production DB. Thêm annotation metadata cho GET /me/tokens để PostgreSQL schema inference không làm mất item DTO; không đổi token runtime. Các path cũ giữ nguyên.

Trong container PHP 8.3 với source/vendor hiện hành và env `compose.testing.yml`:

```sh
vendor/bin/pest tests/Feature/TrustWorkflowApiTest.php tests/Feature/TrustWorkflowConcurrencyTest.php tests/Feature/MarketplaceCloseoutTest.php
vendor/bin/pest --filter '/^(?!.*BeD04)/'
php scripts/test-console.php d04:seed
vendor/bin/pest --filter '/BeD04(?!MigrationPreflight|ConcurrencyRace)/'
php scripts/test-console.php d04:seed
vendor/bin/pest tests/Feature/BeD04ConcurrencyRaceTest.php
php scripts/test-console.php openapi:export
php scripts/verify-openapi-routes.php
```

Không chạy các nhóm đồng thời trên cùng DB. Bảy preflight D04 phải có clone schema trước D04 như bàn giao D04. Lệnh vendor/bin/pest không chuẩn bị fixture/schema theo nhóm hiện chưa phải quy trình all-green duy nhất; CI test step chung cũng cần được tách/chuẩn bị tương ứng trước khi coi CI toàn dự án sẵn sàng. H04 không xóa test hay bỏ database guard để che lỗi này.

## Giới hạn và bước tiếp theo

- Backend/source/tests sẵn cho review trong phạm vi evidence nêu trên; chưa chứng nhận UI, staging/production, tải thực tế hoặc migration legacy production. Không cấp chứng nhận triển khai chỉ từ OpenAPI hay source migration.
- Audit scopes không có nguồn assignment rõ ràng bị deny-by-default; không mở toàn bộ log cho mọi Admin. Scope mở rộng phải được thiết kế và test riêng.
- Test mới kiểm outbox transaction và event uniqueness, không diễn tập lại transport Slack/notification delivery của K02 hoặc WebSocket K03.
- Cần reviewer kiểm mapping dữ liệu Report legacy, policy provision sáu permission mới và tài khoản purge. Chưa có migration ứng dụng, cấp quyền thật, comment issue, commit/push hay PR.
- Cần chuẩn hóa harness/CI test toàn dự án theo các nhóm D04 trước khi công bố CI xanh. Đây là giới hạn kiểm thử tích hợp hiện có; không gộp các số test nhóm rồi gọi là một lượt full suite.
