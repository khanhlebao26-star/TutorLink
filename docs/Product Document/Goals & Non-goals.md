# **TUTORLINK VIETNAM**

## **MỤC TIÊU & NGOÀI PHẠM VI**

MVP · Căn cứ theo Tổng quan dự án · Cập nhật ngày 17 tháng 10 năm 2026

# **1\. Mục tiêu tổng quát**

MVP hướng tới giúp Khách hàng hoàn tất một hành trình dịch vụ 1–1 đáng tin cậy: từ khám phá Gia sư đã được duyệt đến điều phối công việc đã thống nhất trong Chương trình riêng. Phạm vi ưu tiên một sản phẩm web tiếng Việt có thể trình diễn xuyên suốt trong kế hoạch 4 tuần.

# **2\. Mục tiêu sản phẩm**

| MỤC TIÊU | CAM KẾT CỦA MVP | KẾT QUẢ MONG ĐỢI |
| :---- | :---- | :---- |
| **Khám phá dịch vụ** | Người chưa đăng nhập có thể duyệt/tìm kiếm Tin dịch vụ đang hoạt động và hồ sơ Gia sư đã duyệt; hỗ trợ nhiều danh mục. | Người dùng có thể tìm và xem chi tiết công khai qua API thực; có ít nhất 3 danh mục. |
| **Kết nối và thống nhất** | Khách hàng và Gia sư đã đăng nhập trao đổi 1–1. Gia sư đã được duyệt tạo nháp Chương trình và gửi lời mời; Khách hàng chấp nhận hoặc từ chối. | Chỉ cặp đã thống nhất đi vào Chương trình riêng; lời mời có hiệu lực 72 giờ và chỉ Customer được mời mới được chấp nhận một lần. Lời mời hết hạn phải được gửi lại; chấp nhận thành công sẽ kích hoạt Chương trình và tạo thành viên. |
| **Điều phối dịch vụ** | Không gian Chương trình có sáu mục: Tổng quan, Buổi học, Nhiệm vụ, Tiến độ, Thông báo và Khoản phí. Gia sư quản lý công việc; Khách hàng xem tiến độ và nộp Nhiệm vụ được giao. | Chỉ thành viên được truy cập đúng Chương trình; trạng thái và thao tác tuân theo vòng đời hợp lệ. |
| **Tạo dựng niềm tin và bảo vệ quyền riêng tư** | Duyệt Hồ sơ Gia sư, Đánh giá sau khi Chương trình hoàn tất, gửi Báo cáo và kiểm duyệt theo quyền được phân công. | Tutor chỉ được đăng Tin dịch vụ/tạo Chương trình khi tài khoản hoạt động, email đã xác minh, Hồ sơ được duyệt và không bị đình chỉ. Admin chỉ xử lý vụ việc được giao; mọi truy cập liên quan được ghi nhật ký. |
| **Sẵn sàng trình diễn và vận hành** | Ứng dụng web tiếng Việt, hỗ trợ bàn phím và màn hình 360 px / 768 px / máy tính. Mục tiêu tham chiếu: 5.000 tài khoản; 500–1.000 MAU; 50 người dùng đồng thời, cao điểm ngắn 100; API p95 \< 2 giây; tìm kiếm \< 3 giây; mục tiêu nội bộ 99%. | G4 cần bằng chứng kiểm thử xuyên suốt, quyền truy cập, giao diện tương thích, triển khai và khôi phục. |

# **3\. Tiêu chí nghiệm thu**

MVP chỉ được xem là hoàn thành khi đủ bằng chứng và được người nghiệm thu chỉ định chấp thuận cho cả bốn cổng.

| CỔNG | TRỌNG TÂM | BẰNG CHỨNG NGHIỆM THU |
| :---- | :---- | :---- |
| **G1 · Sàn dịch vụ** | Tin dịch vụ, tìm kiếm và trang chi tiết công khai | API thực; quy trình duyệt; từ 3 danh mục trở lên. |
| **G2 · Kết nối** | Cuộc trò chuyện theo cặp và lời mời Chương trình | Chấp nhận nguyên tử; một lần duy nhất; kiểm tra tranh chấp khi xử lý đồng thời. |
| **G3 · Không gian làm việc** | Sáu mục, vòng đời Chương trình và chức năng tạo dựng niềm tin | Kiểm tra quyền thành viên; Đánh giá, Thông báo và Báo cáo hoạt động đúng vòng đời. |
| **G4 · Sẵn sàng trình diễn** | Bàn giao xuyên suốt | Kiểm thử đầu cuối; phân quyền; giao diện tương thích; triển khai; sao lưu và khôi phục. |

# **4\. Ngoài phạm vi MVP và phần để sau**

| HẠNG MỤC | KHÔNG THUỘC MVP | GHI CHÚ PHẠM VI |
| :---- | :---- | :---- |
| **Thanh toán** | Không xử lý, thu, hoàn hoặc xác nhận thanh toán trực tuyến. | Bản ghi khoản phí là thông tin khai báo, không phải xác nhận giao dịch. |
| **Đặt lịch** | Không đặt hoặc giữ chỗ khung giờ. | Thời gian rảnh chỉ mang tính tham khảo. |
| **Video tích hợp** | Không có phòng gọi video tích hợp trong sản phẩm. | Hai bên có thể tự chia sẻ liên kết từ công cụ bên ngoài. |
| **Chương trình nhóm và điểm danh** | Không hỗ trợ Chương trình nhóm hoặc theo dõi điểm danh. | MVP tập trung vào hành trình dịch vụ 1–1. |
| **Tệp của Chương trình/Buổi học** | Không có thư viện tệp riêng cho Chương trình hoặc tệp đính kèm trực tiếp vào Buổi học. | Tệp gửi qua Cuộc trò chuyện và tệp Bài nộp của Nhiệm vụ là luồng riêng, không thuộc giới hạn này. |
| **Ứng dụng di động, AI và thuê bao** | Không phát triển ứng dụng di động riêng, tính năng AI hoặc gói thuê bao trong MVP. | Ưu tiên ứng dụng web tiếng Việt trong phạm vi 4 tuần. |
| **Phần mở rộng sau lõi** | Reverb, Yêu thích, thư viện ảnh/nội dung giới thiệu và biểu đồ nâng cao chưa nằm trong phần cốt lõi. | Chỉ xem xét sau khi hoàn thành lõi; không cam kết trong MVP 4 tuần. |

# **5\. Nguyên tắc ranh giới**

Người chưa đăng nhập chỉ xem dữ liệu sàn dịch vụ công khai; đây là trạng thái truy cập, không phải vai trò. Nhắn tin và sử dụng Chương trình yêu cầu tài khoản. Customer phải từ đủ 15 tuổi; Tutor phải từ đủ 18 tuổi; Admin được tạo nội bộ. Lời mời có hiệu lực 72 giờ; Chương trình chỉ kích hoạt sau khi Customer được mời chấp nhận lời mời còn hạn và chỉ thành viên được truy cập.
