# BE-D02 B4 — Bàn giao Issue #69

**Ngày:** 27/10/2026

**Issue:** #69 — BE-D02 Program CRUD, quyền và snapshot hợp đồng

**Nhánh:** `be-d02`

**Trạng thái:** Implementation và evidence B1–B3 đã có; B4 hoàn tất đối chiếu tài liệu và chuẩn bị bàn giao. Issue chưa được comment/cập nhật và chưa có PR.

## 1. Tóm tắt bàn giao

BE-D02 hiện có API tạo, liệt kê, đọc, sửa Program Draft; gửi và đọc Invitation; Accept Invitation; và quản lý lifecycle Program. Contract OpenAPI được sinh từ Scramble, khớp các route Laravel đã đăng ký; Catalog và Database Design đã được đồng bộ với implementation/migration.

Bằng chứng kiểm thử chi tiết nằm trong [B3 test report](BE-D02-B3-test-report.md). Report đó ghi nhận 50 test/325 assertion pass trên PostgreSQL, gồm bốn race test; SQLite có 46 test/272 assertion pass và skip bốn race test chỉ chạy trên PostgreSQL. Hai nhóm race phụ thuộc writer ngoài BE-D02 vẫn là release gate; toàn bộ backend suite chưa chạy.

## 2. Thay đổi API

Các route sau đã đăng ký và được đánh dấu **Đã đăng ký** trong [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md):

| Method/path | Hành vi đã bàn giao |
| --- | --- |
| `GET /api/v1/programs` | Liệt kê Program trong phạm vi actor, có phân trang. |
| `POST /api/v1/programs` | Tạo Draft cho Customer đích sau khi kiểm tra quyền và điều kiện Tutor/Listing. |
| `GET /api/v1/programs/{program}` | Đọc Program trong scope; Customer chỉ đọc sau khi là member. |
| `PATCH /api/v1/programs/{program}` | Sửa offer Draft với `expected_version`; từ chối stale version và khóa khi Invitation còn hiệu lực. |
| `POST /api/v1/programs/{program}/invitations` | Gửi snapshot bất biến với `expected_version` và `Idempotency-Key`; hết hạn cũ được chốt trong transaction trước khi gửi lại. |
| `GET /api/v1/invitations` | Đọc inbox/outbox theo scope; trạng thái quá hạn được tính theo server time. |
| `GET /api/v1/invitations/{invitation}` | Đọc snapshot theo scope, kể cả trước khi Customer trở thành member; không trả Draft. |
| `POST /api/v1/invitations/{invitation}/accept` | Accept nguyên tử: Invitation, Program, membership, audit, outbox và idempotency receipt. |
| `POST /api/v1/programs/{program}/pause` | Chuyển Active sang Paused. |
| `POST /api/v1/programs/{program}/resume` | Chuyển Paused sang Active. |
| `POST /api/v1/programs/{program}/complete` | Hoàn tất khi không còn Session scheduled hoặc Task chưa terminal. |
| `POST /api/v1/programs/{program}/cancel` | Hủy Draft/Active/Paused theo điều kiện; trả blocker thay vì cascade hủy Session/Task. |

OpenAPI có **60 operations** tổng cộng; 12 method/path BE-D02 ở trên xuất hiện trong artifact. Artifact hiện tại được sinh lại từ code bằng Scramble trên database SQLite tạm và khớp byte với `docs/api/openapi.json`. `php scripts/verify-openapi-routes.php` xác nhận route parity: **60 operations**. Điều này xác nhận method/path, không thay thế bằng chứng về quyền hoặc nghiệp vụ; xem test report cho hành vi.

### Điểm tra cứu code

- Routes và controller: [api.php](../../backend/routes/api.php), [ProgramController.php](../../backend/app/Http/Controllers/Api/V1/ProgramController.php), [InvitationController.php](../../backend/app/Http/Controllers/Api/V1/InvitationController.php), [InvitationAcceptController.php](../../backend/app/Http/Controllers/Api/V1/InvitationAcceptController.php).
- Actions: [CreateProgramDraft.php](../../backend/app/Actions/Programs/CreateProgramDraft.php), [UpdateProgramDraft.php](../../backend/app/Actions/Programs/UpdateProgramDraft.php), [SendInvitation.php](../../backend/app/Actions/Invitations/SendInvitation.php), [AcceptInvitation.php](../../backend/app/Actions/Invitations/AcceptInvitation.php), [TransitionProgram.php](../../backend/app/Actions/Programs/TransitionProgram.php).
- Test evidence: [ProgramApiTest.php](../../backend/tests/Feature/ProgramApiTest.php), [InvitationApiTest.php](../../backend/tests/Feature/InvitationApiTest.php), [InvitationAcceptTest.php](../../backend/tests/Feature/InvitationAcceptTest.php), [ProgramLifecycleApiTest.php](../../backend/tests/Feature/ProgramLifecycleApiTest.php), [InvitationAcceptConcurrencyTest.php](../../backend/tests/Feature/InvitationAcceptConcurrencyTest.php), [NotificationOutboxWriterTest.php](../../backend/tests/Feature/NotificationOutboxWriterTest.php).

## 3. Thay đổi schema

