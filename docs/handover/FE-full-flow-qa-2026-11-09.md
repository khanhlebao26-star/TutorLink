# Kiểm thử FE theo vai trò · 09/11/2026

Branch `codex/2w-fe-integration`, baseline `2ed3eaa`, các sửa đổi còn trong working tree. Root sửa code và chạy kiểm chứng. FE1, FE2 và FE3 chỉ kiểm thử và ghi lỗi. Các ca ghi dữ liệu dùng PostgreSQL test, FE cổng 3001, API cổng 8001 và Mailpit cổng 8026. Các tài khoản và email trong bài test là fixture.

Đối chiếu DB local theo đúng email người dùng đã nêu xác nhận vai trò `tutor`, email đã xác minh và chưa có TutorProfile. Phiên `customer` có chữ PH trong DB dùng một email khác. Không dùng chữ viết tắt avatar để xác định tài khoản. Test đổi tài khoản trong cùng browser context trả đúng role qua `/me`.

## Kiểm tra chung

| Kiểm tra | Kết quả |
| --- | --- |
| TypeScript, ESLint, production build | Pass |
| Vitest/RTL | 37/37 Pass, gồm kiểm tra tên bằng cấp rỗng, giấy tờ hết hạn và tài liệu bằng cấp còn thiếu |
| Mock roles | Pass sau cập nhật script kiểm thử theo enum hồ sơ và quyền hiện tại |
| Coverage FE | 30,95% dòng, 26,96% statement, 27,41% nhánh, 21,97% hàm |
| Smoke route Next.js | 36/36 route đã mở trên browser, gồm 35 route từ matrix và Task detail với ID thật trong ca nộp bài |
| Backend hỗ trợ FE trên DB test thứ hai | 756 pass, 2 fail. Test mail cũ đọc `actionUrl` trong khi template hiện dùng `verificationUrl`. Test hạ tầng yêu cầu DB có tên `tutorlink_test` trong khi runner cô lập dùng `tutorlink_phase2_test`. Nhóm D04 dùng fixture ràng buộc tên DB nên không chạy trong suite này |

Smoke route xác nhận route và bố cục tải được. Bảng luồng bên dưới ghi thao tác và trạng thái được xác nhận riêng.

## Header theo vai trò

Lượt chạy lại trên DB test vừa khởi động đạt 52/52, gồm các ca điều hướng, đổi vai trò, giữ phiên và mở các route trong script. Xem [kết quả từng ca](evidence/fe-followup-2026-11-09/header-and-routes-resume.json).

| Vai trò | Kiểm chứng trên Chrome |
| --- | --- |
| Khách | Nút Đăng nhập hiện, `/me` trả 401, mở và đóng điều hướng mobile bằng bàn phím |
| Học viên | `/me` trả `customer`, đúng menu, logo về trang chủ rồi reload giữ phiên, avatar mở và Escape trả focus, mobile sidebar và logout đạt |
| Gia sư | `/me` trả `tutor`, đúng menu Tutor, logo về trang chủ rồi reload giữ phiên, avatar và logout đạt trên desktop/mobile |
| Admin | `/me` trả `admin`, sidebar quản trị, avatar và logout đạt. Đã sửa topbar để hiển thị Duyệt hồ sơ Tutor và Giám sát hệ thống, bỏ hai link Program/Tin nhắn mà API trả 403. Ca chạy lại đạt |
| Đổi tài khoản | Customer → logout → Tutor trong cùng browser context hiển thị đúng vai trò và sidebar |

## Luồng nghiệp vụ

