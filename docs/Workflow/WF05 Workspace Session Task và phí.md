# WF05 Workspace Session Task và phí

## 01 Mở Workspace

**![Sơ đồ image1](assets/wf05-workspace-session-task-va-phi-image1.png)**

Sáu tab đọc độc lập qua các luồng dưới; không phải gọi toàn bộ theo một chuỗi.

Overview trả đủ số Task theo năm trạng thái, Session scheduled gần nhất trong tương lai, Progress mới nhất và Announcement published mới nhất. Capability cấp Program do server trả; mỗi resource trả action riêng. Timestamp lưu UTC, hiển thị theo Program timezone.

## 02 Lên lịch và hoàn tất Session

**![Sơ đồ image2](assets/wf05-workspace-session-task-va-phi-image2.png)**

Chỉ Program Active cho tạo/sửa Session `scheduled`. Khi Paused, không sửa lịch; Tutor vẫn có thể hoàn tất/hủy Session scheduled đã có. PATCH/transition mới phải gửi `expected_version`; stale trả 409 theo BR-19.

Session dùng `Program.timezone`; meeting URL tùy chọn, chỉ HTTPS, tối đa 2.048 ký tự, không credentials.

## 03 Hủy Session

**![Sơ đồ image3](assets/wf05-workspace-session-task-va-phi-image3.png)**

scheduled → cancelled, có reason; không hard-delete. Khi Paused vẫn được hủy; terminal chỉ replay kết quả hợp lệ. Action mới cần `expected_version`.

## 04 Giao Task và bắt đầu làm

**![Sơ đồ image4](assets/wf05-workspace-session-task-va-phi-image4.png)**

Tutor giao việc; Customer assignee bắt đầu khi Program Active hoặc Paused. Tutor chỉ sửa Task `todo` khi Active; được hủy `todo`/`in_progress` khi Active/Paused. Task `submitted` cần feedback trước. PATCH/start/cancel dùng `expected_version`; file giao bài gắn nguyên tử khi tạo/sửa Task.

## 05 Nộp hoặc cập nhật bài

**![Sơ đồ image5](assets/wf05-workspace-session-task-va-phi-image5.png)**

PUT dùng Idempotency-Key UUID. Cùng key \+ cùng payload trả lại version đã commit; cùng key \+ payload khác trả 409\. Key mới tạo version mới khi expected\_submission\_version khớp hiện tại; version cũ được giữ. Lưu key/hash cùng version và liên kết file, Task state trong transaction; Task.version tăng cùng thay đổi state, còn replay không tăng version/audit/notification. Nội dung tối đa 10.000 ký tự; cần text không trắng hoặc file. Mỗi Submission tối đa 5 tệp, 5 MiB/tệp. Canonical hash gồm expected\_submission\_version, content đã chuẩn hóa newline và file IDs duy nhất sắp tăng dần.

## 06 Tutor chấp nhận bài

**![Sơ đồ image6](assets/wf05-workspace-session-task-va-phi-image6.png)**

Gửi expected\_submission\_version; Submission accepted và Task completed nguyên tử.

## 07 Yêu cầu sửa và nộp lại

**![Sơ đồ image7](assets/wf05-workspace-session-task-va-phi-image7.png)**

Tutor yêu cầu sửa, Customer nộp lại; feedback phiên bản cũ được giữ.

## 08 Sửa hoặc hủy Task

**![Sơ đồ image8](assets/wf05-workspace-session-task-va-phi-image8.png)**

Sửa và hủy là thao tác độc lập khi còn được phép; hủy giữ Submission lịch sử.

## 09 Ghi tiến độ

**![Sơ đồ image9](assets/wf05-workspace-session-task-va-phi-image9.png)**

MVP chỉ ghi `progress_percent` nguyên 0–100 do Tutor tự đánh giá. Sửa dùng `expected_version` và audit; không xóa. Program Active/Paused cho ghi/sửa; Program terminal chỉ đọc.

## 10 Tạo và công bố Announcement

**![Sơ đồ image10](assets/wf05-workspace-session-task-va-phi-image10.png)**

Draft chỉ Tutor owner đọc. Member hiện tại đọc Published; quyền đọc và tải file kiểm tra membership hiện tại. Attachment phải complete, scan clean và gắn đúng resource/purpose. Draft được tạo/sửa/publish khi Active/Paused; published bất biến, đính chính bằng Announcement mới. Khi publish, chốt recipient notification là các member hiện tại khác Tutor owner; member gia nhập sau đọc bài nhưng không nhận thông báo hồi tố. Cập nhật Published, audit và outbox là nguyên tử; retry không nhân đôi notification, stale `expected_version` trả 409. Danh sách mới nhất trước theo `(published_at,id)`; không có read state riêng cho Announcement.

Draft chỉ nhận title (1–180 ký tự), body (1–10.000 ký tự) và tối đa 5 tệp, 5 MiB/tệp.

## 11 Khai báo phí tháng

**![Sơ đồ image11](assets/wf05-workspace-session-task-va-phi-image11.png)**

Mặc định status declared \+ tutor\_payment\_mark unmarked; một Program/tháng, VND nhập tay. Chỉ tạo khi Program Active, từ tháng kích hoạt tới tháng hiện tại theo timezone; cho khai báo bù, không ghi kỳ tương lai. Khi Paused không tạo record mới; record declared chưa marked_paid có thể sửa, không đổi kỳ. 0 đồng nghĩa miễn phí và giữ unmarked; chỉ sửa về 0 khi mark vẫn unmarked; không có record nghĩa chưa khai báo.

Kỳ `YYYY-MM` theo Program timezone, với period_start ngày đầu và period_end ngày cuối tháng.

## 12 Đánh dấu thu phí

**![Sơ đồ image12](assets/wf05-workspace-session-task-va-phi-image12.png)**

Chỉ là Tutor khai báo, không xác nhận ngân hàng. Customer thấy label “Tutor đánh dấu đã thu/chưa thu”, thời điểm và lý do; không hiển thị như xác nhận thanh toán. Fee mutation dùng Idempotency-Key + `expected_version`; stale trả 409, replay không tăng version/audit/notification. Mỗi mutation thành công báo Customer bằng notification trung tính sau commit.

## 13 Sửa dấu thu và vô hiệu phí sai

**![Sơ đồ image13](assets/wf05-workspace-session-task-va-phi-image13.png)**

marked\_paid → marked\_unpaid phải có reason/audit trước sửa amount. Amount edit và void đều cần reason không trắng; void độc lập, cuối, giữ lịch sử/kỳ và chiếm tháng. Cảnh báo trước void vì kỳ đó không thể tạo lại. Mọi mutation dùng Idempotency-Key + `expected_version` và notify sau commit.

## 14 Hoàn tất Program

![Sơ đồ image14](assets/wf05-workspace-session-task-va-phi-image14.png)

Xử lý hết Session/Task trước complete; không tự complete việc chưa làm. paused không tạo Session/Task/Fee mới; Tutor owner vẫn được tạo, sửa Draft và publish Announcement theo BR-10; các quyền xử lý resource đã có theo BR-19. Terminal chỉ đọc, ngoại trừ Review hợp lệ sau completed.
