# FE3 Admin — vòng kiểm thử đầu

- **Ngày:** 09/11/2026, Asia/Ho_Chi_Minh
- **Baseline:** commit `5a0e722`, branch `codex/2w-fe-integration`.
- **Phạm vi:** Filament Admin, dashboard, hồ sơ và quyết định, Report/evidence, Audit, quyền Telescope/Pulse; browser chạy trên runtime cô lập được điều phối cung cấp.
- **Kết quả:** guest redirect, Customer/Tutor bị từ chối, Admin vào dashboard và xem Report/evidence đều đạt qua browser. Test duyệt hồ sơ đã thực hiện thao tác thành công nhưng runner báo fail vì assertion chờ toast không bền; đã sửa locator để kiểm tra trạng thái nghiệp vụ. Backend focused regression do root chạy đạt 89/89 test, 572 assertions. Không chạy lại thao tác vì fixture pending đã được duyệt.
- **Artifacts:** `/private/tmp/tutorlink-fe-test-20261109/FE3/` (JUnit, HTML report, screenshot, video, trace).
- **Working tree:** lúc bắt đầu sạch. Các lane khác/root thay đổi thêm file trong shared checkout khi FE3 đang chạy; FE3 chỉ sửa một locator trong `frontend/e2e/workspace.spec.ts` và báo cáo này. Không sửa source nghiệp vụ, không reset/reseed DB.

## Lệnh và kết quả

| Lệnh / bước | Exit | Ghi chú |
| --- | ---: | --- |
| `git status --short --branch` (đầu lượt) | 0 | Branch đúng, working tree sạch tại baseline. |
| `git rev-parse --short HEAD` | 0 | `5a0e722`. |
| Static source review qua `rg`, `sed`, `nl` | 0 | Đọc Filament resources/page, middleware/actions, backend Admin và monitoring tests. |
| Hai invocation đầu dùng `workspace.spec.ts` / regex có dấu cách | 1 | Playwright trả `No tests found`; không mở browser, không tác động fixture. |
| Chrome trong sandbox | 1 | Chrome abort `SIGABRT` trước khi test chạy. |
| `npx playwright install ffmpeg` | 0 | Cài helper ffmpeg vào cache Playwright để giữ video artifact. |
| `npm run test:e2e -- e2e/workspace.spec.ts --grep=admin` | 1 | 1 test chạy. Login và thao tác duyệt hồ sơ hoạt động; assertion toast ở dòng 157 fail. Screenshot/DOM snapshot ghi trạng thái hồ sơ `Đã duyệt`. |
| Read-only Playwright smoke, context mới cho từng role | 0 | Guest tới trang login; Customer/Tutor 403; Admin dashboard 200 và Report evidence section hiển thị. GET evidence ghi audit `evidence.read` theo thiết kế. |
| `npm run test:e2e -- e2e/workspace.spec.ts --grep=admin --list` (sau sửa locator) | 0 | Thu thập được đúng 1 test Admin approval; không thực thi test/không đổi fixture. |
| Backend Pest focused regression, 6 file (root-run) | 0 | 89 tests, 572 assertions, 7.26s. Log: `/private/tmp/tutorlink-admin-regression-clean-20261109.log`; JUnit: `/tmp/tutorlink-admin-regression-clean-junit.xml`. DB test thứ hai `tutorlink_phase2_test`, guard xác nhận; không reset DB browser `tutorlink_test`. Lượt đầu 88 pass / 1 fail do `ADMIN_PANEL_ENABLED=true` kế thừa từ browser; lượt sạch override biến này riêng process và đạt 89 pass. |
| `git diff --check` | 0 | Không có whitespace error trong tracked diff tại lượt kiểm tra. |
| `command -v php` | 1 | Host không có PHP CLI; không chạy backend Pest trên host. |

Runtime được điều phối xác nhận dùng API `localhost:8001`, FE `localhost:3001`, Mailpit `localhost:8026`, PostgreSQL `tutorlink_test` với guard đạt. Backend suite chạy trên `tutorlink_phase2_test` riêng; browser fixture không bị reset hoặc reseed. Không dùng database `tutorlink` của app, Gmail hoặc Slack.

