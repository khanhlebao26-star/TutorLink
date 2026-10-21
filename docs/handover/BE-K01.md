# BE-K01 — Bàn giao Identity

- Owner: Khanh
- Reviewer: Hoàng
- Branch: `be-k01`
- Phạm vi: M01; đồng bộ trạng thái liên quan Tutor Profile, Admin và Marketplace.
- Trạng thái: Đã triển khai phần chính; đang hoàn thiện nghiệm thu và bàn giao.

## 1. Phạm vi và quyết định đã thống nhất

- SPA xác thực bằng Sanctum cookie session.
- Customer và Tutor đăng ký thành công không tự đăng nhập; người dùng chuyển sang trang login.
- Customer từ đủ 15 tuổi; Tutor từ đủ 18 tuổi.
- `PATCH /api/v1/me` chỉ cập nhật `full_name`.
- Email có quy trình thay đổi riêng, không sửa qua `/me`.
- Người dùng không tự sửa role, status, permissions hoặc `email_verified_at`.
- UserStatus và TutorProfileStatus độc lập.
- PAT, token abilities và expiry PAT được hoãn khỏi MVP theo quyết định team. Các yêu cầu PAT trong issue gốc không được tính là đã triển khai.

## 2. Tóm tắt thay đổi code

### 2.1. Tích hợp Fortify với API hiện có

Các file chính trong backend:

- `composer.json`, `composer.lock`: bổ sung Fortify.
- `bootstrap/providers.php`: đăng ký `FortifyServiceProvider`.
- `app/Providers/FortifyServiceProvider.php`: đăng ký các action tạo User, cập nhật tên, reset và đổi mật khẩu; tắt route tự sinh bằng `Fortify::ignoreRoutes()`.
- `config/fortify.php`: dùng guard `web`, tắt view; không bật 2FA/passkey trong luồng MVP.
- `app/Http/Controllers/Api/V1/AuthController.php`: gọi các contract action Fortify từ endpoint hiện có, giữ response JSON và các URL SPA.

Đăng ký phát `Registered` event một lần, trả User resource với HTTP 201 và không tạo trạng thái đăng nhập.

### 2.2. Chuẩn hóa dữ liệu User và validation

- `app/Actions/Fortify/CreateNewUser.php`: tạo User bằng `full_name`, `password_hash`, role, ngày sinh và `status=active`; dùng lại validation đăng ký.
- `app/Http/Requests/RegisterRequest.php`: chỉ nhận role customer/tutor; kiểm tra tuổi theo role và xác nhận mật khẩu.
- `app/Http/Requests/UpdateMeRequest.php`: allow-list chỉ có `full_name`.
- `app/Actions/Fortify/UpdateUserProfileInformation.php`: chỉ cập nhật dữ liệu tên đã validation; bỏ luồng đổi email mặc định.
- `app/Http/Requests/ResetPasswordRequest.php`: giữ `password_confirmation` trong dữ liệu validated để action kiểm tra lại.
- `app/Http/Requests/UpdatePasswordRequest.php`: khai báo payload đổi mật khẩu và validation tối thiểu.

Model User tiếp tục dùng cột `password_hash` với cast `hashed`; không tạo cột password mới.

### 2.3. Rate limit và CSRF

- `app/Providers/AppServiceProvider.php`: định nghĩa limiter:
  - Login: 5 request/phút theo email chuẩn hóa kết hợp IP.
  - Login: thêm giới hạn tổng 20 request/phút theo IP.
  - Register: 3 request/phút theo IP.
- `routes/api.php`: gắn `throttle:login` và `throttle:register` đúng endpoint.
- `FortifyServiceProvider`: bỏ định nghĩa limiter login trùng.
- Giữ Sanctum stateful middleware và cơ chế CSRF cho mutation SPA.

Các ngưỡng trên là cấu hình hiện tại; reviewer cần xác nhận phù hợp môi trường triển khai.

### 2.4. Đồng bộ trạng thái

