# API Route Catalog

Danh mục **method/path chuẩn cho API đích** của TutorLink. Giữ toàn bộ route trong bảng, gồm 7 route D23; không hoãn hoặc loại route nào khỏi phạm vi. Khi PRD mô tả API đích hoặc code triển khai route mới, dùng method/path tại Catalog. Cột “Mục đích” chỉ tóm tắt chức năng; điều kiện nghiệp vụ nằm trong PRD và Business Rules. [OpenAPI sinh bởi Scramble](../api/README.md) là contract của API hiện được đăng ký trong code, dùng để tích hợp. Catalog không phải contract request/response và không tự trở thành API khả dụng.

Trạng thái đối chiếu ngày 20/10/2026 với `docs/api/openapi.json`:

- **Đã đăng ký:** method/path xuất hiện trong OpenAPI sinh từ code. Riêng `GET /sanctum/csrf-cookie` do Sanctum đăng ký ngoài `/api/v1`, nên không nằm trong artifact Scramble. Trạng thái này không xác nhận hành vi, quyền hay dữ liệu đã đạt PRD.
- **Dự kiến:** method/path thuộc phạm vi API đích nhưng chưa xuất hiện trong OpenAPI sinh từ code; đã được mô tả theo quyết định hiện hành ở Business Rules/PRD. Nhãn này không có nghĩa là hoãn route.

Khi triển khai, kiểm quyền và quy tắc trong code, thêm kiểm thử phù hợp, sinh lại OpenAPI bằng Scramble, rồi đối chiếu method/path để cập nhật cột trạng thái. Không sửa JSON sinh ra bằng tay. Quy tắc Announcement khi Program Paused đã được chốt tại [BR-10](../Domain%20Documentation/Business%20Rules.md): Tutor owner được tạo, sửa Draft và publish khi Active/Paused; các route bên dưới vẫn **Dự kiến** cho đến khi backend triển khai.

Chuẩn mặc định đã chốt tại BR-26: JSON error `{code,message,request_id,errors?}`, `X-Request-ID`, timestamp UTC RFC 3339, tiền VND integer; page 20, tối đa 50. Cursor opaque/scoped áp dụng cho Message và Notification; ngoại lệ được ghi tại route. Không phát hành một response DTO chung thay cho allow-list theo resource.

Các route mutation Session, Task, Fee Record và Announcement tuân version guard của [BR-19](../Domain%20Documentation/Business%20Rules.md): response chứa `version`; PATCH/action POST gửi `expected_version`; stale trả 409 mà không ghi một phần. PUT Submission tiếp tục dùng `expected_submission_version` cùng Idempotency-Key theo D13.