## Ma trận kiểm tra

| Case | Trạng thái | Bằng chứng / giới hạn |
| --- | --- | --- |
| Guest vào Admin | **PASS** | Browser request đến `/admin/operations?section=dashboard` kết thúc ở `/admin/login` (HTTP 200 sau redirect). |
| Customer/Tutor vào Admin | **PASS** | Browser fresh context cho mỗi role; cả hai nhận HTTP 403 tại `/admin/operations`. |
| Admin login và dashboard | **PASS** | Admin đăng nhập qua FE, trang Filament dashboard trả 200 và heading hiển thị. Không có lỗi missing Filament assets. |
| Admin Report/evidence | **PASS** | API list trả một Report open được giao cho Admin; trang Operations Report trả 200 và phần evidence hiển thị. Request đọc evidence được audit. |
| Admin duyệt hồ sơ được giao | **PARTIAL — thao tác đạt, test assertion fail** | E2E click `Phê duyệt`; Playwright trace ghi GET `/api/v1/admin/tutor-profiles` và hai POST `/livewire-53ac0bbe/update` đều 200. Screenshot/DOM tại `test-results/workspace-admin-approves-t-2716d-ugh-shared-Filament-actions-chromium/` cho thấy status `Đã duyệt`, các nút duyệt đã biến mất. Test cũ chờ toast `Đã cập nhật` và timeout. Locator đã đổi sang trạng thái kết quả tại `frontend/e2e/workspace.spec.ts:157`; test được collect lại thành công nhưng chưa rerun thao tác vì fixture pending đã chuyển trạng thái, không được reseed khi FE1 chạy. |
| Panel flag off, non-admin và thu hồi quyền dashboard | **PASS — backend feature regression** | `AdminFrontendTest.php:31-48` đạt trong focused suite; chưa có browser test riêng cho thu hồi quyền giữa phiên. |
| Blade assigned profile, XSS escaping, CSRF, `no-store` | **PASS — backend feature regression** | `AdminFrontendTest.php:50-58` đạt; browser UI chưa chạy case riêng này. |
| Profile approve/request-changes/reject và assignment qua Filament | **Partial — source only ngoài duyệt** | `Operations` gọi `RunAdminOperation`, rồi shared actions; approve đã thử trực tiếp. Assignment/request changes/reject chưa kiểm tra browser. |
| Document approve/reject, quyền download tách biệt | **PASS — API regression; UI not tested** | `TutorProfileApprovalWorkflowTest.php` đạt trong focused suite; FE3 Livewire document controls chưa exercise trên browser. |
| Report revoke khi reassignment/closure | **PASS — backend feature regression** | `AdminFrontendTest.php:83-90` và `TrustWorkflowApiTest.php:49-80` đạt; browser đọc evidence Report hiện được giao. Chưa thử đóng Report qua UI. |
| Review hide giữ nguyên nội dung, audit/version | **PASS — Livewire feature regression** | `AdminFrontendTest.php:93-104` đạt; đây là HTTP/Livewire test, không phải browser automation. |
| Tutor profile/account suspend và restore | **PASS — API regression; UI not tested** | `TutorProfileApprovalWorkflowTest.php` và `AdminApprovalAuditTest.php` đạt; không suspend tài khoản FE1 dùng chung. |
| Audit DTO masking và append-only | **PASS — backend feature regression** | `AdminFrontendTest.php:106-114` và H04 API tests đạt trong nhóm regression. |
| Dashboard aggregate/permission scope | **PASS — backend feature regression** | `SharedAdminActionsTest.php:121-134` đạt; dashboard page cũng mở được bằng browser. |
| Telescope/Pulse auth parity | **PARTIAL — FE3-ADMIN-01 còn mở** | Các monitoring cases hiện có đạt; suite không có case email chưa xác minh nên static authorization gap vẫn chưa được xử lý hoặc tái kiểm tra. |
| Link Admin sang Telescope/Pulse | **FAIL — FE3-ADMIN-02** | Browser screenshot xác nhận navigation chỉ có Dashboard, Audit, Blade và tài nguyên Filament; không có link Telescope/Pulse. |
| Browser test suite | **Partial** | Chạy một Admin approval flow và smoke read-only theo role; các case còn lại chưa chạy. |

