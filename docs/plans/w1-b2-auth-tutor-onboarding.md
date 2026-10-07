# Kế hoạch triển khai W1-B2 — Auth và Tutor onboarding

**Assignee:** Dũng  
**Epic:** B01, B03, B04  
**Sprint:** W01  
**Fix Version:** G1  
**Timebox:** 1,5 ngày làm việc (giả định 12 giờ)  
**Dependency:** W1-B1 và API từ W1-A2  
**Reviewer:** Hoàng  
**Branch:** `feature/W1-B2-auth-tutor-onboarding`  
**Trạng thái tài liệu:** Đã audit lại sau khi merge W1-A2 tại `532ee40`;
capability Auth/File/Profile đã được tích hợp, chỉ giữ blocker review transition
và browser evidence.

## 1. Mục tiêu

Tích hợp frontend Next.js với API Laravel thật cho toàn bộ vòng đời xác thực của
Customer và Tutor, sau đó hoàn thiện Tutor onboarding gồm chọn specialization,
upload avatar, upload giấy tờ xác minh và submit hồ sơ xét duyệt.

Kết quả của issue phải kiểm chứng được trên browser bằng cookie/session thật,
không dùng mock làm bằng chứng nghiệm thu.

## 2. Hiện trạng project đã kiểm tra

| Khu vực | Hiện trạng | Ảnh hưởng tới W1-B2 |
| --- | --- | --- |
| Git/dependency | Nhánh hiện tại `feature/W1-B2-auth-tutor-onboarding` đang ở merge commit `532ee40`; W1-A2 contract bổ sung nằm ở `f1aad0c`. | W1-B1 và phần API đã bàn giao của W1-A2 đều có trong checkout. Các thay đổi W1-B2 hiện vẫn chưa commit. |
| Frontend shell | Đã có route Auth/Profile, API client, kiểu dữ liệu, component nền và mode `mock/api`. | Mở rộng lớp service mỏng hiện có; không xây lại shell hoặc data architecture. |
| Backend runtime | Đã có Auth register/login/logout/verify/resend/forgot/reset, `/me`, Catalog, File upload/complete/link/download/delete và Tutor profile get/save/submit. `User` đã bật email verification. | Có thể bắt đầu Auth và phần Tutor flow đã có contract; không cần endpoint tạm hoặc mock. |
| Sanctum/CORS | `withCredentials`, XSRF, stateful API và CORS cho frontend `:3000` đã có nền cấu hình. | Dùng thống nhất `http://localhost:3000` và `http://localhost:8000` theo tài liệu setup; không trộn với `127.0.0.1`. |
| OpenAPI hiện có | Đã mô tả các route trên và là hợp đồng API chính để frontend bám theo. Đã cập nhật `date_of_birth` bắt buộc và nested `profile` để khớp runtime `/me`. | Frontend bám OpenAPI đã cập nhật; nếu backend thay đổi wire shape phải cập nhật contract trước khi đổi UI. |
| Tutor data | Contract W1-A2 hiện nhận/trả `specialization_id`, có target avatar và endpoint tạo verification document với `document_type`. Migration `000700` đã được áp dụng; Catalog đã seed trong Docker. | Frontend đã có thể hoàn tất specialization và `upload -> complete -> attach`; không cần tự đặt endpoint. |
| Trạng thái | Runtime chỉ trả `status: draft|submitted` và `approval_status: pending|active|rejected|cancelled|expired`; profile đã submit luôn bị khóa. | Chưa có `changes_requested`, feedback, `allowed_actions` hoặc resubmit; frontend không tự suy đoán transition. |
| File policy | `POST /files` nhận multipart trực tiếp, tối đa 10 MiB; frontend giới hạn avatar 2 MiB/JPG/PNG và document 10 MiB theo UX policy. | Upload/complete/attach đã có contract; backend vẫn là lớp kiểm tra cuối cùng. |

## 3. Understanding summary và giả định

- Customer và Tutor cùng dùng Auth flow, nhưng chỉ Tutor đi tiếp vào onboarding.
- Backend là nguồn sự thật cho session, role, validation, trạng thái hồ sơ và
  quyền được sửa/submit.
- Frontend hiển thị action theo trạng thái hoặc `allowed_actions` do API trả về;
  việc khóa form phía client không thay thế authorization phía backend.
