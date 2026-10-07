# Kế hoạch đề xuất cho issue frontend #3

**Người thực hiện:** Dũng · **Timebox:** 1 ngày làm việc (giả định 8 giờ) · **Trạng thái:** Đề xuất để đánh giá, chưa triển khai code

## 1. Mục tiêu và giới hạn

Hoàn thiện nền frontend Next.js để nhóm có thể dựng và kiểm tra UI cho Auth, Profile, Admin và Marketplace trong khi API nghiệp vụ đang được phát triển. Kết quả cần có shell theo ba role, API client dùng cookie/CSRF đúng, form và table dùng chung, trạng thái 401/loading/empty/error, mock Auth/Profile/Listing và các route chính chạy được. Dũng bàn giao Admin shell cùng component chung cho Hoàng.

Kế hoạch này **không xử lý 419** theo yêu cầu cập nhật phạm vi. Không làm Program, Messaging, upload tài liệu, state transition nghiệp vụ hay authorization phía backend. Việc ẩn/hiện điều hướng ở frontend chỉ cải thiện trải nghiệm; Laravel vẫn quyết định quyền truy cập từng request.

## 2. Căn cứ đã kiểm tra

| Nguồn | Phát hiện dùng cho kế hoạch |
| --- | --- |
| [Issue #3](https://github.com/khanhlebao26-star/TutorLink/issues/3) | Phạm vi gốc, timebox và acceptance criteria. Issue trực tuyến hiện vẫn ghi `419`; cần cập nhật sau khi chốt kế hoạch. |
| [Nhánh `schemaDB` tại `88f2235`](https://github.com/khanhlebao26-star/TutorLink/commit/88f2235140341d1b699489e15a60503fc0813d92) | Schema **đã có**. Migration `000100` xác định `users`; migration `000200` xác định Customer/Tutor Profile, catalog và Service Listing. Đây là nguồn cho field, kiểu ID và giá trị enum của mock. |
| `Designv4.docx`, mục 8–10 | Đề xuất base path `/api/v1`, endpoint Auth/Profile/Listing/Admin, response `{ data, meta, request_id }`, lỗi `{ error, request_id }`, Sanctum cookie/CSRF; UI tiếng Việt, responsive và dùng bàn phím cơ bản. |
| Frontend tại `main` | Next.js 16; hiện có trang mẫu và `frontend/src/lib/api/client.ts` với `withCredentials: true`, `withXSRFToken: true`. Chưa có các route/component của issue. |
| Kiểm tra nền 07/10/2026 | `npm run lint` đạt. `npm run build` thất bại do `next/font/google` không tải được Geist/Geist Mono trong môi trường này; cần loại phụ thuộc tải font từ mạng để build lặp lại được. |

`schemaDB` có [tài liệu schema](https://github.com/khanhlebao26-star/TutorLink/blob/88f2235140341d1b699489e15a60503fc0813d92/docs/database/w1-a1-database-schema.md) và [contract mocks W1-C1](https://github.com/khanhlebao26-star/TutorLink/blob/88f2235140341d1b699489e15a60503fc0813d92/docs/contracts/w1-c1-contract-mocks.json). File W1-C1 minh họa chủ yếu Approval, Conversation, Program và Invitation; **không phải** payload API hoàn chỉnh cho Auth/Profile/Listing. Các migration cũng không tự xác định response DTO. Frontend phải tách rõ field database đã xác nhận khỏi projection API còn cần đối chiếu.

## 3. Hiểu biết và giả định

- Đối tượng dùng UI: Customer, Tutor và Admin. `users.role` trong migration dùng `customer | tutor | admin`; một tài khoản có một role trong MVP.
- Nguồn field/enum cho mock là migration ở `schemaDB`, không coi schema là phần còn thiếu. `users.id`, profile ID và listing ID là `bigint` dương; frontend coi ID là giá trị định danh, không tính toán trên ID.
- `Designv4.docx` cung cấp tên endpoint và envelope đề xuất. Backend của checkout hiện mới có `/api/user`, nên request/response chi tiết của endpoint nghiệp vụ chưa thể kiểm chứng bằng API thật.
- Tên URL của trang Next.js ở mục 5 là **đề xuất UI**, không suy ra từ URL API. Có thể đổi khi nhóm chốt navigation mà không đổi schema mock.
- Quy mô/sẵn sàng toàn hệ thống trong `Designv4.docx` là khoảng 5.000 tài khoản, 50 người dùng đồng thời và mục tiêu nội bộ 99%/tháng. Trong issue một ngày, frontend chuẩn bị phân trang, trạng thái tải/lỗi và giao diện responsive; load test và cam kết uptime thuộc công việc hệ thống khác.
- Dữ liệu mock dùng tên và nội dung giả, không có mật khẩu, `password_hash`, giấy tờ xác minh, token hay dữ liệu cá nhân thật.

## 4. Phương án

| Phương án | Cách làm | Độ phức tạp và bảo trì | Rủi ro |
| --- | --- | --- | --- |
| **A — Khuyến nghị: lớp dữ liệu mỏng với chế độ mock/API tường minh** | Giữ Axios client; tạo hàm Auth/Profile/Listing mỏng, cùng kiểu dữ liệu FE. Biến môi trường chọn `mock` hoặc `api`; mock được gắn nhãn trong UI và không tự kích hoạt khi API lỗi. | Phù hợp một ngày, ít dependency, dễ thay mock bằng API. | Payload FE tạm thời cần đối chiếu khi endpoint thật xuất hiện. |
| B — Intercept HTTP bằng thư viện mock | Mọi trang vẫn gọi HTTP; interceptor trả fixture cho một số endpoint. | Gần hành vi mạng thật hơn nhưng thêm cấu hình, dependency và việc quản lý handler. | Dễ lẫn request mock/thật nếu thiếu quy ước rõ; tốn thời gian setup. |
| C — Dùng dữ liệu tĩnh ngay trong page | Mỗi page tự đọc fixture và render. | Nhanh lúc đầu nhưng trùng logic, khó kiểm tra API client và chuyển sang API thật. | Không đáp ứng tốt yêu cầu API client nền và phân biệt mock/API. |

**Đề xuất chọn A.** Chỉ tạo abstraction đủ cho ba nhóm dữ liệu của issue. Không dựng framework state management, mock server tổng quát hay toàn bộ domain DTO trước khi có OpenAPI.

## 5. Thiết kế đề xuất theo phạm vi issue

### 5.1 Route và shell

| Route UI đề xuất | Shell/trạng thái | Mức hoàn thiện trong một ngày |
| --- | --- | --- |
| `/` | Điều hướng tới Marketplace hoặc trang giới thiệu tối giản | Có link rõ tới route chính. |
| `/auth/login`, `/auth/register` | Public/Auth | Form nền, validation hiển thị; request qua lớp dữ liệu đã chọn. |
| `/profile` | Customer hoặc Tutor | Shell theo role; dùng mock Profile và form chung để minh họa field. |
| `/admin` | Admin | Header, navigation, vùng nội dung và table trạng thái hồ sơ mẫu để Hoàng tiếp tục. |
| `/marketplace`, `/marketplace/[slug]` | Public | Danh sách và chi tiết Listing mẫu; loading/empty/error. |

Shell dùng navigation riêng cho Customer, Tutor và Admin, tiếng Việt, responsive desktop/mobile. Trên route cần đăng nhập, frontend có thể chuyển người chưa đăng nhập về login và hiển thị thông báo thiếu quyền, nhưng không xem đây là chốt bảo mật. API thật vẫn phải trả 401/403 theo policy; Admin permission chi tiết không được suy ra chỉ từ `role=admin`.

### 5.2 API client và trạng thái lỗi

- Tận dụng `frontend/src/lib/api/client.ts`. Phân biệt **API origin** với base path `/api/v1`: `/sanctum/csrf-cookie` nằm ở origin, không nằm dưới `/api/v1`. Xác nhận cấu hình này trước khi nối endpoint; tránh ghép nhầm URL.
- Giữ `withCredentials` và XSRF của Axios. Lấy CSRF cookie trước request thay đổi dữ liệu cần session, đặc biệt trước login/register nếu backend yêu cầu; sau đó gửi request tới `/api/v1/...`. Không lưu access token dài hạn trong local storage.
- Chuẩn hóa 401 thành trạng thái hết/chưa có phiên: xóa trạng thái user tạm trong FE, dẫn tới login từ route cần bảo vệ, giữ đường quay lại an toàn. Trang public hiển thị lỗi phù hợp; không tạo vòng lặp redirect.
- Một component trạng thái dùng chung cho loading, empty và error, có thông điệp tiếng Việt và nút thử lại khi thao tác có thể lặp an toàn. Form hiển thị lỗi theo trường khi API trả validation error; các lỗi khác dùng thông báo chung cùng `request_id` nếu API có trả.
- **Không thiết kế interceptor hay retry riêng cho 419.** Request ghi dữ liệu không tự động lặp lại sau lỗi vì có thể tạo thao tác trùng.

### 5.3 Form, table và accessibility

Tạo các phần tử chung ở mức vừa đủ: field có `label`, mô tả và lỗi; form container có trạng thái đang gửi; table có cột, row key, empty state và vùng loading/error. Áp dụng cho login/register, profile và bảng Admin/Marketplace để kiểm chứng tái sử dụng thực tế. Input có label liên kết, lỗi có text, focus nhìn thấy được, thứ tự Tab hợp lý; table đọc được trên màn nhỏ bằng cuộn ngang hoặc bố cục phù hợp. Không truyền quyền nghiệp vụ vào component chung.

### 5.4 Mock Auth/Profile/Listing dựa trên migration

| Nhóm mock | Field database làm căn cứ | Dữ liệu UI cần thể hiện |
| --- | --- | --- |
| Auth (`users`) | `id`, `role`, `status`, `full_name`, `email`, `email_verified_at`; `date_of_birth`/`terms_version` chỉ khi màn hình cần | Ca guest, Customer, Tutor, Admin; tài khoản `active` và `suspended`. Không đưa `password_hash` vào fixture trả về UI. |
| Profile | `customer_profiles`: `id`, `user_id`, `avatar_file_id`, `city`, `district`, `bio`. `tutor_profiles`: `id`, `user_id`, `headline`, `bio`, `experience_years`, `approval_status`, `submitted_at`, `reviewed_at`. | Tutor `pending`, `active`, `rejected`; Customer có/không có bio/avatar để kiểm tra empty và form. Tài liệu xác minh chỉ là phần nghiệp vụ ngoài phạm vi, không thêm nội dung nhạy cảm. |
| Listing (`service_listings`) | `id`, `tutor_profile_id`, `specialization_id`, `slug`, `title`, `description`, `delivery_mode`, `city`, `district`, `price_from_minor`, `currency`, `price_unit`, `status`. | Listing `active`, `draft`, `inactive`; `online`, `offline`, `hybrid`; kết quả rỗng và lỗi tải. Giá giữ số nguyên minor unit, hiển thị VND bằng định dạng UI. |

Migration `000200` quy định `tutor_profiles.approval_status = pending | active | rejected | cancelled | expired`, `service_listings.status = draft | active | inactive | cancelled` và `delivery_mode = online | offline | hybrid`. `Designv4.docx` dùng một số tên trạng thái khác ở phần mô tả nghiệp vụ; fixture phải theo enum của migration, còn nhãn tiếng Việt là lớp hiển thị. Mock public chỉ hiển thị Listing `active` gắn Tutor Profile `active`, phù hợp quy tắc sản phẩm; backend thật phải tự thực thi lọc và authorization.

Mock có thể dùng envelope `{ data, meta, request_id }` theo `Designv4.docx`, nhưng nested object, tên field response, phân trang và payload mutation **chưa được mô tả đầy đủ**. Các DTO FE và fixture lồng quan hệ (ví dụ tên Tutor/Category trên Listing card) là giả định phục vụ dựng UI, phải ghi chú và đối chiếu OpenAPI/API thật khi tích hợp. Không coi field database là response API mặc định.

**Phân biệt mock/API thật:** dùng biến môi trường tường minh, ví dụ `NEXT_PUBLIC_DATA_MODE=mock|api`; chỉ cho hai giá trị hợp lệ. Chế độ mock có nhãn “Dữ liệu mẫu” trong giao diện dev và log nguồn dữ liệu ở tài liệu bàn giao. Chế độ API dùng URL thật, không âm thầm rơi về mock khi lỗi; nếu cấu hình API thiếu thì báo lỗi cấu hình rõ ràng.

## 6. Trình tự một ngày

| Thời lượng | Công việc | Kết quả kiểm tra được |
| ---: | --- | --- |
| 0,5 giờ | Khóa mapping issue/Designv4/schemaDB và danh sách giả định API | Bảng field/enum cho Auth/Profile/Listing, danh sách route. |
| 1,25 giờ | Củng cố Axios client, tách origin/versioned path, CSRF và 401 | Có đường gọi mock/API rõ ràng; kiểm tra URL, cookie và XSRF qua Network khi backend có endpoint. |
| 1,5 giờ | Root layout, role shell và route nền | Mỗi route chính render được; navigation đúng role ở chế độ mock. |
| 1,25 giờ | Form/table và loading/empty/error | Component được dùng trên ít nhất hai màn hình, có label/focus/error. |
| 1,5 giờ | Fixture Auth/Profile/Listing và adapter dữ liệu | Các trạng thái role/profile/listing, empty và error được demo; có nhãn mock. |
| 1,25 giờ | Sửa phụ thuộc font gây build lỗi, chạy lint/build và smoke route | `npm run lint`, `npm run build` đạt; kiểm tra route desktop/mobile và keyboard cơ bản. |
| 0,75 giờ | Ghi chú API assumptions và bàn giao cho Hoàng | Admin shell, props component, fixture và cách chạy mock/API có hướng dẫn ngắn. |
| **8 giờ** |  |  |

Nếu thiếu thời gian, ưu tiên client, route không lỗi, trạng thái UI và bàn giao component; giảm chi tiết trang Listing/Profile, không thêm luồng nghiệp vụ mới.

## 7. Nghiệm thu và cập nhật issue

| Tiêu chí sau khi cập nhật | Cách kiểm tra khi triển khai |
| --- | --- |
| Build và lint thành công | Chạy `npm run lint` và `npm run build` trong `frontend/`; build không phụ thuộc tải font từ Google lúc chạy. |
| API client dùng credentials và CSRF đúng | Network check: cookie được gửi, CSRF cookie lấy ở `/sanctum/csrf-cookie`, request versioned đi đúng `/api/v1`, header XSRF có ở request cần thiết. Chỉ kiểm chứng end-to-end sau khi backend có endpoint tương ứng. |
| Route chính không lỗi | Smoke `/`, `/auth/login`, `/auth/register`, `/profile`, `/admin`, `/marketplace`, `/marketplace/[slug]` ở mock mode; kiểm tra 401, loading, empty, error. |
| Mock phân biệt API thật | Nhãn “Dữ liệu mẫu”, mode cấu hình tường minh, API mode không tự fallback sang mock. |
| Không đặt authorization chỉ ở frontend | FE chỉ điều hướng/ẩn thao tác theo dữ liệu nhận; backend tiếp tục kiểm tra session, role, owner và permission. |
| Bàn giao Admin shell/component | Hoàng nhận route Admin nền, form/table chung, props cần dùng, fixture và ghi chú các DTO cần xác nhận. |

**Đề nghị sửa issue #3:** đổi dòng phạm vi `Bổ sung xử lý 401, 419, loading, empty và error` thành `Bổ sung xử lý 401, loading, empty và error`. Acceptance criteria hiện không có dòng 419 riêng; ghi chú trong issue rằng `419` bị loại khỏi phạm vi/nghiệm thu của #3, không dùng nó làm điều kiện hoàn thành. Kế hoạch này chỉ đề xuất nội dung cập nhật, chưa chỉnh issue trên GitHub.

## 8. Rủi ro và điểm đối chiếu khi tích hợp

1. **Schema khác mô tả trạng thái trong Designv4:** dùng enum migration cho fixture và nhãn UI; không tự tạo trạng thái `submitted`, `changes_requested`, `paused` nếu backend chưa ánh xạ. Chủ sở hữu API cần xác nhận response khi tích hợp.
2. **Endpoint đã được thiết kế nhưng chưa hiện diện trong backend checkout:** mock phục vụ UI. Không báo “API thật đã chạy” chỉ vì mock pass; kiểm tra lại `GET /me`, Auth và Listing khi backend triển khai.
3. **Payload còn thiếu chi tiết:** cần đối chiếu OpenAPI hoặc response thật cho field optional, nested relation, pagination, validation và lỗi; chỉnh adapter thay vì sửa tất cả page.
4. **CSRF URL và origin:** nếu baseURL chứa `/api/v1`, lời gọi `/sanctum/csrf-cookie` hiện tại có thể sai. Tách đường dẫn trước khi kiểm thử credential/XSRF.
5. **Build phụ thuộc mạng do font:** thay cách dùng font bằng phương án chạy được offline hoặc font local, rồi chạy lại build trong môi trường sạch.

## 9. Nhật ký quyết định

| Quyết định | Phương án khác | Lý do | Trạng thái |
| --- | --- | --- | --- |
| Bỏ `419` khỏi kế hoạch và đề nghị sửa issue | Giữ 419 như issue gốc | Người giao việc đã chỉ định bỏ. | Đã chốt theo yêu cầu. |
| Dùng migration `schemaDB` cho field/enum mock | Dùng mô tả Designv4 như schema | Migration là cấu trúc dữ liệu đã có; mô tả sản phẩm có tên trạng thái khác. | Đã chốt theo yêu cầu. |
| Dùng lớp dữ liệu mỏng và mode mock/API tường minh | Interceptor HTTP; fixture trực tiếp trong page | Phù hợp timebox, dễ kiểm tra ranh giới mock/API. | Đề xuất để đánh giá. |
| Giữ route UI tối thiểu và Admin shell có table mẫu | Xây toàn bộ workflow Admin | Đủ bàn giao cho Hoàng mà không vượt issue #3. | Đề xuất để đánh giá. |
| Đánh dấu response DTO chưa được mô tả là giả định | Sao chép field DB thành response mặc định | Tránh biến mock UI thành hợp đồng API giả. | Đã chốt theo yêu cầu. |

**Điểm cần xác nhận sau khi duyệt kế hoạch:** tên route UI cuối cùng và response DTO chi tiết khi OpenAPI/API thật sẵn sàng. Các điểm này không cản trở việc dựng mock bám schema trong timebox một ngày.
