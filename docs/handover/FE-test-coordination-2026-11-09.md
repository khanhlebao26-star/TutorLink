# Điều phối kiểm thử FE — 09/11/2026

Baseline: `5a0e722`, branch `codex/2w-fe-integration`. Ba chat đã hoàn tất vòng đầu. Kết quả local; chưa phải CI, merge, reviewer sign-off hoặc Gate FE Pass. Không thay đổi API/schema/code nghiệp vụ trong vòng kiểm thử này.

## Phân công ba chat hiện có

| Chat | Phạm vi | Báo cáo |
| --- | --- | --- |
| FE1 - Chat test FE trang Tutor | Tutor auth/onboarding/file/polling; E2E cross-role invitation → Session → Task → complete/review | [FE1](evidence/fe-test-2026-11-09/FE1-tutor.md) |
| FE2 — Kiểm thử FE trang khách hàng | Customer contracts/workspace/collaboration; account register/verify/reset qua Mailpit; guest gate | [FE2](evidence/fe-test-2026-11-09/FE2-customer.md) |
| FE3 – Test trang Admin | Filament/Blade approval, permission, reports/evidence/audit/monitoring; Admin browser | [FE3](evidence/fe-test-2026-11-09/FE3-admin.md) |

Root giữ runtime, lint/typecheck/build/coverage chung và tổng hợp. Mỗi lane giữ browser context/artifacts riêng. Không reset/reseed DB dùng chung giữa các lượt browser, không sửa source nghiệp vụ hoặc gửi Gmail/Slack. Vòng đầu chỉ sửa tối thiểu test harness/config khi cần; findings tĩnh phải ghi rõ chưa runtime reproduce.

## Runtime

- Môi trường chính: FE `http://localhost:3000`, API `http://localhost:8000`. Người dùng tự khởi động Docker/container. FE trả 200, CSRF 204. Không seed/reset/migrate DB chính trong đợt này.
- Môi trường E2E: FE `http://localhost:3001`, API `http://localhost:8001`, Mailpit `http://localhost:8026`.
- Dùng cùng Compose project `tutorlink`, bổ sung `test-db`, `backend-fe-test`, `frontend-fe-test`, `mailpit-fe-test`; DB test dùng tmpfs và không publish cổng PostgreSQL.
- DB `test-db/tutorlink_test`, user `tutorlink_test_runner`: `scripts/test-console.php check` đã xác minh identity; migrations và `FrontendDemoSeeder` exit 0.
- `.env` thật được che bằng `docker/fe-testing.env`; test API không nhận credentials Gmail/Slack. SMTP chỉ trỏ tới Mailpit test. Storage và `.next` tách khỏi runtime chính.
- Docker Desktop hiện tại cần thêm bridge `default` cho API/Mailpit mới publish được các cổng host; DB vẫn chỉ nối mạng `testing` internal. Sau sửa, FE 200, CSRF 204, Mailpit API 200.

Khởi động lại các service test đã có (không seed/reset tự động):

```sh
docker compose -p tutorlink -f docker-compose.yml -f compose.workers.yml -f compose.testing.yml -f compose.fe-testing.yml up -d --no-build test-db backend-fe-test frontend-fe-test mailpit-fe-test
```

Runtime test dùng image/dependency volumes local đã có; không phải cấu hình CI độc lập. Bản build đóng gói trước đây không được dùng làm chứng cứ code mới; source hiện tại được mount cho test server.

Playwright dùng `E2E_BASE_URL=http://localhost:3001`, `E2E_API_URL=http://localhost:8001`, `E2E_MAILPIT_URL=http://localhost:8026`, `E2E_PASSWORD=Demo-pass-2026` (public fixture password), `E2E_BROWSER_CHANNEL=chrome`. `E2E_ARTIFACT_DIR` phải riêng mỗi lane/lượt, dưới `/private/tmp/tutorlink-fe-test-20261109/`.

## Shared checks

| Check | Kết quả hiện tại | Evidence |
| --- | --- | --- |
| ESLint | Pass, exit 0 | `/private/tmp/tutorlink-fe-lint-20261109.log` |
| TypeScript baseline | Fail, TS2769: `exact` không thuộc `ByRoleOptions` trong hai assertion của `workspace-form.test.tsx` | `/private/tmp/tutorlink-fe-typecheck-20261109.log` |
| TypeScript sau sửa test harness | Pass, exit 0; FE2 dùng regex tên nút giữ ý nghĩa exact-match | `/private/tmp/tutorlink-fe-typecheck-after-20261109.log` |
| Production build | Pass, exit 0; bản sao source trong `/private/tmp/tutorlink-fe-build-20261109`, dependencies symlink local, API-mode; không chạm `.next` runtime | `/private/tmp/tutorlink-fe-build-20261109.log` |
| Unit/RTL toàn FE + coverage | Fail, exit 1; 29 pass / 1 fail trong 30 tests, 8 files. Case giữ draft khi 409 ném `ApiClientError: Changed`; FE2 đang phân loại nguyên nhân. Không chứng nhận coverage >70% từ run thất bại. | `/private/tmp/tutorlink-fe-coverage-20261109.log` |
| Admin/Filament/API permission regression | Pass, 89 tests / 572 assertions, exit 0. Sáu file: AdminFrontend, SharedAdminActions, TutorProfileApprovalWorkflow, AdminApprovalAudit, TrustWorkflowApi, Monitoring. DB thứ hai `tutorlink_phase2_test` được guard xác minh, tách khỏi fixture E2E. | `/private/tmp/tutorlink-admin-regression-clean-20261109.log` |
| Browser/API thật | Vòng đầu hoàn tất, Partial/Fail; guest và Admin permission smoke đạt, hai full journey chưa pass. | Artifacts và báo cáo theo từng lane |

