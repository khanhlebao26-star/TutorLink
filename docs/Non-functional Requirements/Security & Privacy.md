# Security & Privacy

# 1\. Yêu cầu và cách kiểm chứng

## SP-01 · Xác thực và trạng thái tài khoản

### **Yêu cầu**

* **Đăng nhập:** TutorLink sử dụng cookie phiên của Laravel Sanctum để xác thực người dùng. Đăng ký không tạo phiên; sau khi xác minh email, đăng nhập thành công phải tạo session ID mới.
* **Đăng xuất:** Server phải vô hiệu hóa phiên hiện tại và tạo CSRF token mới. Chỉ xóa cookie trên trình duyệt là chưa đủ.  
* **Kiểm tra trạng thái tài khoản:** Chỉ tài khoản có UserStatus \= active mới được thực hiện những thao tác yêu cầu tài khoản đang hoạt động. Server phải kiểm tra trạng thái hiện tại trong mỗi request cần xác thực.  
* **Xác minh email:** Tài khoản chưa xác minh tồn tại để nhận và kiểm tra link email nhưng không được đăng nhập hoặc dùng API riêng tư. Gửi lại email xác minh là thao tác công khai có phản hồi trung tính và rate limit. Xác minh link không tự tạo session; người dùng đăng nhập sau đó.
* **Điều kiện dành cho Tutor:** Những thao tác yêu cầu Tutor đã được duyệt chỉ được phép thực hiện khi hồ sơ Tutor đáp ứng điều kiện tương ứng. Trạng thái tài khoản và trạng thái hồ sơ Tutor phải được kiểm tra riêng.  
* **Quyền do server quản lý:** Người dùng không được tự thay đổi role, permissions hoặc trạng thái tài khoản bằng dữ liệu gửi từ frontend.

### **Cách kiểm chứng**

* Đăng xuất rồi gửi lại cookie phiên cũ. Request không được truy cập dữ liệu riêng tư.  
* Thử đăng nhập và truy cập API bằng tài khoản suspended hoặc deactivated. Các thao tác bị cấm phải bị từ chối, kể cả khi trình duyệt còn cookie phiên cũ.  
* Thử thực hiện chức năng dành riêng cho Tutor đã xác minh hoặc được duyệt bằng tài khoản chưa đáp ứng điều kiện. Server phải từ chối.  
* Thử gửi role, permissions hoặc status giả trong request. Người dùng không được tự cấp quyền cho mình.
* Sau đăng ký không có session xác thực; login trước xác minh bị từ chối, link hợp lệ xác minh email, sau đó login mới tạo session. Resend không cần login và không phân biệt email không tồn tại/đã xác minh trong phản hồi.

## SP-02 · HTTPS, cookie và CSRF

### **Yêu cầu**

* **HTTPS:** Môi trường production phải sử dụng HTTPS để bảo vệ dữ liệu khi truyền giữa trình duyệt và server.  
* **Bảo vệ cookie:** Cookie phiên phải được cấu hình phù hợp:  
  * `HttpOnly`: JavaScript phía trình duyệt không thể đọc cookie phiên.  
  * `Secure`: Cookie chỉ được gửi qua HTTPS.  
  * `SameSite`: Được cấu hình phù hợp với cách triển khai domain của frontend và backend.  
* **CORS:** Chỉ cho phép những origin đã được cấu hình. Không sử dụng wildcard origin (`*`) khi cho phép gửi credentials.  
* **CSRF:** Những request làm thay đổi dữ liệu phải có CSRF token hợp lệ. Request thiếu hoặc sai token phải bị từ chối và không được ghi dữ liệu.  
* **XSRF-TOKEN:** Frontend có thể đọc cookie `XSRF-TOKEN` để gửi token trong header theo cơ chế của Sanctum. Token này không phải cookie phiên và không được nhầm với session secret.  
* **Không tự gửi lại thao tác:** Khi nhận lỗi `419` do CSRF hoặc session, frontend không được tự động gửi lại request làm thay đổi dữ liệu nếu chưa xác định nguyên nhân và trạng thái của thao tác trước đó.

### **Cách kiểm chứng**

Kiểm tra trực tiếp trên trình duyệt bằng DevTools, gồm cookie, request và response thực tế:

* Origin được phép và origin không được phép.  
* Cookie có đúng thuộc tính bảo mật.  
* Request thiếu CSRF token hoặc sử dụng token sai.  
* Quy trình đăng xuất và sử dụng lại cookie cũ.

