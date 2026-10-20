State Lifecycles

**Listing**

| Chuyển trạng thái | Hành động |
| :---- | :---- |
| draft → active | Publish, kiểm điều kiện Tutor và catalog. |
| active → inactive | Tutor tạm dừng, hoặc Admin hide đặt moderation hold và ghi nguồn inactive; cả hai ngừng hiển thị. |
| inactive → active | Tutor publish lại khi không có hold; hoặc Admin restore hold tự kích hoạt nếu inactive chỉ do moderation, Tutor không tự pause và đủ public gate. |
| draft/active/inactive → archived | Action archive; ListingStatus archived là lưu trữ cuối, không sửa/khôi phục. |

draft → inactive không hợp lệ. Publish active → active vẫn kiểm điều kiện. inactive là tạm dừng; archived là lưu trữ. ListingStatusFilter chỉ draft/active/inactive/archived; xem tất cả bằng cách bỏ bộ lọc, không thêm all vào enum.

Khôi phục User hoặc Tutor Profile không chuyển trạng thái Listing. Theo BR-14, Listing vốn active tự hiện lại khi các public gate trở lại hợp lệ; Listing inactive do Tutor tự tạm dừng vẫn ẩn và chỉ Tutor publish lại; archived là terminal. Điều này khác Admin restore moderation hold: hold đã chuyển active → inactive và chỉ trường hợp inactive do Admin, không có Tutor pause, đủ gate mới được tự chuyển inactive → active theo dòng transition trên.

Moderation hold là bản ghi riêng, không thêm `hidden` vào ListingStatus. Admin hide Listing active đặt hold và chuyển active → inactive cùng giao dịch, ghi nguồn inactive do moderation; hide Listing đã inactive do Tutor không tạo quyền tự active. Tutor không tự bỏ hold qua edit/pause/publish; nếu Tutor chọn pause trong lúc hold thì ghi lại ý định này dù status đang inactive. Admin active có `admin.listings.moderate` xử lý mọi Listing trong MVP, không cần assignment riêng; hide/restore cần reason_code, reason và audit. Nếu nguồn inactive chỉ do Admin, không có Tutor pause và các public gate khác đạt, restore gỡ hold và chuyển inactive → active cùng giao dịch; thiếu gate thì 409, giữ hold và inactive. Nếu Tutor tự pause, restore có thể gỡ hold nhưng giữ inactive dù thiếu public gate, để Tutor publish lại sau. Listing có hold không được trả ở bề mặt công khai hoặc làm nguồn cho Program Draft/Invitation mới; Program/snapshot cũ không tự hủy. Hide và restore thành công thông báo đúng kết quả cho Tutor owner.

**Program và Invitation**

draft → active ↔ paused; active/paused → completed/cancelled; draft → cancelled

* Program version là version của offer Draft, tăng sau mỗi lần sửa thành công; PATCH/send dùng expected\_version. Invitation lưu version/snapshot bất biến và khóa Draft khi pending. Invitation hết hạn 72 giờ UTC từ sent\_at; accept chỉ khi server time < expires\_at. Đúng Customer accept: Invitation → accepted, tạo membership và Program → active cùng transaction, retry không tạo trùng.
* Tutor owner thực hiện pause/resume/complete/cancel theo ProgramAction và quyền; active/paused đều complete/cancel được, draft cancel được. Invitation pending bị rejected/cancelled/expired thì Program còn draft để chỉnh và gửi mới; không tạo member.  
* Complete Program chỉ khi không còn Session scheduled và mọi Task đã completed hoặc cancelled tường minh. Task todo/in\_progress/submitted còn lại đều chặn; submitted phải được Tutor feedback xử lý. Complete không tự chuyển trạng thái Task. Hủy Program Active/Paused cũng cần các Session/Task về terminal trước; còn blocker thì 409, không tự cascade cancel.
* Program completed/cancelled kết thúc, không mở lại. Chỉ completed cho phép Customer member đủ điều kiện Review một lần; không tự làm membership active thành expired/cancelled.  
* InvitationStatus: pending → accepted/rejected/cancelled/expired. Customer đích accept/reject; Tutor owner cancel; hệ thống expire theo expires\_at. `server_now < expires_at` mới accept được; tại/qua biên hết hạn là không hợp lệ dù worker chưa chạy. Các trạng thái sau pending kết thúc. Snapshot lời mời cũ bất biến; chỉ một pending còn hiệu lực mỗi Program. Draft bị khóa khi pending; Draft cancel phải cancel Invitation cùng transaction.
* Theo BR-18, Invitation pending có source Listing đang moderation hold vẫn giữ snapshot và expiry nhưng không được accept khi hold còn. Gỡ hold trước expiry thì có thể accept nếu gate còn đạt; sau expiry không gia hạn.

