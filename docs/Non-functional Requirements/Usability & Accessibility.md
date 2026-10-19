# Usability & Accessibility

## **Yêu cầu về tính dễ sử dụng và khả năng tiếp cận**

# 1\. Phạm vi và trạng thái

Tài liệu này quy định cách thiết kế giao diện TutorLink để người dùng dễ hiểu, dễ thao tác và có thể sử dụng trên nhiều loại thiết bị.

TutorLink là ứng dụng web tiếng Việt dành cho Guest, Customer, Tutor và Admin. Giao diện cần hoạt động trên điện thoại, tablet và máy tính, đồng thời hỗ trợ người dùng thao tác bằng bàn phím.

Có hai loại yêu cầu trong tài liệu:

* **Nguồn:** Yêu cầu đã có trong các tài liệu của dự án.  
* **Bổ sung NFR:** Tiêu chí được đề xuất thêm để việc kiểm tra và nghiệm thu rõ ràng hơn. Các tiêu chí này chưa có nghĩa là hệ thống đã triển khai hoặc đã đạt được.

Mục tiêu bổ sung là hướng tới **WCAG 2.2 mức AA** cho các trang và quy trình thuộc phạm vi MVP. Đây là bộ tiêu chí giúp website dễ tiếp cận hơn với nhiều đối tượng người dùng. Checklist trong tài liệu này chỉ hỗ trợ kiểm tra, không thay thế việc đánh giá WCAG đầy đủ.

# 2\. Yêu cầu và cách kiểm chứng

## UA-01 \- Ngôn ngữ và cách gọi tên thống nhất

**Yêu cầu**

Giao diện sử dụng tiếng Việt dễ hiểu. Tên vai trò, chức năng và trạng thái phải thống nhất với tài liệu Domain và Enum của dự án.

Không hiển thị tên cột database, trạng thái cũ hoặc thuật ngữ kỹ thuật khó hiểu trong thông báo dành cho người dùng.

Các quy tắc hiển thị được đề xuất thêm:

* Tiền Việt Nam đồng (VND) hiển thị không có phần thập phân, ví dụ: `250.000 ₫`.  
* Thời gian hiển thị rõ ràng theo múi giờ Việt Nam `Asia/Ho_Chi_Minh`.  
* API tiếp tục sử dụng thời gian UTC theo định dạng RFC 3339\.

**Điều kiện nghiệm thu**

* Một trạng thái phải có cùng tên hiển thị ở trang danh sách, trang chi tiết và notification.  
* Fee Record phải có lời giải thích rõ rằng đây là khoản phí do Tutor khai báo, không phải bằng chứng đã thanh toán.  
* Khi hiển thị lịch trống của Tutor, giao diện không được khiến Customer hiểu nhầm rằng lịch đã được đặt thành công.

## UA-02 \- Giao diện trên điện thoại và khả năng đọc

**Yêu cầu**

Giao diện phải dễ đọc và dễ thao tác trên các kích thước màn hình sau:

* Điện thoại: 360 px.  
* Tablet: 768 px.  
* Desktop: màn hình máy tính.

Các tiêu chí bổ sung:

* Khi phóng to nội dung 200%, người dùng vẫn có thể đọc và sử dụng các chức năng chính.  
* Ở chiều rộng 320 CSS px, nội dung cần tự sắp xếp lại theo các tiêu chí áp dụng, không làm mất thông tin hoặc thao tác quan trọng.  
* Nếu bảng dữ liệu quá rộng, chỉ vùng bảng được cuộn ngang; toàn bộ trang không nên bị tràn ngang. Vùng cuộn cần có cách nhận biết phù hợp.

**Điều kiện nghiệm thu**

Kiểm tra các khu vực:

* Marketplace và tìm kiếm.  
* Chat.  
* Invitation.  
* Sáu mục trong Program Workspace.  
* Admin Dashboard.

Không được có nút xác nhận bị che khuất hoặc cửa sổ dialog vượt khỏi màn hình đến mức không thể thao tác. Nội dung và hình ảnh quan trọng phải hiển thị đầy đủ ở cả giao diện sáng và tối.

## UA-03 \- Sử dụng bằng bàn phím và thao tác thuận tiện

