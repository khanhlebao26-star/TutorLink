# Deployment & Environment Requirements

# 1\. Phạm vi và trạng thái

Tài liệu này quy định các yêu cầu về triển khai và quản lý môi trường cho phiên bản MVP của TutorLink. Ngày cập nhật: 18/10/2026.

Trong tài liệu:

* **Nguồn:** Yêu cầu đã được đề cập trong các tài liệu của dự án.  
* **NFR bổ sung:** Yêu cầu hoặc tiêu chí được đề xuất để hệ thống dễ triển khai, kiểm tra và vận hành hơn. Đây chưa phải bằng chứng cho thấy hệ thống đã đáp ứng.

Một số chức năng hoặc API vẫn đang chờ quyết định về nghiệp vụ hoặc quyền truy cập. Không được tự coi những nội dung này là đã được phê duyệt chỉ vì chúng xuất hiện trong OpenAPI.

Phạm vi triển khai MVP gồm:

* Frontend web.  
* Backend API.  
* PostgreSQL để lưu dữ liệu.  
* Lưu trữ file riêng tư.  
* Email phục vụ xác thực tài khoản.  
* Worker xử lý notification và các công việc nền.

TutorLink cần phân biệt bốn môi trường:

* **Local:** Môi trường phát triển trên máy lập trình viên.  
* **Test:** Môi trường chạy kiểm thử.  
* **Staging:** Môi trường thử nghiệm trước khi phát hành.  
* **Production:** Môi trường dành cho người dùng thật.

Tài liệu này mô tả yêu cầu cần đạt, không khẳng định rằng production đã được triển khai hoặc nhà cung cấp hosting đã được lựa chọn.

Không bắt buộc bổ sung Redis, Reverb hoặc dịch vụ trả phí mới chỉ để đáp ứng các yêu cầu trong tài liệu này.

Một số thuật ngữ cần hiểu:

| Thuật ngữ | Ý nghĩa đơn giản |
| ----- | ----- |
| Environment | Một môi trường chạy ứng dụng, chẳng hạn local hoặc production. |
| Secret | Thông tin bí mật như mật khẩu DB, `APP_KEY`, SMTP password. |
| Runtime | Phiên bản và môi trường cần có để chạy ứng dụng, ví dụ PHP, Node.js, PostgreSQL. |
| Artifact | Sản phẩm đã build, sẵn sàng được triển khai. |
| Migration | Các thay đổi cấu trúc database được quản lý bằng code. |
| Worker | Tiến trình xử lý công việc nền, như gửi notification. |
| Rollback | Đưa ứng dụng về phiên bản trước khi phát hành có lỗi. |
| Smoke test | Một nhóm kiểm tra nhanh các chức năng quan trọng sau triển khai. |
| Release manifest | Bản ghi các phiên bản code, dependency, cấu hình và migration của một lần phát hành. |

# 2\. Yêu cầu và cách kiểm chứng

## DE-01 \- Tách môi trường và bảo vệ thông tin bí mật

**Yêu cầu**

Mỗi môi trường phải sử dụng tài nguyên riêng, bao gồm:

* Database.  
* Khu vực lưu trữ file.  
* Domain hoặc địa chỉ truy cập.  
* Secret và thông tin xác thực.  
* Cấu hình gửi email.

Dữ liệu kiểm thử không được làm ảnh hưởng đến dữ liệu production.

Đối với kiểm thử tự động:

* Có thể dùng SQLite chạy trong bộ nhớ (`SQLite :memory:`) cho những bài test phù hợp.  
* Những bài test cần tính năng đặc thù của PostgreSQL phải sử dụng database PostgreSQL dành riêng cho test.  
* Lệnh migration và test phải kiểm tra môi trường cùng database đích trước khi chạy.  
* Không được để bài test tự động kết nối nhầm vào database production.

Staging và demo chỉ sử dụng dữ liệu giả, không sao chép dữ liệu cá nhân thật nếu chưa có quy trình bảo vệ phù hợp.

