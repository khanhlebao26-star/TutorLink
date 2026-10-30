# Performance & Capacity

# 1\. Phạm vi và trạng thái

Tài liệu này quy định mục tiêu về tốc độ, khả năng chịu tải và sử dụng tài nguyên của TutorLink trong phiên bản MVP. Cập nhật ngày 18/10/2026.

Trong tài liệu có hai loại yêu cầu:

- **Yêu cầu đã có trong Documents:** các mục tiêu được ghi nhận trong tài liệu dự án.
- **Bổ sung NFR:** các con số/cách kiểm thử được đề xuất thêm. Trạng thái phê duyệt hiện hành được ghi ở BR-26 và mục 4; được duyệt làm target không đồng nghĩa hệ thống đã đạt.

Nếu một quy tắc nghiệp vụ trong OpenAPI vẫn đang chờ quyết định hoặc chỉ được áp dụng khi đáp ứng điều kiện nhất định, không được tự xem quy tắc đó là đã được phê duyệt.

Tài liệu áp dụng cho website tiếng Việt, REST API, PostgreSQL, chức năng tải tệp lên và worker xử lý công việc nền (outbox) trong phạm vi MVP.

Các mục tiêu quy mô ban đầu của TutorLink:

- 5.000 tài khoản.
- 500–1.000 người dùng hoạt động trong một tháng (MAU).
- 50 người dùng hoạt động đồng thời trong điều kiện tải thông thường.
- Có thể kiểm thử khi số người dùng đồng thời tăng lên 100 trong thời gian ngắn.
- 95% request API phản hồi dưới 2 giây (p95).
- Thao tác tìm kiếm hoàn thành trong dưới 3 giây.

Đây là các mục tiêu cần kiểm chứng, không phải kết quả đã được đo hoặc xác nhận đạt được.

## **Một số thuật ngữ cần hiểu**

- MAU (Monthly Active Users): số người dùng hoạt động trong một tháng.
- Concurrent users: số người dùng hoạt động đồng thời trong một khoảng thời gian.
- API latency: thời gian API xử lý request và trả kết quả.
- p95: phân vị thứ 95\. Ví dụ, nếu p95 là 1,8 giây thì 95% request có thời gian phản hồi không quá 1,8 giây; 5% còn lại có thể chậm hơn.
- Benchmark: phép đo hiệu năng có kiểm soát để đánh giá hệ thống.
- Worker: tiến trình chạy nền để xử lý các công việc không nhất thiết phải hoàn thành ngay trong request của người dùng, chẳng hạn gửi thông báo.

# 2\. Yêu cầu và cách kiểm chứng

## PC-01 · Hồ sơ tải và dữ liệu

**Mục tiêu:** Kiểm tra TutorLink với lượng dữ liệu đủ lớn, gần với quy mô dự kiến, thay vì chỉ chạy thử trên database có rất ít dữ liệu.

### **Yêu cầu**

TutorLink cần hướng tới quy mô 5.000 tài khoản, 500–1.000 người dùng hoạt động mỗi tháng và 50 người dùng đồng thời. Trong bài kiểm thử tăng tải, hệ thống cần được kiểm tra với tối đa 100 người dùng đồng thời trong thời gian ngắn.

Bổ sung NFR: bộ dữ liệu kiểm thử cần có tối thiểu:

- 5.000 Listing.
- 1.000 Program.
- 100.000 Message.
- 10.000 Task.

Tất cả tài khoản và tệp sử dụng trong kiểm thử phải là dữ liệu giả, không sử dụng dữ liệu cá nhân thật.

Bộ dữ liệu cần có cả thông tin riêng tư và nhiều tin nhắn có cùng thời điểm tạo, để kiểm tra cả hiệu năng lẫn việc phân quyền và sắp xếp dữ liệu chính xác.

### **Cách nghiệm thu**

Khi báo cáo kết quả kiểm thử, cần ghi rõ:

- Số lượng bản ghi của từng loại dữ liệu.
- Dung lượng database và nơi lưu trữ tệp.
- CPU và RAM của môi trường kiểm thử.
- Phiên bản database.
- Số worker đang chạy.
- Cấu hình connection pool, tức số kết nối database được quản lý và tái sử dụng.