**Yêu cầu**

Người dùng phải có thể hoàn thành các quy trình chính mà không bắt buộc sử dụng chuột.

Các phím cần hỗ trợ:

* `Tab`: Chuyển đến thành phần tiếp theo có thể thao tác.  
* `Shift + Tab`: Quay lại thành phần trước đó.  
* `Enter`: Kích hoạt nút hoặc liên kết phù hợp.  
* `Escape`: Đóng dialog khi được phép.

Vị trí đang được chọn bằng bàn phím (*focus*) phải dễ nhìn thấy và không bị các thành phần khác che khuất.

Đối với dialog:

* Focus phải được quản lý hợp lý khi dialog mở.  
* Khi đóng dialog, focus phải quay về vị trí phù hợp.  
* Người dùng cần có cách đóng dialog rõ ràng.

Không bắt buộc người dùng kéo-thả để hoàn thành tác vụ. Kích thước vùng chạm cần đáp ứng các tiêu chí WCAG 2.2 AA tương ứng khi áp dụng.

**Điều kiện nghiệm thu**

Người dùng có thể thực hiện các tác vụ sau chỉ bằng bàn phím:

* Đăng nhập.  
* Tìm kiếm.  
* Gửi tin nhắn.  
* Chấp nhận Invitation.  
* Nộp bài.  
* Thực hiện quyết định của Admin.

Không được xảy ra trường hợp người dùng bị mắc kẹt trong một khu vực giao diện và không thể chuyển đến nơi khác bằng bàn phím.

## UA-04 \- Hỗ trợ biểu mẫu và trình đọc màn hình

**Yêu cầu**

Các thành phần giao diện phải có thông tin rõ ràng để cả người dùng thông thường và công cụ hỗ trợ như trình đọc màn hình (*screen reader*) đều có thể hiểu.

Các quy tắc cần áp dụng:

* Mỗi ô nhập liệu và nút cần có tên hoặc nhãn mà công cụ hỗ trợ có thể nhận biết.  
* Không chỉ dùng placeholder làm nhãn duy nhất của ô nhập liệu.  
* Khi nhập sai, thông báo lỗi phải liên kết với trường tương ứng.  
* Tiêu đề và thông báo thay đổi trên giao diện phải có cấu trúc phù hợp để công cụ hỗ trợ nhận biết.  
* Cho phép người dùng dán nội dung, sử dụng autofill và password manager.  
* Nút hiện hoặc ẩn mật khẩu phải có tên và trạng thái rõ ràng.  
* Màu chữ và màu nền phải đủ tương phản theo các tiêu chí WCAG AA áp dụng.

**Điều kiện nghiệm thu**

* Dùng screen reader để kiểm tra form, dialog và thông báo thành công hoặc thất bại.  
* Lỗi phải được mô tả bằng chữ, không chỉ biểu thị bằng cách đổi màu.  
* Kết quả kiểm tra accessibility tự động phải được bổ sung bằng kiểm tra thủ công.

## UA-05 \- Hiển thị trạng thái lỗi và phiên hết hạn

**Yêu cầu**

Giao diện phải phản ánh đúng trạng thái do server trả về. Không được thông báo thành công trước khi server xác nhận dữ liệu đã được lưu.

Các trạng thái cần có:

* **Loading:** Đang xử lý yêu cầu.  
* **Empty:** Chưa có dữ liệu.  
* **Success:** Thao tác đã thành công.  
* **Error:** Có lỗi và cần hướng dẫn cách xử lý.

Hệ thống cần xử lý các mã HTTP sau:

| Mã HTTP | Ý nghĩa | Cách giao diện xử lý |
| ----- | ----- | ----- |
| `401` | Chưa đăng nhập hoặc phiên không còn hợp lệ | Hướng dẫn đăng nhập lại khi cần |
| `403` | Người dùng không có quyền | Thông báo không đủ quyền |
| `409` | Dữ liệu bị xung đột hoặc đã thay đổi | Tải lại trạng thái mới và hướng dẫn xử lý |
| `419` | Phiên hoặc CSRF token không còn hợp lệ theo cơ chế của ứng dụng | Khởi tạo lại CSRF theo thao tác phù hợp |
| `422` | Dữ liệu gửi lên không hợp lệ | Hiển thị lỗi tại trường liên quan |
| `429` | Gửi yêu cầu quá thường xuyên | Hiển thị thời gian cần chờ trước khi thử lại |