Không chỉ dựa vào unit test khi middleware CSRF hoặc các lớp bảo vệ liên quan đã bị tắt.

## SP-03 · Mật khẩu và reset

### **Yêu cầu**

* **Quy tắc mật khẩu:** Mật khẩu phải có tối thiểu 8 ký tự và trường xác nhận mật khẩu phải khớp theo quy định trong M01.  
* **Lưu mật khẩu:** Không lưu mật khẩu dạng văn bản thuần. Server phải sử dụng password hasher chuyên dụng có salt, chẳng hạn bcrypt hoặc Argon2id. Không dùng các hash nhanh như MD5 hoặc SHA-256 trực tiếp để lưu mật khẩu.  
* **Không cắt mật khẩu:** Hệ thống không được âm thầm cắt bớt mật khẩu. Nếu sử dụng bcrypt, phải xử lý giới hạn đầu vào 72 byte UTF-8 một cách rõ ràng. Nếu sử dụng Argon2id, phải kiểm thử cấu hình trước khi triển khai.  
* **Token đặt lại mật khẩu:** Token phải được tạo ngẫu nhiên, lưu dưới dạng hash, có hiệu lực 60 phút và chỉ được sử dụng một lần.  
* **Thu hồi phiên sau khi reset:** Product đã chốt ngày 20/10/2026: sau reset thành công, mọi phiên đăng nhập có trước của tài khoản bị vô hiệu hóa, để các thiết bị phải đăng nhập lại. Reset thất bại không thu hồi phiên. Không tự tạo phiên mới hoặc mở chức năng quản lý thiết bị từ quyết định này.

### **Cách kiểm chứng**

* Token sai, token hết hạn, email không phù hợp hoặc token đã được sử dụng không được phép thay đổi mật khẩu.  
* Sau khi đổi mật khẩu thành công, mật khẩu cũ không còn đăng nhập được.  
* Kiểm tra rằng mật khẩu không bị cắt âm thầm.  
* Kiểm thử bằng ít nhất hai session trình duyệt: reset thành công làm cookie cũ không thể gọi API riêng tư; reset thất bại giữ nguyên các session.

## SP-04 · Chống dò mật khẩu và spam

### **Yêu cầu**

* Rate limiting là cơ chế giới hạn số request trong một khoảng thời gian. Mục đích là giảm việc thử mật khẩu hàng loạt, gửi spam và lạm dụng API.  
* Các quota mục tiêu trong bảng được Product/nhóm chốt ngày 21/10/2026 để đặc tả D07 và kiểm thử. Thay đổi sau này phải có quyết định mới; runtime cần chia sẻ counter giữa worker và chưa được xác nhận đã triển khai.

| Chức năng | Quota mục tiêu đã chốt |
| ----- | ----- |
| Đăng nhập | Tối đa 5 lần sai trong 15 phút cho mỗi cặp email chuẩn hóa \+ IP |
| Đăng nhập | Tối đa 20 lần sai cho mỗi email trong 15 phút |
| Đăng nhập | Tối đa 100 lần sai cho mỗi IP trong 15 phút |
| Đăng ký | Tối đa 20 lần cho mỗi IP trong 1 giờ |
| Quên mật khẩu | Tối đa 3 lần cho mỗi email trong 1 giờ và 20 lần cho mỗi IP trong 1 giờ |
| Đặt lại mật khẩu | Tối đa 10 lần cho mỗi cặp email \+ IP trong 15 phút và 100 lần cho mỗi IP trong 15 phút |
| Gửi lại email xác minh | Tối đa 3 lần cho mỗi user trong 1 giờ, đồng thời không được nới lỏng giới hạn hiện có |
| Gửi tin nhắn mới | Tối đa 30 tin/phút cho mỗi user trên tất cả Conversation; retry idempotent không tính quota (BR-21) |
| Gửi Report | Tối đa 10 lần/giờ và 30 lần/ngày cho mỗi reporter (BR-24) |
| Upload tệp | Tối đa 10 lần cho mỗi user trong 1 phút |

Các giới hạn trên phải được áp dụng theo đúng cách tính đã thống nhất. Ví dụ, một lần gửi tin nhắn cần được tính nhất quán ở cả giới hạn theo Conversation và giới hạn tổng của user.