Không được dùng kết quả chạy trên database gần như trống để kết luận hệ thống đáp ứng mục tiêu quy mô.

## PC-02 · Độ trễ API và tìm kiếm

**Mục tiêu:** Đảm bảo API và chức năng tìm kiếm phản hồi đủ nhanh khi người dùng sử dụng hệ thống.

### **Yêu cầu**

Theo mục tiêu trong tài liệu dự án:

- API có p95 dưới 2.000 ms (2 giây).
- Tìm kiếm có thời gian hoàn thành dưới 3.000 ms (3 giây).

**p95** là thời gian mà 95% request hoàn thành trong hoặc trước đó. Ví dụ, p95 bằng 1,8 giây nghĩa là 95% request không mất quá 1,8 giây, còn 5% có thể lâu hơn.

Bổ sung NFR: đo thời gian API từ lúc gửi request đến khi nhận đủ dữ liệu JSON. Bài đo cần sử dụng session, quyền truy cập và truy vấn thực tế như khi người dùng sử dụng TutorLink.

Không tính thời gian người dùng nhập nội dung vào form hoặc thời gian tải tệp lên/xuống vào chỉ số API JSON này.

Với tìm kiếm, cần đo hai khoảng thời gian riêng:

1. **Thời gian phản hồi API tìm kiếm:** từ lúc gửi request đến khi nhận đủ JSON.
2. **Thời gian hoàn thành tìm kiếm trên giao diện:** từ lúc người dùng áp dụng bộ lọc đến khi kết quả được hiển thị đầy đủ.

### **Cách nghiệm thu**

Ở mức tải chuẩn 50 người dùng đồng thời, mỗi nhóm sau cần có ít nhất 1.000 mẫu đo hợp lệ sau giai đoạn khởi động:

- Auth: đăng nhập và các thao tác xác thực.
- Read: đọc dữ liệu.
- Write: tạo hoặc cập nhật dữ liệu.
- Search: tìm kiếm và lọc dữ liệu.

Báo cáo cần có p50, p95 và tỷ lệ lỗi cho từng nhóm. p50 là thời gian mà 50% request hoàn thành trong hoặc trước đó.

Không dùng thời gian trung bình thay cho p95. Không được bỏ các request timeout khỏi báo cáo chỉ để làm cho kết quả trông nhanh hơn.

## PC-03 · Kịch bản kiểm thử tải

**Mục tiêu:** Kiểm tra hệ thống khi nhiều người sử dụng cùng lúc và khi lượng truy cập tăng đột ngột.

### **Kịch bản kiểm thử**

Bổ sung NFR: bài kiểm thử tải chuẩn gồm ba giai đoạn:

1. **Khởi động:** 5 phút để hệ thống ổn định trước khi lấy số liệu chính thức.
2. **Tải chuẩn:** 50 người dùng đồng thời trong 30 phút.
3. **Tải tăng đột ngột (burst):** 100 người dùng đồng thời trong 5 phút.

Trong giai đoạn tải chuẩn, các thao tác được phân bổ như sau:

- 50%: xem và tìm kiếm Listing trên marketplace.
- 20%: đọc chat và kiểm tra tin nhắn mới bằng polling.
- 15%: xem dữ liệu trong workspace.
- 10%: tạo hoặc cập nhật dữ liệu bằng các thao tác hợp lệ.
- 5%: đăng nhập và các thao tác xác thực.

Mục tiêu mô phỏng là khoảng một HTTP request mỗi 5 giây cho mỗi người dùng, có think time tự nhiên; tỷ lệ 50/20/15/10/5% tính theo HTTP request, không theo số lần bấm nút. Ở 50 người trong 30 phút có khoảng 18.000 request và 900 request Auth, chưa đủ 1.000 mẫu Auth hợp lệ của PC-02. Nếu thiếu mẫu, kéo dài riêng giai đoạn tải chuẩn, giữ 50 người và cùng tỷ lệ/tốc độ cho đến khi từng nhóm Auth/Read/Write/Search đủ ít nhất 1.000 mẫu hợp lệ; báo cáo thời lượng thực tế và mọi lỗi. Burst không thay mẫu tải chuẩn.

