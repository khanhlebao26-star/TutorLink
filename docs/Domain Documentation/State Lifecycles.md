State Lifecycles

**Listing**

| Chuyển trạng thái | Hành động |
| :---- | :---- |
| draft → active | Publish, kiểm điều kiện Tutor và catalog. |
| active → inactive | Tạm dừng hiển thị. |
| inactive → active | Publish lại, kiểm lại điều kiện. |
| draft/active/inactive → archived | Action archive; ListingStatus archived là lưu trữ cuối, không sửa/khôi phục. |

draft → inactive không hợp lệ. Publish active → active vẫn kiểm điều kiện. inactive là tạm dừng; archived là lưu trữ. ListingStatusFilter chỉ draft/active/inactive/archived; xem tất cả bằng cách bỏ bộ lọc, không thêm all vào enum.

**Program và Invitation**

draft → active ↔ paused; active/paused → completed/cancelled; draft → cancelled

* Program draft gửi Invitation pending, có hiệu lực 72 giờ. Đúng Customer accept trước expires\_at: Invitation → accepted, tạo membership và Program → active cùng transaction, retry không tạo trùng.  
* Tutor owner thực hiện pause/resume/complete/cancel theo ProgramAction và quyền; active/paused đều complete/cancel được, draft cancel được. Invitation pending bị rejected/cancelled/expired thì Program còn draft để chỉnh và gửi mới; không tạo member.  
* Complete Program chỉ khi không còn Session scheduled và mọi Task đã completed hoặc cancelled tường minh. Task todo/in\_progress/submitted còn lại đều chặn; submitted phải được Tutor feedback xử lý. Complete không tự chuyển trạng thái Task.  
* Program completed/cancelled kết thúc, không mở lại. Chỉ completed cho phép Customer member đủ điều kiện Review một lần; không tự làm membership active thành expired/cancelled.  
* InvitationStatus: pending → accepted/rejected/cancelled/expired. Customer đích accept/reject; Tutor owner cancel; hệ thống expire theo expires\_at. Các trạng thái sau pending kết thúc. Snapshot lời mời cũ bất biến; chỉ một pending còn hiệu lực mỗi Program.

Workspace active cho vận hành theo quyền; paused chặn tạo Session/Task/Announcement/Fee mới, còn nộp bài/feedback/Progress và xử lý phí có sẵn theo Business Rules. completed/cancelled chỉ đọc, ngoại trừ Review đủ điều kiện sau completed. Snapshot không đổi theo Listing.

**Fee Record và dấu thanh toán của Tutor**

| Trạng thái | Ý nghĩa |
| :---- | :---- |
| FeeRecordStatus | declared \= đã khai báo; voided \= đã vô hiệu hóa. |
| TutorPaymentMark | unmarked \= chưa đánh dấu; marked\_paid \= Tutor đánh dấu đã thu; marked\_unpaid \= Tutor đánh dấu chưa thu. |
| Mặc định khi tạo | status \= declared; tutor\_payment\_mark \= unmarked. Hai trường độc lập. |

Các trường phí là khai báo của Tutor, không xác nhận ngân hàng/cổng thanh toán. FeeRecordStatus: declared → voided, không mở lại. TutorPaymentMark: unmarked → marked\_paid/marked\_unpaid; marked\_paid ↔ marked\_unpaid có audit, đổi từ đã thu sang chưa thu cần reason. Không reset về unmarked. void giữ nguyên dấu thanh toán để bảo toàn lịch sử; voided chỉ đọc và vẫn chiếm kỳ tháng. Không tạo checkout/refund.

## Tài khoản và hồ sơ

* UserStatus: active → suspended và suspended → active theo quyền quản trị. deactivated dành cho quy trình vô hiệu hóa riêng, không dùng thay suspended và không restore bằng account-restore; MVP không tự cung cấp quy trình deactivation mới.  
* TutorProfileStatus: draft → submitted; submitted → approved/changes\_requested/rejected; changes\_requested → submitted. Mọi trạng thái hồ sơ chưa suspended có thể bị đình chỉ theo quyền; lưu trạng thái trước đình chỉ. Restore suspended về đúng trạng thái đã lưu, không tự approved. Đình chỉ hồ sơ kèm khóa account đang active phải cùng transaction và có cả hai permission; restore profile và account là hai thao tác riêng theo Business Rules.

## Conversation

* ConversationStatus chỉ active/cancelled. Khi có Program completed, active → cancelled chỉ nếu cặp không còn Program active/paused và không còn Invitation pending còn hạn. cancelled chỉ đọc. Nếu còn quan hệ đang tiếp diễn thì giữ active. Khi bắt đầu trao đổi dịch vụ mới và đủ điều kiện, cancelled → active trên chính Conversation cũ; không tạo hội thoại trùng cặp.

## Session và bài nộp

* SessionStatus scheduled → completed/cancelled; trạng thái cuối chỉ đọc. Tutor owner hoàn tất/hủy lịch đã có khi Program Active hoặc Paused theo xác nhận ngày 18/10/2026; Paused chặn tạo lịch mới, edit khi Paused còn mở. Program terminal chặn transition mới; replay chỉ đọc không tạo actual/audit lần hai.  
* TaskStatus: todo → in\_progress; todo/in\_progress → submitted; submitted → completed khi chấp nhận bài hoặc in\_progress khi yêu cầu sửa; todo/in\_progress/submitted → cancelled khi hủy tường minh. submitted → submitted chỉ khi Customer cập nhật bài và tăng version. Task completed/cancelled không tự mở lại.  
* SubmissionStatus: submitted → accepted/revision\_requested; revision\_requested → submitted khi nộp lại. Nộp/cập nhật tạo version mới, feedback gắn version, stale bị từ chối. Không dùng reviewed làm literal đích.

## Các trạng thái hỗ trợ

* VerificationDocumentStatus pending → approved/rejected; approved → expired khi có căn cứ hết hiệu lực. ReportStatus open → resolved/dismissed; ReviewVisibility visible ↔ hidden theo quyền moderation và audit. AnnouncementViewState draft → published; private/public là toàn bộ FileVisibility, quyền membership được kiểm riêng.

## Đối chiếu dữ liệu cũ

* Enum Catalog là tập giá trị đích; các dòng dưới chỉ là mapping migration, không thêm literal vào catalog. Listing cancelled hoặc lỗi gõ archieved → archived; Tutor approval active → approved, pending → draft/submitted theo submitted\_at; Verification Document active → approved; Invitation active → accepted; Submission reviewed → accepted. File visibility program → private nhưng giữ resource links và policy membership.  
* Phí cũ declared → status declared \+ tutor\_payment\_mark unmarked; confirmed → declared \+ marked\_paid; cancelled → voided, giữ dấu đã biết hoặc unmarked nếu chưa có bằng chứng. Không biến voided thành chưa thu. Khi migration hồ sơ suspended phải giữ trạng thái gốc để restore. Dữ liệu mâu thuẫn/không đủ căn cứ phải đối soát, không tự kích hoạt hoặc giả lịch sử.  
* Các vòng đời trên là chuẩn nghiệp vụ để triển khai; không chứng nhận schema/API đã có đầy đủ.