* Khi vượt giới hạn, API trả về HTTP `429 Too Many Requests` và header `Retry-After` để thông báo thời gian chờ nếu phù hợp.  
* Bộ đếm phải hoạt động nhất quán giữa nhiều worker. Không chỉ lưu giới hạn trong session của người dùng vì session có thể thay đổi.  
* Không khóa tài khoản vĩnh viễn chỉ vì có nhiều lần đăng nhập sai.  
* Cần tính đến người dùng hợp lệ sử dụng chung IP, chẳng hạn sinh viên cùng mạng trường học.

### **Cách kiểm chứng**

* Gửi nhiều request liên tiếp để kiểm tra giới hạn, thử thay session và kiểm tra nhiều worker cùng xử lý request.  
* Quota D07 đã chốt; Scramble/OpenAPI chỉ phản ánh giới hạn khi route đã được đăng ký trong code.

## SP-05 · Không tiết lộ danh sách tài khoản

### **Yêu cầu**

Phạm vi SP-05 là đăng nhập, quên/đặt lại mật khẩu và gửi lại email xác minh: các luồng này không được cho người ngoài xác định email đã đăng ký qua lỗi, mã phản hồi, throttle hoặc hành vi khác nhau. Theo xác nhận của Dũng ngày 18/10/2026, đăng ký giữ validation email trùng hiện tại; đây là ngoại lệ được chấp thuận, không tuyên bố đăng ký chống account enumeration. Không thay luồng đăng ký sang phản hồi trung tính trong đợt đồng bộ này.

* **Đăng nhập:** Khi email hoặc mật khẩu không đúng, sử dụng thông báo chung, không cho biết riêng email có tồn tại hay không.  
* **Quên mật khẩu và đặt lại mật khẩu:** Không tiết lộ tài khoản tồn tại thông qua status code, response body hoặc cách áp dụng giới hạn request.  
* **Gửi lại email xác minh:** Route công khai nhận email và trả phản hồi trung tính dù email không tồn tại, đã xác minh hoặc còn chờ; chỉ gửi thư cho tài khoản chưa xác minh, áp rate limit và không ghi link/token vào log.
* **Giới hạn request nhất quán:** Các email có tài khoản và không có tài khoản phải được xử lý theo chính sách rate limiting nhất quán.  
* **Bảo vệ token:** Không ghi token đặt lại mật khẩu vào log hoặc analytics, và không trả token trong response nếu không cần thiết cho quy trình.  
* **Bảo vệ trang reset:** Không để token trong URL bị gửi qua header `Referer` đến dịch vụ bên ngoài. Cần cấu hình chính sách Referrer-Policy và hạn chế tài nguyên bên thứ ba trên trang reset.  
* **Thời gian phản hồi:** Tránh khác biệt thời gian phản hồi quá rõ ràng giữa email tồn tại và không tồn tại.

### **Cách kiểm chứng**

* Gửi request với email đã đăng ký và email chưa đăng ký trong cùng điều kiện. Status code và response body công khai phải giống nhau theo hợp đồng.  
* Kiểm tra việc áp dụng rate limiting ở cả hai trường hợp.  
* Thử reset bằng token sai và xác nhận hệ thống không tiết lộ thông tin nhạy cảm.  
* Kiểm tra log, analytics và các request rời khỏi trang reset để đảm bảo token không bị lộ.  
* Kiểm tra các khác biệt thời gian phản hồi có thể bị khai thác để dò tài khoản.

## SP-06 · Quyền theo tài nguyên và assignment

### **Yêu cầu**

Đăng nhập thành công không đồng nghĩa với việc người dùng được phép truy cập mọi dữ liệu. Server phải kiểm tra quyền trên từng tài nguyên và từng hành động.

Các điều kiện cần kiểm tra gồm:

* **Role:** Người dùng có vai trò phù hợp không?  
* **Permission:** Người dùng có quyền thực hiện hành động này không?  
* **Ownership/Membership:** Người dùng có sở hữu tài nguyên hoặc là thành viên của Program/Conversation liên quan không?  
* **State:** Trạng thái hiện tại của tài nguyên có cho phép hành động không?  
* **Assignment:** Admin hoặc người xử lý có được giao quyền truy cập vào phạm vi dữ liệu này không?

Những kiểm tra trên phải được áp dụng cho việc xem danh sách, xem chi tiết, tải tệp, thay đổi dữ liệu và thực hiện lại request.