- `app/Http/Middleware/EnsureActiveAccount.php`: chỉ cho User active thực hiện các thao tác được bảo vệ.
- `AuthController`: từ chối login khi User không active.
- `AdminController`: dùng trạng thái mới để xét duyệt, suspend/restore và tính `allowed_actions`.
- `TutorProfileController`: lưu draft, gửi submitted, cho chỉnh sửa ở draft/changes_requested.
- `TutorProfileResource`: trả trực tiếp `status`, bỏ field `approval_status`.
- `ServiceListing`, `CreateListingRequest`, các controller Listing/Public Tutor/Review và seeder Marketplace: kiểm tra hồ sơ approved thay cho approval_status active.
- `FileController`: kiểm tra quyền chỉnh sửa hồ sơ theo status mới.
- `AvailabilityRuleController`: xác định đình chỉ bằng status; giữ quy tắc riêng cho quản lý lịch cá nhân.

Không thay đổi enum cùng tên của Listing hoặc Catalog.

### 2.5. Thu hồi phiên và đổi mật khẩu

- `app/Actions/Auth/RevokeUserSessions.php`: xóa database session theo user_id; hỗ trợ giữ lại một session ID.
- `ResetUserPassword`: đổi password_hash, xoay remember_token và thu hồi mọi phiên.
- `UpdateUserPassword`: kiểm tra mật khẩu hiện tại, đổi password_hash, xoay remember_token, thu hồi phiên khác và xoay ID phiên hiện tại.
- `AuthController::resetPassword()`: hủy phiên hiện tại nếu request đang đăng nhập đúng tài khoản vừa reset; không logout nhầm tài khoản khác.
- `AdminController::changeAccountStatus()`: suspend User đồng thời xoay remember_token và thu hồi phiên trong transaction cùng thay đổi trạng thái/audit.
- `.env.docker.example`: chuyển session driver sang database.
- `tests/TestCase.php`, `phpunit.xml`: cấu hình database session cho kiểm thử.

### 2.6. Frontend

- `lib/data/types.ts`: dùng enum User/Tutor Profile mới; bỏ TutorApprovalStatus và approval_status.
- `lib/data/services.ts`, `mock.ts`: đồng bộ API service và dữ liệu mô phỏng.
- `app/admin/page.tsx`: filter mặc định submitted; badge/action theo trạng thái mới.
- `components/tutor-onboarding-form.tsx`: khóa chỉnh sửa theo status; hiển thị đúng hồ sơ suspended.
- `app/settings/page.tsx`: nhãn UserStatus mới.
- `components/auth-form.tsx`: giới hạn ngày sinh và hướng dẫn theo Customer 15/Tutor 18.

## 3. API delta

| API                                | Hành vi                                                                                                 |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------- |
| POST `/api/v1/auth/register`       | Kiểm tra tuổi theo role; không tự đăng nhập; thành công trả 201                                         |
| POST `/api/v1/auth/login`          | Chỉ User active; tạo cookie session, xoay session ID; áp dụng limiter                                   |
| POST `/api/v1/auth/logout`         | Hủy phiên hiện tại, đổi CSRF; trả 204                                                                   |
| PATCH `/api/v1/me`                 | Chỉ cập nhật full_name                                                                                  |
| PUT `/api/v1/me/password`          | Nhận current_password, password, password_confirmation; giữ phiên hiện tại, thu hồi phiên khác; trả 204 |
| POST `/api/v1/auth/reset-password` | Thu hồi mọi phiên sau reset thành công                                                                  |
| GET `/api/v1/admin/tutor-profiles` | Filter dùng enum mới; mặc định submitted; bản nháp không thuộc hàng chờ                                 |

Response Tutor Profile sử dụng `status`; frontend không còn phụ thuộc `approval_status`.

Không bổ sung endpoint phát hành/list/revoke PAT.

## 4. Schema delta và mapping

Migration:

`backend/database/migrations/2026_10_20_075315_align_user_and_tutor_profile_statuses.php`

### UserStatus

| Cũ        | Mới         |
| --------- | ----------- |
| active    | active      |
| inactive  | suspended   |
| cancelled | deactivated |

Dữ liệu ngoài mapping làm migration dừng để kiểm tra, không tự chuyển thành active.

Giá trị mới: active, suspended, deactivated; mặc định active.