9 tests pass của FE1 nằm trong tổng 30 tests nói trên, không cộng hai lần. Source test, rà tĩnh và báo cáo lịch sử không thay thế evidence runtime hiện tại. Test/browser đang dùng dev server; production build pass riêng không có nghĩa E2E trên production server đã pass.

Lần Admin regression đầu có 88 pass / 1 fail vì process thừa hưởng `ADMIN_PANEL_ENABLED=true` của browser server, trong khi SharedAdminActionsTest kiểm default false. Chạy lại riêng process với `ADMIN_PANEL_ENABLED=false` theo giả định suite cho kết quả 89 pass; không sửa assertion và không thay flag của browser server. Các test hiện có không bao phủ mọi finding tĩnh mới, đặc biệt Admin chưa verified truy cập monitoring.

Kết quả browser vòng đầu đã quan sát: guest workspace gate pass; account register/verify đi được qua Mailpit nhưng điều hướng sau login chưa đạt assertion; journey cross-role đã accept Invitation #1 / Program #2 trước khi dừng ở Tutor login; Admin đã gửi approve và UI hiển thị trạng thái đã duyệt nhưng assertion thông báo thất bại. Không full-rerun trên các fixture đã mutate, không suy toàn journey pass từ trạng thái từng bước. Xem báo cáo lane để phân loại nguyên nhân và trace.

Chẩn đoán FE1 login-only trong context mới sau đó vào `/profile` trong 1.739 giây; login và `/me` đều 200. Cold compilation của Next dev là giả thuyết, chưa kết luận app login hỏng hoặc full journey đạt. FE3 xác nhận guest redirect, Customer/Tutor 403 ở Admin, Admin dashboard và assigned Report evidence truy cập được. FE3 sửa locator E2E từ toast sang trạng thái `Đã duyệt`; mới xác nhận collection, chưa rerun action với fixture pending mới.

Logs shared checks đã được copy vào `evidence/fe-test-2026-11-09/`; [JUnit Admin](evidence/fe-test-2026-11-09/admin-regression-junit.xml) giữ kết quả 89 tests. Trace/screenshot browser nằm dưới `/private/tmp/tutorlink-fe-test-20261109/`, đường dẫn cụ thể trong từng report.

## Finding và việc tiếp theo theo owner

- FE1: hai khoảng trống validation onboarding tìm từ source (claim trống, submit thiếu identity); chưa browser reproduce. Cần invitation pending mới và môi trường E2E production/warm route để kiểm tra tiếp Session/Task/submission/complete/review, không full-rerun fixture #1 đã accept.
- FE2: Customer Profile còn placeholder/chưa có luồng API thực (static finding); conflict 409 RTL vẫn fail chưa phân loại; account E2E đi được qua register/verify rồi chưa đạt điều hướng sau login, chưa đến reset password. Cần đối chiếu contract Profile với owner API và chẩn đoán các lỗi trên.
- FE3: monitoring gate thiếu kiểm tra verified (static, current passing suite chưa có case); Admin thiếu link Telescope/Pulse được xác nhận trên browser. Cần pending profile mới để rerun locator đã sửa, cùng browser cases assignment/revoke/closure và monitoring unverified.
- Root: kiểm thử lại phần bị ảnh hưởng sau sửa, hoàn tất E2E/coverage từng codebase và các case Not tested. Chưa cấp Gate FE Pass hoặc đóng issue; chưa có commit/push/CI/reviewer sign-off.

## Còn phải chốt

- Thu kết quả E2E và bug có bước tái hiện/expected/actual từ ba lane.
- Giữ riêng lỗi môi trường, lỗi test harness và lỗi ứng dụng; không nới assertions để báo Pass.
- Chạy lại phần bị ảnh hưởng sau mỗi sửa được chấp thuận; cập nhật checklist coverage/permission/responsive/keyboard theo evidence thực tế.
- Các service test và artifacts được giữ để tái hiện. Khi kết thúc có thể stop đúng bốn service test; không dùng `down -v` lên project chung.
