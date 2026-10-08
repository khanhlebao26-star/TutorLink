# Business rules

BR-01 Identity: User role cố định, Customer/Tutor từ 18 tuổi; Admin không đăng ký public; xác minh và trạng thái account theo contract.

BR-02 Eligibility: publish/create Program đòi Tutor Approved (`active`), Profile không suspended, User active và điều kiện verified đã chốt. Public Listing cần cả Listing Active, eligibility và catalog visible; không chỉ check status Listing.

BR-03 Profile: chỉ sửa Draft/Changes Requested; Admin decide Submitted pending có reason và audit cùng transaction; restore cần đúng permission.

BR-04 Suspension: Profile suspension chặn public/new activities, không tự xóa lịch sử hay hủy Program. Đọc/nộp bài/feedback ở quan hệ hiện có theo policy đích. Account suspended chặn login/private request. Không mặc định Admin có mọi quyền.

BR-05 Money: MVP VND, exponent 0. `200000` nghĩa 200.000 VND, không nhân/chia 100. Số nguyên không âm; filter/sort giá chỉ so sánh cùng price_unit, không FX. Sample cũ trong docs database không quyết định phép quy đổi.

BR-06 Chat: một direct Conversation mỗi cặp Customer–Tutor, Listing không tham gia direct_key. Server tạo key, check participants/member; read cursor cùng conversation và chỉ tiến lên.

BR-07 Message: unique sender/client_message_id; cùng key/payload retry trả record cũ, key/payload khác theo conflict contract; cursor `(created_at,id)` ổn định. Persistence trước response/event.

BR-08 Offer: một Tutor/một Customer target; snapshot khóa khi gửi invitation và khi Active. Invitation history bất biến; source Listing không sửa offer cũ.

BR-09 Accept: check actor/target/state/expiry/snapshot/eligibility trong transaction; membership và Active atomic. Send/accept/cancel/expire/suspend dùng lock order thống nhất Customer User → Tutor Profile → Program → Invitation. Khóa account liên quan bổ sung phải theo cùng thứ tự đã review.

BR-10 Workspace: Active được vận hành; Paused cấm tạo mới Session/Task/Announcement/Fee nhưng còn submission/feedback/progress; terminal readonly. Complete không tự hoàn thành task còn mở.

BR-11 Task: submission version tăng khi cập nhật; feedback dùng expected version, stale trả 409. is_overdue chỉ task còn mở; submitted_late dựa submitted_at và due_at, không dựa ngày hiện tại.

BR-12 Fee: một record mỗi Program/tháng YYYY-MM; amount VND không âm, nhập thủ công. Mọi trạng thái vẫn là Tutor khai báo, không bằng chứng payment; không tính phí tự động từ Session.

BR-13 Review/Report: review một lần bởi Customer member sau Completed, không sau Cancelled. Report resource allow-list và evidence theo permission; quyết định ghi reason/audit.

BR-14 File: complete + owner/purpose/visibility/resource permission; verification private. Attach chỉ các resource được scope cho phép. Không xóa File phá FK/historical links; `clean` hiện không chứng minh malware scan.

BR-15 Side effects: Audit trong mutation transaction; notification/event sau commit, event_key unique chống retry. Queue rollback không tạo notification giả; không dùng realtime thay REST.

BR-16 Acceptance: bảng có sẵn ≠ API chạy; mock ≠ integrated; test pass ≠ accepted. Không nâng gate hoặc đóng issue khi còn AC chưa kiểm chứng/P0/P1 thuộc gate.
