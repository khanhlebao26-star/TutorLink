# API Route Catalog

| API | Chức năng và phạm vi quyền |
| :---- | :---- |
| **01 Tài khoản** |  |
| GET /sanctum/csrf-cookie | Khởi tạo CSRF cho SPA Quyền: Public |
| POST /api/v1/auth/register | Đăng ký Customer ≥15 hoặc Tutor ≥18 Quyền: Public |
| POST /api/v1/auth/login | Đăng nhập bằng cookie Quyền: Public |
| POST /api/v1/auth/logout | Đăng xuất, hủy phiên Quyền: Đăng nhập |
| POST /api/v1/auth/forgot-password | Gửi link đặt lại mật khẩu Quyền: Public |
| POST /api/v1/auth/reset-password | Đổi mật khẩu bằng token hợp lệ Quyền: Public |
| GET /api/v1/auth/email/verify/{id}/{hash} | Xác minh email qua signed URL Quyền: Chủ email |
| POST /api/v1/auth/email/verification-notification | Gửi lại email xác minh Quyền: Đăng nhập |
| GET /api/v1/me | Đọc tài khoản/hồ sơ và quyền hiện tại Quyền: Chủ tài khoản |
| PATCH /api/v1/me | full\_name đã đồng bộ giữa M01-F07, API Catalog và OpenAPI. M01-F07 còn đề nghị PATCH date\_of\_birth kèm age gate Customer 15+/Tutor 18+; allow-list DOB vẫn chờ Product chốt. Hợp đồng hiện chỉ nhận full\_name; chưa suy quyền sửa từ DB nullability. Quyền: Chủ tài khoản |
| GET /api/v1/customer/profile | Đọc Customer Profile của mình Quyền: Customer |
| PUT /api/v1/customer/profile | Tạo/cập nhật Customer Profile Quyền: Customer |
| **02 Hồ sơ Tutor và xét duyệt** |  |
| GET /api/v1/tutor/profile | Đọc hồ sơ và lý do xét duyệt Quyền: Tutor |
| PUT /api/v1/tutor/profile | Lưu Draft/Changes Requested Quyền: Tutor |
| PUT /api/v1/tutor/profile/avatar | Gắn File avatar đã complete Quyền: Tutor |
| POST /api/v1/tutor/profile/verification-documents | Gắn tài liệu xác minh và document\_type Quyền: Tutor |
| POST /api/v1/tutor/profile/submit | Gửi/gửi lại hồ sơ xét duyệt Quyền: Tutor |
| GET /api/v1/admin/tutor-profiles | Danh sách hồ sơ theo trạng thái Quyền: Admin đúng quyền |
| GET /api/v1/admin/tutor-profiles/{tutorProfile} | Chi tiết hồ sơ trong phạm vi xét duyệt Quyền: Admin đúng quyền |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/approve | Duyệt hồ sơ, ghi reason/audit Quyền: Admin đúng quyền |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/request-changes | Yêu cầu chỉnh sửa hồ sơ Quyền: Admin đúng quyền |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/reject | Từ chối hồ sơ Quyền: Admin đúng quyền |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/suspend | Đình chỉ hồ sơ và account theo policy Quyền: Admin có đủ hai quyền |
| POST /api/v1/admin/tutor-profiles/{tutorProfile}/restore | Khôi phục đúng trạng thái hồ sơ trước đình chỉ Quyền: Admin đúng quyền |
| POST /api/v1/admin/users/{user}/suspend | Đình chỉ tài khoản Quyền: Admin đúng quyền |
| POST /api/v1/admin/users/{user}/restore | Mở lại account suspended, không deactivated Quyền: Admin đúng quyền |
| POST /api/v1/admin/verification-documents/{document}/approve | Chấp nhận tài liệu xác minh cụ thể Quyền: Admin quyền xét duyệt |
| POST /api/v1/admin/verification-documents/{document}/reject | Từ chối tài liệu xác minh cụ thể Quyền: Admin quyền xét duyệt |
| **03 Catalog và khám phá** |  |
| GET /api/v1/catalog/categories | Đọc cây danh mục công khai Quyền: Public |
| GET /api/v1/catalog/specializations | Đọc/lọc chuyên môn đang hiển thị Quyền: Public |
| GET /api/v1/listings | Tìm/lọc/sắp xếp/phân trang Listing công khai Quyền: Public |
| GET /api/v1/listings/{slug} | Xem Listing đủ điều kiện public Quyền: Public |
| GET /api/v1/tutors/{slug} | Xem hồ sơ, dịch vụ và lịch rảnh tham khảo Quyền: Public |
| GET /api/v1/tutors/{slug}/reviews | Review visible hợp lệ và thống kê sao Quyền: Public |
| **04 Listing và lịch rảnh** |  |
| GET /api/v1/me/listings | Danh sách/count Listing của owner Quyền: Tutor |
| POST /api/v1/me/listings | Tạo Listing draft Quyền: Tutor đủ điều kiện |
| GET /api/v1/me/listings/{listing} | Chi tiết Listing của mình Quyền: Tutor owner |
| PATCH /api/v1/me/listings/{listing} | Sửa nội dung, không tùy ý đổi status đích Quyền: Tutor owner |
| POST /api/v1/me/listings/{listing}/publish | draft/inactive → active, kiểm lại eligibility Quyền: Tutor owner |
| POST /api/v1/me/listings/{listing}/pause | active → inactive Quyền: Tutor owner |
| DELETE /api/v1/me/listings/{listing} | Archive mềm → archived, không hard-delete Quyền: Tutor owner |
| GET /api/v1/me/availability-rules | Đọc lịch rảnh của mình Quyền: Tutor |
| PUT /api/v1/me/availability-rules | Thay bộ lịch rảnh, không booking Quyền: Tutor |
| **05 File** |  |
| POST /api/v1/files | Upload multipart, trả file\_id Quyền: Chủ upload |
| GET /api/v1/files/{file} | Đọc metadata theo policy Quyền: Người được phép |
| POST /api/v1/files/{file}/complete | Kiểm object/MIME/size/checksum Quyền: Chủ upload |
| POST /api/v1/files/{file}/links | Gắn đúng owner/purpose/resource Quyền: Chủ upload đúng quyền |
| GET /api/v1/files/{file}/download | Tải theo quyền tài nguyên Quyền: Người được phép |
| DELETE /api/v1/files/{file} | Xóa tệp chưa liên kết, không phá lịch sử Quyền: Chủ upload |
| **06 Chat** |  |
| POST /api/v1/conversations | Find-or-create một cặp; tái dùng khi kết nối mới Quyền: Customer/Tutor |
| GET /api/v1/conversations | Danh sách hội thoại của mình Quyền: Thành viên |
| GET /api/v1/conversations/{conversation} | Chi tiết hội thoại trong quyền Quyền: Thành viên |
| GET /api/v1/conversations/{conversation}/messages | Đọc/poll theo cursor Quyền: Thành viên |
| POST /api/v1/conversations/{conversation}/messages | Gửi text/file với client\_message\_id Quyền: Thành viên |
| PATCH /api/v1/conversations/{conversation}/read | Cập nhật read cursor chỉ tiến lên Quyền: Thành viên |
| **07 Program và Invitation** |  |
| GET /api/v1/programs | Danh sách Program theo actor/state Quyền: Tutor owner/Customer member |
| POST /api/v1/programs | Tạo Draft cho Customer đích Quyền: Tutor đủ điều kiện |
| GET /api/v1/programs/{program} | Chi tiết thỏa thuận và allowed\_actions Quyền: Owner/member |
| PATCH /api/v1/programs/{program} | Sửa Draft khi không có Pending khóa snapshot Quyền: Tutor owner |
| POST /api/v1/programs/{program}/invitations | Gửi snapshot, hạn 72 giờ Quyền: Tutor owner |
| GET /api/v1/invitations | Inbox/outbox theo người được phép Quyền: Customer đích/Tutor owner |
| GET /api/v1/invitations/{invitation} | Đọc snapshot trước khi trở thành member Quyền: Customer đích/Tutor owner |
| POST /api/v1/invitations/{invitation}/accept | Atomic accepted \+ membership \+ active Quyền: Customer đích |
| POST /api/v1/invitations/{invitation}/reject | Từ chối Pending còn hiệu lực Quyền: Customer đích |
| POST /api/v1/invitations/{invitation}/cancel | Thu hồi Pending Quyền: Tutor owner |
| GET /api/v1/programs/{program}/members | Đọc thành viên, không mở CRUD membership Quyền: Owner/member |
| POST /api/v1/programs/{program}/pause | active → paused Quyền: Tutor owner |
| POST /api/v1/programs/{program}/resume | paused → active Quyền: Tutor owner |
| POST /api/v1/programs/{program}/complete | Không Session scheduled; Task đều completed/cancelled; 409 trả blockers Quyền: Tutor owner |
| POST /api/v1/programs/{program}/cancel | Hủy mềm Program, giữ lịch sử Quyền: Tutor owner |
| **08 Workspace và Session** |  |
| GET /api/v1/programs/{program}/overview | Tổng quan, next\_session và counts Quyền: Owner/member |
| GET /api/v1/programs/{program}/sessions | Danh sách Session Quyền: Owner/member |
| POST /api/v1/programs/{program}/sessions | Tạo Session scheduled Quyền: Tutor owner |
| GET /api/v1/sessions/{session} | Chi tiết một Session của Program Quyền: Owner/member |
| PATCH /api/v1/sessions/{session} | Sửa lịch/nội dung khi được phép Quyền: Tutor owner |
| POST /api/v1/sessions/{session}/complete | scheduled → completed Quyền: Tutor owner |
| POST /api/v1/sessions/{session}/cancel | scheduled → cancelled, có reason Quyền: Tutor owner |
| **09 Task và Submission** |  |
| GET /api/v1/programs/{program}/tasks | Danh sách Task và trạng thái suy ra Quyền: Owner/member |
| POST /api/v1/programs/{program}/tasks | Giao Task cho Customer member Quyền: Tutor owner |
| GET /api/v1/tasks/{task} | Chi tiết Task, assignee và actions Quyền: Owner/assignee |
| PATCH /api/v1/tasks/{task} | Sửa nội dung nhiệm vụ còn cho phép Quyền: Tutor owner |
| POST /api/v1/tasks/{task}/start | todo → in\_progress Quyền: Customer assignee |
| POST /api/v1/tasks/{task}/cancel | Hủy tường minh, giữ bài và lịch sử Quyền: Tutor owner |
| GET /api/v1/tasks/{task}/submission | Đọc version hiện tại và feedback Quyền: Owner/assignee |
| PUT /api/v1/tasks/{task}/submission | Customer assignee PUT với Idempotency-Key UUID; key+payload trùng trả version đã commit, cùng key khác payload trả 409\. Key mới chỉ tạo version khi expected\_submission\_version khớp; stale trả 409\. Key/hash lưu cùng version trong PostgreSQL; quyền kiểm trước replay. Quyền: Customer assignee |
| GET /api/v1/tasks/{task}/submission/versions | Đọc lịch sử version và feedback bất biến Quyền: Owner/assignee |
| POST /api/v1/tasks/{task}/submission/review | Chấp nhận đúng version → accepted, Task completed Quyền: Tutor owner |
| POST /api/v1/tasks/{task}/submission/request-revision | Yêu cầu sửa đúng version → revision\_requested Quyền: Tutor owner |
| **10 Progress và Announcement** |  |
| GET /api/v1/programs/{program}/progress-records | Đọc tiến độ theo ngày/metric Quyền: Owner/member |
| POST /api/v1/programs/{program}/progress-records | Ghi tiến độ Quyền: Tutor owner |
| PATCH /api/v1/progress-records/{progressRecord} | Sửa record tiến độ theo quyền Quyền: Tutor owner |
| GET /api/v1/programs/{program}/announcements | Member chỉ xem published, owner xem Draft Quyền: Owner/member |
| POST /api/v1/programs/{program}/announcements | Tạo Announcement draft Quyền: Tutor owner |
| GET /api/v1/announcements/{announcement} | Chi tiết thông báo được phép xem Quyền: Owner/member |
| PATCH /api/v1/announcements/{announcement} | Sửa thông báo theo Program state Quyền: Tutor owner |
| POST /api/v1/announcements/{announcement}/publish | draft → published, phát notification Quyền: Tutor owner |
| **11 Fee Record** |  |
| GET /api/v1/programs/{program}/fee-records | Đọc phí theo tháng và hai trạng thái Quyền: Owner/member |
| POST /api/v1/programs/{program}/fee-records | Tạo declared \+ unmarked, unique kỳ tháng Quyền: Tutor owner |
| GET /api/v1/fee-records/{feeRecord} | Chi tiết Fee Record Quyền: Owner/member |
| PATCH /api/v1/fee-records/{feeRecord} | Sửa phí declared khi không marked\_paid Quyền: Tutor owner |
| POST /api/v1/fee-records/{feeRecord}/mark-paid | Đặt TutorPaymentMark marked\_paid Quyền: Tutor owner |
| POST /api/v1/fee-records/{feeRecord}/mark-unpaid | Đặt marked\_unpaid, reason khi sửa đã thu Quyền: Tutor owner |
| POST /api/v1/fee-records/{feeRecord}/void | declared → voided, giữ dấu/lịch sử Quyền: Tutor owner |
| **12 Review và Report** |  |
| GET /api/v1/programs/{program}/review | Xem Review/điều kiện tạo trong Program Quyền: Owner/member |
| POST /api/v1/programs/{program}/review | Tạo một Review sau completed Quyền: Customer member |
| GET /api/v1/admin/reviews | Danh sách kiểm duyệt theo quyền Quyền: Admin review moderator |
| GET /api/v1/admin/reviews/{review} | Chi tiết Review để moderation Quyền: Admin review moderator |
| POST /api/v1/admin/reviews/{review}/hide | visible → hidden, reason/audit Quyền: Admin review moderator |
| POST /api/v1/admin/reviews/{review}/restore | hidden → visible, reason/audit Quyền: Admin review moderator |
| POST /api/v1/reports | Báo cáo resource allow-list trong quyền Quyền: Customer/Tutor |
| GET /api/v1/me/reports | Xem các báo cáo do mình gửi Quyền: Reporter |
| GET /api/v1/me/reports/{report} | Xem kết quả, không lộ private evidence khác Quyền: Reporter |
| GET /api/v1/admin/reports | Danh sách báo cáo summary Quyền: Admin report reviewer |
| GET /api/v1/admin/reports/{report} | Chi tiết vụ việc được giao Quyền: Admin report reviewer |
| GET /api/v1/admin/reports/{report}/evidence | Chỉ đọc evidence tối thiểu, audit; policy đề xuất Quyền: Admin đúng quyền \+ vụ việc |
| POST /api/v1/admin/reports/{report}/resolve | open → resolved, cần resolution\_note Quyền: Admin report decider |
| POST /api/v1/admin/reports/{report}/dismiss | open → dismissed, cần resolution\_note Quyền: Admin report decider |
| **13 Notification và Audit** |  |
| GET /api/v1/notifications | Danh sách notification của recipient Quyền: Chủ notification |
| GET /api/v1/notifications/unread-count | Đếm notification chưa đọc Quyền: Chủ notification |
| PATCH /api/v1/notifications/{notification}/read | Đánh dấu đã đọc idempotent Quyền: Chủ notification |
| POST /api/v1/notifications/read-all | Đánh dấu toàn bộ đã đọc Quyền: Chủ notification |
| GET /api/v1/admin/audit-logs | Tra cứu audit theo scope Quyền: Admin audit viewer |
| GET /api/v1/admin/audit-logs/{auditLog} | Chi tiết audit, không sửa/xóa Quyền: Admin audit viewer |
| **14 Product yêu cầu nhưng quyền còn thiếu** |  |
| POST /api/v1/admin/listings/{listing}/hide | Đề xuất ẩn do moderation, độc lập owner status Quyền: Admin được cấp quyền mới |
| POST /api/v1/admin/listings/{listing}/restore-visibility | Gỡ ẩn moderation, vẫn kiểm public eligibility Quyền: Admin được cấp quyền mới |
| GET /api/v1/admin/users | Tra cứu user hỗ trợ có giới hạn Quyền: Admin được cấp quyền mới |
| GET /api/v1/admin/users/{user} | Đọc dữ liệu hỗ trợ trong scope Quyền: Admin được cấp quyền mới |
| PATCH /api/v1/admin/users/{user} | Sửa field hỗ trợ allow-list; không role/credentials Quyền: Admin được cấp quyền mới |
| GET /api/v1/admin/metrics | Chỉ số tổng hợp, không lộ private content Quyền: Admin được cấp quyền mới |