**Tiêu chí nghiệm thu**

* Chứng minh được các môi trường sử dụng đúng tài nguyên riêng trước khi chạy CI/E2E.  
* Ứng dụng báo lỗi ngay khi thiếu secret bắt buộc hoặc phát hiện cấu hình trỏ nhầm database.  
* Log khởi động không chứa `APP_KEY`, `DB_PASSWORD`, mật khẩu SMTP, cookie hoặc thông tin xác thực nhạy cảm.

## DE-02 \- Thống nhất phiên bản công nghệ và cách build ứng dụng

**Yêu cầu**

Các môi trường phải sử dụng phiên bản PHP, Node.js và PostgreSQL tương thích với ứng dụng cùng các thư viện đã khai báo.

Cụ thể:

* Ghi lại phiên bản runtime và dependency trong thông tin của mỗi lần phát hành.  
* CI và staging phải sử dụng cùng phiên bản major/minor PostgreSQL với production.  
* Khóa phiên bản dependency hoặc image cần thiết; không sử dụng phiên bản `latest` một cách thiếu kiểm soát.  
* Cài đặt thư viện dựa trên lockfile như `composer.lock` và `package-lock.json`.  
* Build ứng dụng production một lần rồi triển khai cùng một bản build sang môi trường đích, thay vì tạo ra những bản build khác nhau ngoài dự kiến.

Frontend, backend, worker, scheduler và migration phải tương thích với cùng phiên bản phát hành.

Không sử dụng server dành cho phát triển như `php artisan serve` hoặc `next dev` làm server production.

**Tiêu chí nghiệm thu**

* Một máy mới có thể cài đặt và chạy ứng dụng theo hướng dẫn trong runbook.  
* Có thông tin về phiên bản PHP, Node.js, PostgreSQL và dependency của bản phát hành.  
* Kiểm tra được các thành phần cần thiết trước khi triển khai.  
* Worker, scheduler, ứng dụng và migration sử dụng phiên bản tương thích với nhau.

## DE-03 \- Đảm bảo các thành phần cần thiết hoạt động

**Nguồn**

Các thành phần cần thiết của TutorLink gồm:

* Frontend web.  
* Backend API.  
* PostgreSQL.  
* Dịch vụ lưu trữ file riêng tư.  
* Email phục vụ xác thực tài khoản.  
* Notification nội bộ (`in_app`) sử dụng PostgreSQL Outbox và worker.

Outbox là nơi lưu lại công việc cần xử lý tiếp theo trong cùng transaction với thay đổi nghiệp vụ. Worker sẽ đọc các công việc này và xử lý sau.

**NFR bổ sung**

Worker và scheduler phải có cơ chế:

* Tự khởi động lại khi gặp lỗi theo chính sách đã cấu hình.  
* Giới hạn thời gian xử lý phù hợp.  
* Kiểm tra trạng thái hoạt động bằng health check hoặc heartbeat.  
* Dừng an toàn khi cần triển khai phiên bản mới.

Dữ liệu trong database, file storage và Outbox phải được lưu trữ bền vững, không bị mất chỉ vì container hoặc tiến trình được khởi động lại.

**Tiêu chí nghiệm thu**

* Khởi động lại container không làm mất dữ liệu database, file hoặc Outbox đã lưu.  
* Nếu worker tạm dừng, API vẫn có thể trả về kết quả nghiệp vụ đã được lưu thành công.  
* Khi worker hoạt động trở lại, các công việc còn chờ được xử lý tiếp theo đúng quy tắc.  
* Mailpit chỉ được sử dụng ở local để thử gửi email.  
* Production phải sử dụng dịch vụ gửi email thực tế đã được kiểm thử.  
* Không tự mở rộng phạm vi gửi email hoặc SMS notification ngoài chức năng Auth đã xác định.

## DE-04 \- Cấu hình domain, kết nối và bảo mật production

**Nguồn**