Các quy tắc bổ sung:

* Phải lọc dữ liệu theo phạm vi người dùng được phép truy cập trước khi tính tổng số bản ghi hoặc phân trang.  
* Việc được giao xử lý một Report không tự động cho phép đọc toàn bộ hồ sơ hoặc evidence liên quan.  
* Khi assignment bị thu hồi hoặc chuyển cho người khác, người không còn được phân quyền phải mất quyền truy cập từ request tiếp theo.  
* `allowed_actions` chỉ giúp frontend hiển thị những thao tác phù hợp. Server vẫn phải kiểm tra quyền khi nhận request.  
* Nếu thao tác đình chỉ hồ sơ Tutor đồng thời giữ tài khoản ở trạng thái active hoặc thay đổi trạng thái tài khoản, phải kiểm tra đủ hai permission theo chính sách và thực hiện các thay đổi liên quan trong cùng transaction.  
* Theo BR-11, chỉ Admin active có `admin.reports.view` và assignment hiện tại được đọc evidence khi Report còn `open`; Report đóng hoặc assignment đổi/thu hồi chặn từ request tiếp theo. Với message, giới hạn message bị báo cáo cùng tối đa 10 tin trước/5 tin sau; mỗi lần đọc phải audit. BR-27 đã chốt exact lookup, allow-list DTO và quyền riêng cho Admin directory; runtime chưa triển khai.

### **Cách kiểm chứng**

* Đổi ID hoặc cursor trong URL/request để thử truy cập tài nguyên của người khác.  
* Thử thay `owner`, `sender` hoặc `recipient` trong request body.  
* Thử truy cập dữ liệu bằng Admin không có permission hoặc không có assignment phù hợp.  
* Thu hồi assignment rồi thử truy cập lại bằng tài khoản cũ.  
* Kiểm tra không thể dùng `allowed_actions` hoặc dữ liệu gửi từ frontend để vượt qua authorization.

## SP-07 · Tệp và nội dung không tin cậy

### **Yêu cầu**

* **Lưu trữ riêng tư:** Storage phải ở chế độ private theo mặc định. Việc tải xuống phải được kiểm tra quyền truy cập đối với người dùng, mục đích sử dụng và tài nguyên liên quan.  
* **Kiểm tra file:** Trước khi gắn file vào tài nguyên hoặc cho phép tải xuống, phải kiểm tra quyền sở hữu, mục đích sử dụng (`purpose`), MIME type thực tế, extension, dung lượng, checksum và tài nguyên liên quan.  
* **Giới hạn điều chỉnh 29/10/2026:** Theo [K03.1](../handover/BE-K03-K03.1-contract.md), mọi upload mới tối đa 5.000.000 byte/file; avatar JPG/JPEG/PNG, tài liệu bài tập/bài nộp/CV PDF/JPG/JPEG/PNG/DOC/DOCX. Chat chỉ text, không upload/gắn file. Giới hạn số file/resource và quyền nghiệp vụ vẫn theo module. Runtime K03.7a còn avatar 2 MiB/tài liệu 10 MiB; K03.7b phải đồng bộ giới hạn mới trước khi nghiệm thu.
* **Loại file được phép:** Mỗi `FilePurpose` phải có danh sách định dạng được phép rõ ràng. Không tự cho phép định dạng mới nếu chưa có quy định.  
* **Trạng thái upload:** Chỉ file đã upload hoàn tất và vượt qua các kiểm tra bắt buộc mới được sử dụng.  
* **Bảo vệ đường dẫn:** Không trả object key hoặc đường dẫn storage nội bộ trong API công khai.  
* **Xử lý file rủi ro:** File có thể chứa nội dung nguy hiểm phải được cung cấp theo cách hạn chế thực thi, chẳng hạn tải về dưới dạng attachment khi phù hợp. Server không được thực thi HTML, script hoặc macro Office từ file người dùng tải lên.  
* **Nội dung do người dùng nhập:** Không hiển thị nội dung người dùng dưới dạng HTML không được lọc hoặc mã hóa phù hợp.  
* **Complete và nội dung:** Không có scanner antivirus tự động theo K03.1 mới. File pending chưa được sử dụng; kiểm tra kỹ thuật đạt mới clean. Vi phạm metadata/archive → rejected; storage/validation tạm không khả dụng → pending và cho retry. clean/complete không xác nhận không có malware. Không giả evidence quét; giữ evidence thật hợp lệ trước đây. Task/Submission không chờ duyệt thủ công; CV duyệt nội dung theo đúng file/version và permission/assignment, độc lập với complete. Duyệt CV không đồng nghĩa antivirus-scan. Các luồng nghiệp vụ này phải có implementation/evidence riêng.