| API | Mục đích thiết kế đích | Trạng thái |
| :---- | :---- | :---- |
| **01 Tài khoản** |  |  |
| GET /sanctum/csrf-cookie | Khởi tạo CSRF cho SPA Quyền: Public | Đã đăng ký |
| POST /api/v1/auth/register | Đăng ký Customer ≥15 hoặc Tutor ≥18, đồng ý điều khoản hiện hành, tạo tài khoản unverified nhưng không tạo phiên; runtime còn lệch tuổi/điều khoản/auto-login. Quyền: Public | Đã đăng ký |
| POST /api/v1/auth/login | Đăng nhập bằng cookie chỉ sau xác minh email; runtime chưa chặn unverified. Quyền: Public | Đã đăng ký |
| POST /api/v1/auth/logout | Đăng xuất, hủy phiên Quyền: Đăng nhập | Đã đăng ký |
| POST /api/v1/auth/forgot-password | Gửi link đặt lại mật khẩu Quyền: Public | Đã đăng ký |
| POST /api/v1/auth/reset-password | Đổi mật khẩu bằng token hợp lệ, thu hồi các phiên cũ theo quy tắc đích; runtime chưa thu hồi session. Quyền: Public | Đã đăng ký |
| GET /api/v1/auth/email/verify/{id}/{hash} | Xác minh email qua signed URL Quyền: Chủ email | Đã đăng ký |
| POST /api/v1/auth/email/verification-notification | Gửi lại email xác minh cho tài khoản unverified, phản hồi trung tính và rate limit; runtime vẫn yêu cầu session. Quyền đích: Public | Đã đăng ký |
| GET /api/v1/me | Đọc User/role/trạng thái của mình; Customer Profile đọc qua route riêng Quyền: Chủ tài khoản | Đã đăng ký |
| PATCH /api/v1/me | Sửa full_name/ngày sinh từ màn hình hồ sơ; tuổi Customer ≥15, Tutor ≥18. Runtime hiện áp tuổi 18 cho cả hai. Quyền: Chủ tài khoản | Đã đăng ký |
| POST /api/v1/me/tokens | Tạo PAT cho CLI/server-to-server; chỉ lộ plaintext một lần, abilities allow-list, hết hạn 1–90 ngày. Session cookie + CSRF. Quyền: Chủ tài khoản active | Đã đăng ký |
| GET /api/v1/me/tokens | Liệt kê metadata PAT của chính chủ, không gồm secret. Chỉ session cookie. Quyền: Chủ tài khoản active | Đã đăng ký |
| DELETE /api/v1/me/tokens/{tokenId} | Thu hồi PAT thuộc chính chủ; không cho PAT khác quản lý token. Chỉ session cookie + CSRF. Quyền: Chủ tài khoản active | Đã đăng ký |
| GET /api/v1/customer/profile | Đọc Customer Profile riêng; chưa có row trả `profile: null` và không tạo row. Quyền: Customer active, owner | Dự kiến |
| PUT /api/v1/customer/profile | Upsert field tùy chọn; omission giữ nguyên, `null` gỡ field/avatar nullable; avatar là File complete đúng owner/purpose. Quyền: Customer active, owner; cookie + CSRF | Dự kiến |
| **02 Hồ sơ Tutor và xét duyệt** |  |  |
| GET /api/v1/tutor/profile | Đọc hồ sơ/bản approved và revision đang xử lý theo BR-16; runtime chưa có revision Quyền: Tutor | Đã đăng ký |
| PUT /api/v1/tutor/profile | Lưu Draft/Changes Requested hoặc revision riêng của Profile approved; runtime chưa có revision Quyền: Tutor | Đã đăng ký |
| PUT /api/v1/tutor/profile/avatar | Gắn File avatar đã complete Quyền: Tutor | Đã đăng ký |
| POST /api/v1/tutor/profile/verification-documents | Gắn tài liệu xác minh và document\_type Quyền: Tutor | Đã đăng ký |
| POST /api/v1/tutor/profile/submit | Gửi/gửi lại hồ sơ hoặc revision approved; checklist identity/document và assignment theo BR-16, runtime chưa đủ Quyền: Tutor | Đã đăng ký |
| GET /api/v1/admin/tutor-profiles | Danh sách hồ sơ theo trạng thái Quyền: Admin đúng quyền | Đã đăng ký |
| GET /api/v1/admin/tutor-profiles/{tutorProfile} | Chi tiết hồ sơ trong phạm vi xét duyệt Quyền: Admin đúng quyền | Đã đăng ký |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/approve | Duyệt hồ sơ khi giấy tờ bắt buộc approved/còn hạn; với Profile approved quyết định revision submitted và thay bản công khai nguyên tử theo BR-16, runtime chưa đủ Quyền: Admin có `admin.tutor_profiles.decide` và assignment hiện tại | Đã đăng ký |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/request-changes | Yêu cầu sửa hồ sơ/revision submitted, giữ bản approved cũ khi xét revision; runtime chưa có revision Quyền: Admin có `admin.tutor_profiles.decide` và assignment hiện tại | Đã đăng ký |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/reject | Từ chối hồ sơ/revision submitted, reason/audit; không tự nộp lại, runtime chưa có revision Quyền: Admin có `admin.tutor_profiles.decide` và assignment hiện tại | Đã đăng ký |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/suspend | Đình chỉ hồ sơ và account theo policy Quyền: Admin có đủ hai quyền | Đã đăng ký |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/restore | Khôi phục đúng trạng thái hồ sơ trước đình chỉ Quyền: Admin đúng quyền | Đã đăng ký |
| PUT /api/v1/admin/tutor-profiles/{tutorProfile}/assignment | Giao/chuyển/thu hồi Admin phụ trách hồ sơ Tutor; quyền admin.tutor_profiles.assign | Dự kiến |
| POST /api/v1/admin/users/{user}/suspend | Đình chỉ tài khoản Quyền: Admin đúng quyền | Đã đăng ký |
| POST /api/v1/admin/users/{user}/restore | Mở lại account suspended, không deactivated Quyền: Admin đúng quyền | Đã đăng ký |
| POST /api/v1/admin/verification-documents/{document}/approve | Duyệt document pending, audit; Admin active có `admin.verification_documents.decide` và assignment Profile hiện tại theo BR-16 | Dự kiến |
| POST /api/v1/admin/verification-documents/{document}/reject | Từ chối document pending với reason bắt buộc/audit; Admin active có `admin.verification_documents.decide` và assignment Profile hiện tại theo BR-16 | Dự kiến |
| **03 Catalog và khám phá** |  |  |
| GET /api/v1/catalog/categories | Đọc cây danh mục công khai Quyền: Public | Đã đăng ký |
| GET /api/v1/catalog/specializations | Đọc/lọc chuyên môn đang hiển thị Quyền: Public | Đã đăng ký |
| GET /api/v1/listings | Tìm/lọc/sắp xếp/phân trang Listing công khai Quyền: Public | Đã đăng ký |
| GET /api/v1/listings/{slug} | Xem Listing đủ điều kiện public Quyền: Public | Đã đăng ký |
| GET /api/v1/tutors/{slug} | Hồ sơ public theo D06 allow-list, avatar hợp lệ; Listing nhúng 10/trang với listings_page và listings_meta, slug ổn định. Runtime còn thiếu email gate/giới hạn/avatar/DTO. Quyền: Public | Đã đăng ký |
| GET /api/v1/tutors/{slug}/reviews | Review visible hợp lệ và thống kê sao Quyền: Public | Đã đăng ký |
| **04 Listing và lịch rảnh** |  |  |
| GET /api/v1/me/listings | Danh sách/count Listing của owner Quyền: Tutor | Đã đăng ký |
| POST /api/v1/me/listings | Tạo Listing draft Quyền: Tutor đủ điều kiện | Đã đăng ký |
| GET /api/v1/me/listings/{listing} | Chi tiết Listing của mình Quyền: Tutor owner | Đã đăng ký |
| PATCH /api/v1/me/listings/{listing} | Sửa nội dung; active revalidate, audit rồi hiện công khai ngay; không tùy ý đổi status đích. Runtime còn thiếu audit/gate đích Quyền: Tutor owner | Đã đăng ký |
| POST /api/v1/me/listings/{listing}/publish | draft/inactive → active, kiểm lại eligibility Quyền: Tutor owner | Dự kiến |
| POST /api/v1/me/listings/{listing}/pause | active → inactive Quyền: Tutor owner | Dự kiến |
| DELETE /api/v1/me/listings/{listing} | Archive mềm → archived, không hard-delete Quyền: Tutor owner | Đã đăng ký |
| GET /api/v1/me/availability-rules | Đọc lịch rảnh riêng; không cần Profile approved Quyền: Tutor active, verified, Profile không suspended | Đã đăng ký |
| PUT /api/v1/me/availability-rules | Thay bộ lịch rảnh tham khảo, không booking; không cần Profile approved Quyền: Tutor active, verified, Profile không suspended | Đã đăng ký |
| **05 File** |  |  |
| POST /api/v1/files | Upload private; giới hạn theo purpose BR-20/BR-21; pending được quarantine đến scan clean Quyền: Chủ upload | Đã đăng ký |
| GET /api/v1/files/{file} | Đọc metadata theo policy Quyền: Người được phép | Đã đăng ký |
| POST /api/v1/files/{file}/complete | Kiểm object/MIME/size/chữ ký/checksum và kết quả malware scanner; lỗi scanner giữ pending Quyền: Chủ upload | Đã đăng ký |
| POST /api/v1/files/{file}/links | Gắn đúng owner/purpose/resource Quyền: Chủ upload đúng quyền | Đã đăng ký |
| GET /api/v1/files/{file}/download | Tải theo quyền tài nguyên Quyền: Người được phép | Đã đăng ký |
| DELETE /api/v1/files/{file} | Xóa tệp chưa liên kết, không phá lịch sử Quyền: Chủ upload | Đã đăng ký |
| **06 Chat** |  |  |
| POST /api/v1/conversations | Find-or-create cặp Customer–Tutor theo `peer_user_id`, `context_listing_id` tùy chọn; response peer tối thiểu, context lần tạo đầu tiên bất biến theo BR-17 Quyền: Customer/Tutor đủ gate M04-F01 | Dự kiến |
| GET /api/v1/conversations | Theo membership; sort message mới nhất `(created_at,id)` giảm dần, chat rỗng theo `(created_at,id)`; preview allow-list, unread đếm message peer sau read cursor (không tính system), read receipt chỉ ở mức Conversation; phân trang cursor ổn định. Member active vẫn đọc lịch sử khi peer bị đình chỉ Quyền: Thành viên active | Dự kiến |
| GET /api/v1/conversations/{conversation} | Chi tiết/peer/context gốc trong quyền, cancelled chỉ đọc Quyền: Thành viên active | Dự kiến |
| GET /api/v1/conversations/{conversation}/messages | Đọc/poll theo cursor; member active đọc lịch sử khi peer bị đình chỉ hoặc chat cancelled Quyền: Thành viên active | Dự kiến |
| POST /api/v1/conversations/{conversation}/messages | Text không trắng, tối đa 4.000 ký tự; chuẩn hóa newline CRLF/CR thành LF. Cùng sender/key/payload canonical trả bản ghi cũ; key cùng sender nhưng payload khác trả 409. 30 message mới/phút/người dùng, retry không tính quota; 429 kèm Retry-After. Không gửi khi peer bị đình chỉ hoặc Conversation cancelled. File: một tệp/message, 5 MiB, PDF/JPG/PNG/DOC/DOCX, complete + scan clean, gắn nguyên tử. Quyền: Thành viên đủ điều kiện theo BR-21 | Dự kiến |
| PATCH /api/v1/conversations/{conversation}/read | Cập nhật read cursor chỉ tiến lên Quyền: Thành viên | Dự kiến |
| **07 Program và Invitation** |  |  |
| GET /api/v1/programs | Danh sách Program theo actor/state Quyền: Tutor owner/Customer member | Dự kiến |
| POST /api/v1/programs | Tạo Draft cho Customer đích Quyền: Tutor đủ điều kiện | Dự kiến |
| GET /api/v1/programs/{program} | Chi tiết thỏa thuận và allowed\_actions Quyền: Owner/member | Dự kiến |
| PATCH /api/v1/programs/{program} | Sửa Draft khi không có Invitation pending; bắt buộc `expected_version`, thành công tăng version Quyền: Tutor owner | Dự kiến |
| POST /api/v1/programs/{program}/invitations | Gửi snapshot theo `expected_version`, Draft pending bị khóa; expires_at = sent_at + 72 giờ UTC Quyền: Tutor owner | Dự kiến |
| GET /api/v1/invitations | Inbox/outbox theo người được phép Quyền: Customer đích/Tutor owner | Dự kiến |
| GET /api/v1/invitations/{invitation} | Đọc snapshot trước khi trở thành member Quyền: Customer đích/Tutor owner | Dự kiến |
| POST /api/v1/invitations/{invitation}/accept | Atomic accepted + membership + active; chỉ trước expires_at, kiểm Program.version; 409 khi source Listing đang hold; sau khi gỡ hold có thể accept nếu còn hạn và các gate Customer/Tutor đạt, không cần Listing active lại Quyền: Customer đích | Dự kiến |
| POST /api/v1/invitations/{invitation}/reject | Từ chối Pending còn hiệu lực Quyền: Customer đích | Dự kiến |
| POST /api/v1/invitations/{invitation}/cancel | Thu hồi Pending Quyền: Tutor owner | Dự kiến |
| GET /api/v1/programs/{program}/members | Đọc thành viên, không mở CRUD membership Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/pause | active → paused Quyền: Tutor owner | Dự kiến |
| POST /api/v1/programs/{program}/resume | paused → active Quyền: Tutor owner | Dự kiến |
| POST /api/v1/programs/{program}/complete | Không Session scheduled; Task đều completed/cancelled; 409 trả blockers Quyền: Tutor owner | Dự kiến |
| POST /api/v1/programs/{program}/cancel | Draft cancel pending Invitation cùng giao dịch; Active/Paused chỉ cancel khi không còn Session scheduled/Task nonterminal, nếu còn `409 program_cancellation_blocked` với blockers; không cascade cancel Quyền: Tutor owner | Dự kiến |
| **08 Workspace và Session** |  |  |
| GET /api/v1/programs/{program}/overview | `next_session`: Session scheduled tương lai theo (planned\_starts\_at,id) tăng dần; đủ counts cho 5 TaskStatus kể cả 0; latest\_progress theo (recorded\_at,id) giảm dần; latest\_announcement published theo (published\_at,id) giảm dần; summary rỗng là null. Program `capabilities`: `can_create_session`, `can_create_task`, `can_record_progress`, `can_create_fee_record`, `can_create_announcement`, `can_create_review`. Timestamp RFC 3339, hiển thị theo Program.timezone. Quyền: Owner/member | Dự kiến |
| GET /api/v1/programs/{program}/sessions | Danh sách Session Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/sessions | Tạo Session scheduled; UTC/RFC 3339, hiển thị theo Program.timezone; `meeting_url` optional HTTPS ≤2.048 ký tự, không credentials. Quyền: Tutor owner | Dự kiến |
| GET /api/v1/sessions/{session} | Chi tiết một Session của Program Quyền: Owner/member | Dự kiến |
| PATCH /api/v1/sessions/{session} | Sửa Session scheduled chỉ khi Program Active; UTC/RFC 3339, `meeting_url` optional HTTPS ≤2.048 ký tự, không credentials; yêu cầu `expected_version`, stale → 409 Quyền: Tutor owner | Dự kiến |
| POST /api/v1/sessions/{session}/complete | scheduled → completed; `expected_version`; Paused cho phép xử lý Session đã có Quyền: Tutor owner | Dự kiến |
| POST /api/v1/sessions/{session}/cancel | scheduled → cancelled, có reason và `expected_version`; Paused cho phép xử lý Session đã có Quyền: Tutor owner | Dự kiến |
| **09 Task và Submission** |  |  |
| GET /api/v1/programs/{program}/tasks | Danh sách Task và trạng thái suy ra Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/tasks | Giao Task cho Customer member; `due_at` tùy chọn, timestamp RFC 3339 hiển thị theo Program.timezone Quyền: Tutor owner | Dự kiến |
| GET /api/v1/tasks/{task} | Chi tiết Task, assignee và actions Quyền: Owner/assignee | Dự kiến |
| PATCH /api/v1/tasks/{task} | Sửa Task `todo` khi Program Active; yêu cầu `expected_version`, stale → 409 Quyền: Tutor owner | Dự kiến |
| POST /api/v1/tasks/{task}/start | todo → in\_progress khi Active/Paused; yêu cầu `expected_version` Quyền: Customer assignee | Dự kiến |
| POST /api/v1/tasks/{task}/cancel | Hủy Task `todo`/`in_progress` khi Active/Paused, giữ bài/lịch sử; yêu cầu `expected_version` Quyền: Tutor owner | Dự kiến |
| GET /api/v1/tasks/{task}/submission | Đọc version hiện tại và feedback Quyền: Owner/assignee | Dự kiến |
| PUT /api/v1/tasks/{task}/submission | Có nội dung không trắng hoặc file; content ≤10.000 ký tự; tối đa 5 file, 5 MiB/file, MIME PDF/JPG/PNG/DOC/DOCX. Hash/replay canonical theo BR-20; cập nhật Task state/version nguyên tử. Quyền: Customer assignee | Dự kiến |
| GET /api/v1/tasks/{task}/submission/versions | Đọc lịch sử version và feedback bất biến Quyền: Owner/assignee | Dự kiến |
| POST /api/v1/tasks/{task}/submission/review | Chấp nhận đúng `expected_submission_version` → accepted, Task completed; feedback optional, ≤5.000 ký tự; Task.version tăng nguyên tử Quyền: Tutor owner | Dự kiến |
| POST /api/v1/tasks/{task}/submission/request-revision | Yêu cầu sửa đúng `expected_submission_version` → revision\_requested; feedback bắt buộc làm lý do, ≤5.000 ký tự; Task.version tăng nguyên tử Quyền: Tutor owner | Dự kiến |
| **10 Progress và Announcement** |  |  |
| GET /api/v1/programs/{program}/progress-records | Đọc tiến độ theo ngày/metric Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/progress-records | Ghi `progress_percent` integer 0–100 do Tutor tự đánh giá; `recorded_at` do server đặt. Quyền: Tutor owner | Dự kiến |
| PATCH /api/v1/progress-records/{progressRecord} | Sửa Progress `progress_percent` (integer 0–100), cần `expected_version`; stale → 409; audit before/after; không DELETE. Quyền: Tutor owner | Dự kiến |
| GET /api/v1/programs/{program}/announcements | Draft chỉ Tutor owner; member hiện tại chỉ xem Published; sort `(published_at,id)` giảm dần; không có read state riêng Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/announcements | Tạo Draft: title 1–180, body 1–10.000 ký tự, tối đa 5 file 5 MiB/file (PDF/JPG/PNG/DOC/DOCX), file complete + scan clean, liên kết đúng purpose/resource theo BR-20/21. Quyền: Tutor owner | Dự kiến |
| GET /api/v1/announcements/{announcement} | Draft chỉ owner; Published chỉ member hiện tại; kiểm tra authorization lại khi đọc Quyền: Owner/member | Dự kiến |
| PATCH /api/v1/announcements/{announcement} | Chỉ field title/body/file IDs; giới hạn theo BR-20; sửa Draft khi Program Active/Paused; file complete + scan clean; published bất biến; yêu cầu `expected_version`. Quyền: Tutor owner | Dự kiến |
| POST /api/v1/announcements/{announcement}/publish | Publish Draft khi Program Active/Paused; `expected_version`; nguyên tử cập nhật Published + audit + recipient snapshot/outbox cho member hiện tại trừ Tutor owner. Notification trung tính chỉ có Announcement ID/deep link, không body/file metadata; retry không nhân đôi. Quyền: Tutor owner | Dự kiến |
| **11 Fee Record** |  |  |
| GET /api/v1/programs/{program}/fee-records | Đọc theo kỳ; Customer thấy amount, status, TutorPaymentMark, last-updated/reason và disclaimer theo BR-22 Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/fee-records | Chỉ Program Active; kỳ từ tháng kích hoạt đến tháng hiện tại theo Program.timezone, không tương lai; amount integer VND ≥0; 0 = miễn phí/unmarked; PATCH về 0 chỉ khi mark unmarked; Idempotency-Key. Same key/payload replay; khác key cùng kỳ 409. Thông báo Customer sau commit Quyền: Tutor owner | Dự kiến |
| GET /api/v1/fee-records/{feeRecord} | Chi tiết Fee Record Quyền: Owner/member | Dự kiến |
| PATCH /api/v1/fee-records/{feeRecord} | Sửa amount khi declared, mark chưa marked_paid; reason không trắng bắt buộc; `expected_version` + Idempotency-Key; thông báo Customer sau commit Quyền: Tutor owner | Dự kiến |
| POST /api/v1/fee-records/{feeRecord}/mark-paid | Đặt TutorPaymentMark `marked_paid`, cấm amount=0; `expected_version` + Idempotency-Key; notify sau commit Quyền: Tutor owner | Dự kiến |
| POST /api/v1/fee-records/{feeRecord}/mark-unpaid | Đặt `marked_unpaid`, cấm amount=0; reason bắt buộc khi đổi từ marked_paid; `expected_version` + Idempotency-Key; notify sau commit Quyền: Tutor owner | Dự kiến |
| POST /api/v1/fee-records/{feeRecord}/void | declared → voided, yêu cầu reason không trắng; giữ dấu/kỳ và record chiếm unique tháng; `expected_version` + Idempotency-Key; notify sau commit Quyền: Tutor owner | Dự kiến |
| **12 Review và Report** |  |  |
| GET /api/v1/programs/{program}/review | Xem Review/điều kiện tạo trong Program Quyền: Owner/member | Dự kiến |
| POST /api/v1/programs/{program}/review | Tạo một Review sau completed Quyền: Customer member | Dự kiến |
| GET /api/v1/admin/reviews | Danh sách kiểm duyệt theo quyền Quyền: Admin review moderator | Dự kiến |
| GET /api/v1/admin/reviews/{review} | Chi tiết Review để moderation Quyền: Admin review moderator | Dự kiến |
| POST /api/v1/admin/reviews/{review}/hide | visible → hidden, reason/audit Quyền: Admin review moderator | Dự kiến |
| POST /api/v1/admin/reviews/{review}/restore | hidden → visible, reason/audit Quyền: Admin review moderator | Dự kiến |
| POST /api/v1/reports | Báo cáo resource allow-list trong quyền Quyền: Customer/Tutor | Dự kiến |
| GET /api/v1/me/reports | Xem các báo cáo do mình gửi Quyền: Reporter | Dự kiến |
| GET /api/v1/me/reports/{report} | Xem kết quả, không lộ private evidence khác Quyền: Reporter | Dự kiến |
| GET /api/v1/admin/reports | Danh sách báo cáo summary Quyền: Admin report reviewer | Dự kiến |
| GET /api/v1/admin/reports/{report} | Chi tiết vụ việc được giao Quyền: Admin report reviewer | Dự kiến |
| PUT /api/v1/admin/reports/{report}/assignment | Giao/chuyển/thu hồi Admin phụ trách Report; quyền admin.reports.assign | Dự kiến |
| GET /api/v1/admin/reports/{report}/evidence | Chỉ đọc khi Report open: admin.reports.view + assignment hiện tại + audit; message bị báo cáo và tối đa 10 tin trước/5 tin sau theo BR-11 Quyền: Admin đúng vụ việc | Dự kiến |
| POST /api/v1/admin/reports/{report}/resolve | open → resolved, cần resolution\_note Quyền: Admin report decider | Dự kiến |
| POST /api/v1/admin/reports/{report}/dismiss | open → dismissed, cần resolution\_note Quyền: Admin report decider | Dự kiến |
| **13 Notification và Audit** |  |  |
| GET /api/v1/notifications | Danh sách notification của recipient Quyền: Chủ notification | Dự kiến |
| GET /api/v1/notifications/unread-count | Đếm notification chưa đọc Quyền: Chủ notification | Dự kiến |
| PATCH /api/v1/notifications/{notification}/read | Đánh dấu đã đọc idempotent Quyền: Chủ notification | Dự kiến |
| POST /api/v1/notifications/read-all | Đánh dấu toàn bộ đã đọc Quyền: Chủ notification | Dự kiến |
| GET /api/v1/admin/audit-logs | Tra cứu audit theo scope Quyền: Admin audit viewer | Dự kiến |
| GET /api/v1/admin/audit-logs/{auditLog} | Chi tiết audit, không sửa/xóa Quyền: Admin audit viewer | Dự kiến |
| **14 API Admin** |  |  |
| POST /api/v1/admin/listings/{listing}/hide | Đặt hold; active → inactive do Admin, reason_code/reason/audit, thông báo Tutor sau commit. Quyền: Admin active có admin.listings.moderate, toàn bộ Listing MVP | Dự kiến |
| POST /api/v1/admin/listings/{listing}/restore-visibility | Gỡ hold; inactive do Admin → active khi đủ gate, thiếu gate 409 giữ hold; Tutor tự pause thì giữ inactive. Reason_code/reason/audit, thông báo Tutor. Quyền: Admin active có admin.listings.moderate, toàn bộ Listing MVP | Dự kiến |
| GET /api/v1/admin/users | `admin.users.view`; hỗ trợ exact `user_id` hoặc email chuẩn hóa chính xác; danh sách chỉ Admin active đủ quyền giao việc, DTO tối thiểu id/display_name; không directory browse hàng loạt | Dự kiến |
| GET /api/v1/admin/users/{user} | `admin.users.view`; allow-list id/full_name/role/status/email_verified_at/created_at; email chỉ hiện trong kết quả exact lookup phục vụ hỗ trợ | Dự kiến |
| PATCH /api/v1/admin/users/{user} | `admin.users.update`; chỉ sửa full_name, reason bắt buộc + audit; cấm role/email/DOB/password/status | Dự kiến |
| GET /api/v1/admin/metrics | `admin.metrics.view`; aggregate theo role/status/Profile/Listing/Program/Report/outbox; không trả individual row hay private content | Dự kiến |