Workspace active cho vận hành theo quyền; paused chặn tạo Session/Task/Fee mới. Session scheduled không được sửa khi paused nhưng có thể hoàn tất/hủy. Task đã giao vẫn cho Customer start/nộp và Tutor feedback; Tutor chỉ sửa Task todo khi Program active, được hủy todo/in_progress khi active/paused. Fee Record có sẵn chỉ sửa amount khi declared và chưa marked_paid; có thể xử lý mark/void theo policy. Announcement Draft được tạo/sửa/publish ở active/paused; published bất biến, đính chính bằng bản mới. completed/cancelled chỉ đọc, ngoại trừ Review đủ điều kiện sau completed. Các resource Session/Task/Fee/Announcement dùng version + expected_version; stale trả 409, không ghi đè. Submission giữ expected_submission_version và Idempotency-Key theo D13. Xem BR-19.

**Fee Record và dấu thanh toán của Tutor**

| Trạng thái | Ý nghĩa |
| :---- | :---- |
| FeeRecordStatus | declared \= đã khai báo; voided \= đã vô hiệu hóa. |
| TutorPaymentMark | unmarked \= chưa đánh dấu; marked\_paid \= Tutor đánh dấu đã thu; marked\_unpaid \= Tutor đánh dấu chưa thu. |
| Mặc định khi tạo | status \= declared; tutor\_payment\_mark \= unmarked. Hai trường độc lập. |

Các trường phí là khai báo của Tutor, không xác nhận ngân hàng/cổng thanh toán. FeeRecordStatus: declared → voided, không mở lại. TutorPaymentMark: unmarked → marked\_paid/marked\_unpaid; marked\_paid ↔ marked\_unpaid có audit, đổi từ đã thu sang chưa thu cần reason. Không reset về unmarked. void giữ nguyên dấu thanh toán để bảo toàn lịch sử; voided chỉ đọc và vẫn chiếm kỳ tháng. Không tạo checkout/refund. BR-22: chỉ tạo record khi Program Active, kỳ từ tháng activated_at theo Program.timezone tới tháng hiện tại; cho khai báo bù, không tạo kỳ tương lai. Amount 0 ghi nhận miễn phí, không có payment mark; chỉ sửa về 0 khi record còn unmarked, không reset mark. Mutation ghi lý do, cho Customer member xem snapshot/reason/update time và phát fee_record_changed sau commit. Idempotency-Key + expected_version chống retry/stale; voided vẫn chiếm tháng.

## Tài khoản và hồ sơ

* UserStatus: active → suspended và suspended → active theo quyền quản trị. deactivated dành cho quy trình vô hiệu hóa riêng, không dùng thay suspended và không restore bằng account-restore; MVP không tự cung cấp quy trình deactivation mới.  
* TutorProfileStatus: draft → submitted; submitted → approved/changes\_requested/rejected; changes\_requested → submitted. Mọi trạng thái hồ sơ chưa suspended có thể bị đình chỉ theo quyền; lưu trạng thái trước đình chỉ. Restore suspended về đúng trạng thái đã lưu, không tự approved. Đình chỉ hồ sơ kèm khóa account đang active phải cùng transaction và có cả hai permission; restore profile và account là hai thao tác riêng theo Business Rules.
* Bản sửa Profile approved là revision riêng: draft → submitted → approved/changes_requested/rejected; changes_requested → submitted. Quyết định approved thay bản công khai nguyên tử và đóng revision; request-changes/reject chỉ đổi revision, giữ Profile approved và nội dung công khai cũ. Chỉ một revision đang xử lý mỗi Profile; rejected không tự nộp lại. Hồ sơ gốc rejected không có transition tự nộp lại trong MVP.

