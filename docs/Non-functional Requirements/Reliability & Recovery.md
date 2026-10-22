# Reliability & Recovery

**Yêu cầu về độ tin cậy và khả năng khôi phục hệ thống**

# 1\. Phạm vi và trạng thái

Tài liệu này mô tả cách TutorLink cần hoạt động khi có lỗi, cách bảo vệ dữ liệu và cách khôi phục hệ thống sau sự cố.

Có hai loại yêu cầu:

* **Nguồn:** Yêu cầu đã có trong các tài liệu của dự án.  
* **Bổ sung NFR:** Mục tiêu hoặc cách kiểm tra được đề xuất thêm. Những nội dung này chưa có nghĩa là hệ thống đã triển khai hoặc đã đạt được.

Các quyết định nghiệp vụ hiện hành nằm trong Business Rules/PRD; OpenAPI sinh từ code chỉ xác nhận route đã đăng ký, không phải cơ chế phê duyệt nghiệp vụ.

**Mục tiêu chính:**

* Một thao tác nghiệp vụ không được tạo ra kết quả trùng lặp chỉ vì người dùng bấm nhiều lần hoặc hệ thống gửi lại yêu cầu.  
* Dữ liệu phải nhất quán ngay cả khi có lỗi mạng, lỗi cơ sở dữ liệu, worker hoặc nơi lưu trữ tệp.  
* Hệ thống phải có khả năng khôi phục dữ liệu và hoạt động trong giới hạn đã thống nhất.  
* PostgreSQL là nơi lưu trạng thái chính thức của hệ thống. Realtime và notification chỉ giúp thông báo thay đổi, không phải nguồn dữ liệu chính thức.

# 2\. Yêu cầu và cách kiểm chứng

## RR-01 \- Tính sẵn sàng của hệ thống

**Yêu cầu**

Mục tiêu ban đầu của dự án là hệ thống hoạt động ít nhất 99% thời gian trong mỗi tháng. Đây là mục tiêu nội bộ, chưa phải SLA thương mại đã được đo lường và xác nhận.

**Cách đề xuất để đo lường**

* Cứ mỗi phút, một công cụ kiểm tra từ bên ngoài sẽ gửi yêu cầu đến hệ thống.  
* Công cụ kiểm tra cả Public API và một quy trình sử dụng chức năng riêng tư bằng tài khoản kiểm thử.  
* Nếu một bước bắt buộc bị lỗi hoặc quá thời gian chờ, lượt kiểm tra đó được tính là thất bại.  
* Nếu công cụ kiểm tra không có dữ liệu, không được mặc định rằng hệ thống hoạt động bình thường.

**Điều kiện nghiệm thu**

* Có báo cáo số lượt kiểm tra thành công trên tổng số lượt đã lên lịch.  
* Ghi nhận thời gian hệ thống bị gián đoạn và thời gian bảo trì riêng.  
* Thời gian bảo trì vẫn được tính khi đánh giá mục tiêu hoạt động tổng thể.  
* Ở giai đoạn G4, phải có công cụ đo lường và thực hiện diễn tập sự cố.  
* Chỉ kết luận hệ thống đạt mục tiêu 99% sau khi có đủ dữ liệu của cả tháng. Một bài kiểm tra chạy tốt trong vài phút hoặc vài giờ không đủ để chứng minh điều này.

## RR-02 \- Đảm bảo giao dịch và xử lý thao tác đồng thời

**Yêu cầu**

Khi Customer chấp nhận lời mời tham gia Program, các thay đổi liên quan phải được thực hiện cùng nhau trong một transaction, bao gồm:

1. Cập nhật trạng thái Invitation.  
2. Tạo Program Member.  
3. Kích hoạt Program.  
4. Ghi Audit Log.  
5. Tạo Outbox Event để xử lý các tác vụ tiếp theo.

Nếu một bước bắt buộc thất bại, toàn bộ thay đổi trong transaction phải được hoàn tác. Không được xảy ra trường hợp Program đã kích hoạt nhưng chưa có thành viên hoặc thiếu bản ghi audit bắt buộc.

Khi nhiều yêu cầu cùng sửa dữ liệu, thực hiện kiểm tra concurrency trên PostgreSQL theo ADR-09. Thứ tự khóa hiện ghi trong PRD là bản nháp kỹ thuật, chưa đủ chứng minh accept an toàn với account-suspend: trạng thái tài khoản Tutor nằm ở users, khác tutor\_profiles. Giao thức khóa/isolation giữa các thao tác liên quan phải được owner kỹ thuật xác nhận và kiểm thử trước phát hành, không tự bổ sung thứ tự khóa hoặc trường dữ liệu trong lượt đồng bộ tài liệu này.

