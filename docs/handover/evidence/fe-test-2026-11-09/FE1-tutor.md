# FE1 Tutor — vòng kiểm thử 1

Ngày: 09/11/2026 (Asia/Ho_Chi_Minh)

## Baseline và giới hạn

- Branch: `codex/2w-fe-integration`
- HEAD lúc bắt đầu: `5a0e722` (`fix mail va van con 1 so loi can fix`), khớp baseline được giao.
- Worktree sạch tại lần kiểm tra đầu.
- Chạy các unit/RTL suite được giao ưu tiên và E2E chỉ sau khi root cấp môi trường cô lập. Không chạy build, lint, typecheck, coverage toàn FE, backend suite, DB seed/reset.
- Không gửi email/Slack, không sửa cấu hình/Compose/shared client/package lock, không sửa code nghiệp vụ, không commit/push.

## Kết quả test

| Nhóm | Trạng thái | Bằng chứng / giới hạn |
|---|---|---|
| Auth/onboarding | **Pass** | `auth-onboarding.test.tsx`: suite nằm trong lần chạy thành công bên dưới; có kiểm tra login lỗi/điều hướng theo role và revision onboarding. Không bao phủ đăng ký và resend verification. |
| File/auth service contracts | **Pass** | `file-auth-contracts.test.ts`: kiểm tra file rỗng/quá 5.000.000 byte bị chặn, payload attach avatar có version, payload reset/signed verification/logout. |
| Polling | **Pass** | `polling.test.tsx`: dừng khi hidden/offline, resume không chồng request, Retry-After và dừng sau 403. |
| Listings/availability | **Not tested** | Không chạy suite riêng trong vòng này. |
| Program/invitations | **Fail (partial journey)** | Journey đã chấp nhận invitation #1 cho Program #2, sau đó dừng ở bước đăng nhập Tutor trước khi tạo Session/Task. Không chạy suite collaboration riêng của FE2. |
| Chat text | **Not tested** | Polling hook pass không xác nhận toàn component hoặc API chat. Rà tĩnh thấy composer chỉ nhận văn bản và giới hạn 4.000 ký tự. |
| Sáu tab workspace, notifications, review/report | **Not tested** | Không chạy suite workspace/contracts/collaboration thuộc lane FE2; chỉ rà tĩnh route/điểm tích hợp được giao. |
| Browser/API E2E | **Fail** | Môi trường cô lập đã được cấp. Journey thất bại tại đăng nhập/điều hướng Tutor; API login và `/me` đều trả 200. Chi tiết ở phần E2E bên dưới. |

Lệnh đã chạy từ `frontend/`:

```text
npm test -- src/test/auth-onboarding.test.tsx src/test/file-auth-contracts.test.ts src/test/polling.test.tsx
Exit code: 0
Test files: 3 passed (3)
Tests: 9 passed (9)
Duration: 2.30s
```

Vitest in cảnh báo `vite-tsconfig-paths` có thể được thay bằng hỗ trợ `resolve.tsconfigPaths` của Vite; cảnh báo không làm suite thất bại.

## Browser/API E2E

- Lần gọi đầu với grep `^real recipient` trả `No tests found` (exit 1), chưa mở browser hoặc gọi API mutation. Đã giữ artifact ở `/private/tmp/tutorlink-fe-test-20261109/FE1-initial-no-tests/`.
- Lần chạy mặc định tiếp theo, Chrome bị `SIGABRT`/`EPERM` trước khi test bắt đầu (exit 1). Root cho phép chạy lại với quyền browser/localhost; trace lỗi khởi chạy được giữ ở `/private/tmp/tutorlink-fe-test-20261109/FE1-browser-launch-blocked/`.
- Lệnh test đã chạy sau đó (dùng `E2E_*` environment do root cung cấp):

```text
npm run test:e2e -- workspace.spec.ts --grep=real
Exit code: 1
1 test failed: real recipient invitation -> sessions -> submission -> feedback -> completed review
Failure: e2e/workspace.spec.ts:15, gọi từ line 56 (login Tutor); 24.7s
```

