# W1-B1/B2 — Auth và Tutor onboarding theo Figma

Issue: [#22](https://github.com/khanhlebao26-star/TutorLink/issues/22). Branch: `feature/W1-B1-B2-figma-layout`. Baseline: `e25d531`. Ngày triển khai: 13/10/2026. Owner: Dũng; reviewer giao diện: Hoàng; reviewer tích hợp API: Khanh.

Nguồn: [TutorLink — Desktop Tutor Platform, trang Screens](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=12-3). PNG đối chiếu nằm trong `docs/FE-figmaDesign`.

Yêu cầu tiếp theo đã chuyển khung trang từ centered container sang full-width. Xem [bàn giao full-width](full-width-layout.md) cho thay đổi và evidence mới; 42 ảnh trong báo cáo #22 này giữ nguyên snapshot trước thay đổi chiều rộng.

PNG/Figma đã được người dùng cập nhật lần nữa. Xem [bản cập nhật theo thiết kế full-width](issue-22-fullwidth-redesign.md) để xem source và evidence mới nhất; phần còn lại của tài liệu này là bàn giao lịch sử.

## Route và frame

| Route / bước | Frame Figma | PNG trong repo |
| --- | --- | --- |
| `/auth/login` | [Login page — 53:125](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=53-125) | `Login page.png` |
| `/auth/register` — Customer/Tutor | [Register page — 52:2](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=52-2) | `Register page.png` |
| `/auth/forgot-password` | [Forgot password page — 57:952](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=57-952) | `Forgot password page.png` |
| `/auth/reset-password` | [Reset password — 58:1010](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=58-1010) | `Reset password.png` |
| `/auth/verify-email` — Verify/Resend | [Verify Password — 58:1086](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=58-1086) | `Verify Password.png` |
| `/profile` — Thông tin cơ bản | [86:3](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=86-3) | `Tutor Dashboard-thongtincoban.png` |
| `/profile` — Chuyên môn | [86:166](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=86-166) | `Tutor Dashboard-chuyenmon.png` |
| `/profile` — Kinh nghiệm | [86:334](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=86-334) | `Tutor Dashboard-kinhnghiem.png` |
| `/profile` — Tài liệu xác minh | [86:489](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=86-489) | `Tutor Dashboard-tailieuxacminh.png` |
| `/profile` — Xem lại | [86:670](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink?node-id=86-670) | `Tutor Dashboard-xemlai.png` |
| Layout Customer/Tutor, `/admin` shell | Header/sidebar của các frame dashboard và Auth | `Customer Dasboard-cai-dat.png`, các frame onboarding |

## Phạm vi và khác biệt với thiết kế

- Auth và onboarding dùng token/component chung; giữ nguyên API contract, CSRF client và session cookie. Marketplace và nghiệp vụ Admin approval ngoài scope.
- Register giữ xác nhận mật khẩu theo contract hiện tại. Verify dùng chữ ký từ email; không thay bằng OTP chỉ để giống hình minh họa.
- Onboarding chỉ dùng các trường có API: headline, bio, specialization, experience, avatar, verification document. Không thêm video, quốc gia, ngôn ngữ, bảng phí hoặc các chức năng chưa duyệt MVP.
- Giới hạn avatar theo backend là JPG/PNG tối đa 2 MiB; thiết kế ghi 5 MB. Tài liệu theo backend tối đa 10 MiB. Không thêm xóa tài liệu đã attach vì backend không cho phép.
- Navigation chỉ liên kết các route đã có; ngôn ngữ/VND hiển thị thông tin tĩnh. Avatar/tên người dùng lấy từ phiên, không dùng nhân vật Maya mẫu làm dữ liệu tài khoản.
- Figma MCP trả đầy đủ context cho Login, Register, Verify, Documents, Review; các frame còn lại chạm giới hạn Starter và được đối chiếu bằng PNG do người dùng cung cấp.
- Login/Register/Verify dùng asset PNG/SVG gốc được lưu tại `frontend/public/figma/auth`, có `ASSET-SOURCES.txt` ghi frame/layer. Artwork Forgot/Reset được dựng bằng CSS do không lấy được layer gốc; hình và kiểu chữ slogan có thể khác PNG. Inter được lưu cục bộ, có giấy phép OFL.
- Label luôn hiển thị và lỗi có text để hỗ trợ khả năng truy cập; card Register cao hơn frame vì có xác nhận mật khẩu. Onboarding hiển thị trạng thái/review reason từ API và dùng ký tự đầu tên cho avatar thay vì ảnh Maya mẫu.

## Verification

Kiểm thử thực hiện bởi sub-agent GPT-6 Luna mức medium; triển khai bởi các sub-agent GPT-6 Luna mức extra high. Các kết quả lịch sử không được tính là evidence cho diff này.

| Kiểm tra | Kết quả hiện tại | Evidence |
| --- | --- | --- |
| Frontend ESLint | Pass, exit 0 | [Quality checks](../../outputs/issue-22-figma-layout/quality-checks.md) |
| TypeScript `tsc --noEmit` | Pass, exit 0 | Quality checks |
| Production `npm run build` | Pass, exit 0; sinh 12 trang tĩnh | Quality checks |
| Auth/File/Profile regression | Pass: 38 tests, 153 assertions | Quality checks; SQLite `:memory:` |
| Browser Auth / onboarding | Pass: 100/100 checks, 0 failure | [API browser report](../../outputs/issue-22-figma-layout/api-browser-report.json) |
| Viewport / labels / focus / IDs | Pass: 96/96 checks, 0 failure | [Responsive report](../../outputs/issue-22-figma-layout/report.json) |
| So sánh frame / ảnh browser | 42 PNG và trang đối chiếu Auth + 5 bước onboarding | [Comparison](../../outputs/issue-22-figma-layout/comparison.html) |

Môi trường: Docker Compose Node.js 22/Next.js 16.3.8, API Laravel tại `localhost:8000`, frontend `localhost:3000`, Mailpit `localhost:8025`; `NEXT_PUBLIC_DATA_MODE=api`. Backend regression sử dụng SQLite trong bộ nhớ cùng guard trong `tests/TestCase.php`, không chạy RefreshDatabase/migration trên PostgreSQL phát triển. Browser tạo tài khoản/file giả lập có prefix `issue22` trong môi trường local.

Các lỗi phát hiện trong lượt kiểm tra đã được sửa: effect đồng bộ không đạt lint, narrowing TypeScript của field errors, import CSS sai ở `/profile`, slogan hiển thị ký tự `\\n`, token nhập thủ công bị ẩn sau ký tự đầu, trùng ID wrapper/control, stepper khóa lại các bước đã hoàn tất và tự submit khi nút Next được React chuyển thành nút Submit. Next chặn default action trước khi chuyển bước; browser kiểm tra không có POST submit khi mở Xem lại và chỉ có một POST khi bấm gửi. Báo cáo quality giữ cả lần lỗi và lần chạy lại đạt.

Runner browser nằm trong `frontend/scripts/issue-22-browser.mjs` và `frontend/scripts/issue-22-api-browser.mjs`; dùng Playwright từ môi trường ngoài, không thêm dependency vào frontend. Chạy từ repo root với `PLAYWRIGHT_MODULE` và `TUTORLINK_CHROME_PATH` nếu chưa có Playwright/browser trong môi trường thông thường. Dùng `localhost` để giữ đúng cookie/Sanctum origin. Không ghi token/cookie/password vào evidence.

Browser kiểm tra thực tế đăng ký cả Customer/Tutor, mail verify/resend qua Mailpit, signed verify, login sai/đúng, forgot/reset qua email, login với mật khẩu mới, logout, `/me` 200→401 và mutation thiếu CSRF 419. Upload avatar/PDF chạy create 201 → complete 200 → attach 200/201. Một lỗi mạng ở complete được cố ý mô phỏng; retry dùng file cũ và các request hoàn tất/attach sau đó chạy trên API thật, không tạo file trùng và không mất headline chưa lưu. Submit được giữ chờ để kiểm tra disabled và hai lần click chỉ tạo một request. Admin test gửi request-changes bằng API thật trên hồ sơ test, rồi Tutor chỉnh sửa và gửi lại; Submitted vẫn khóa sau refresh.

## Đối chiếu acceptance criteria

| AC theo thứ tự trong issue | Kết quả / bằng chứng |
| --- | --- |
| 1. Link Figma và frame trong Issue/PR | Link và ID frame đã chuẩn bị trong tài liệu này; chưa đăng lên Issue/PR. |
| 2. Component/layout theo Figma, ghi khác biệt | Implemented, ảnh so sánh và khác biệt ở trên; cần Hoàng review giao diện. |
| 3. Auth/onboarding dùng component/token chung | `ui.tsx`, `globals.css`; onboarding dùng Button/Input/Select/Card/Field/Badge. |
| 4. Header/navigation/sidebar theo role | Browser kiểm Customer/Tutor, active navigation và menu mobile; Admin shell lấy role thật và được dùng khi login Admin test. |
| 5. 360px/768px/desktop không tràn ngang | Pass cho 5 route Auth và cả 5 bước onboarding ở 360/768/1536. |
| 6. Auth qua API thật | Pass: register, verify/resend, login, forgot/reset, logout. |
| 7. Cookie/session/CSRF | Pass: cookie session HttpOnly, XSRF cookie, `/me` 200/401, thiếu CSRF 419. |
| 8. Validation field và lỗi tổng thể | Shared Field liên kết error/control; browser kiểm bắt buộc và giới hạn avatar; regression kiểm 422/field errors. |
| 9. Loading/disabled ngăn submit trùng | Pass: giữ request chờ, button disabled và một POST submit cho hai click. |
| 10. Upload/complete/attach và xử lý lỗi | Pass avatar/PDF; lỗi mạng complete mô phỏng có retry cùng file. |
| 11. Submitted khóa; Changes Requested chỉnh/sửa gửi lại | Pass bằng state backend thật và kiểm sau refresh. |
| 12. Label, focus bàn phím và text status | Pass label/ID/focus/menu checks, status/error hiển thị bằng text. |
| 13. Lint/TypeScript/production build | Pass sau bản sửa cuối, xem quality checks. |
| 14. Ảnh đối chiếu và browser evidence | 42 PNG, hai báo cáo JSON đã che định danh verification, comparison HTML. |

## Giới hạn và bước chưa thực hiện

- `/api/v1/listings` trả 404 trong môi trường local khi Customer vào Marketplace. Đây là chức năng ngoài scope #22, được giữ nguyên và không dùng làm bằng chứng Marketplace đã hoàn thành.
- Screenshot được chụp trên dev server nên có biểu tượng Next.js dev indicator; không phải thành phần của giao diện production. Production build đã được kiểm riêng; chưa chạy lại toàn bộ browser suite trên production server.
- Chưa có CI remote, PR hoặc sign-off của Hoàng/Khanh. Không đánh dấu issue Accepted hay chuyển sang W1-B3 chỉ từ kết quả local.

## Source bàn giao

- Component/token/layout: `frontend/src/components/ui.tsx`, `app-shell.tsx`, `logout-button.tsx`, `frontend/src/app/globals.css`.
- Auth: các route `frontend/src/app/auth`, `auth-layout.tsx`, `auth-form.tsx`, `auth-support-form.tsx`, `auth.css`.
- Onboarding: `frontend/src/app/profile/page.tsx`, `frontend/src/components/tutor-onboarding-form.tsx`, `frontend/src/app/onboarding.css`.
- Asset/font cục bộ và nguồn: `frontend/public/figma/auth`, `frontend/public/fonts`.
- Runner/evidence: `frontend/scripts/issue-22-*.mjs`, `outputs/issue-22-figma-layout`.
- Cách dùng shared component/Admin shell: [UI design system](../07-architecture/ui-design-system.md).

Không thay backend, schema, API contract hoặc CSRF client. Các dirty changes có sẵn trong `docker-compose.yml`, `docs/setup.md`, `frontend/next.config.ts` được giữ nguyên, không gộp vào phần triển khai #22.

## Bàn giao và nghiệm thu

Shared components/token và Admin shell được bàn giao qua source và tài liệu `docs/07-architecture/ui-design-system.md`. Tài liệu này có thể dùng làm nội dung Issue/PR; chưa gửi bình luận, push, mở PR hay đóng issue.

Trạng thái đề nghị: Verified cho implementation và local QA; Issue/PR publication và reviewer decision: Pending. Chỉ reviewer/người nghiệm thu chuyển sang Accepted.