Customer User → Tutor Profile → Program → Invitation

Trước khi hoàn thành Program, hệ thống phải kiểm tra các Session hoặc Task còn ngăn cản việc hoàn thành. Khi thay đổi người được phân công, hệ thống phải quản lý version để quyền của người được phân công cũ bị thu hồi từ yêu cầu tiếp theo.

**Điều kiện nghiệm thu**

Phải kiểm tra trên PostgreSQL thật các trường hợp:

* Hai yêu cầu cùng chấp nhận một Invitation.  
* Một yêu cầu chấp nhận Invitation trong khi yêu cầu khác hủy/làm hết hạn Invitation hoặc đình chỉ tài khoản/hồ sơ Tutor liên quan.  
* Hai yêu cầu đồng thời thay đổi người được phân công.  
* Ghi Audit Log hoặc Outbox thất bại.

Kết quả phải đảm bảo dữ liệu không bị cập nhật một phần. Nếu Audit Log hoặc Outbox bắt buộc không ghi được, thao tác nghiệp vụ phải rollback.

Ngoài việc khóa dữ liệu, cần dùng kiểm soát version khi có nguy cơ một yêu cầu ghi đè thay đổi mới hơn của yêu cầu khác. Chỉ dùng row lock không giải quyết được mọi trường hợp cập nhật mất dữ liệu.

## RR-03 Quản lý Session và lịch sử Submission

**Yêu cầu**

TutorLink phân biệt lịch học dự kiến với buổi học thực tế:

* program\_sessions: Lưu lịch học đã lên kế hoạch.  
* lesson\_sessions: Lưu kết quả buổi học thực tế. Một lịch dự kiến có thể chưa có hoặc có tối đa một bản ghi buổi học thực tế.

API sử dụng ID của lịch học dự kiến để xác định buổi học cần xử lý.

Khi Tutor xác nhận đã hoàn thành một buổi học, hệ thống phải tạo bản ghi buổi học thực tế và cập nhật trạng thái trong cùng transaction. Nếu buổi học bị hủy, hệ thống không tạo bản ghi buổi học thực tế.

Đối với Submission:

* Khi Customer nộp phiên bản mới, phiên bản cũ vẫn phải được giữ lại.  
* Feedback phải gắn với đúng phiên bản Submission.  
* Trạng thái Task, con trỏ chỉ phiên bản hiện tại và liên kết tệp phải được cập nhật cùng transaction.

**Điều kiện nghiệm thu**

* Mỗi program\_session\_id chỉ được liên kết với tối đa một bản ghi lesson\_sessions, được bảo vệ bằng ràng buộc unique.  
* Nếu yêu cầu hoàn thành Session được gửi lại, hệ thống không tạo thêm bản ghi buổi học thực tế, Audit Log hoặc Outbox Event trùng lặp.  
* Chỉ phiên bản Submission mới nhất được nhận Feedback theo quy tắc nghiệp vụ.  
* Nếu expected\_submission\_version không khớp với version hiện tại, API trả về HTTP 409 Conflict.  
* Không được coi một buổi học là đã diễn ra chỉ dựa trên thời gian dự kiến. Không tự tạo dữ liệu điểm danh hoặc video khi không có nghiệp vụ tương ứng.

## RR-04 \- Xử lý yêu cầu gửi lại và chống trùng lặp

**Yêu cầu**

Người dùng có thể bấm nút nhiều lần, mất kết nối hoặc gửi lại yêu cầu khi không nhận được phản hồi. Hệ thống phải tránh tạo nhiều kết quả cho cùng một thao tác.

Đây là mục tiêu của **idempotency**: cùng một thao tác được gửi lại không tạo thêm kết quả nghiệp vụ trùng lặp.

Đối với tin nhắn:

* Mỗi tin nhắn giữ client\_message\_id.  
* Nếu gửi lại cùng ID và cùng nội dung, hệ thống trả về tin nhắn đã được lưu thành công.  
* Nếu cùng ID nhưng nội dung khác, hệ thống trả về lỗi xung đột.

Đối với Submission:

* Dùng Idempotency-Key có định dạng UUID.  
* Lưu request\_hash để kiểm tra nội dung yêu cầu.  
* Dùng ràng buộc UNIQUE(task\_id, customer\_id, idempotency\_key) để ngăn tạo bản ghi trùng.  
* Key được giữ theo vòng đời version liên quan, không tự áp dụng thời hạn xóa chung cho mọi loại yêu cầu.  
* Trước khi trả về kết quả của một yêu cầu đã xử lý, hệ thống vẫn phải kiểm tra quyền truy cập và trạng thái nghiệp vụ.

Đối với command chưa có replay policy riêng, D08 được chốt ngày 21/10/2026: Idempotency-Key UUID; unique theo actor và key; lưu method, path, hash payload canonical, status/body response và thời điểm hết hạn trong PostgreSQL. Canonical payload được xác định sau validation theo quy tắc field của endpoint; JSON dùng RFC 8785, chuỗi không chuẩn hóa Unicode, newline chỉ chuẩn hóa nếu endpoint quy định, file IDs được sắp tăng dần khi thứ tự không có ý nghĩa. `expected_version` là một phần payload. Cùng actor/key/method/path/hash trả response đã lưu sau khi kiểm quyền hiện tại; cùng key nhưng request khác trả 409. Transaction đồng thời chỉ cho một command tạo tác động. Giữ receipt 30 ngày; sau hết hạn xóa receipt, còn state/version/unique constraints vẫn kiểm soát lệnh mới. Submission D13, Messaging BR-21 và Fee BR-22 tiếp tục theo policy riêng.

**Điều kiện nghiệm thu**

Các trường hợp sau không được tạo thêm bản ghi, version hoặc tác động nghiệp vụ ngoài ý muốn:

* Người dùng bấm nút hai lần.  
* Server đã lưu dữ liệu nhưng phản hồi bị mất.  
* Người dùng kết nối lại và gửi lại yêu cầu.  
* Hai yêu cầu có cùng Idempotency Key được gửi đồng thời.

**Lưu ý:** D08 đã được chốt; các policy riêng của Messaging BR-21, Fee BR-22 và Submission D13 vẫn được ưu tiên cho đúng nhóm command đó.

## RR-05 \- Outbox và worker xử lý tác vụ nền

**Yêu cầu**

Outbox giúp hệ thống không làm mất sự kiện khi giao dịch nghiệp vụ đã thành công nhưng tác vụ nền chưa được xử lý.

Ví dụ: Khi Customer chấp nhận Invitation, hệ thống lưu thay đổi nghiệp vụ và Outbox Event trong cùng transaction. Worker sẽ đọc sự kiện đã được commit để tạo notification sau đó.

Các quy tắc cần đảm bảo:

* Outbox Event được lưu cùng transaction với thay đổi nghiệp vụ tương ứng.  
* Worker chỉ xử lý sự kiện đã được commit.  
* Worker sử dụng FOR UPDATE SKIP LOCKED để các worker có thể xử lý công việc mà không lấy cùng một bản ghi đang bị worker khác khóa.  
* Việc tạo notification và đánh dấu processed\_at phải nằm trong cùng transaction.  
* Ràng buộc UNIQUE(user\_id, type, event\_key) giúp ngăn tạo notification trùng.  
* Khi worker bị dừng hoặc khởi động lại, các sự kiện chưa xử lý vẫn phải được giữ lại.

**Chính sách thử lại đã chốt ngày 21/10/2026**

* Khoảng thời gian giữa các lần thử tăng dần và có thêm độ ngẫu nhiên (*backoff with jitter*) để tránh nhiều worker thử lại cùng lúc.  
* Khoảng cách tối đa giữa các lần thử là 5 phút.  
* Dừng tự động thử lại khi đạt 10 lần thử hoặc đã qua 60 phút kể từ lần thử đầu tiên, tùy điều kiện nào đến trước.  
* Ghi lại failed\_at và mã lỗi an toàn để hỗ trợ điều tra.  
* Giữ bản ghi thất bại để người vận hành có thể kiểm tra và xử lý lại khi cần.

Sau ngưỡng, giữ hàng failed để operator kiểm tra. Replay operator dùng event_key cũ, yêu cầu quyền phù hợp, reason và audit; UNIQUE(user_id,type,event_key) ngăn tạo notification trùng. Cảnh báo khi có hàng failed hoặc hàng pending lâu hơn 5 phút. Retention đã chốt: xóa outbox processed sau 30 ngày; giữ failed cho tới khi xử lý rồi thêm 90 ngày; giữ notification 180 ngày; giữ Audit 24 tháng. Đây là chính sách thiết kế đã được Product/nhóm duyệt ngày 21/10/2026, chưa khẳng định runtime đã triển khai.