### TutorProfileStatus

| Dữ liệu cũ                                    | Status mới        |
| --------------------------------------------- | ----------------- |
| pending, chưa submit, không suspended         | draft             |
| pending, đã submit, không suspended           | submitted         |
| changes_requested, đã submit, không suspended | changes_requested |
| active, đã submit, không suspended            | approved          |
| active, đã submit, có suspended_at            | suspended         |
| rejected, đã submit, không suspended          | rejected          |

Thêm `tutor_profiles.status`, default draft và index. PostgreSQL có constraint enum; SQLite có nhánh tạo schema/mapping phục vụ CI.

Các timestamp phục vụ lịch sử vẫn được giữ. Cột legacy approval_status còn trong schema nhưng không còn được code ứng dụng sử dụng.

Email verification tiếp tục suy từ email_verified_at; không thêm cột trạng thái riêng. RegisterRole chỉ là giới hạn đầu vào, không tạo cột riêng.

## 5. Chính sách session và trạng thái

| Sự kiện               | Chính sách                                                                |
| --------------------- | ------------------------------------------------------------------------- |
| Logout                | Hủy phiên hiện tại                                                        |
| Reset mật khẩu        | Hủy mọi phiên và vô hiệu remember-cookie cũ                               |
| Đổi mật khẩu          | Giữ phiên hiện tại, xoay session ID; hủy phiên khác và remember-cookie cũ |
| Suspend User          | Hủy mọi phiên, chặn đăng nhập                                             |
| Restore User          | Không phục hồi credential cũ; phải đăng nhập mới                          |
| Suspend Tutor Profile | Chỉ approved → suspended; không hủy session tài khoản                     |
| Restore Tutor Profile | Chỉ suspended → approved; không mở khóa User đang bị suspend              |

## 6. Kiểm thử và evidence

### Kết quả kiểm tra mới nhất — 21/10/2026

Kết quả dưới đây được ghi từ output lệnh do Khanh cung cấp trong phiên làm việc; không phải một lần chạy lại khi cập nhật báo cáo.

| Kiểm tra | Kết quả |
| --- | --- |
| IdentitySecurityTest — CSRF và verify | 6 passed, 0 failed, 15 assertions; 2,21 giây |
| Toàn bộ backend trên tutorlink_test | 102 passed, 0 failed, 2 skipped, 532 assertions; 15,14 giây |
| Frontend ESLint | Đạt; không báo lỗi |
| Frontend TypeScript (`tsc --noEmit`) | Đạt; không báo lỗi |
| Export OpenAPI từ SQLite và copy về repository | Thành công |
| OpenAPI route parity | Đạt — 43 operations |
| OpenAPI lint — Redocly CLI 2.60.0 | Hợp lệ; 0 errors, 49 warnings |
| GitHub CI | Chưa có kết quả xác nhận trong báo cáo |

Schema `components.schemas.UpdatePasswordRequest` đã có đủ ba field bắt buộc: `current_password`, `password`, `password_confirmation`. Endpoint `PUT /me/password` tham chiếu schema này và mô tả response 204, 401, 422.

Các warning lint gồm thiếu license, server localhost, thiếu operation summary và thiếu mô tả response 4xx ở một số API. Không tính warnings là errors hoặc tuyên bố tài liệu không còn cảnh báo.

### Các mốc kiểm tra trước

- Baseline trước thay đổi: 84 passed, 2 skipped, 362 assertions.
- Bộ test thu hồi phiên: 7 passed, 123 assertions.
- Regression đã xác nhận trước các thay đổi bảo mật/OpenAPI mới nhất: 96 passed, 2 skipped, 517 assertions.

Các mốc này được giữ để đối chiếu tiến độ; dùng bảng kết quả mới nhất phía trên cho lần bàn giao hiện tại.

### Coverage đã bổ sung

- Tuổi Customer/Tutor tại mốc đủ tuổi và thiếu một ngày.
- Rate limit login/register.
- Login/logout bằng cookie.
- Đổi password giữ A, thu hồi B và session ID cũ.
- Reset thu hồi mọi phiên và không logout nhầm user khác.
- Suspend/restore không khôi phục credential cũ.
- Thu hồi đúng user và rollback khi audit thất bại.
- IdentitySecurityTest bổ sung CSRF thiếu/sai/đúng token và verify sai chữ ký/hết hạn/sai hash email.