## Nguồn và phạm vi

- PRD theo module: [M01](<../Modules Documentation/M01 — Identity & Access/M01 — Identity & Access — Mục lục PRD.md>), [M02](<../Modules Documentation/M02 — Tutor Profile & Approval/M02 — Tutor Profile & Approval — Mục lục PRD.md>), [M03](<../Modules Documentation/M03 — Catalog & Marketplace/M03 — Catalog & Marketplace — PRD Index.md>), [M04](<../Modules Documentation/M04 — Messaging/M04 — Messaging — PRD Index.md>), [M05](<../Modules Documentation/M05 — Program & Invitation/M05 — Program & Invitation — PRD Index.md>), [M06](<../Modules Documentation/M06 — Session & Workspace/M06 — Session & Workspace — PRD Index.md>), [M07](<../Modules Documentation/M07 — Task & Progress/M07 — Task & Progress — PRD Index.md>), [M08](<../Modules Documentation/M08 — Fee, Announcement & Notification/M08 — Fee, Announcement & Notification — PRD Index.md>), [M09](<../Modules Documentation/M09 — Review, Report & Audit/M09 — Review, Report & Audit — PRD Index.md>), [M10](<../Modules Documentation/M10 — Files/M10 — Files — PRD Index.md>).
- [Business Rules](<../Domain Documentation/Business Rules.md>) và [State Lifecycles](<../Domain Documentation/State Lifecycles.md>) quyết định điều kiện nghiệp vụ. Quyết định nghiệp vụ hiện hành nằm trong PRD/Business Rules/UNRESOLVED_DECISIONS; nhãn **Dự kiến** chỉ có nghĩa route chưa được đăng ký, không đồng nghĩa hoãn route.
- Bản OpenAPI 0.2.0 draft được mô tả trong [hướng dẫn lịch sử](<TutorLink — OpenAPI 0.2.0 draft - hướng dẫn, thay đổi và điểm cần chốt.md>). Nó không phải contract triển khai hiện tại.