### **Cách kiểm chứng**

* Upload file vượt quá giới hạn, sai định dạng hoặc giả MIME type.  
* Thử gắn file vào tài nguyên mà người dùng không có quyền sửa.  
* Thử tải file của người khác hoặc tải file chưa hoàn tất kiểm tra.  
* Kiểm tra object key và đường dẫn nội bộ không bị lộ qua response.

## SP-08 · Tối thiểu hóa dữ liệu và quyền riêng tư

### **Yêu cầu**

Chỉ cung cấp dữ liệu cần thiết cho đúng người và đúng mục đích. API không được trả toàn bộ thông tin trong database chỉ vì frontend cần một vài trường dữ liệu.

* **Dữ liệu công khai:** Public Tutor Profile và Listing phải sử dụng DTO/Resource với danh sách field được phép trả về (allow-list).  
* **Dữ liệu không được công khai:** Không để lộ email riêng, ngày sinh, giấy tờ xác minh, nội dung chat, storage key hoặc evidence ngoài phạm vi được phép.  
* **Thông báo:** Notification chỉ được chứa thông tin tài nguyên, đường dẫn và phần nội dung xem trước đã được cho phép.  
* **Kiểm tra lại quyền:** Khi người dùng mở đường dẫn từ notification, server phải kiểm tra quyền truy cập như với request thông thường.  
* **Dữ liệu riêng tư trong database:** Không trả nguyên entity hoặc toàn bộ trường JSONB riêng tư qua API công khai.  
* **Dữ liệu kiểm thử:** Không sử dụng dữ liệu cá nhân thật trong demo hoặc môi trường test.  
* **Trước khi sử dụng dữ liệu thật:** Phải có thông báo quyền riêng tư và chính sách lưu giữ dữ liệu cho từng loại dữ liệu, nêu rõ mục đích sử dụng, người có quyền truy cập, thời gian lưu và cách xử lý yêu cầu của chủ thể dữ liệu.  
* **Tuân thủ pháp luật:** Việc có tài liệu kỹ thuật này không tự chứng minh TutorLink đã đáp ứng mọi nghĩa vụ pháp lý về bảo vệ dữ liệu cá nhân.

### **Cách kiểm chứng**

* Kiểm tra response của public profile và Listing để xác nhận không có field riêng tư ngoài allow-list.  
* Kiểm tra notification không chứa dữ liệu nhạy cảm không cần thiết.  
* Thử mở deep link bằng tài khoản không có quyền.  
* Xác nhận môi trường demo/test không chứa dữ liệu cá nhân thật ngoài phạm vi được phê duyệt.

## SP-09 · Secret, log và lưu trữ

### **Yêu cầu**

* **Không ghi thông tin bí mật vào log:** Không ghi mật khẩu, reset/signature token, cookie, header Authorization, nội dung riêng tư đầy đủ hoặc toàn bộ evidence vào log vận hành hay audit log.  
* **Quản lý secret:** Secret phải được cung cấp từ cấu hình môi trường hoặc hệ thống quản lý secret phù hợp, không hard-code trong source code hoặc Docker image. Các môi trường development, staging và production phải sử dụng secret riêng.  
* **Bảo vệ backup và storage:** Backup và private storage phải được mã hóa phù hợp, giới hạn quyền truy cập và có quy trình khôi phục được kiểm tra.  
* **Thời hạn lưu:** Audit nghiệp vụ 24 tháng theo BR-25. Log vận hành 30 ngày là baseline đề xuất riêng, cần cấu hình theo môi trường. Không tự động xóa dữ liệu đang cần cho một vụ việc hoặc phải giữ theo chính sách resource.
* **Lịch xóa dữ liệu:** Trước production, phải xác nhận thời gian lưu và quy trình xóa dữ liệu thực tế cho từng loại dữ liệu.

### **Cách kiểm chứng**