Các bài kiểm thử tải tệp và kiểm tra nhiều thao tác xảy ra đồng thời trên cùng dữ liệu cần chạy riêng. Không gộp thời gian truyền tệp lớn vào chỉ số tốc độ phản hồi JSON.

### **Cách nghiệm thu**

- Tổng tỷ lệ lỗi HTTP 5xx và timeout phải dưới 1% trong bài kiểm thử tải chuẩn.
- Khi tăng lên 100 người dùng, hệ thống không được làm mất dữ liệu hoặc khiến worker bị treo.
- Báo cáo kết quả tải chuẩn và burst riêng biệt.
- Không được dùng kết quả burst để khẳng định đã đạt mục tiêu p95 của tải chuẩn, hoặc ngược lại.

Các phản hồi 4xx, 409 và 429 phải được thống kê riêng. Đây có thể là các phản hồi hợp lệ trong một số tình huống, nhưng không được dùng chúng để che giấu lỗi chức năng hoặc lỗi hệ thống.

## PC-04 · Tải trang phía client

**Mục tiêu:** Đảm bảo người dùng có thể mở trang, xem nội dung và thao tác trên giao diện một cách mượt mà.

### **Yêu cầu**

Bổ sung NFR: trên bản production build, TutorLink hướng tới các chỉ số trải nghiệm sau ở phân vị 75 (p75) khi có đủ dữ liệu từ người dùng thật:

- **LCP ≤ 2,5 giây:** nội dung lớn quan trọng nhất trên trang xuất hiện đủ nhanh.
- **INP ≤ 200 ms:** giao diện phản hồi nhanh khi người dùng nhấn nút, nhập liệu hoặc thao tác.
- **CLS ≤ 0,1:** bố cục không bị dịch chuyển bất ngờ trong lúc trang tải.

Trước khi có đủ dữ liệu từ người dùng thật, cần kiểm thử trong môi trường lab có cấu hình thiết bị và mạng được ghi lại.

Các trang cần kiểm tra gồm:

- Marketplace.
- Trang chi tiết Tutor.
- Chat.
- Workspace.

Kết quả kiểm thử lab chỉ phản ánh môi trường thử nghiệm, không được xem là bằng chứng đã đạt mục tiêu p75 trên người dùng thật.

### **Cách nghiệm thu**

- Hình ảnh có kích thước phù hợp, không tải ảnh quá lớn khi không cần thiết.
- Nội dung dài được chia trang hoặc tải dần khi cần.
- Không tải toàn bộ lịch sử chat ngay khi mở cuộc trò chuyện.
- Không tải toàn bộ dữ liệu workspace ngay lần mở đầu nếu người dùng chưa cần đến.
- Lưu báo cáo quá trình tải tài nguyên (_waterfall_) và cấu hình đo, không chỉ nộp một điểm Lighthouse.

## PC-05 · Phân trang và truy vấn

**Mục tiêu:** Giảm lượng dữ liệu phải tải mỗi lần và đảm bảo truy vấn vẫn hoạt động tốt khi dữ liệu tăng lên.

### **Yêu cầu**

Marketplace phải phân trang ở phía server. API không được trả toàn bộ Listing trong một request.

Giới hạn số bản ghi mỗi trang của marketplace là tối đa 50\. Bổ sung NFR: số bản ghi mặc định là 20 nếu hợp đồng API chưa quy định giá trị khác.

Khi đếm tổng số bản ghi và trả danh sách dữ liệu, API phải áp dụng cùng điều kiện lọc và phạm vi truy cập. Ví dụ, số lượng Listing được đếm phải khớp với điều kiện tìm kiếm và phạm vi dữ liệu mà người dùng được phép xem.

Đối với các loại dữ liệu khác:

- **Message:** sử dụng cursor dựa trên `created_at` và `id` để tiếp tục lấy tin nhắn từ vị trí xác định.
- **Notification:** sử dụng cursor ổn định để tránh bỏ sót hoặc lặp dữ liệu khi tải tiếp.
- **Submission:** sử dụng version để xác định đúng phiên bản dữ liệu.