## Conversation

* ConversationStatus chỉ active/cancelled. Khi có Program completed, active → cancelled chỉ nếu cặp không còn Program active/paused và không còn Invitation pending còn hạn. cancelled chỉ đọc. Nếu còn quan hệ đang tiếp diễn thì giữ active. Khi bắt đầu trao đổi dịch vụ mới và đủ điều kiện, cancelled → active trên chính Conversation cũ; không tạo hội thoại trùng cặp.
* BR-17: đình chỉ participant không tự chuyển active → cancelled, không xóa membership/message/context. Member active vẫn đọc lịch sử của mình; member bị đình chỉ bị từ chối đọc/gửi kể cả khi còn session cũ và đọc lại được sau khôi phục. `context_listing_id` chỉ được ghi khi tạo lần đầu, không đổi khi dùng lại hoặc tái mở. BR-21: member active không gửi message mới khi peer bị đình chỉ; cancelled chỉ mở lại bằng request mở liên hệ mới đủ gate; lifecycle không tự chèn system message.

## Session và bài nộp

* SessionStatus scheduled → completed/cancelled; trạng thái cuối chỉ đọc. Tutor owner hoàn tất/hủy lịch đã có khi Program Active hoặc Paused; Paused chặn tạo/sửa lịch. Program terminal chặn transition mới; replay không tạo actual/audit lần hai. Mọi mutation kiểm `expected_version` theo BR-19.
* TaskStatus: todo → in\_progress; todo/in\_progress → submitted; submitted → completed khi chấp nhận bài hoặc in\_progress khi yêu cầu sửa; todo/in\_progress → cancelled khi hủy tường minh. Task submitted phải được feedback trước; nếu yêu cầu sửa thì trở lại in\_progress rồi mới có thể hủy. submitted → submitted chỉ khi Customer cập nhật bài và tăng Submission version. Task completed/cancelled không tự mở lại. Task mutation dùng `version`/`expected_version`; Submission guard theo D13.
* SubmissionStatus: submitted → accepted/revision\_requested; revision\_requested → submitted khi nộp lại. Nộp/cập nhật tạo version mới, feedback gắn version, stale bị từ chối. Không dùng reviewed làm literal đích.

## Các trạng thái hỗ trợ

* VerificationDocumentStatus pending → approved/rejected; approved → expired khi có căn cứ hết hiệu lực. Thay file tạo document pending mới, không ghi đè lịch sử bản cũ. Document bắt buộc expired làm mất eligibility public/tạo dịch vụ mới mà không đổi TutorProfileStatus/UserStatus; bản thay thế approved khôi phục gate theo BR-16. ReportStatus open → resolved/dismissed; ReviewVisibility visible ↔ hidden theo quyền moderation và audit. AnnouncementViewState draft → published; private/public là toàn bộ FileVisibility, quyền membership được kiểm riêng.

## Đối chiếu dữ liệu cũ

* Enum Catalog là tập giá trị đích; các dòng dưới chỉ là mapping migration, không thêm literal vào catalog. Listing cancelled hoặc lỗi gõ archieved → archived; Tutor approval active → approved, pending → draft/submitted theo submitted\_at; Verification Document active → approved; Invitation active → accepted; Submission reviewed → accepted. File visibility program → private nhưng giữ resource links và policy membership.  
* Phí cũ declared → status declared \+ tutor\_payment\_mark unmarked; confirmed → declared \+ marked\_paid; cancelled → voided, giữ dấu đã biết hoặc unmarked nếu chưa có bằng chứng. Không biến voided thành chưa thu. Khi migration hồ sơ suspended phải giữ trạng thái gốc để restore. Dữ liệu mâu thuẫn/không đủ căn cứ phải đối soát, không tự kích hoạt hoặc giả lịch sử.  
* Các vòng đời trên là chuẩn nghiệp vụ để triển khai; không chứng nhận schema/API đã có đầy đủ.
