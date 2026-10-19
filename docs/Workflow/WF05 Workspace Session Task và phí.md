# WF05 Workspace Session Task và phí

## 01 Mở Workspace

**![Sơ đồ image1](assets/wf05-workspace-session-task-va-phi-image1.png)**

Sáu tab đọc độc lập qua các luồng dưới; không phải gọi toàn bộ theo một chuỗi.

## 02 Lên lịch và hoàn tất Session

**![Sơ đồ image2](assets/wf05-workspace-session-task-va-phi-image2.png)**

## 03 Hủy Session

**![Sơ đồ image3](assets/wf05-workspace-session-task-va-phi-image3.png)**

scheduled → cancelled, có reason; không hard-delete.

## 04 Giao Task và bắt đầu làm

**![Sơ đồ image4](assets/wf05-workspace-session-task-va-phi-image4.png)**

Tutor giao việc; Customer assignee bắt đầu. File giao bài gắn nguyên tử khi tạo/sửa Task.

## 05 Nộp hoặc cập nhật bài

**![Sơ đồ image5](assets/wf05-workspace-session-task-va-phi-image5.png)**

PUT dùng Idempotency-Key UUID. Cùng key \+ cùng payload trả lại version đã commit; cùng key \+ payload khác trả 409\. Key mới tạo version mới khi expected\_submission\_version khớp hiện tại; version cũ được giữ. Lưu key/hash cùng version và liên kết file, Task state trong transaction.

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

## 10 Tạo và công bố Announcement

**![Sơ đồ image10](assets/wf05-workspace-session-task-va-phi-image10.png)**

Member chỉ đọc published; attachment gắn đúng resource và purpose.

## 11 Khai báo phí tháng

**![Sơ đồ image11](assets/wf05-workspace-session-task-va-phi-image11.png)**

Mặc định status declared \+ tutor\_payment\_mark unmarked; một Program/tháng, VND nhập tay.

## 12 Đánh dấu thu phí

**![Sơ đồ image12](assets/wf05-workspace-session-task-va-phi-image12.png)**

Chỉ là Tutor khai báo, không xác nhận ngân hàng.

## 13 Sửa dấu thu và vô hiệu phí sai

**![Sơ đồ image13](assets/wf05-workspace-session-task-va-phi-image13.png)**

Đây là các nhánh xử lý khi cần: marked\_paid → marked\_unpaid phải có reason trước sửa amount; void độc lập, cuối và giữ lịch sử/kỳ.

## 14 Hoàn tất Program

![Sơ đồ image14](assets/wf05-workspace-session-task-va-phi-image14.png)

Xử lý hết Session/Task trước complete; không tự complete việc chưa làm. paused không tạo Session/Task/Announcement/Fee mới; terminal chỉ đọc.