API không được trả về danh sách không giới hạn số lượng bản ghi. D06/BR-14 đã chốt Listing nhúng trong PublicTutor tối đa 10/trang, tải trang sau bằng `listings_page` trên cùng GET Tutor và `listings_meta.has_more`/`next_page`; không thêm endpoint. Runtime hiện còn dùng `get()` cho toàn bộ Listing nên chưa đạt PC-05. Truy vấn không được phát sinh thêm một truy vấn riêng cho từng bản ghi theo cách gây N+1.

### **Cách nghiệm thu**

- Kiểm tra kế hoạch thực thi truy vấn (_query plan_) để xác nhận database sử dụng index phù hợp với điều kiện lọc, sắp xếp và phạm vi truy cập.
- Thực hiện kiểm tra trên bộ dữ liệu của PC-01.
- Nếu sử dụng cache cho dữ liệu riêng tư, khóa cache phải phân biệt đúng người dùng hoặc phạm vi truy cập.
- Thay đổi quyền do đình chỉ tài khoản hoặc thu hồi assignment phải có hiệu lực theo quy tắc phân quyền, không phụ thuộc vào việc cache hết hạn.

Phân trang mặc định 20, tối đa 50 đã chốt tại BR-26; ngoại lệ route-specific nằm trong API Route Catalog. Runtime chưa đồng bộ toàn bộ.

## PC-06 · Polling, backlog và tài nguyên

**Mục tiêu:** Giữ cho chat và notification cập nhật đúng cách mà không tạo quá nhiều request hoặc tiêu tốn tài nguyên không cần thiết.

### **Yêu cầu**

Phương án cơ bản của MVP là dùng REST API để polling và đồng bộ lại dữ liệu khi kết nối được khôi phục. Reverb không bắt buộc phải được triển khai để đáp ứng yêu cầu này.

Tần suất polling đã chốt tại BR-26:

- Chat đang mở: mỗi 5 giây.
- Inbox và notification: mỗi 15 giây.

Mỗi nguồn dữ liệu chỉ được có tối đa một request polling đang chạy tại cùng thời điểm.

Khi request gặp lỗi hoặc bị giới hạn tốc độ (`429`), frontend tăng thời gian chờ theo thứ tự 5 → 10 → 20 → 30 giây. Nếu server trả `Retry-After`, frontend phải tuân thủ thời gian được yêu cầu.

Khi tab trình duyệt bị ẩn, frontend nên giảm tần suất polling. Khi người dùng quay lại, hệ thống đồng bộ dữ liệu mới bằng cursor để tiếp tục từ vị trí đã biết.

### **Cách nghiệm thu**

- Không tạo các request polling chồng lên nhau.
- Không bỏ sót tin nhắn khi kết nối bị gián đoạn và được khôi phục.
- Dừng polling khi người dùng đăng xuất hoặc component giao diện không còn được sử dụng.
- Tần suất polling được cấu hình thống nhất và có thể điều chỉnh.

Các khoảng thời gian trên là target đã chốt cho D07/BR-26. Không bắt buộc bổ sung Reverb hoặc Redis vào kiến trúc MVP.

## **PC-07 · Tệp tải lên và giới hạn tài nguyên**

**Mục tiêu:** Đảm bảo chức năng upload/download không làm tiêu tốn quá nhiều bộ nhớ, kết nối hoặc tài nguyên server.

### **Yêu cầu**

Các giới hạn tệp hiện có:

- Avatar: tối đa 5 MB (5.000.000 byte).
- Các tệp khác: tối đa 5 MB (5.000.000 byte) theo danh sách định dạng và mục đích sử dụng được cho phép trong M10.

Khi bổ sung mục đích tải tệp mới, không được tự động cho phép tệp lớn hơn các giới hạn hiện hành.

Bổ sung NFR:

- Mỗi người dùng được tải lên tối đa 2 tệp đồng thời.
- Kiểm tra kích thước tệp ở cả proxy và ứng dụng.
- Có giới hạn cấu hình cho hàng đợi, kết nối và thời gian chờ xử lý.
- Khi tải tệp xuống, sử dụng stream khi phù hợp thay vì nạp toàn bộ tệp vào bộ nhớ rồi trả về trong một JSON response.