IdentitySecurityTest đã chạy đạt cả sáu case: CSRF thiếu/sai/đúng token và verify sai chữ ký/hết hạn/sai hash email.

### Lệnh kiểm tra

```powershell
docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=tutorlink_test backend php artisan test --filter=IdentitySecurityTest
docker compose exec -e DB_CONNECTION=pgsql -e DB_DATABASE=tutorlink_test backend php artisan test
docker compose exec frontend npm run lint
docker compose exec frontend npx tsc --noEmit
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/tutorlink-be-k01-openapi.sqlite backend php artisan scramble:export --no-interaction
docker compose cp backend:/var/www/docs/api/openapi.json ./docs/api/openapi.json
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=/tmp/tutorlink-be-k01-openapi.sqlite backend php scripts/verify-openapi-routes.php /var/www/docs/api/openapi.json
npx --yes @redocly/cli@2.60.0 lint docs/api/openapi.json --config .redocly.yaml
```

Lệnh export/parity trên dùng database SQLite tạm đã migrate và thư mục đích có sẵn trong container. Không áp dụng các lệnh reset database lên tutorlink. Cần chạy lại kiểm tra thích hợp nếu code hoặc artifact thay đổi sau mốc này.

### Evidence còn cần đính kèm

- Lưu output regression, IdentitySecurityTest, frontend và OpenAPI tương ứng với bảng kết quả mới nhất vào file evidence đã làm sạch.
- Link kết quả GitHub CI sau khi push.
- Số lượng User/Tutor Profile trước và sau migration dữ liệu cũ.
- Ảnh Network xác nhận SPA cookie và X-XSRF-TOKEN, đã che giá trị nhạy cảm.
- Evidence luồng Admin/Tutor sau migration.

## 7. Known limitations

- PAT, ability và expiry PAT ngoài MVP; không tuyên bố đã đạt các acceptance criteria PAT của issue gốc.
- Chưa xây workflow deactivated; đã có trạng thái và kiểm tra từ chối truy cập.
- Migration không hỗ trợ rollback tự động; cần backup hoặc reverse migration được review.
- SQLite chưa áp dụng constraint enum tương đương PostgreSQL.
- Hai test W3 được skip do phần Program lifecycle chưa hỗ trợ fixture completed; không tính là pass.
- Hai migration 2FA/passkey sinh từ Fortify còn cần review phạm vi và lịch sử áp dụng; không coi là tính năng đã bàn giao.
- Test CSRF middleware không thay thế kiểm tra frontend gửi X-XSRF-TOKEN thực tế.
- Cần nghiệm thu riêng register không auto-login và bearer token đơn lẻ bị từ chối nếu chưa có evidence tương ứng.
- OpenAPI lint hiện có 49 warnings; chưa xử lý hết chất lượng mô tả API.
- Response 204 của `/me/password` vẫn sinh `content: application/json` trong OpenAPI; cần sửa khai báo response ở source/cấu hình Scramble và export lại để mô tả đúng response không có body.

## 8. Việc còn lại trước khi Ready for review

- [x] Sửa handler đổi password dùng UpdatePasswordRequest.
- [x] Chạy IdentitySecurityTest và toàn bộ regression tại mốc kết quả mới nhất.
- [x] Kiểm tra ESLint và TypeScript.
- [x] Export lại OpenAPI sau thay đổi request; lint hợp lệ với 49 warnings, route parity đạt 43 operations.
- [ ] Sửa mô tả response 204 không có body và export/kiểm tra lại OpenAPI.
- [ ] Hoàn thiện evidence migration và trình duyệt.
- [ ] Review migration ngoài scope và diff trước commit.
- [ ] Commit/push trên be-k01.
- [ ] Cập nhật issue với quyết định hoãn PAT.
- [ ] Mở PR, gắn issue và yêu cầu Hoàng review.
- [ ] Xác nhận CI xanh.