TutorLink sử dụng cookie của Laravel Sanctum và cơ chế CSRF để bảo vệ các request cần xác thực.

Frontend và API phải được cấu hình đúng domain, Origin và danh sách domain được phép sử dụng cơ chế xác thực của Sanctum.

Production phải sử dụng HTTPS.

**NFR bổ sung**

* Đặt `APP_DEBUG=false` trên production để không hiển thị thông tin lỗi chi tiết cho người dùng.  
* Chỉ công khai những thành phần thực sự cần thiết, như frontend, API hoặc reverse proxy.  
* Không công khai trực tiếp database, công cụ quản trị file, Mailpit, Telescope hoặc các công cụ vận hành.  
* Chỉ tin cậy những host và proxy đã được xác định rõ trong cấu hình.

Cookie phiên đăng nhập cần có các thuộc tính phù hợp:

* `Secure`: chỉ gửi qua HTTPS.  
* `HttpOnly`: JavaScript phía trình duyệt không thể đọc trực tiếp cookie.  
* `SameSite`: kiểm soát việc gửi cookie trong các ngữ cảnh cross-site.

**Tiêu chí nghiệm thu**

* Kiểm tra được login, CSRF và logout trên domain HTTPS thực tế.  
* Request từ Origin không được phép không thể truy cập dữ liệu riêng tư trái phép.  
* Cookie phiên được cấu hình đúng theo mô hình domain và xác thực của hệ thống.  
* Không dùng kết quả chạy trên localhost làm bằng chứng duy nhất cho cấu hình production.

## DE-05 \- Quản lý cấu hình và tài nguyên dùng chung

**NFR bổ sung**

Các cấu hình quan trọng phải được khai báo rõ ràng và thống nhất với những tài liệu NFR liên quan, bao gồm:

* Thời gian hết hạn session.  
* Giới hạn số request và tần suất truy cập.  
* Giới hạn kích thước request và file upload.  
* Timeout của API.  
* Chu kỳ polling.  
* Giới hạn kết nối database.  
* Quy tắc retry và heartbeat của worker.  
* Đường dẫn lưu file và thời gian lưu trữ.  
* URL API mà frontend sử dụng.

Thông tin bí mật chỉ được lưu ở phía server. Các biến môi trường có tiền tố `NEXT_PUBLIC_` chỉ được chứa giá trị được phép công khai cho trình duyệt.

Nếu TutorLink chạy nhiều instance ứng dụng, những dữ liệu cần dùng chung phải được lưu trong hệ thống dùng chung và bền vững. Điều này bao gồm session, giới hạn truy cập, trạng thái chống xử lý lặp và file storage.

Không được phụ thuộc vào bộ nhớ riêng của từng instance cho những trạng thái cần nhất quán trên toàn hệ thống.

**Tiêu chí nghiệm thu**

* Ứng dụng kiểm tra các biến môi trường bắt buộc khi khởi động.  
* Nếu thiếu cấu hình quan trọng, ứng dụng phải báo lỗi thay vì âm thầm chuyển sang dữ liệu giả hoặc chế độ local.  
* Không lưu cache công khai đối với response có chứa dữ liệu riêng tư.  
* Ghi nhận tài nguyên sử dụng tại staging và production để có thể đối chiếu với kết quả kiểm thử hiệu năng.

## DE-06 \- Quản lý migration và đồng bộ tài liệu

**Nguồn**

Các quyết định thiết kế được cập nhật ngày 18/10/2026 cần được phản ánh nhất quán trong code và database, bao gồm:

* Phân biệt hai loại Session theo mô hình nghiệp vụ đã thống nhất.  
* Lưu thông tin người được giao việc và version tương ứng.  
* Submission có cơ chế chống xử lý lặp và kiểm tra hash request.  
* Sử dụng Outbox để lưu công việc cần xử lý nền.

Đây là những quyết định ở cấp tài liệu. Việc một bảng database hoặc API route đã được mô tả không chứng minh rằng chức năng tương ứng đã được triển khai đầy đủ.

