# Business Rules

| Tài khoản: | Customer từ đủ 15 tuổi, Tutor từ đủ 18 tuổi; `UserRole` cố định `customer`/`tutor`/`admin`, `RegisterRole` chỉ `customer`/`tutor`. `UserStatus` gồm `active`/`suspended`/`deactivated`; chỉ `active` được thao tác cần tài khoản hoạt động. `deactivated` dành cho quy trình vô hiệu hóa riêng, không được dùng thay `suspended`; không mở quy trình này trong MVP nếu chưa được hỗ trợ. `EmailVerificationState` `verified`/`unverified` suy ra từ `email_verified_at`. |
| :---- | ----- |
| **Điều kiện Tutor:** | **Tutor đủ điều kiện quản lý Listing, tạo Program và gửi Invitation khi UserStatus active, EmailVerificationState verified và TutorProfileStatus approved; suspended không đủ điều kiện. Chỉ Customer đích là actor accept Invitation. Khi accept, server kiểm lại Customer và Tutor liên quan còn đủ điều kiện.![Sơ đồ image1](assets/business-rules-image1.png)** |
| **Hiển thị:** | **Listing chỉ công khai khi ListingStatus active, UserStatus active, email verified, hồ sơ approved và chuỗi catalog active. Điều kiện email verified cho public Listing/Profile được Dũng xác nhận ngày 18/10/2026. ListingStatus draft/active/inactive/archived; archive chuyển sang archived, không dùng cancelled. archived là lưu trữ cuối, không sửa/khôi phục. Mất điều kiện hiển thị không tự hủy Program.** |

# **Đình chỉ:** 

## 4.1. Tutor Profile Suspension

![Sơ đồ image2](assets/business-rules-image2.png)

## 4.2. Account Suspension

![Sơ đồ image3](assets/business-rules-image3.png)

## 4.3. Restoration

![Sơ đồ image4](assets/business-rules-image4.png)

**![Sơ đồ image5](assets/business-rules-image5.png)**

# **Workspace:** 

`![Sơ đồ image6](assets/business-rules-image6.png)`

## **Hoàn thành:** 

![Sơ đồ image7](assets/business-rules-image7.png)

# Tiền và phí:

* Tiền tệ sử dụng là VND; số tiền được lưu dưới dạng số nguyên không âm. Ví dụ: `200000` tương ứng `200.000 VND`.  
* Mỗi Program có tối đa một Fee Record cho mỗi tháng, được định danh bằng `program_id` và tháng theo định dạng `YYYY-MM`.  
* Fee Record do Tutor nhập thủ công; hệ thống không tự động tính Fee Record từ số Session.  
* Fee Record là thông tin phí được Tutor khai báo, không phải bằng chứng xác nhận thanh toán thực tế.

![Sơ đồ image8](assets/business-rules-image8.png)

**![Sơ đồ image9](assets/business-rules-image9.png)**

**![Sơ đồ image10](assets/business-rules-image10.png)**

**![Sơ đồ image11](assets/business-rules-image11.png)**

# State Consistency and authorization:

* Backend là nơi quyết định quyền truy cập, điều kiện nghiệp vụ và các hành động được phép; không được chỉ dựa vào kiểm tra phía Frontend.  
* Các chuyển trạng thái không hợp lệ phải bị từ chối mà không gây tác động phụ.  
* Các thao tác dựa trên dữ liệu lỗi thời hoặc xung đột trạng thái phải bị từ chối mà không ghi đè dữ liệu mới hơn.  
* Những quy tắc về transaction, tính duy nhất, xử lý đồng thời và mã lỗi cụ thể được đặc tả thêm trong tài liệu thiết kế kỹ thuật/API.  
* Session thực hiện: program\_sessions là lịch dự kiến, không tự chứng minh buổi học đã diễn ra. Chỉ Tutor owner mới xác nhận Hoàn tất; trong cùng transaction, Session chuyển completed và tạo tối đa một lesson\_sessions. Hủy chuyển trạng thái lịch sang cancelled, không tạo kết quả thực hiện. Chỉ đếm lesson\_sessions đã hoàn tất trong số buổi đã thực hiện.  
* F03 — Quy tắc assignment Admin  
* Tutor Profile và Report có tối đa một Admin hiện được giao; giá trị NULL nghĩa là chưa phân công. Coordinator cần admin.tutor\_profiles.assign hoặc admin.reports.assign. Đích giao phải là Admin nội bộ active, có quyền xử lý tương ứng. Hợp đồng hiện nhận assigned\_admin\_id và máy chủ phải kiểm tra đích; nhưng chưa chốt API để giao diện liệt kê/chọn Admin đủ điều kiện. GET /api/v1/admin/users trong Catalog vẫn là conditional, không được coi là directory đã duyệt. Assignment không cấp thêm quyền: xem chi tiết, quyết định, tải verification document và đọc Report evidence lần lượt cần permission riêng cùng assignment hiện tại. Assignment change/revoke cập nhật đồng thời actor, thời gian, lý do, version và audit; version sai trả conflict và không ghi đè. Reassignment làm mất scope của Admin cũ từ request tiếp theo. Lọc scope trước count/pagination. Evidence chỉ theo allow-list tối thiểu của resource; quyền sau khi Report đóng và assignment khi Tutor gửi lại vẫn là câu hỏi mở. M02-F08 suspend/restore giữ permission hiện có; F03 không thêm assignment gate  
* Nhóm 4 — Eligibility và visibility (đề xuất để Product chốt)  
* Tin dịch vụ tạo/đăng: Tutor owner, User active, email verified, Profile approved và không suspended. Public visibility cũng bắt buộc email verified theo xác nhận ngày 18/10/2026, cùng Listing active và catalog chain active. Khôi phục Account/Profile không đổi ListingStatus. Cách Listing active hiện lại hay phải publish lại sau restore vẫn là đề xuất chờ Product; không coi xác nhận gate email là quyết định restore.  
* Availability: API hiện yêu cầu Tutor active và Profile không suspended; Product chưa chốt email verified/Profile approved có phải gate quản lý availability hay không (M03-F09). Không mở rộng quyền từ các gate Listing.  
* Conversation first-contact: M04-F01 còn chờ chốt actor khởi tạo, eligibility, Listing active/context và cách xử lý suspended/reopen; giữ D11 blocking cho tới khi Product quyết định.  
* Announcement: M08-F02 quy định Program Active cho phép create/publish và Paused/terminal chặn create/publish; Domain mô tả rõ việc chặn tạo mới nhưng chưa làm rõ publish draft khi Paused. Cần Product xác nhận rồi đồng bộ M08-F02, Domain và contract.  
* Admin moderation Listing: F14 vẫn conditional; chưa thêm moderation state/permission/cột DB hoặc bật endpoint trước khi Product chốt model, scope, reason/audit, restore và appeal.

* # F19 — Sổ đăng ký quy tắc và truy vết

* Quy ước: ID là tham chiếu ổn định giữa PRD và tài liệu nguồn. Các nội dung dưới đây là thiết kế nháp, không xác nhận Product đã duyệt hoặc code đã chạy. Mọi ghi nhận current-state cần ngày và commit kiểm chứng; nếu thiếu, đánh dấu chưa xác minh và chưa gán owner.  
* ADR-09 — Thứ tự khóa khi chấp nhận Invitation (Draft; owner kỹ thuật chưa gán): M05-F03 hiện ghi Customer User → Tutor Profile → Program → Invitation. Đây chưa là giao thức concurrency hoàn chỉnh cho accept cạnh tranh đình chỉ riêng tài khoản Tutor, vì users.status và tutor\_profiles là hai row khác nhau. Owner kỹ thuật phải xác nhận shared locking/isolation và test PostgreSQL trước phát hành; không kết luận runtime có lỗi hoặc tự thêm thứ tự khóa/cột mới. Mutation/audit/outbox ghi cùng transaction; worker chỉ xử lý sau commit.  
* BR-10 — Quyền ghi khi Program Paused (Draft; Product owner chưa gán): vẫn cho đọc; chặn tạo Session, Task, Announcement và Fee Record mới. Chỉ cho phép thao tác tiếp diễn nếu PRD chức năng nêu rõ; hiện các tài liệu nêu việc nộp/cập nhật Submission và ghi Progress. Program Completed/Cancelled readonly.  
* Đã được Dũng xác nhận ngày 18/10/2026: khi Program Paused, Tutor owner vẫn được hoàn tất hoặc hủy Session đã lên lịch theo transition hợp lệ; không tạo Session mới. Program Completed/Cancelled cấm chuyển trạng thái Session mới; replay chỉ trả kết quả hợp lệ đã lưu, không ghi lần hai. Còn chờ chốt riêng: sửa lịch Session khi Paused và publish Announcement draft khi Paused; không suy quyền từ complete/cancel sang edit/publish.  
* Traceability hiện có: ADR-09 → M05-F03; BR-10 → M05-F06, M06-F01, M07-F02, M07-F04. Liên kết API/DB/migration/test và bằng chứng commit chưa được xác nhận trong lượt Drive-only này; các PRD/index phải giữ trạng thái draft/unverified đến khi owner bổ sung bằng chứng.