Không có migration mới ở B4. Migration BE-D02 [2026_10_26_212524_add_invitation_acceptance_support_tables.php](../../backend/database/migrations/2026_10_26_212524_add_invitation_acceptance_support_tables.php), được thêm trong B1, cung cấp:

- `listing_moderation_holds`, gồm partial unique index bảo đảm tối đa một hold đang hoạt động cho mỗi Listing và constraint trạng thái restore trên PostgreSQL.
- `idempotency_receipts`, với unique `(actor_id, idempotency_key)` và index `expires_at`; hiện runtime BE-D02 dùng cho send/Accept theo contract.
- `verification_documents.expires_at`; dữ liệu legacy `active` được chuyển thành `approved`, vocabulary được giới hạn theo trạng thái đích trên PostgreSQL.

[Database Design](../Architecture%20%26%20Technical%20Design/Database%20Design%20%E2%80%94%20TutorLink.md) đã cập nhật để phản ánh các thay đổi đó. Test report chỉ chứng minh hành vi trên database kiểm thử cô lập; chưa xác nhận migration đã được áp dụng trên staging hoặc production.

## 4. Bằng chứng và cách tái kiểm tra

| Kiểm tra | Kết quả | Chi tiết |
| --- | --- | --- |
| BE-D02 tests trên PostgreSQL 16 dùng một lần | **50 pass / 325 assertions / 0 fail hoặc skip** | Bao gồm Accept-vs-Accept, Accept-vs-lazy-expiry, Accept-vs-cancel Draft và Accept-vs-suspend Tutor. |
| Cùng nhóm tests trên SQLite `:memory:` | **46 pass / 272 assertions / 4 skip / 0 fail** | Bốn skip là race test phụ thuộc row locking và nhiều PostgreSQL session. |
| OpenAPI so với routes Laravel | **Pass — 60 operations** | Chạy trong `backend/`: `php scripts/verify-openapi-routes.php`. |
| Artifact OpenAPI | **Khớp export Scramble** | Export vào file tạm từ code và schema đã migrate, so sánh byte với `docs/api/openapi.json`. |
| `git diff --check` | **Pass** | Không có whitespace error trong các thay đổi đang xét. |
| Redocly lint | **Chưa xác nhận** | `npx` không truy cập được `registry.npmjs.org` (`EAI_AGAIN`); không có kết quả lint để báo pass/fail. |

Ma trận tiêu chí → test/assertion, command chạy nhóm test và cách tạo race fixture nằm trong [B3 test report](BE-D02-B3-test-report.md). Nhóm test ở đó chưa phải toàn bộ backend suite.

## 5. Giới hạn và việc cần phối hợp

1. **Accept-vs-moderation-hold:** BE-D02 kiểm tra và từ chối khi hold đã tồn tại; chưa có moderation-hold writer để kiểm chứng cạnh tranh hai writer.
2. **Complete/cancel-vs-Session/Task write:** lifecycle chặn khi blocker đã tồn tại; writer Session/Task chưa thuộc phạm vi BE-D02 nên race này chưa được kiểm chứng.
3. **Các API chưa nằm trong implementation:** public Invitation reject/cancel và worker tự expire chưa được triển khai. GET trả trạng thái hết hạn hiệu lực; PATCH/send chốt Invitation quá hạn trong transaction.
4. **Môi trường triển khai:** chưa xác nhận migration đã chạy trên staging/production.
5. **Kiểm thử phát hành:** chưa chạy toàn bộ backend suite; chạy `php artisan test --compact` trước khi merge/release theo quy trình repo. Hai nhóm race ở mục 1–2 cần được đóng khi writer tương ứng được triển khai.
6. **PR và issue:** hiện chưa có PR. Các commit B1–B3 trên nhánh `be-d02` là `3c07f79`, `b272d2c`, `3e582f9`. Issue #69 chưa được cập nhật; draft comment dưới đây chỉ để review và người dùng sẽ tự đăng.

## 6. Draft comment cho Issue #69 — CHƯA ĐĂNG

> BE-D02 đã triển khai Program CRUD (create/list/detail/update), gửi và đọc Invitation, Accept Invitation cùng các transition pause/resume/complete/cancel. OpenAPI được sinh từ Scramble và route parity đạt 60 operations; API Route Catalog và Database Design đã được đồng bộ. Bằng chứng B3: 50 test/325 assertion pass trên PostgreSQL (gồm 4 race test); SQLite 46 test/272 assertion pass, 4 race test PostgreSQL-only được skip. Chi tiết: `docs/handover/BE-D02-B3-test-report.md`; bàn giao API/schema và giới hạn: `docs/handover/BE-D02-B4-handover.md`. Còn release gate: full backend suite chưa chạy; race Accept-vs-moderation-hold và complete/cancel-vs-Session/Task write cần writer ngoài BE-D02; migration trên staging/production chưa xác nhận. Chưa có PR; comment này được chuẩn bị để review trước khi đăng.

## 7. Tài liệu tham chiếu

- [Contract giai đoạn A](BE-D02-phase-A-contract.md)
- [Roadmap BE-D02](BE-D02-roadmap.md)
- [B3 test report](BE-D02-B3-test-report.md)
- [API contract README](../api/README.md)
- [Business Rules](../Domain%20Documentation/Business%20Rules.md), BR-18, BR-23, BR-25, BR-26