Mỗi lần phát hành cần ghi lại:

* Những quyết định nghiệp vụ và kỹ thuật được áp dụng.  
* Các migration và thao tác chuyển đổi dữ liệu (backfill).  
* DTO, Enum và các thay đổi liên quan đến hợp đồng API.  
* Khả năng tương thích của frontend và các client hiện có.

Không dùng số lượng bảng database cố định làm tiêu chí duy nhất để đánh giá hệ thống đạt yêu cầu.

**Tiêu chí nghiệm thu**

* Chạy thử migration trên bộ dữ liệu giả có cấu trúc tương đương dữ liệu cần nâng cấp.  
* Bảo toàn dữ liệu lịch sử, snapshot, version và Audit theo quy tắc nghiệp vụ.  
* Có backup phù hợp trước migration có rủi ro.  
* Đối chiếu Domain, PRD, API Catalog, OpenAPI, tài liệu database và sơ đồ draw.io.  
* Nếu các tài liệu mâu thuẫn hoặc một API đang bật nhưng chưa được phê duyệt nghiệp vụ/quyền truy cập, phải xử lý trước khi phát hành.  
* Không tự sửa hoặc ghi đè tài liệu khác chỉ để loại bỏ mâu thuẫn trên giấy tờ.

## DE-07 \- Kiểm thử tự động và tài liệu phát hành

**NFR bổ sung**

Quy trình CI cần thực hiện các kiểm tra phù hợp với từng giai đoạn của dự án, bao gồm:

* Lint và typecheck để phát hiện lỗi code.  
* Unit test và feature test để kiểm tra chức năng.  
* Kiểm tra tính hợp lệ và tính nhất quán của OpenAPI.  
* Security regression test để phát hiện lỗi bảo mật đã biết.  
* PostgreSQL integration test để kiểm tra database thực tế.  
* Kiểm thử race condition, khóa dữ liệu, unique constraint và Outbox.  
* Browser E2E để kiểm tra cookie, CSRF và thao tác bàn phím.  
* Kiểm thử hiệu năng và khôi phục dữ liệu trước khi hoàn tất G4.

Tài liệu OpenAPI đầy đủ vẫn có thể chứa endpoint đang chờ quyết định. Việc endpoint xuất hiện trong OpenAPI không có nghĩa là endpoint đó đã được phép sử dụng.

**Tiêu chí nghiệm thu**

* Không bật 7 endpoint đang ở trạng thái conditional hoặc các endpoint Admin directory/evidence khi chưa có quyết định rõ ràng về phạm vi và quyền truy cập.  
* Nếu công cụ kiểm tra chưa hỗ trợ đầy đủ, kết quả phải ghi rõ phần đã kiểm tra và phần còn thiếu; không được báo cáo đạt hoàn toàn.  
* Không phát hành khi còn lỗ hổng Critical hoặc High chưa được xử lý theo tiêu chí SP-10.  
* Có bằng chứng kiểm thử cho từng cổng phát hành G1–G4.

## DE-08 \- Triển khai, quay lại phiên bản cũ và bàn giao

**NFR bổ sung**

Trước khi thực hiện migration có rủi ro, cần tạo backup phù hợp.

Sau khi triển khai, hệ thống chỉ được nhận traffic khi health check và readiness check đạt yêu cầu.

Khi có thể, nên thay đổi database theo cách vẫn tương thích với phiên bản ứng dụng trước và sau khi nâng cấp.

Nếu cần rollback:

* Có thể đưa ứng dụng về phiên bản trước theo runbook.  
* Không tự động chạy migration `down` nếu thao tác đó có thể làm mất hoặc phá hủy dữ liệu.  
* Dừng hoặc chuyển đổi worker theo đúng quy trình để tránh phiên bản cũ xử lý dữ liệu không tương thích với code mới.

**Tiêu chí nghiệm thu**

Thực hiện diễn tập deploy và rollback bằng dữ liệu giả, sau đó:

* Chạy smoke test cho Guest, Customer, Tutor và Admin.  
* Kiểm tra request ID, cảnh báo hệ thống và Outbox.  
* Xác nhận dữ liệu vẫn nhất quán sau quá trình triển khai hoặc rollback.

Runbook phải hướng dẫn:

* Cài đặt và cấu hình môi trường.  
* Chạy migration và tạo dữ liệu demo.  
* Tạo tài khoản Admin nội bộ theo quy trình an toàn.  
* Thay đổi hoặc luân chuyển secret.  
* Backup và restore theo mục tiêu đề xuất RPO ≤ 24 giờ, RTO ≤ 4 giờ khi đã được duyệt; áp dụng bước đối soát trạng thái/quyền trước mở lại dịch vụ của RR-08. Backup không tự bảo toàn các lần thu hồi quyền xảy ra sau snapshot.  
* Khởi động, dừng và kiểm tra worker.  
* Xử lý hoặc chạy lại công việc Outbox khi được phép.  
* Xác định người chịu trách nhiệm vận hành.

Khi bàn giao, phải cung cấp release manifest và bằng chứng kiểm tra tương ứng.

# 3\. Nghiệm thu

G4 chỉ được xem là hoàn tất khi có môi trường triển khai thực tế và các bằng chứng đã được người nghiệm thu chỉ định chấp thuận, bao gồm:

* URL hoặc thông tin truy cập môi trường triển khai.  
* Release manifest ghi nhận phiên bản được triển khai.  
* Kết quả pipeline kiểm thử.  
* Kết quả smoke test và E2E.  
* Kết quả kiểm thử hiệu năng.  
* Kết quả kiểm tra bảo mật.  
* Kết quả diễn tập backup và restore.

Tài liệu đầy đủ, OpenAPI hợp lệ hoặc database schema đúng thiết kế không tự chứng minh rằng hệ thống đã triển khai và vận hành thành công.

# 4\. Phụ thuộc và điểm chưa chốt

Trước khi phát hành, nhóm cần thống nhất các cấu hình còn phụ thuộc vào những tài liệu khác, bao gồm:

* Giới hạn truy cập (quota).  
* Thời gian lưu dữ liệu và log (retention).  
* Quy tắc thử lại công việc thất bại (retry).  
* Mục tiêu hiệu năng và độ sẵn sàng có thể đo lường (SLO).

Các quyết định nghiệp vụ đang chờ xử lý trong những mục Dxx cũng phải được giải quyết theo từng API hoặc chức năng liên quan.

Tài liệu NFR này không tự quyết định những vấn đề sau:

* Quy tắc cho phép sử dụng ngày sinh (DOB allow-list).  
* Những trường thông tin nào của Tutor được phép công khai.  
* Quy tắc bắt đầu hoặc mở lại Conversation.  
* Phạm vi truy cập Admin directory.  
* Quyền xem evidence sau khi Report đã đóng.

Cuối cùng, không sử dụng log tiến độ, nhãn “Có route” hoặc việc tài liệu đã có schema làm bằng chứng rằng chức năng đã hoạt động đúng trên môi trường thực tế

Đồng bộ trạng thái NFR 18/10/2026: mục ghi Nguồn giữ quyết định đã có; mọi Bổ sung NFR/đề xuất chưa có xác nhận vẫn Proposed, chưa dùng đơn lẻ để pass/fail G4. Trước khi áp dụng, ghi mã yêu cầu, giá trị, owner/approver, ngày xác nhận, test/evidence và phụ thuộc Dxx trong chính phần yêu cầu hoặc Lịch sử làm việc hiện có. Nếu thiếu xác nhận thì giữ Pending; không suy phê duyệt từ từ “phải” trong mục nghiệm thu, không dùng NFR mới để tự mở lại quyết định đã chốt. Chỉ nghiệm thu phạm vi và môi trường đã được duyệt; không đồng nhất G4 trình diễn với production thực tế.

.