Liên kết hợp đồng chi tiết — F02

Bảng này tiếp tục là danh mục route và tình trạng tài liệu; hợp đồng request/response đầy đủ nằm trong OpenAPI draft ở đây: https\://drive.google.com/file/d/1L1z1rxhyd2xUecNXju4a\_FLatxyBe1f1/view?usp=drivesdk . Draft chưa phải release đã được nhóm duyệt; các điểm chặn còn liệt kê trong tài liệu hướng dẫn: https\://docs.google.com/document/d/1Li7buMBJb84sSB0Sm1sHkDSEQ5jU76QxmN1b\_ikHbzk/edit?usp=drivesdk .

F03 — Thao tác assignment mới trong hợp đồng: PUT /api/v1/admin/tutor-profiles/{tutorProfile}/assignment và PUT /api/v1/admin/reports/{report}/assignment. Xem schema, quyền, assignment\_version và lỗi 409 trong OpenAPI draft sau khi đồng bộ.

02 Hồ sơ Tutor và xét duyệt

PUT /api/v1/admin/tutor-profiles/{tutorProfile}/assignment

Giao/chuyển/thu hồi Admin phụ trách hồ sơ Tutor

Quyền: Admin có admin.tutor\_profiles.assign

Cần bổ sung

12 Review và Report

