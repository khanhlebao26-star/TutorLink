# Issue22 — cập nhật theo Figma full-width

Issue [#22](https://github.com/khanhlebao26-star/TutorLink/issues/22), branch `feature/W1-B1-B2-figma-layout`, baseline `d773195c6c3e9c0e5444f2ecc3762e280010a443`. Bản cập nhật ngày13/10/2026 theo [Figma do người dùng cung cấp](https://www.figma.com/design/RDb5CBbqPOxuwX4UQ26Kfz/TutorLink-%25E2%2580%2594-Desktop-Tutor-Platform?node-id=12-3&p=f&t=hQaHRWAztFDawzt6-0) và PNG mới trong `docs/FE-figmaDesign`.

## Thiết kế và phạm vi

Header/sidebar/component chung, năm route Auth, và năm bước Tutor onboarding được chỉnh theo bố cục mới. Khung ứng dụng phủ ngang viewport; form Auth giữ độ rộng đọc được. Profile có inset15px sau sidebar180px, header tài khoản61px, heading đậm, khoảng cách và control gọn hơn. Các bước1–4 dùng panel full-width; Xem lại dùng summary với status aside320px. Header dùng ký tự đầu tên từ phiên thật; không dùng ảnh Maya trong thiết kế làm tài khoản người dùng.

Nguồn đo đạc: [Design specification](../../outputs/issue-22-redesign-2026-10-13/design-spec.md). Các PNG đã được snapshot riêng trong `outputs/issue-22-redesign-2026-10-13/references`, giữ bằng chứng ổn định nếu thiết kế tiếp tục thay đổi. Figma MCP báo hết hạn mức ngay ở lần đọc mới đầu tiên; các ID dưới đây là ID lịch sử, chưa xác minh lại với file hiện tại.

| Route/bước | Frame lịch sử | PNG tham chiếu |
| --- | --- | --- |
| `/auth/login` | `53:125` | `Login page.png` |
| `/auth/register`, Customer/Tutor | `52:2` | `Register page.png` |
| `/auth/forgot-password` | `57:952` | `Forgot password page.png` |
| `/auth/reset-password` | `58:1010` | `Reset password.png` |
| `/auth/verify-email`, Verify/Resend | `58:1086` | `Verify Password.png` |
| `/profile`, Thông tin cơ bản | `86:3` | `Tutor Dashboard-thongtincoban.png` |
| `/profile`, Chuyên môn | `86:166` | `Tutor Dashboard-chuyenmon.png` |
| `/profile`, Kinh nghiệm | `86:334` | `Tutor Dashboard-kinhnghiem.png` |
| `/profile`, Tài liệu xác minh | `86:489` | `Tutor Dashboard-tailieuxacminh.png` |
| `/profile`, Xem lại | `86:670` | `Tutor Dashboard-xemlai.png` |
| Header/sidebar Customer, Admin shell | Dùng component chung | `Customer Dasboard.png` và PNG Tutor |

## Khác biệt có chủ đích

- Giữ xác nhận mật khẩu, email và token phục hồi theo API hiện có; card có thể cao hơn bản minh họa. Liên kết đặt lại hợp lệ truyền token qua URL; fallback nhập token thủ công vẫn hoạt động.
- Onboarding chỉ lưu headline, bio, specialization, experience_years, avatar và verification document. Không thêm video, ngôn ngữ/quốc gia, học phí, nhiều bằng cấp hoặc chức năng dashboard chưa có API.
- Avatar tối đa2MiB theo backend, tài liệu tối đa10MiB. Không thêm nút xóa tệp đã attach hoặc progress phần trăm giả.
- Điều hướng chỉ có route đang hỗ trợ. Không thêm search/help/favorite/notification hoặc About giả để lấp chỗ trong header.
- Login/Register/Verify dùng asset gốc đã lưu cục bộ. Chưa lấy được artwork3D gốc của Forgot/Reset do hạn mức Figma; artwork CSS có sẵn còn khác thiết kế. ID frame hiện tại và font slogan chưa được xác minh trực tiếp.
- Public header dùng component chung cao76px; PNG Register cao84px. Card Register vẫn nằm tại y102px theo ảnh. Slogan dùng Inter italic cục bộ vì chưa xác minh được font viết tay gốc; card Login cao hơn mẫu khoảng8px.
- Card Register cuối cao593.8px, hơn mẫu khoảng46px do field xác nhận bổ sung; nút đăng ký kết thúc ở670.8px, nằm trọn trong viewport681px. Date dùng affordance calendar native; asset lịch sử mang tên `calendar.svg` chứa hình envelope nên không dùng ở field ngày sinh.
- PNG Tutor có viền/shadow ngoài vùng ứng dụng; FE full-width bỏ viền export này. Register cần thêm field theo contract nên không cam kết pixel-perfect toàn màn hình.

## Cách dùng và bàn giao

Shared `Button`, `Input`, `Select`, `Card`, `Badge`, `Field`, `StatePanel` giữ API hiện có. Token/header/sidebar ở `globals.css` và `app-shell.tsx`; màn hình dùng CSS riêng có prefix Auth/onboarding. Hướng dẫn và ví dụ: [UI design system](../07-architecture/ui-design-system.md). Admin tiếp tục dùng shell chung; nghiệp vụ duyệt hồ sơ không đổi.

Phân tích: GPT‑6.1 Sol Medium. Foundation và Screen Builders: GPT‑6 Luna Extra High (công cụ không có GPT‑6.1 Luna). Visual QA: GPT‑6 Luna Extra High. Điều phối viên tích hợp source và kiểm tra tổng.

## Bằng chứng kiểm tra

[Quality checks](../../outputs/issue-22-redesign-2026-10-13/quality-checks.md) ghi kết quả chạy mới. Các thư mục `issue-22-figma-layout` và `full-width-layout-2026-10-13` là bằng chứng lịch sử, không dùng làm pass cho bản cập nhật này.

| Kiểm tra | Kết quả cuối | Bằng chứng |
| --- | --- | --- |
| Lint / TypeScript / production build | PASS, exit0; lint không warning | Quality checks |
| Backend Auth/File/Profile | 38/38 tests, 153 assertions; SQLite bộ nhớ | Quality checks |
| Auth responsive/labels/focus/menu | 96/96 checks | [Auth report](../../outputs/issue-22-redesign-2026-10-13/report.json) |
| Auth/onboarding bằng API thật | 100/100 checks | [API report](../../outputs/issue-22-redesign-2026-10-13/api-browser-report.json) |
| Visual/role/error recovery | 231/231 checks | [Visual report](../../outputs/issue-22-redesign-2026-10-13/visual-report.json), [QA notes](../../outputs/issue-22-redesign-2026-10-13/visual-qa.md) |
| Ảnh Figma/browser | Năm Auth và năm bước onboarding; đúng viewport tham chiếu và responsive | [Trang đối chiếu](../../outputs/issue-22-redesign-2026-10-13/visual-comparison.html) |

Đã kiểm tra360/768/1536/2560px và viewport nội bộ của PNG Tutor rộng2034px. Panel Basic bắt đầu y240.9 so với y239 sau loại viền export; input38px, textarea78px; Review summary1490px, gap14px, aside320px. Đối chiếu trực tiếp các ảnh bởi Visual QA và điều phối viên; không tuyên bố pixel-perfect với toàn bộ file Figma.

API thật được kiểm: register Customer/Tutor, Mailpit verify/resend, login sai/đúng, forgot/reset qua email, logout và cookie/session/CSRF, upload→complete→attach, retry lỗi mạng mà không tạo file trùng, Next4→Review không tự submit, hai click chỉ gửi một POST, Submitted khóa sau refresh, Changes Requested sửa và gửi lại. Kiểm tra thêm token nhập thủ công không biến mất, lỗi email trên link reset mở field để sửa, và xác minh thành công chỉ còn một CTA đăng nhập.

## Acceptance criteria

| AC trong issue | Tình trạng |
| --- | --- |
| 1. Link/frame trong Issue/PR | Chuẩn bị tại tài liệu và PR description; chưa đăng lên GitHub. Frame ID mới chưa đọc lại được do quota. |
| 2–3. Bám design, ghi khác biệt, component/token chung | Đã triển khai; ảnh đối chiếu và khác biệt ở trên; cần reviewer sign-off. |
| 4–5. Shell theo role và responsive | PASS;231 visual/role checks,96 Auth checks. |
| 6–7. Auth API thật, session/cookie/CSRF | PASS;100 API checks. |
| 8–9. Validation/lỗi rõ, loading/disabled ngăn gửi trùng | PASS;field labels/errors, email/token recovery, pending/double-click assertions. |
| 10–11. Upload/retry, lock/resubmit theo backend | PASS;API flow thực và hồi quy backend. |
| 12. Label, keyboard focus, trạng thái bằng text | PASS;Auth và năm bước onboarding có label/ID/focus; lifecycle list chỉ đọc. |
| 13–14. Lint/TS/build, ảnh và browser evidence | PASS;quality checks và bộ ảnh mới. |

Môi trường localhost Docker Compose, Next.js16.3.8/Node.js22, Laravel API8000 và Mailpit8025. Browser dùng tài khoản/tệp giả lập riêng. Marketplace `/api/v1/listings` vẫn thiếu API và ngoài phạm vi#22; không lấy render lỗi làm bằng chứng hoàn thành Marketplace. Screenshot dùng dev server; production build kiểm riêng. Backend/schema/API contract và CSRF client không đổi.

Trạng thái đề nghị: **Verified local**. GitHub publication và nghiệm thu của reviewer còn Pending.

Reviewer giao diện: Hoàng; reviewer API: Khanh. Link/frame và nội dung bàn giao được chuẩn bị tại đây; chưa đăng bình luận Issue/PR hoặc nhận sign-off. Chỉ reviewer/người nghiệm thu chuyển trạng thái sang Accepted.