Khi mất mạng hoặc yêu cầu bị timeout, người dùng phải có cách thử lại. Dữ liệu đã nhập và không nhạy cảm nên được giữ lại khi có thể.

Password và token không được lưu trong `localStorage`.

**Điều kiện nghiệm thu**

* Sau lỗi mạng hoặc timeout, người dùng biết cách xử lý hoặc thử lại.  
* Khi nhận `409`, giao diện tải lại trạng thái hoặc version mới, không âm thầm ghi đè dữ liệu.  
* Khi nhận `419`, hệ thống khởi tạo lại CSRF theo thao tác của người dùng, không tự động gửi lại lệnh nghiệp vụ.  
* Khi nhận `429`, giao diện hiển thị thời gian chờ nếu có thông tin phù hợp.  
* Không hiển thị thành công khi server chưa xác nhận thao tác đã được lưu.

## UA-06 \- Ngăn thao tác nhầm và thao tác lặp

**Yêu cầu**

Giao diện cần giúp người dùng tránh nhấn nhầm hoặc thực hiện hành động quan trọng khi chưa hiểu hậu quả.

Giao diện không phải là cơ chế bảo vệ quyền duy nhất. Backend vẫn phải kiểm tra quyền truy cập và điều kiện nghiệp vụ.

Đối với các hành động có ảnh hưởng lớn, cần giải thích hậu quả và yêu cầu xác nhận hoặc nhập lý do theo PRD. Ví dụ:

* Archive.  
* Complete hoặc Cancel Program.  
* Void Fee Record.  
* Suspend tài khoản.  
* Các quyết định quan trọng của Admin.

Khi đang gửi yêu cầu, nút thao tác nên được vô hiệu hóa để hạn chế nhấn liên tục. Nếu cần gửi lại cùng một thao tác, hệ thống phải giữ nguyên Idempotency Key theo quy tắc của API.

**Điều kiện nghiệm thu**

* Nhấn nút nhiều lần không tạo bản ghi trùng.  
* Khi Complete Program bị chặn do còn Session hoặc Task chưa đáp ứng điều kiện, giao diện phải chỉ rõ những mục đang cản trở và cách xử lý.  
* Không tự động hủy hoặc hoàn thành Task để vượt qua điều kiện hoàn thành Program.  
* Khi tải lại dữ liệu, giao diện không được làm trạng thái đã đọc hoặc vị trí con trỏ bị lùi một cách sai lệch.

## UA-07 \- Tìm kiếm, khám phá và điều hướng

**Yêu cầu**

Người dùng cần biết mình đang ở Program hoặc Conversation nào và có cách quay lại vị trí trước đó.

Program Workspace gồm sáu mục:

1. Overview.  
2. Session.  
3. Task.  
4. Progress.  
5. Announcement.  
6. Fee.

Các quy tắc điều hướng:

* Khi tải lại hoặc chia sẻ đường dẫn Marketplace, thông tin tìm kiếm (*query*) phải được giữ lại.  
* Khi chuyển trang danh sách, các bộ lọc, cách sắp xếp và phân trang phải được giữ phù hợp.  
* Người dùng có thể mở đúng tài nguyên từ notification.  
* Khi mở đường dẫn trực tiếp, hệ thống vẫn phải kiểm tra quyền truy cập ở server.  
* Người không có quyền không được nhìn thấy nội dung riêng tư trước khi nhận thông báo từ chối truy cập.  
* Khi Invitation hết hạn sau 72 giờ, giao diện chỉ hướng dẫn người dùng yêu cầu lời mời mới, không cho chấp nhận bằng dữ liệu cũ.

**Điều kiện nghiệm thu**

* Deep link từ notification mở đúng tài nguyên và vẫn kiểm tra quyền.  
* Người không có quyền không nhìn thấy nội dung riêng tư trong chốc lát trước khi bị từ chối.  
* Invitation đã hết hạn không thể được chấp nhận bằng dữ liệu cũ.