- Upload là flow nhiều bước `upload -> complete -> attach`; mỗi bước phải dùng
  response thật của bước trước, không giữ một `file_id` giả ở frontend.
- Evidence chỉ hợp lệ khi chạy `NEXT_PUBLIC_DATA_MODE=api` (hoặc cấu hình API
  tương đương đã được W1-B1 chốt).
- Không đưa password, session cookie, XSRF token, signed upload URL hoặc nội dung
  giấy tờ xác minh vào log, screenshot, Issue hay PR.
- Timebox chỉ bắt đầu khi W1-A2 đạt Definition of Ready; thời gian chờ dependency
  không tính vào 1,5 ngày triển khai.

### Giả định phi chức năng

| Nhóm | Giả định dùng cho W1-B2 |
| --- | --- |
| Hiệu năng/quy mô | Khoảng 5.000 tài khoản và 50 người dùng đồng thời theo tài liệu kế hoạch trước; không làm load test trong issue này. Form không tạo request theo từng phím gõ. Catalog tải một lần khi mở onboarding. |
| Bảo mật và riêng tư | Sanctum first-party SPA cookie; không lưu access token trong `localStorage`; file validation phía client chỉ hỗ trợ UX, backend vẫn kiểm tra MIME/kích thước/quyền sở hữu. |
| Độ tin cậy | Không tự động retry mutation, complete hoặc attach. Khi lỗi, giữ dữ liệu form và cho người dùng chủ động thử lại bước an toàn. |
| Bảo trì | Tận dụng Axios, React state và component từ W1-B1; không thêm form library, query library hoặc global state library trong timebox. Dũng bàn giao flow submit cho Hoàng. |

## 4. Definition of Ready cho dependency

Chỉ bắt đầu tính 1,5 ngày triển khai sau khi các mục sau có câu trả lời hoặc API
chạy được. Nếu thiếu, ghi blocker vào Issue thay vì tự tạo contract tạm.

### W1-B1 — đã bàn giao

- Commit nền đã xác nhận: `ffc67df`.
- API client tách API origin và `/api/v1`; CSRF gọi
  `/sanctum/csrf-cookie` ở origin.
- Có mode API thật và không tự fallback sang mock khi request lỗi.
- Audit hiện tại: `npm run lint` và
  `npx tsc --noEmit --incremental false` đạt. `npm run build` cần chạy lại
  sau khi xử lý quyền sở hữu cache `.next` được tạo bởi container; đây là
  preflight local, không mở rộng phạm vi W1-B2.

### W1-A2 — audit sau merge

- [x] Có endpoint và implementation cho register, login, logout, verify, resend
  verification, forgot/reset password và `/me`.
- [x] Có Catalog endpoint trả category/specialization đang hoạt động.
- [x] Có Tutor profile get/save draft/submit; profile đã submit bị backend khóa.
- [x] Có upload multipart, complete, download/delete và generic file link.
- [x] Backend feature test hiện có đạt `14 passed (47 assertions)` trong Docker.
- [x] OpenAPI đã thêm `date_of_birth` vào danh sách bắt buộc của register và
  mô tả nested `profile` trong User response.
- [x] Tutor profile contract nhận và trả specialization đã chọn.
- [x] Có contract gắn avatar vào Tutor profile bằng `PUT /tutor/profile/avatar`.
- [x] Có endpoint/payload tạo verification document với `document_type` bằng
  `POST /tutor/profile/verification-documents`.
- [ ] Có state/transition cho `changes_requested`, `approved`, `rejected`,
  feedback và resubmit. Hiện runtime chỉ hỗ trợ draft/submit và khóa vĩnh viễn
  sau submit.
- [ ] OpenAPI mô tả nested `profile` của `/me`, lỗi 419 và correlation/request ID
  nếu đây là contract bắt buộc.
- [ ] Có dữ liệu/cách chuyển đủ trạng thái review và xác nhận CORS, session,
  CSRF, mail verification/reset bằng browser thật.

Quyết định thực thi: các capability Auth/Catalog/Profile/File/attach đã có
contract và đã được tích hợp. Chỉ còn review transition/evidence là dependency;
không dùng endpoint giả hoặc mock để đánh dấu acceptance là Done.

## 5. Phạm vi đã scale cho 1,5 ngày

### Bắt buộc hoàn thành