| Luồng | Kết quả đã xác nhận |
| --- | --- |
| Đăng ký, xác minh, đăng nhập, reset password | Playwright `account-mailpit.spec.ts` pass. FE1 đăng ký Tutor từ CTA, xác minh qua Mailpit, đăng nhập UI và `/me` trả `tutor`. FE1 xác nhận wizard mở trong phiên mới. Timeout trước đó xảy ra khi script đăng nhập lần hai trong cùng context |
| Form auth khi JavaScript tải chậm | Root xác nhận HTML ban đầu của login và đăng ký từng cho nhập/gửi trước khi trình xử lý sẵn sàng. Sau sửa, Chrome kiểm bản production build với JavaScript tắt thấy cả hai form khóa nhập/gửi. Khi bật JavaScript, login mở lại và nhận nhập liệu. Xem [kết quả Chrome](evidence/fe-followup-2026-11-09/auth-interactivity-build.log) |
| Hồ sơ Tutor mới | FE1 xác nhận chặn tên bằng cấp rỗng, lưu và mở lại nháp, tải và gắn ảnh, tải CCCD, chặn gửi khi thiếu chứng chỉ, tải chứng chỉ và gửi duyệt 200. API trả `submitted` với đủ ảnh và hai loại tài liệu. Root bổ sung trạng thái ảnh ở bước tài liệu và bỏ thông báo tải trùng. Xem [kết quả gửi duyệt](evidence/fe-followup-2026-11-09/tutor-onboarding-resume.json) |
| Lịch khả dụng | FE1 xác nhận chặn múi giờ dạng UTC offset và lưu một khung giờ thứ Hai với múi giờ IANA `Asia/Ho_Chi_Minh` |
| Chat hai chiều | FE1 gửi và nhận hai tin text trên conversation test riêng. API xác nhận đủ hai sender, conversation seed giữ nguyên |
| Marketplace và Customer | FE2 xác nhận tìm theo tiêu đề, trạng thái rỗng, thao tác tìm bằng Enter trên mobile, lưu cài đặt và xem sáu tab với quyền Customer. Program ngoài membership trả 404. Lượt kiểm lại đạt bộ lọc, chi tiết dịch vụ, hồ sơ Tutor, nhận xét công khai và CTA liên hệ dẫn đến gửi tin text. Lượt đầu API chi tiết trả 200 sau 10–12 giây, quá ngưỡng chờ 10 giây của locator. Xem [kết quả kiểm lại](evidence/fe-followup-2026-11-09/customer-detail-resume.json) |
| Quản lý dịch vụ Tutor | FE1 tạo, sửa và công khai dịch vụ demo. Chi tiết công khai trả 200. Ca tạm dừng lần đầu đọc trạng thái trước khi xác nhận request hoàn tất, cần chạy lại nhánh tạm dừng, công khai lại và lưu trữ |
| Chấp nhận invitation, mở workspace, pause/resume, hoàn thành Program và review | FE1 xác nhận thao tác browser trên hai context được đăng nhập qua API. Đã mở đủ sáu tab cho Tutor và Customer. Root chạy lại Playwright chính thức sau khi đồng bộ chờ bài tập lưu và phản hồi hoàn tất. Journey và guest guard đều pass |
| Session và task | FE1 tạo và hoàn thành Session. Root tạo Task, Customer nộp bài, Tutor phản hồi, reload hiện kết quả |
| File và phiên bản bài nộp | Upload PDF demo đạt 100%, Tutor tải được, outsider trả 403, yêu cầu sửa và giữ lịch sử phiên bản 1/2 |
| Progress | Tạo 201, stale PATCH 409, giữ draft trên form, reload version mới rồi lưu, Customer thấy giá trị mới |
| Announcement | Sửa lỗi danh sách Tutor chỉ hỏi dữ liệu đã đăng. Sau sửa, tạo draft, chỉnh sửa và publish đạt. Customer chỉ thấy nội dung sau publish |
| Fee | Sửa hiển thị `marked_paid`/`marked_unpaid` và yêu cầu lý do khi đổi từ đã trả sang chưa trả. Tạo VND, trùng tháng 409, đánh dấu đã trả → chưa trả → hủy khai báo và nhãn phía Customer đều đạt |
| Báo cáo | Customer tạo báo cáo cho Program và thấy trong danh sách của mình. Danh sách của Tutor không chứa báo cáo đó |
| Thông báo | Xử lý 19 outbox event trong môi trường test, kiểm nhận đúng người, mở deep link, đánh dấu một và tất cả. Unread 15 → 14 → 0 |
| Admin quyền | FE3 xác nhận Customer/Tutor bị từ chối ở các route Admin web/API, Admin vào được và logout trả `/me` 401. Dashboard, Blade/Filament, tải tài liệu và evidence đã được mở |
| Admin phân công và duyệt | FE3 gỡ giao hồ sơ demo #2, GET chi tiết trả 404. Gán lại qua Filament ở v3, GET chi tiết và POST approve cùng 200. Đọc lại API và UI xác nhận đã duyệt và giữ người được giao. Xem [kết quả cuối](evidence/fe-followup-2026-11-09/admin-assignment-resume.json) |