## UA-08 \- Kiểm thử với người dùng và lưu bằng chứng

**Yêu cầu**

Trước G4, dự án đề xuất tổ chức thử nghiệm khả dụng với ít nhất 5 người, sử dụng dữ liệu giả và các tác vụ phù hợp với vai trò của họ.

Mục tiêu đề xuất là ít nhất 4/5 người hoàn thành mỗi luồng được giao mà không cần người hướng dẫn thao tác hộ.

Trong quá trình thử nghiệm, cần ghi lại:

* Màn hình và thiết bị được sử dụng.  
* Các lỗi gặp phải.  
* Thời gian hoàn thành tác vụ.  
* Những bước khiến người dùng bối rối hoặc không biết cách tiếp tục.

Các lỗi khiến người dùng không thể hoàn thành tác vụ phải được xử lý trước khi nghiệm thu.

**Điều kiện nghiệm thu**

* Kiểm tra các phiên bản hiện hành của Chrome, Edge và Firefox; kiểm tra Safari khi có môi trường phù hợp.  
* Kiểm tra trên desktop và mobile.  
* Kiểm tra bằng bàn phím và ít nhất một screen reader.  
* Chỉ báo cáo trình duyệt, thiết bị và môi trường đã thực sự được kiểm tra.

Không được kết luận rằng hệ thống hỗ trợ tốt mọi trình duyệt hoặc đạt WCAG chỉ dựa vào kết quả kiểm tra trên một trình duyệt hay một công cụ tự động.

# 3\. Nghiệm thu

### **G1 — Public pages và tìm kiếm**

Kiểm tra khả năng sử dụng các trang công khai và chức năng tìm kiếm.

### **G2 — Chat và Invitation**

Kiểm tra việc gửi tin nhắn, xử lý Invitation và các trạng thái liên quan.

### **G3 — Program Workspace**

Kiểm tra sáu mục trong Program Workspace, feedback và Fee Record.

### **G4 — Accessibility và kiểm thử người dùng**

Kiểm tra thao tác bằng bàn phím, screen reader, giao diện responsive và khả năng hoàn thành tác vụ của người dùng.

**Bằng chứng nghiệm thu cần lưu:**

* Checklist WCAG áp dụng.  
* Kết quả kiểm tra theo trình duyệt và thiết bị.  
* Các lỗi đã phát hiện và cách khắc phục.  
* Kết quả thử nghiệm với người dùng.

Không tuyên bố TutorLink được chứng nhận WCAG 2.2 AA chỉ dựa trên checklist này.

# 4\. Phụ thuộc và điểm chưa chốt

Các nội dung sau là đề xuất bổ sung, cần được xác nhận trước khi coi là yêu cầu chính thức:

* Mục tiêu WCAG 2.2 mức AA.  
* Yêu cầu phóng to 200% và reflow ở 320 CSS px.  
* Danh sách trình duyệt cần hỗ trợ.  
* Thử nghiệm với ít nhất 5 người và mục tiêu 4/5 hoàn thành mỗi luồng.

Không tự phê duyệt các chức năng hoặc quyền Admin vẫn đang ở trạng thái cần xác nhận trong tài liệu khác.

Tên gọi Session trên giao diện phải tuân theo quyết định nghiệp vụ đã thống nhất. Không đưa tên bảng hoặc chi tiết schema database vào giao diện dành cho người dùng

Đồng bộ trạng thái NFR 18/10/2026: mục ghi Nguồn giữ quyết định đã có; mọi Bổ sung NFR/đề xuất chưa có xác nhận vẫn Proposed, chưa dùng đơn lẻ để pass/fail G4. Trước khi áp dụng, ghi mã yêu cầu, giá trị, owner/approver, ngày xác nhận, test/evidence và phụ thuộc Dxx trong chính phần yêu cầu hoặc Lịch sử làm việc hiện có. Nếu thiếu xác nhận thì giữ Pending; không suy phê duyệt từ từ “phải” trong mục nghiệm thu, không dùng NFR mới để tự mở lại quyết định đã chốt. Chỉ nghiệm thu phạm vi và môi trường đã được duyệt; không đồng nhất G4 trình diễn với production thực tế.

.