### **Cách nghiệm thu**

- Tệp vượt giới hạn bị từ chối rõ ràng và có kiểm soát.
- Upload tệp không hợp lệ hoặc quá lớn không làm server hết RAM.
- Upload hợp lệ không khiến API JSON vượt mục tiêu PC-02 trong điều kiện tải chuẩn.
- Log chỉ ghi các thông tin tổng hợp cần thiết như dung lượng và thời gian xử lý; không ghi nội dung tệp hoặc storage key.

## PC-08 · Theo dõi dung lượng và mở rộng

**Mục tiêu:** Phát hiện sớm tình trạng thiếu tài nguyên và chuẩn bị cho việc tăng năng lực xử lý khi TutorLink phát triển.

### **Yêu cầu**

Đã chốt ngày 21/10/2026: cảnh báo khi dung lượng database hoặc private storage đạt 80%; chủ động xử lý trước khi đạt 90%.

Target đã chốt ngày 21/10/2026: 95% notification được đưa vào inbox và đọc được trong vòng 30 giây sau commit, dưới tải chuẩn. Cảnh báo outbox pending quá 5 phút theo BR-25.

Thời gian này phải được đo đến khi bản ghi notification thực sự đọc được từ inbox, không chỉ đến lúc công việc được đưa vào hàng đợi.

Connection pool của database không được vượt quá giới hạn kết nối đã khai báo cho môi trường triển khai.

### **Cách nghiệm thu**

- Có báo cáo xác định các điểm nghẽn có thể xảy ra ở CPU, RAM, truy vấn database, storage và lượng công việc đang chờ xử lý.
- Có phương án tăng số worker hoặc instance khi cần.
- Trước khi chạy nhiều instance ứng dụng, cần đảm bảo session, bộ giới hạn request, replay store và storage được cấu hình phù hợp để dùng chung khi cần thiết.
- Không cam kết hệ thống đáp ứng lượng người dùng lớn hơn mức đã kiểm thử và có bằng chứng.

# 3\. Nghiệm thu

Việc kiểm tra hiệu năng được chia thành bốn giai đoạn:

- **G1 — Marketplace và Search:** kiểm tra danh sách Listing, bộ lọc, phân trang, truy vấn database và tốc độ tìm kiếm.
- **G2 — Chat và Invitation:** kiểm tra polling, tải tin nhắn, khôi phục kết nối và các thao tác Invitation.
- **G3 — Workspace, File và Outbox:** kiểm tra dữ liệu workspace, upload/download, worker và thời gian xử lý notification.
- **G4 — Kiểm tra toàn bộ bản phát hành:** chạy lại các bài kiểm thử trên đúng bản release build dự kiến triển khai.

Mỗi lần nghiệm thu cần lưu lại script kiểm thử, dữ liệu seed, cấu hình môi trường và kết quả đo thô kèm thời điểm thực hiện. Nhờ đó, nhóm có thể chạy lại và so sánh kết quả sau khi thay đổi code hoặc cấu hình.

# 4. Trạng thái quyết định và implementation gaps

Các target PC-01–PC-08 được Product chốt ngày 21/10/2026 làm tiêu chí nghiệm thu cho môi trường MVP, không phải bằng chứng hệ thống hiện đã đạt. Bao gồm profile/dataset và tải kiểm thử đã nêu ở phần trên; API p95 <2 giây, search p95 <3 giây; Web Vitals p75 LCP ≤2.5 giây, INP ≤200 ms, CLS ≤0.1; polling chat 5 giây, inbox/notification 15 giây; backoff 5→10→20→30 giây và tôn trọng Retry-After; page mặc định 20, tối đa 50; tối đa 2 upload đồng thời; cảnh báo storage 80%, hành động trước 90%; 95% notification sẵn sàng trong 30 giây dưới tải chuẩn.

Implementation gaps: cần đo trên build/môi trường nghiệm thu đã thống nhất, lưu dataset/script/kết quả và đối chiếu từng target. Không ghi “đạt” nếu chưa có kết quả đo.