1. Register Customer và Tutor bằng API thật, có `password_confirmation` và các
   field bắt buộc theo W1-A2.
2. Login, logout, email verification, resend verification, forgot/reset
   password và bootstrap session qua `/me`.
3. Điều hướng theo role: Customer vào khu vực Customer; Tutor chưa hoàn tất vào
   onboarding; Tutor đã submit/approved vào trang trạng thái phù hợp.
4. Tutor xem/sửa draft gồm field profile và specialization từ Catalog.
5. Upload avatar và verification document theo đúng
   `upload -> complete -> attach`.
6. Hiển thị validation theo field và thông báo rõ cho 401, 419, 422.
7. Hiển thị đủ `Draft`, `Submitted`, `Changes Requested`, `Approved`, `Rejected`.
8. Khóa toàn bộ thao tác sửa/submit ở `Submitted`; mở lại ở
   `Changes Requested` và cho phép resubmit.
9. Browser evidence bằng API thật, đã che dữ liệu nhạy cảm.

### Chi tiết phụ thuộc contract W1-A2

- Gửi/nhập `document_type` khi request schema bắt buộc; không tự thiết kế taxonomy
  ngoài contract.
- Hiển thị lý do hoặc field cần sửa khi `Changes Requested`/`Rejected`.

### Ngoài phạm vi

- Admin approval/reject UI; Hoàng chỉ nhận Tutor submit để test phía Admin.
- Backend endpoint, mail template, queue, object storage lifecycle hoặc virus
  scanning.
- Marketplace listing, scheduling, messaging và Program.
- Crop/resize ảnh, drag-and-drop nâng cao, upload song song hoặc resume upload.
- Progress bar upload và quản lý file nâng cao.
- Tự động refresh/retry request ghi dữ liệu sau 419.
- Đổi toàn bộ kiến trúc data layer hoặc thêm dependency chỉ để xử lý form.

## 6. Phương án triển khai

| Phương án | Mô tả | Đánh giá |
| --- | --- | --- |
| **A — Chọn: mở rộng service/API layer mỏng của W1-B1** | Mỗi capability có hàm API rõ ràng; page/component giữ state cục bộ; thêm adapter lỗi và upload flow nhỏ. | Ít file và dependency nhất, phù hợp 12 giờ, dễ đối chiếu Network. |
| B — Auth context và onboarding state machine toàn cục | Tập trung session và mọi transition vào provider/reducer. | Có ích khi nhiều route cùng ghi state, nhưng vượt nhu cầu hiện tại và tăng rủi ro hydration. |
| C — Sinh client từ OpenAPI và dùng query/form library | Type an toàn và cache chuẩn hóa. | Chỉ hợp lý khi W1-A2 đã ổn định và team đã chọn toolchain; không phù hợp timebox. |

Chọn phương án A. Chỉ tách logic dùng chung thật sự: API error, session bootstrap,
field error và upload step. Không tạo abstraction cho nhu cầu giả định.

## 7. Thiết kế flow

### 7.1 Customer

```text
Register(role=customer)
  -> yêu cầu xác minh email
  -> verify/resend nếu cần
  -> login hoặc session do API trả về
  -> GET /me
  -> /marketplace
```

Customer không nhìn thấy Tutor onboarding. Nếu truy cập trực tiếp route
onboarding, frontend điều hướng ra ngoài và backend vẫn phải từ chối API Tutor.

### 7.2 Tutor

```text
Register(role=tutor)
  -> xác minh email
  -> login/session
  -> GET /me
  -> GET Tutor profile
  -> Draft/Changes Requested: cho sửa và submit
  -> Submitted: khóa form
  -> Approved/Rejected: chỉ hiển thị trạng thái
```

Sau submit/resubmit, frontend dùng profile được backend trả về hoặc refetch
profile; không tự gán trạng thái `submitted` trước khi server xác nhận.

### 7.3 State matrix

| Trạng thái UI | Có thể sửa | Có thể upload/attach | Primary action | Hành vi bắt buộc |
| --- | :---: | :---: | --- | --- |
| `Draft` | Có | Có | Submit | Cho lưu draft và hiển thị lỗi field. |
| `Submitted` | Không | Không | Không có | Disable/read-only field, không chỉ ẩn nút submit. |
| `Changes Requested` | Có | Có | Resubmit | Hiển thị feedback từ Admin nếu API trả về; giữ hồ sơ hiện tại để sửa. |
| `Approved` | Không | Không | Không có trong issue | Chỉ hiển thị trạng thái thành công; không tự mở form sửa. |
| `Rejected` | Không | Không | Không có trong issue | Chỉ hiển thị trạng thái; không tự coi là `Changes Requested`. |