## RR-06 \- Sao lưu và khôi phục dữ liệu

**Mục tiêu khôi phục được đề xuất**

* **RPO ≤ 24 giờ:** Mục tiêu giới hạn lượng dữ liệu có thể bị mất trong một sự cố ở mức tối đa 24 giờ.  
* **RTO ≤ 4 giờ:** Mục tiêu đưa các chức năng chính trở lại hoạt động trong vòng 4 giờ kể từ khi sự cố được công bố.

Hai giá trị trên là mục tiêu đề xuất, cần được xác nhận và kiểm thử trước khi cam kết chính thức.

**Yêu cầu sao lưu**

* Sao lưu cơ sở dữ liệu hằng ngày.  
* Sao lưu các tệp riêng tư theo cách có thể xác định chính xác những tệp thuộc về bản sao lưu tương ứng, thông qua snapshot hoặc manifest nhất quán.  
* Giữ 7 bản sao lưu hằng ngày và 4 bản sao lưu hằng tuần.  
* Mã hóa bản sao lưu.  
* Có ít nhất một bản sao được lưu ngoài máy đang chạy ứng dụng.  
* Không chỉ sao lưu source code hoặc container image, vì chúng không chứa đầy đủ dữ liệu nghiệp vụ và tệp người dùng.

**Điều kiện nghiệm thu**

* Thực hiện khôi phục vào môi trường sạch trước G4 và ít nhất mỗi tháng một lần khi hệ thống đi vào vận hành.  
* Sau khi khôi phục, kiểm tra tính nhất quán của Program Member, snapshot, Submission, Fee Record, Audit Log, Outbox và checksum/manifest của tệp.  
* Ghi lại thời gian khôi phục thực tế và lượng dữ liệu bị mất nếu có.  
* Không tuyên bố RPO bằng 0 hoặc hệ thống có tính sẵn sàng cao chỉ dựa trên việc sao lưu mỗi ngày.

## RR-07 \- xử lý lỗi DB và storage

**Yêu cầu**

PostgreSQL và nơi lưu trữ tệp không dùng chung một transaction. Vì vậy, có thể xảy ra trường hợp một bên đã cập nhật nhưng bên còn lại thất bại.

Ví dụ:

* Tệp đã được tải lên storage nhưng lưu metadata vào database thất bại. Tệp đó trở thành tệp chưa được liên kết với dữ liệu nghiệp vụ.  
* Database đã lưu thông tin tệp nhưng object thực tế không tồn tại hoặc không thể truy cập.

Hệ thống cần đảm bảo:

* Chỉ coi tệp là hợp lệ và có thể gắn với tài nguyên khi object thực sự tồn tại và metadata khớp.  
* Không xóa object vẫn đang được bản ghi nghiệp vụ tham chiếu.  
* Khi DB hoặc storage gặp lỗi, hệ thống phải phát hiện và xử lý trạng thái không nhất quán thay vì giả định rằng cả hai bên đều thành công.

**Cơ chế dọn tệp được đề xuất**

* Tìm các tệp tải lên chưa được liên kết và đã quá 24 giờ.  
* Chạy tác vụ dọn dẹp có chế độ kiểm tra trước khi xóa (*dry-run*) và ghi log.  
* Kiểm tra lại mọi tham chiếu đến tệp ngay trong quá trình xử lý để tránh xóa nhầm tệp vừa được sử dụng.  
* Nếu xóa thất bại, có thể thử lại an toàn.

**Điều kiện nghiệm thu**

* Mô phỏng trường hợp storage mất kết nối, hết dung lượng và transaction database bị rollback.  
* Không để tài nguyên nghiệp vụ sử dụng tệp không tồn tại.  
* Không xóa tệp lịch sử vẫn cần được lưu giữ theo chính sách retention.  
* Khi dữ liệu không nhất quán, phải xử lý bằng cơ chế bù trừ hoặc đối soát (*compensation/reconciliation*) và ghi nhận lại. Không được tự xóa bản ghi lịch sử chỉ để làm cho dữ liệu trông sạch hơn.

## RR-08 \- Khôi phục dịch vụ sau sự cố

**Quy trình khôi phục được đề xuất**

Khi xảy ra sự cố nghiêm trọng, hệ thống cần có quy trình rõ ràng:

1. Tạm ngừng các thao tác ghi dữ liệu để tránh làm tình trạng không nhất quán nghiêm trọng hơn.  
2. Xác định bản sao lưu và thời điểm dữ liệu cần khôi phục.  
3. Khôi phục database và các tệp liên quan.  
4. Kiểm tra tính toàn vẹn và đối chiếu dữ liệu.  
5. Cho phép hệ thống đọc và ghi dữ liệu trở lại khi đã xác nhận an toàn.

Trước khi bật lại worker, cần kiểm tra các Outbox Event chưa xử lý và các Idempotency Key. Việc này giúp tránh gửi lại thông báo không cần thiết hoặc thực hiện lại những lệnh đã hết hạn sau khi khôi phục.

Sau restore, chưa mở quyền ghi nghiệp vụ, quyền Admin nhạy cảm hoặc worker cho đến khi người vận hành xác nhận trạng thái và quyền truy cập. Snapshot cũ có thể khôi phục cả session, assignment và trạng thái trước một sự kiện thu hồi/kết thúc; đọc lại chính snapshot không chứng minh quyền hiện tại. Runbook phải xác định nguồn đối soát và bước thu hồi/xác nhận lại quyền. Nếu không đủ bằng chứng, giữ các thao tác liên quan bị chặn; không tuyên bố backup có RPO 24 giờ tự bảo toàn mọi sự kiện thu hồi sau snapshot. Cách vận hành cụ thể vẫn cần được duyệt trước production, không bổ sung dịch vụ/journal mới trong đợt này.

**Điều kiện nghiệm thu**

Phải có tài liệu hướng dẫn xử lý sự cố (*runbook*), người phụ trách và biên bản diễn tập các trường hợp:

* Worker dừng giữa quá trình xử lý.  
* Giao dịch đã commit nhưng phản hồi gửi về người dùng bị mất.  
* Bản sao lưu thiếu object tệp cần thiết.  
* Phải quay lại phiên bản triển khai trước đó (*rollback deploy*).

Sau khi khôi phục:

* Không tự động mở lại Program hoặc Listing đã ở trạng thái kết thúc.  
* Không tự đánh dấu Fee Record là đã thu tiền.  
* Không khôi phục quyền truy cập đã bị thu hồi mà không kiểm tra lại.

# 3\. Nghiệm thu

### **G2 — Kiểm tra giao dịch đồng thời**

* Bắt buộc kiểm thử các tình huống nhiều yêu cầu xảy ra cùng lúc trên PostgreSQL thật.  
* Đảm bảo không tạo dữ liệu trùng hoặc dữ liệu chỉ được cập nhật một phần.

### **G3 — Kiểm tra tính nhất quán dữ liệu**

* Kiểm tra Audit Log và Outbox.  
* Kiểm tra version của Submission và Session.  
* Kiểm tra liên kết giữa database và tệp lưu trữ.  
* Kiểm tra việc thử lại yêu cầu và xử lý lỗi.

### **G4 — Kiểm tra khôi phục và xử lý sự cố**

* Khôi phục dữ liệu vào môi trường sạch.  
* Có bằng chứng kiểm thử đạt mục tiêu RPO/RTO đã được phê duyệt.  
* Thực hiện diễn tập các tình huống lỗi quan trọng và ghi lại kết quả.

**Lưu ý kỹ thuật:** Unit test hoặc test bằng SQLite không đủ để chứng minh các hành vi phụ thuộc vào khóa đồng thời, unique partial index và SKIP LOCKED của PostgreSQL. Những trường hợp này cần được kiểm thử trên PostgreSQL.

# 4. Implementation gaps và chính sách reliability độc lập

* Mục tiêu hoạt động 99% mỗi tháng được lấy từ tài liệu Product.  
* Cách đo availability, RPO/RTO, lịch sao lưu, thời gian dọn tệp chưa liên kết và giới hạn thử lại là các yêu cầu NFR được đề xuất bổ sung.  
* Retention Report/Audit evidence đã chốt tại BR-24/BR-25; retention của resource nguồn và tệp liên kết tiếp tục theo policy resource tương ứng. Không áp dụng cơ chế dọn tệp chưa liên kết cho tệp đã gắn với dữ liệu nghiệp vụ hoặc cần lưu giữ.
* D08, D19 và D20 đã có policy đích trong RR-04, RR-05 và M09-F05; việc triển khai và đo kiểm vẫn là implementation gap.
* Các Bổ sung NFR được chốt ngày 21/10/2026 là mục tiêu nghiệm thu, không phải bằng chứng hệ thống đã đạt. Chỉ báo cáo đạt khi có số liệu/evidence trên môi trường và tải đã nêu.
* .