* Gửi dữ liệu kiểm thử có chứa secret rồi kiểm tra log đã được che hoặc loại bỏ đúng cách.  
* Dùng tài khoản không có quyền để thử xem log hoặc tải backup.  
* Kiểm tra backup và private storage có cấu hình bảo vệ phù hợp.  
* Xác nhận quy trình lưu giữ, xử lý ngoại lệ và xóa dữ liệu đã được xác định trước khi phát hành production.

## SP-10 · Cổng phát hành bảo mật

### **Yêu cầu**

Trước mỗi lần phát hành, nhóm phát triển phải kiểm tra các rủi ro bảo mật chính và lưu lại bằng chứng kiểm thử.

* **Lỗ hổng nghiêm trọng:** Không phát hành khi còn lỗ hổng Critical/High chưa được xử lý trong phạm vi xác thực, phân quyền và dependency runtime theo chính sách release.  
* **Truy vấn an toàn:** Dùng parameterized queries hoặc cơ chế truy vấn an toàn tương đương; không ghép trực tiếp input người dùng vào câu lệnh SQL.  
* **Kiểm tra input:** Chỉ chấp nhận các field và giá trị được phép. Client không được tự ghi đè những field do server quản lý.  
* **Mã hóa output:** Dữ liệu phải được encode phù hợp với ngữ cảnh hiển thị để giảm nguy cơ XSS.  
* **Kiểm tra dependency:** Mỗi release phải kiểm tra dependency lockfile và các dependency runtime.  
* **Kiểm tra secret:** Chạy secret scanning để tìm secret bị commit nhầm vào repository.  
* **Kiểm thử trường hợp bị từ chối:** Phải kiểm tra các request không hợp lệ hoặc không có quyền, không chỉ kiểm tra luồng hoạt động bình thường.  
* **Lưu bằng chứng:** Ghi nhận phạm vi kiểm thử, kết quả và những giới hạn chưa được kiểm tra.

### **Cách kiểm chứng**

Trên đúng bản build dự kiến phát hành, phải có bằng chứng kiểm thử cho:

* CSRF và cookie.  
* Truy cập trái phép vào tài nguyên (IDOR).  
* Tự nâng quyền hoặc thay đổi role trái phép.  
* Dò mật khẩu và spam request.  
* Dò tài khoản tồn tại.  
* Sử dụng lại reset token.  
* Truy cập file trái phép.  
* Race condition khi xử lý assignment.

Khi một lỗi đã được tái hiện, việc chạy lại test và vẫn tái hiện được lỗi không có nghĩa là lỗi đã sửa. Sau khi sửa, phải có regression test chứng minh hành vi không an toàn đã bị chặn.

# 2\. Nghiệm thu

## **G1 · Authentication và Public DTO**

Kiểm tra đăng nhập, đăng xuất, session, trạng thái tài khoản, CSRF và các trường dữ liệu được phép công khai trên Tutor Profile/Listing.

## **G2 · Chat và Invitation**

Kiểm tra quyền truy cập Conversation, gửi tin nhắn, quyền của các thành viên và những thao tác liên quan đến Invitation.

## **G3 · Workspace, Evidence và File**

Kiểm tra quyền truy cập Program, Task, file đính kèm, giấy tờ xác minh và evidence của Report.

## **G4 · Cấu hình phát hành**

Kiểm tra HTTPS, cookie, log, backup, secret và dependency trên đúng bản build dự kiến phát hành.

# 3. Implementation gaps và policy độc lập còn lại

| Hạng mục | Trạng thái |
| ----- | ----- |
| Public allow-list | Xác định chính xác field nào được phép xuất hiện trong Public Tutor Profile và Listing. |
| Report evidence | BR-11 và BR-24 chốt actor, permission, assignment, cửa sổ, allow-list, retention 24 tháng và DTO theo loại resource. Runtime chưa triển khai. |
| Admin directory | BR-27 đã chốt exact lookup, allow-list DTO và permission; runtime chưa triển khai. |
| Retention dữ liệu thật | BR-24/BR-25 chốt Report, Audit, Outbox và Notification; các loại dữ liệu nguồn khác vẫn theo policy resource riêng. |
| Rate limiting | Quota mục tiêu đã chốt tại SP-04/BR-21/BR-24; cấu hình runtime và test đa worker chưa triển khai/xác minh. |
| Thu hồi session sau reset | Đã chốt tại BR-12; runtime còn phải triển khai và kiểm chứng cùng M01-F06. |