Nếu wire value khác nhãn trên, tạo một mapping duy nhất trong data layer sau khi
W1-A2 xác nhận. Không rải phép so sánh trạng thái ở nhiều component.

### 7.4 Upload `upload -> complete -> attach`

```text
Chọn file
  -> kiểm tra UX: purpose, MIME, size
  -> POST multipart binary + purpose vào /api/v1/files
  -> gọi POST /api/v1/files/{file}/complete
  -> attach file đã complete vào avatar hoặc verification document
  -> refetch/render attachment từ profile response
```

State tối thiểu của một upload field:

```text
idle -> preparing -> uploading -> completing -> attaching -> attached
                                      \-> error <-/
```

Quy tắc:

- Không gọi `attach` nếu `complete` chưa thành công.
- Không đánh dấu UI hoàn tất ngay sau khi request multipart upload xong.
- Khi attach lỗi, giữ identifier của file đã complete trong state hiện tại để
  người dùng retry attach; không upload lại binary một cách im lặng.
- Khi người dùng chọn file mới, hủy state hiển thị của file cũ nhưng không tự
  xóa object/file server nếu contract chưa quy định cleanup.
- Avatar và verification document dùng cùng orchestration, khác `purpose`,
  validation và attach endpoint/payload.
- File content, cookie và XSRF header không được ghi vào console hoặc evidence.

## 8. API/data layer cần triển khai

Endpoint dưới đây bám OpenAPI/backend đã merge. Capability chưa có contract vẫn
được giữ dưới dạng blocker, không tự đặt path.

| Nhóm | Capability |
| --- | --- |
| Auth | `register`, `login`, `logout`, `verifyEmail`, `resendVerification`, `requestPasswordReset`, `resetPassword`, `getMe` |
| Catalog | `getSpecializations` |
| Tutor profile | `getTutorProfile` (`GET /tutor/profile`), `saveTutorDraft` (`PUT /tutor/profile`), `submitTutorProfile` (`POST /tutor/profile/submit`); resubmit vẫn chờ backend `changes_requested` |
| Files | `uploadFile` (`POST /files` multipart), `completeUpload` (`POST /files/{id}/complete`), `attachTutorAvatar` (`PUT /tutor/profile/avatar`) và `createVerificationDocument` (`POST /tutor/profile/verification-documents`) |

### Chuẩn hóa lỗi

Mở rộng lỗi API của W1-B1 thành một shape tối thiểu:

```ts
type ApiError = {
  status?: number;
  message: string;
  fieldErrors?: Record<string, string[]>;
  requestId?: string;
};
```

| HTTP | UX |
| --- | --- |
| 401 | Báo phiên đăng nhập không còn hợp lệ và hiển thị action đi tới login; không tự redirect ngay làm mất dữ liệu form. |
| 419 | Báo phiên bảo mật/CSRF đã hết hạn, cho nút thử lại. Lần thử lại lấy CSRF cookie mới trước mutation; không tự submit lại. |
| 422 | Gắn `errors[field]` vào đúng input; lỗi không map được hiển thị ở form summary. Focus vào lỗi đầu tiên sau submit. |
| Khác | Hiển thị thông điệp an toàn và `request_id` nếu có; không render raw stack/message nhạy cảm. |

Không dùng response interceptor để tự retry request ghi dữ liệu. Interceptor chỉ
chuẩn hóa lỗi. Adapter phải bám response cuối cùng của W1-A2; contract hiện tại
dùng `message`/`errors` ở top-level trong khi client nền đang đọc
`error.message`, nên không giữ cả hai shape như hai contract song song.

## 9. Route và component dự kiến

Route cuối cùng phải bám convention W1-B1; danh sách dưới là thay đổi tối thiểu:

| Route | Trách nhiệm |
| --- | --- |
| `/auth/register` | Chọn Customer/Tutor và register. |
| `/auth/login` | Login và điều hướng theo `/me`. |
| `/auth/verify-email` | Nhận kết quả verify hoặc hướng dẫn kiểm tra email; resend có cooldown theo response backend. |
| `/auth/forgot-password` | Gửi yêu cầu reset. |
| `/auth/reset-password` | Nhận token/email theo contract và đặt mật khẩu mới. |
| `/profile` | Tutor draft, upload, submit và status view; tái sử dụng route đã có từ W1-B1. |

