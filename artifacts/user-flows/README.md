# TutorLink — rà soát luồng người dùng

Ngày rà soát: 12/11/2026. Bám theo code trong workspace; các khối màu cam là đề xuất, chưa phải thay đổi đã áp dụng. Không dùng dữ liệu cá nhân hay nội dung cuộc trò chuyện thật.

File `tutorlink-user-flows.drawio` gồm ba trang chỉnh sửa được: Tutor, Học viên, Admin. Trang đầu giữ ID `ASlQo1yBcWQfyRQkByOP` để liên kết draw.io ban đầu vẫn mở đúng. File Drive ban đầu chỉ có một trang rỗng.

## Điểm nên quyết định trước khi thiết kế tiếp

| Vị trí | Hiện trạng | Đề xuất |
| --- | --- | --- |
| Duyệt hồ sơ admin | Frontend `/admin`, backend Filament `/admin`, server `/admin-business/assigned-profiles` cùng có nghiệp vụ duyệt hồ sơ | Ưu tiên chọn một giao diện chính. Filament đã có thêm báo cáo, review và Operations; có thể dùng làm admin chính cho demo. Chỉ ngừng giao diện còn lại sau khi kiểm tra phụ thuộc và được người dùng chấp thuận. |
| Lời mời | `/invitations` có xem trước và phản hồi; `/invitations/{id}` lại trình bày chi tiết | Có thể dùng một component chi tiết cho panel/drawer và deep link, tránh thiết kế hai giao diện gần giống. |
| Dịch vụ của tôi | Danh sách → chi tiết `/me/listings/{id}` → chỉnh sửa | Cân nhắc chuyển màn hình chỉ xem thành drawer/preview trong danh sách; giữ trang chỉnh sửa. |
| Review tutor | `/tutors/{slug}/reviews` redirect về `?tab=reviews` | Đã gộp; không cần thiết kế lại trang review cũ. API review riêng vẫn cần để lấy dữ liệu. |
| Quản lý giảng dạy | Dịch vụ và Chương trình học đã dùng chung một mục menu | Giữ hai tab con. Dịch vụ là nội dung chào bán; chương trình là thỏa thuận và không gian học của một học viên. |
| Lịch | `/me/availability` là khả dụng tham khảo; workspace sessions là lịch buổi học thực tế | Giữ nghiệp vụ riêng, dùng chung thành phần giao diện khi phù hợp. |
| Thông báo | `/notifications` là sự kiện tài khoản; workspace announcements là nội dung tutor đăng cho chương trình | Giữ riêng dữ liệu và quyền; có thể làm nhãn rõ hơn. |

## Nguồn kiểm chứng trong dự án

- `frontend/src/app/`: danh sách route hiện có, bao gồm redirect review cũ.
- `frontend/src/components/app-shell.tsx`: menu theo vai trò, avatar và header.
- `frontend/src/components/teaching-management.tsx`: hai tab Dịch vụ / Chương trình học.
- `frontend/src/components/tutor-onboarding-form.tsx`: năm bước hồ sơ và yêu cầu tài liệu tùy cấu hình.
- `frontend/src/components/programs.tsx`: lưu bản nháp, route sau khi lưu, gửi lời mời, trạng thái chương trình.
- `frontend/src/components/invitations.tsx`: inbox có preview, chi tiết riêng, phản hồi và hủy lời mời.
- `frontend/src/components/messaging.tsx`: liên hệ tutor, tạo chương trình từ cuộc trò chuyện.
- `frontend/src/components/workspace-tabs.tsx`, `task-workspace.tsx`, `trust-workspace.tsx`: quyền làm việc theo trạng thái và vai trò; review khi đủ điều kiện.
- `frontend/src/app/admin/page.tsx`: nhận hồ sơ, xét tài liệu, quyết định có lý do.
- `frontend/src/app/monitoring/page.tsx`: Telescope, Pulse, dashboard máy chủ tùy cấu hình.
- `backend/app/Support/TutorEligibility.php`: tài khoản active, email xác minh, hồ sơ approved, không suspended; tài liệu khi cấu hình yêu cầu.
- `backend/app/Providers/Filament/AdminPanelProvider.php`, `backend/app/Filament/Resources/`, `backend/app/Filament/Pages/Operations.php`: admin backend có hồ sơ, báo cáo, review và thao tác điều hành.
- `backend/routes/web.php`: giao diện admin-business còn tồn tại và có điều kiện bật panel / quyền.
- `backend/routes/api.php`: API báo cáo, review, audit, workspace, lời mời.

## Những giới hạn cần hiểu đúng

- Sơ đồ là luồng nghiệp vụ và điểm vào màn hình, không phải danh sách mọi API hay mọi trạng thái lỗi.
- Một hộp có thể là thao tác hoặc tab, không nhất thiết là trang độc lập. Màu và chú thích phân biệt rõ.
- Đăng dịch vụ không tự tạo chương trình; tutor phải chọn học viên và gửi lời mời. Học viên chấp nhận mới kích hoạt chương trình.
- Lịch trên hồ sơ tutor không phải xác nhận đặt lịch tự động.
- Tab học phí hiện dùng để ghi nhận khoản phí và dấu đã thanh toán, không phải cổng thanh toán trực tuyến.
- Đề xuất gộp trang không đồng nghĩa bỏ route deep link, quyền backend hay dấu vết kiểm duyệt.
- Danh sách sáu tab workspace được nhóm trong cùng một khu vực. Không có ý chỉ mũi tên vào một tab là bắt buộc phải mở tab đó trước các tab khác.