PUT /api/v1/admin/reports/{report}/assignment

Giao/chuyển/thu hồi Admin phụ trách Report

Quyền: Admin có admin.reports.assign

Cần bổ sung

F03 — Tra cứu Admin nhận phân công: hai route assignment nhận assigned\_admin\_id; chưa có API directory được chốt để UI liệt kê Admin đủ điều kiện. GET /api/v1/admin/users hiện vẫn conditional, không phải nguồn danh sách đã duyệt. Trước khi UI dùng cần chốt permission, scope và DTO; backend vẫn kiểm tra trạng thái/quyền của target. Ghi chú này không thêm route hoặc đổi tổng số operation

Đồng bộ 18/10/2026 — Hợp đồng đích: PATCH /me chỉ full\_name; DOB chờ D04. PATCH Fee Record còn chặn D22 do chưa chốt giao thức chống stale; không tự thêm version/ETag. PublicTutor.listings còn cần chốt giới hạn/cách tải tiếp theo D06, không tự thêm filter/endpoint. Report reason=other yêu cầu details không rỗng theo WF06 (description là bí danh PRD). Submission giữ thiết kế row-level D13 nhưng canonicalize và giới hạn attachment/content chưa chốt. “Có route” không xác nhận runtime, “core” không thay phê duyệt policy.

.