- Trace ghi nhận Customer login `POST /api/v1/auth/login` 200, `/api/v1/me` 200; `POST /api/v1/invitations/1/accept` 200; Program #2 tải được. Sau đó Tutor login cũng trả 200 và `/api/v1/me` trả 200, nhưng assertion vẫn thấy `/auth/login`; lần điều hướng đầu tới `GET /profile` chưa có response trước khi assertion timeout.
- Chạy chẩn đoán login-only trong context mới, không lặp journey: Tutor login và `/me` trả 200, `GET /profile` trả 200, URL sang `/profile` trong 1.739s, không có console error. Trace giữ tại `/private/tmp/tutorlink-fe-test-20261109/FE1-login-diagnostic/trace.zip`. Điều này gợi ý điều hướng Next dev lần đầu bị compile chậm, nhưng chưa chứng minh nguyên nhân; không nới assertion và chưa tính full journey là pass.
- **Fixture đã bị mutate:** invitation #1 chuyển qua accept, dẫn tới Program #2. Test dừng trước khi tạo Session, Task, submission, feedback hoặc review. Không chạy lại full journey trên DB này; cần root cấp pending invitation mới theo targeted fixture.

## Rà tĩnh và findings

Các route/điểm nối được rà tĩnh gồm profile/onboarding, upload tài liệu, availability, listing của Tutor, Program/invitation, messages, notifications, public reviews và reports. Đây không phải xác nhận runtime/API hoặc nghiệm thu UI trên browser.

1. **[P2] Tên bằng cấp/chứng chỉ trống lọt qua kiểm tra phía FE.** `frontend/src/components/tutor-onboarding-form.tsx:165-183, 553` chỉ kiểm tra `experience_years` ở bước 3; form có `noValidate` (dòng 506), trong khi nút thêm claim tạo `name: ""` (dòng 553). `draftInput` gửi nguyên `claims` (dòng 154-162). Backend yêu cầu `qualification_claims.*.name` ( `backend/app/Http/Requests/TutorProfileRequest.php:21-24`). **Tái hiện theo code:** ở bước 3 thêm bằng cấp/chứng chỉ, để trống tên rồi bấm Tiếp theo. **Actual suy ra từ code:** FE gửi request lưu draft; backend trả validation error, nhưng ô tên claim không có phần hiển thị field error riêng. **Expected:** chặn request và gắn lỗi dễ thấy vào ô tên trước khi lưu.

2. **[P3] Có thể gửi hồ sơ dù chưa có giấy tờ định danh hợp lệ.** `frontend/src/components/tutor-onboarding-form.tsx:358-381` kiểm tra các trường hồ sơ và avatar nhưng không kiểm tra identity card/passport. Backend yêu cầu tài liệu định danh; regression backend xác nhận trường hợp tài liệu không phải định danh bị từ chối tại `backend/tests/Feature/TutorProfileApprovalWorkflowTest.php:93-100`. **Tái hiện theo code:** hoàn tất các trường và avatar, bỏ qua upload giấy tờ ở bước 4 rồi bấm gửi tại bước 5. **Actual suy ra từ code:** FE gọi save/submit; API trả 422 rồi FE mới chuyển về bước tài liệu. **Expected:** báo thiếu giấy tờ và không gọi submit trước khi đủ giấy tờ định danh.

Hai finding trên là suy luận từ static code và validation hiện có, không phải browser repro. E2E có assertion điều hướng Tutor thất bại như mô tả trên; chưa phân lập được lỗi nghiệp vụ FE vì login API và login-only warm-route chẩn đoán đều thành công.

## Theo dõi worktree dùng chung

Ở lần kiểm tra thứ hai, sau baseline ban đầu, các thay đổi sau đã xuất hiện song song trong checkout; lane FE1 không sửa hoặc đọc nội dung các file đó:

- `frontend/src/test/workspace-form.test.tsx` — modified
- `compose.fe-testing.yml` — untracked
- `docker/fe-testing.env` — untracked

Ở snapshot sau E2E, cũng thấy các cập nhật điều phối/FE2/FE3 đang được làm đồng thời (`docs/README.md`, `frontend/e2e/workspace.spec.ts`, `frontend/playwright.config.ts`, `docs/handover/FE-test-coordination-2026-11-09.md`) và các artifact riêng `FE2-account`, `FE2-guest`, `FE3` dưới `/private/tmp/tutorlink-fe-test-20261109/`. FE1 không sửa hoặc ghi đè các nội dung đó.

## Việc tiếp theo

1. FE1 xử lý/định người nhận hai finding validation onboarding và thêm test RTL riêng nếu cần.
2. Khi root cấp invitation pending mới hoặc fixture riêng sau mutation, chạy tiếp full journey trong browser/API context riêng; giữ output riêng FE1.
3. Sau đó mới đánh giá các nhóm Not tested. Không suy ra Pass từ test hook hoặc rà tĩnh.