Component tối thiểu:

- `AuthForm`: hoàn thiện field, password confirmation, field errors và redirect
  theo kết quả `/me`.
- Form verify/resend/reset đặt tại route tương ứng; chỉ tách component khi dùng
  lại thật sự.
- `TutorOnboardingForm` chứa status banner nếu banner không được dùng ở nơi khác.
- `UploadField` dùng lại cho avatar/document nhưng nhận validation/purpose khác nhau.
- `AppShell`: chỉ bổ sung logout và trạng thái role cần cho flow auth; không
  redesign navigation hoặc tạo auth framework mới.

Không cần tạo component riêng nếu chỉ được dùng một lần và không làm page khó đọc.

## 10. Trình tự triển khai trong 12 giờ

| Thời lượng | Công việc | Kết quả kiểm tra được |
| ---: | --- | --- |
| 0,75 giờ | Preflight checkout, đọc W1-A2, lập mapping capability/DTO/status. | Build chạy được; không còn endpoint hoặc trạng thái bị đoán. |
| 1,25 giờ | Củng cố API client, CSRF, session bootstrap và lỗi 401/419/422. | `/me` và error shape chạy bằng API thật. |
| 2,25 giờ | Register, login, logout, verify/resend, forgot/reset password. | Customer/Tutor hoàn thành Auth flow trên browser. |
| 0,75 giờ | Role routing và `/me` refresh/reload behavior. | Reload giữ session; role sai không vào Tutor form. |
| 2,5 giờ | Tutor form, Catalog, state banner, edit lock và submit/resubmit. | Năm trạng thái render đúng; Submitted không sửa được. |
| 2 giờ | Avatar/document upload, complete và attach. | Network thể hiện đúng thứ tự, profile refetch thấy attachment. |
| 1,5 giờ | Browser test các happy/error path và thu evidence đã che dữ liệu. | Evidence đủ cho Issue/PR, không có mock. |
| 1 giờ | Sửa lỗi, lint/build và handover Hoàng/Khanh. | Checklist nghiệm thu và request IDs được bàn giao. |
| **12 giờ** |  |  |

Không bắt đầu tính timebox khi W1-A2 chưa đạt Definition of Ready. Khi đã bắt
đầu, không cắt CSRF, validation, state lock, upload steps hoặc evidence API thật
vì đây là acceptance criteria.

## 11. Kiểm thử và browser evidence

### Static checks

Chạy trong `frontend/`:

```bash
npm run lint
npm run build
```

### Browser matrix

| Ca kiểm thử | Kết quả mong đợi | Evidence tối thiểu |
| --- | --- | --- |
| Customer register | Request API thật thành công, role đúng, không vào Tutor onboarding. | UI kết quả + Network request/status đã che PII. |
| Tutor register và verify | Nhận email/link test, verify thành công, resend hoạt động. | UI trước/sau verify + status API. |
| Login, reload, `/me`, logout | Cookie session tồn tại qua reload; logout làm `/me` trả 401. | Network sequence, không chụp cookie value. |
| Forgot/reset password | Request reset và đặt mật khẩu mới; login bằng mật khẩu mới. | Status và UI, che token/email. |
| Draft | Catalog tải từ API; lưu/sửa và upload được. | UI + Catalog/profile requests. |
| Upload avatar | `multipart upload -> complete -> attach` đúng thứ tự. | Network rows/status; che cookie/XSRF và PII. |
| Upload verification document | Đúng file policy và attach vào Tutor profile. | Network rows/status; không đính kèm tài liệu thật. |
| Submitted | Form read-only/disabled sau response server. | UI status + submit response. |
| Changes Requested | Feedback hiển thị, form mở lại, resubmit chuyển về Submitted. | UI trước/sau + request/status. |
| Approved/Rejected | Nhãn đúng response backend; không triển khai action Admin. | UI của từng trạng thái. |
| 401 | Phiên hết hạn được giải thích rõ và có action về login. | UI + status 401. |
| 419 | Hiển thị lỗi CSRF/session và cho retry chủ động. | UI + status 419, che token. |
| 422 | Lỗi hiển thị tại đúng field và form summary. | UI + response đã che dữ liệu. |