## Findings

### FE3-ADMIN-01 — P2: Monitoring gate không khớp điều kiện Admin verified

- **File/line:** `backend/app/Providers/MonitoringServiceProvider.php:67-78`; đối chiếu `backend/app/Support/AdminAuthorization.php:9-14` và `backend/app/Support/TrustWorkflow.php:20-25`.
- **Repro tĩnh:** Actor `role=admin`, `status=active`, có `admin.monitoring.view`, `email_verified_at=null`, và phiên đăng nhập còn hiệu lực. Gate Telescope/Pulse chỉ xét soft-delete, `status` và `hasPermission`; `hasPermission` kiểm role/quyền nhưng không kiểm email verification.
- **Expected:** `/telescope` và `/pulse` từ chối Actor chưa xác minh, như panel và Admin API dùng `AdminAuthorization::allows`.
- **Actual theo source:** Gate có thể cho phép Actor đó vào monitoring. Chưa xác nhận HTTP runtime.
- **Evidence gap:** `MonitoringTest.php:37-60` không có case unverified; `SharedAdminActionsTest.php:136-147` xác nhận panel cần Admin verified.
- **Severity:** P2, khác biệt quyền truy cập giữa monitoring và Admin API/panel.

### FE3-ADMIN-02 — P3: Trang Admin không có link Telescope/Pulse

- **File/line:** `backend/resources/views/filament/pages/operations.blade.php:3`.
- **Repro:** Mở Operations dashboard với Admin đủ `admin.monitoring.view`; kiểm tra navigation.
- **Expected:** Có link điều hướng tới `/telescope` và `/pulse` theo phạm vi FE3.
- **Actual:** Chỉ có Dashboard, Audit, Blade; không có hai monitoring link. Đã thấy trên screenshot browser sau thao tác duyệt.
- **Severity:** P3; route trực tiếp còn truy cập được.

### FE3-ADMIN-03 — P3: Assertion E2E chờ toast thay vì trạng thái kết quả

- **File/line:** `frontend/e2e/workspace.spec.ts:157`.
- **Repro:** Chạy test `admin approves the assigned complete fixture through shared Filament actions` trên fixture pending. Sau click, UI đã hiển thị `Đã duyệt` nhưng test chờ `Đã cập nhật` và timeout.
- **Expected:** Test xác nhận trạng thái hồ sơ sau quyết định.
- **Actual:** Locator cũ tạo false negative sau thao tác thành công; trace ghi hai Livewire POST trả 200 và page snapshot ghi `Đã duyệt`. Đã đổi locator sang text `Đã duyệt`; test collection pass, chưa rerun vì fixture đã được duyệt và không được reset/reseed.
- **Severity:** P3, lỗi độ tin cậy test, không phải lỗi action nghiệp vụ.

## Handoff / bước tiếp theo

1. Khi có fixture pending mới trong DB test tách riêng, chạy lại một lần E2E duyệt hồ sơ để xác nhận locator đã sửa; không reseed DB đang dùng chung.
2. Bổ sung test case monitoring Admin chưa verified và kiểm tra `/pulse`, `/telescope` sau khi policy được xử lý; hiện chỉ là finding tĩnh.
3. Bổ sung browser coverage cho assignment, document approve/reject/download permission, Report reassignment/closure, Review hide/restore, suspend/restore và Audit read-only.
4. Backend regression của sáu file `AdminFrontendTest.php`, `SharedAdminActionsTest.php`, `TutorProfileApprovalWorkflowTest.php`, `AdminApprovalAuditTest.php`, `TrustWorkflowApiTest.php`, `MonitoringTest.php` đã đạt. Bước tiếp là bổ sung case monitoring Admin chưa verified và chạy browser case duyệt lại trên fixture pending riêng, không reset fixture đang dùng.