## Evidence

Bản sao kết quả chính nằm tại [thư mục evidence](evidence/fe-followup-2026-11-09/). Script và trace browser theo lượt nằm tại `/private/tmp/tutorlink-role-qa-20261109/`. Trong JSON header ban đầu có hai fail. Guest fail do locator giữ tên cũ sau khi nút đổi sang “Đóng điều hướng”. Admin fail do link ngoài quyền. Cả hai đạt trong `targeted-header-after.json`.

Trace lượt tiếp tục nằm ở `/private/tmp/tutorlink-fe-resume-20261109/`. Ca Mailpit lần đầu timeout trong lúc xác minh. Trace ghi trang xác minh trả 200 sau 56 giây, request API xác minh chưa có kết quả khi ca dừng. Chạy lại cùng spec với tài khoản demo mới đạt 1/1, gồm đăng ký, xác minh, đăng nhập và đặt lại mật khẩu. Lượt này chỉ xác nhận gửi thư qua Mailpit.

## Điểm bắt đầu lại

| Phần | Việc tiếp theo |
| --- | --- |
| FE1 Tutor | Hồ sơ mới, lịch khả dụng và chat hai chiều đã có bằng chứng browser. Hết hạn giấy tờ định danh đã có regression RTL. Tiếp tục các trạng thái Listing còn thiếu và nhánh invitation reject/expire |
| FE2 Customer | Phân trang bị chặn vì lượt test chỉ có một dịch vụ công khai. Cần fixture đủ hai trang. Customer Profile theo BR-13 vẫn thiếu API đọc/sửa riêng, `/profile` hiện chỉ phục vụ Tutor |
| FE3 Admin | Đã chốt hồ sơ demo #2 duyệt thành công sau khi gán lại. Ca Report bị chặn trong lượt thử lại do phiên test trả 401 trước khi gọi thao tác nghiệp vụ. Tiếp tục quyết định tài liệu, reassignment/close Report, review visibility, suspend/restore và monitoring. Ghi riêng browser và PHP feature test |
| Root | Sửa các lỗi đã tái hiện, chạy lại đúng ca bị ảnh hưởng. Xác nhận các nhánh nghiệp vụ còn trống trước Gate FE. Kiểm tra lại test email cũ và test DB name khi chạy suite backend theo runner chuẩn |

DB browser là `tutorlink_test` dạng tmpfs. Lượt trước dùng Program #2 cho journey FE1, Program #4/#5 cho workspace, Program #7 và Invitation #5 cho E2E. Lượt tiếp tục khởi động lại Docker, guard xác nhận DB test đang trống, chạy migrate và nạp lại `FrontendDemoSeeder`. Các ID của lượt trước chỉ dùng để đọc evidence. Kiểm tra fixture hiện tại trước các ca ghi dữ liệu.

Nếu test containers đã dừng, khởi động lại bốn container có sẵn `tutorlink-test-db-1`, `tutorlink-backend-fe-test-1`, `tutorlink-frontend-fe-test-1`, `tutorlink-mailpit-fe-test-1`. `docker/fe-testing.env` là file rỗng về cấu hình, dùng để che `.env` khi mount API test. Xác nhận `php scripts/test-console.php check` bên trong `tutorlink-backend-fe-test-1` trước khi migrate/seed DB test trống. Khi các container đã bị xóa, cần phục hồi cấu hình test cô lập từ runbook trước khi chạy browser có mutation.

Prompt để tiếp tục ở lần sau:

> Đọc `docs/handover/FE-full-flow-qa-2026-11-09.md` và báo cáo FE1–FE3. Tiếp tục từ các ca chưa đạt, ưu tiên wizard Tutor mới và Admin assignment. Chỉ chat điều phối chỉnh logic/code. FE1–FE3 chạy test, ghi lỗi và evidence trên DB test riêng. Xác minh trạng thái fixture trước khi chạy lại.

Kết quả chưa đạt Gate FE. Coverage dưới mục tiêu và các ca nghiệp vụ chưa có bằng chứng browser cần tiếp tục được ghi rõ trong báo cáo từng lane.