Evidence phải ghi rõ:

- commit SHA frontend và backend;
- môi trường/URL dùng test;
- `NEXT_PUBLIC_DATA_MODE=api` hoặc cấu hình tương đương;
- thời điểm test và test account role;
- request ID khi có lỗi;
- dữ liệu đã che: email, password, cookie/token, signed URL, file content và PII.

Không upload HAR nguyên bản lên Issue/PR nếu chưa loại bỏ header/cookie/token.

## 12. Acceptance checklist

- [ ] Register Customer chạy bằng API thật trên browser.
- [ ] Register Tutor chạy bằng API thật trên browser.
- [ ] Login, logout, verify, resend verification và reset password chạy được.
- [ ] `/me` bootstrap đúng sau login, reload và logout.
- [ ] CSRF và session cookie hoạt động đúng; không dùng token storage thay thế.
- [ ] Tutor chọn specialization từ Catalog API.
- [ ] Avatar hoàn thành `upload -> complete -> attach`.
- [ ] Verification document hoàn thành `upload -> complete -> attach`.
- [ ] 422 hiển thị theo field; 401 và 419 có thông báo/action rõ ràng.
- [ ] Draft cho sửa và submit.
- [ ] Submitted khóa form.
- [ ] Changes Requested cho sửa và resubmit.
- [ ] Approved và Rejected hiển thị đúng trạng thái; không có action Admin.
- [ ] `npm run lint` đạt.
- [ ] `npm run build` đạt.
- [ ] Evidence ghi rõ API mode và không chứa dữ liệu nhạy cảm.
- [ ] Không dùng mock để đánh dấu Done.

## 13. Handover

### Cho Hoàng

- URL và test account Tutor đã submit.
- Tutor profile ID/request ID cần dùng để kiểm thử Admin approval.
- Trạng thái trước/sau submit và kỳ vọng UI khi Admin chọn Approve, Reject hoặc
  Changes Requested.
- Commit SHA, cách chạy frontend ở API mode và browser evidence liên quan.

### Cho Khanh khi lỗi API

Gửi một gói tái hiện tối thiểu:

- endpoint, method, timestamp và environment;
- HTTP status và `request_id`;
- request/response body đã che email, password, token, URL ký và PII;
- trạng thái hồ sơ trước thao tác;
- các bước browser ngắn để tái hiện.

Không gửi cookie header, XSRF value, reset/verify token hoặc verification document
thật.

## 14. Rủi ro và cách xử lý

| Rủi ro | Ảnh hưởng | Xử lý |
| --- | --- | --- |
| W1-A2 thiếu state/transition review | Không thể nghiệm thu Changes Requested/resubmit và đủ trạng thái trên browser. | Giữ blocker cho W1-C2/A2; frontend chỉ render khi backend trả wire value hợp lệ. |
| Backend file policy khác UX policy | Upload có thể bị backend từ chối dù qua client validation. | Hiển thị lỗi 422; backend vẫn là nguồn sự thật, không nới validation ở frontend. |
| Wire status không phân biệt đủ năm trạng thái | UI khóa sai hoặc cho phép transition sai. | Dùng contract W1-A2 làm nguồn thật; chỉ mapping tập trung khi wire values đã chốt. |
| Review transition chưa có API/test data | Không kiểm chứng Submitted/Changes Requested/Approved/Rejected đầy đủ. | Hoàng/W1-A2 cung cấp seed, endpoint Admin hoặc test data; ghi rõ evidence nguồn trạng thái. |
| PHP/proxy chặn file trên 2 MiB | Verification document thất bại trước controller. | Xác nhận giới hạn runtime trước test; backend/infra sửa, frontend chỉ hiển thị lỗi. |
| Dùng lẫn `localhost` và `127.0.0.1` | Cookie/CSRF không đi cùng request. | Dùng một host thống nhất cho frontend, API và stateful-domain config. |
| Không có cách tạo đủ trạng thái review | Không kiểm chứng Submitted/Changes Requested/Approved/Rejected. | Hoàng/W1-A2 cung cấp seed, endpoint Admin hoặc test data; ghi rõ evidence nguồn trạng thái. |

## 15. Nhật ký quyết định

| Quyết định | Phương án khác | Lý do |
| --- | --- | --- |
| Mở rộng data/service layer mỏng của W1-B1. | Auth provider/state machine hoặc generated client. | Phù hợp timebox và codebase hiện tại, ít dependency. |
| OpenAPI là hợp đồng API chính; runtime/test dùng để phát hiện lệch contract. | Suy ra chỉ từ migration hoặc chỉ tin implementation. | Frontend cần contract ổn định; chỗ lệch phải được sửa/ghi blocker thay vì âm thầm hỗ trợ hai shape. |
| Không fallback từ API sang mock. | Dùng mock khi API lỗi để tiếp tục UI. | Acceptance criteria cấm đánh dấu Done bằng mock. |
| Không tự retry mutation sau 419. | Refresh CSRF và replay request tự động. | Tránh register/submit/attach trùng và cho người dùng biết phiên đã hết hạn. |
| Backend response quyết định trạng thái sau submit. | Frontend optimistic status. | Trạng thái review là invariant của backend. |
| Chỉ giữ component dùng lại thực tế. | Tạo framework form/upload tổng quát. | YAGNI; avatar và document chỉ cần chung orchestration nhỏ. |
| Evidence che toàn bộ secret/PII. | Đính HAR hoặc request đầy đủ. | Auth và verification document là dữ liệu nhạy cảm. |

## 16. Điều kiện hoàn tất

Issue chỉ được đánh dấu Done khi toàn bộ acceptance checklist bắt buộc đã chạy
trên browser với API thật, lint/build đạt, evidence đã đính kèm và Hoàng nhận
được Tutor submit để kiểm thử Admin approval. Nếu W1-A2 chưa cung cấp API hoặc
test state cần thiết, trạng thái đúng là Blocked/Dependency pending, không phải
Done với mock.

## 17. Trạng thái thực thi sau audit W1-A2

### Đã triển khai

- Chuẩn hóa API error theo `message`, `errors`, `request_id` và thông báo riêng
  cho 401/419/422.
- Register Customer/Tutor với `password_confirmation`, `date_of_birth` và kiểm
  tra tuổi tối thiểu ở browser.
- Login, kiểm tra session bằng `/me`, logout và điều hướng theo role.
- Verify email, resend verification, forgot password và reset password pages.
- Tutor profile draft/save/submit theo `GET/PUT /tutor/profile` và
  `POST /tutor/profile/submit`; form khóa sau response `submitted`.
- Catalog specialization được đọc từ `GET /catalog/specializations`.
- Upload multipart theo purpose và complete theo
  `POST /files/{id}/complete`.
- Lưu specialization vào Tutor profile bằng `specialization_id`.
- Attach avatar bằng `PUT /tutor/profile/avatar` sau khi file complete.
- Tạo verification document bằng `document_type` sau khi file complete.
- Giữ file đã complete và cho phép retry attach nếu bước attach lỗi.
- Hiển thị lỗi validation theo field trên Auth và Tutor form.
- API middleware trả JSON `401` cho request unauthenticated thay vì redirect tới
  route login không tồn tại.
- Docker runtime đã chạy được frontend/API; Catalog seeder và migration
  `000700_add_tutor_onboarding_file_links` đã được áp dụng trong môi trường test.
- `npm run lint`, `npx tsc --noEmit --incremental false` và `npm run build`
  đều đạt trong container frontend.

### Có thể triển khai tiếp bằng contract hiện có

- Verify email, resend verification và reset password.
- Browser evidence đầy đủ cho API mode, cookie/session, CSRF và các mutation
  Auth/Tutor vẫn cần được thu thập trước khi đóng issue.

### Chưa thể hoàn tất — blocker contract

- `Changes Requested`, feedback và resubmit chưa có trong backend runtime; đây là
  dependency của W1-C2/W1-A2, không tự mô phỏng ở frontend.
- Evidence browser cho register/login/verify/reset, 419, upload/attach và đủ
  state review chưa được đính kèm.

Các blocker không được thay bằng endpoint tự đặt hoặc mock khi nghiệm thu. Audit
backend đã chạy `AuthFileProfileTest` trong Docker và đạt 14 test/47 assertions;
đây chưa thay thế browser evidence về cookie/CSRF. W1-B2 chưa nên đánh dấu Done
cho tới khi có evidence thật và backend cung cấp transition Changes Requested.
