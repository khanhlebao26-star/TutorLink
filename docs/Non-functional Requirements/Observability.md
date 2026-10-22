# Observability

## **Yêu cầu về giám sát và theo dõi hoạt động hệ thống**

# 1\. Phạm vi và trạng thái

Tài liệu này quy định cách theo dõi hoạt động của TutorLink để phát hiện lỗi, tìm nguyên nhân sự cố và kiểm tra hệ thống có đáp ứng các yêu cầu NFR hay không.

Mục tiêu chính:

* Biết hệ thống có đang hoạt động bình thường hay không.  
* Tìm được request hoặc job liên quan khi xảy ra lỗi.  
* Theo dõi hiệu năng, tài nguyên và các tác vụ nền.  
* Gửi cảnh báo khi phát hiện dấu hiệu bất thường.  
* Lưu lại các hành động nghiệp vụ quan trọng để có thể kiểm tra khi cần.

Có hai loại yêu cầu:

* **Nguồn:** Yêu cầu đã có trong tài liệu dự án.  
* **Bổ sung NFR:** Tiêu chí được đề xuất thêm để việc giám sát và nghiệm thu rõ ràng hơn. Các tiêu chí này chưa có nghĩa là hệ thống đã triển khai hoặc đã đạt được.

Cần phân biệt ba loại dữ liệu:

* **Log kỹ thuật:** Ghi lại hoạt động của ứng dụng để tìm lỗi.  
* **Metrics:** Số liệu cho biết hiệu năng và tình trạng hệ thống.  
* **Audit nghiệp vụ:** Ghi lại những hành động quan trọng như chấp nhận Invitation, thay đổi assignment hoặc Admin suspend tài khoản.

Ba loại dữ liệu này có mục đích khác nhau và không thể thay thế cho nhau.

Hệ thống giám sát vận hành cũng không bắt buộc phải sử dụng trang Metrics trong Admin nếu chức năng đó vẫn đang chờ quyết định. Có thể dùng công cụ giám sát riêng được bảo vệ phù hợp.

Các khái niệm chính:

| Khái niệm | Giải thích dễ hiểu | Ví dụ trong TutorLink |
| ----- | ----- | ----- |
| Log | Nhật ký ghi lại sự kiện đã xảy ra | API trả về lỗi khi Customer chấp nhận Invitation |
| Metrics | Số liệu đo tình trạng hệ thống | Số request lỗi, thời gian phản hồi, số Outbox chưa xử lý |
| Monitoring | Theo dõi số liệu để phát hiện vấn đề | Phát hiện API phản hồi chậm hoặc worker ngừng hoạt động |
| Alert | Cảnh báo khi có dấu hiệu bất thường | Gửi cảnh báo cho người vận hành khi tỷ lệ lỗi tăng |
| Request ID | Mã dùng để tìm lại một yêu cầu cụ thể | Tìm toàn bộ log liên quan đến lần nộp bài bị lỗi |
| Audit Log | Nhật ký ghi lại hành động nghiệp vụ quan trọng | Ghi nhận Admin đã suspend tài khoản nào và vì sao |
| Health check | Kiểm tra ứng dụng hoặc thành phần có hoạt động không | Kiểm tra API có kết nối được database hay không |
| Trace | Theo dõi một yêu cầu đi qua nhiều thành phần | Từ API → transaction → Outbox → worker → notification |

# 2\. Yêu cầu và cách kiểm chứng

## OB-01 \- Mã truy vết request và log dễ tìm kiếm

**Yêu cầu**

Mỗi request gửi đến server cần có một mã định danh duy nhất gọi là `request_id`. Server chịu trách nhiệm tạo mã hoặc kiểm tra và chuẩn hóa mã được gửi lên.

Server trả mã này trong header `X-Request-ID`. Nếu request dẫn đến transaction, job hoặc Outbox Event, cần có thông tin tham chiếu để tìm được các hoạt động liên quan.

Mỗi bản ghi log nên có các thông tin cần thiết:

* Thời điểm xảy ra sự kiện theo UTC.  
* Môi trường đang chạy, ví dụ local, staging hoặc production.  
* Phiên bản ứng dụng hoặc commit đang triển khai.  
* Nhóm API được gọi, không dùng URL có token hoặc dữ liệu nhạy cảm làm tên route.  
* HTTP status code.  
* Thời gian xử lý, tính bằng mili giây.  
* Mã lỗi nếu có.  
* ID người dùng hoặc tài nguyên khi thực sự cần thiết và được phép ghi nhận.

**Ví dụ**

Customer chấp nhận Invitation nhưng request bị lỗi. Người phát triển có thể tìm log theo `request_id` để biết API nào xử lý, lỗi gì và job nào liên quan.

**Điều kiện nghiệm thu**

* Tìm được các log API và job liên quan từ một `request_id`.  
* Request ID không hợp lệ được xử lý an toàn, không thể lợi dụng để chèn dòng log.  
* Quy ước header và các trường lỗi phải thống nhất với tài liệu API D03.

Header `X-Request-ID` và error request_id đã chốt tại BR-26; trace API→transaction→job/outbox theo cùng request_id là implementation gap, chưa xác nhận runtime hỗ trợ đầy đủ.

## OB-02 \- Bảo vệ dữ liệu nhạy cảm trong log

**Yêu cầu**

Log cần đủ thông tin để điều tra sự cố nhưng không được làm lộ dữ liệu riêng tư.

Không ghi password, cookie, token, nội dung chat riêng tư, giấy tờ xác minh hoặc storage key nhạy cảm vào log hay Audit.

Việc che dữ liệu nhạy cảm (*redaction*) phải áp dụng cho tất cả nơi có thể lưu thông tin đó, bao gồm:

* Log của ứng dụng.  
* Proxy access log.  
* Công cụ theo dõi lỗi.  
* Laravel Telescope.  
* Giá trị tham số truy vấn database (*query bindings*).

Thời hạn lưu trữ được đề xuất:

* Log phục vụ vận hành: 30 ngày.  
* Audit nghiệp vụ: 24 tháng theo BR-25.

Log vận hành 30 ngày là đề xuất riêng; thời hạn Audit 24 tháng đã chốt tại BR-25. Cả hai cần được cấu hình và chưa phải bằng chứng runtime đã áp dụng.

**Điều kiện nghiệm thu**

* Gửi các chuỗi dữ liệu bí mật giả lập trong body, header và query rồi kiểm tra tất cả nơi lưu log.  
* Đảm bảo những dữ liệu này không xuất hiện trong log.  
* Log viewer và Telescope không được công khai cho mọi người.  
* Chỉ operator có quyền mới được xem log vận hành.  
* Việc xem giấy tờ hoặc evidence vẫn phải kiểm tra quyền truy cập và ghi Audit theo quy định riêng.

## OB-03 \- Theo dõi các chỉ số quan trong

**Yêu cầu**

Hệ thống cần thu thập số liệu để biết API có chậm không, lỗi có tăng không, database còn đủ tài nguyên không và các tác vụ nền có đang hoạt động không.

Các nhóm số liệu cần theo dõi:

**1\. API và hiệu năng**

* Số lượng request trong một khoảng thời gian.  
* Thời gian phản hồi p50 và p95 theo nhóm API.  
* Số lượng và tỷ lệ lỗi 4xx, 5xx, timeout và 429\.  
* Số lần xác thực thất bại.

**2\. Tài nguyên hệ thống**

* Mức sử dụng CPU và RAM.  
* Tình trạng kết nối database.  
* Thời gian chờ khóa database.  
* Dung lượng storage còn lại.

**3\. Worker và Outbox**

* Số Outbox Event đang chờ xử lý.  
* Số sự kiện xử lý thất bại.  
* Số lần thử xử lý.  
* Thời gian chờ của sự kiện lâu nhất.  
* Thời gian từ lúc transaction được commit đến khi notification tương ứng được tạo trong hệ thống.

Trong đó:

* **p50:** 50% request có thời gian phản hồi bằng hoặc nhanh hơn giá trị này.  
* **p95:** 95% request có thời gian phản hồi bằng hoặc nhanh hơn giá trị này.  
* **Worker heartbeat:** Dấu hiệu cho biết worker vẫn hoạt động.  
* **Outbox pending:** Sự kiện đã được ghi nhận nhưng chưa xử lý xong.  
* **Cardinality cao:** Một chỉ số có quá nhiều giá trị nhãn khác nhau, khiến việc lưu trữ và truy vấn tốn tài nguyên.

Không sử dụng `user_id`, email, `message_id` hoặc `request_id` làm nhãn metrics vì chúng có thể tạo ra quá nhiều giá trị riêng biệt. Những thông tin dùng để điều tra một request cụ thể nên nằm trong log.

**Điều kiện nghiệm thu**

* Có dashboard hoặc báo cáo phục vụ việc kiểm tra hiệu năng và độ tin cậy.  
* Không chỉ hiển thị tổng số request mà còn theo dõi tỷ lệ lỗi, thời gian phản hồi, database và worker.  
* Phân biệt lỗi nghiệp vụ như `409` hoặc `422` với lỗi hệ thống như `5xx` và timeout.  
* Vẫn theo dõi khi các lỗi nghiệp vụ tăng đột biến, vì đây có thể là dấu hiệu bất thường.

## OB-04 \- Kiểm tra ứng dụng có sẵn sàng hoạt động không

**Yêu cầu**

Hệ thống cần có các cơ chế kiểm tra riêng để biết ứng dụng còn chạy, đã sẵn sàng nhận request hay một thành phần phụ thuộc đang gặp lỗi.

Ba khái niệm cần phân biệt:

* **Liveness:** Kiểm tra tiến trình ứng dụng còn chạy hay không.  
* **Readiness:** Kiểm tra ứng dụng có đủ điều kiện để nhận request hay không, bao gồm các kết nối bắt buộc như database và storage.  
* **Heartbeat:** Dấu hiệu hoạt động định kỳ của worker hoặc scheduler.

Ví dụ: API Laravel vẫn chạy nhưng PostgreSQL bị mất kết nối. Liveness có thể vẫn thành công, nhưng readiness cần phản ánh rằng ứng dụng chưa sẵn sàng xử lý những thao tác cần database.

Tương tự, endpoint `/up` trả về HTTP 200 không chứng minh worker đang chạy hoặc notification đang được xử lý bình thường.

Các endpoint health check không được trả về credential, stack trace hoặc thông tin hạ tầng nhạy cảm. Việc kiểm tra dependency cần có timeout để tránh chờ vô hạn.

**Điều kiện nghiệm thu**

* Khi ngắt kết nối database hoặc storage, hệ thống thể hiện đúng thành phần đang lỗi.  
* Khi dừng worker, heartbeat phải cho thấy worker không còn hoạt động.  
* Deployment không đưa instance chưa sẵn sàng vào phục vụ.  
* Probe kiểm tra availability chạy mỗi phút bằng tài khoản và dữ liệu tổng hợp, theo RR-01.

## OB-05 \- Cảnh báo khi hệ thống có dấu hiệu bất thường

**Yêu cầu**

Hệ thống cần tự động cảnh báo cho người vận hành khi các chỉ số vượt ngưỡng đã đặt.

Các ngưỡng được xác định như sau (trạng thái phê duyệt ghi ngay dưới bảng):

| Điều kiện | Ngưỡng cảnh báo |
| ----- | ----- |
| Probe kiểm tra hệ thống thất bại | 3 lần liên tiếp |
| API lỗi `5xx` hoặc timeout | Trên 1% trong 5 phút, với tối thiểu 100 request |
| API phản hồi chậm | p95 trên 2 giây trong 10 phút, với đủ số lượng mẫu |
| Database hoặc storage | Mức sử dụng đạt từ 80% |
| Worker | Không có heartbeat trong hơn 60 giây |
| Outbox | Có sự kiện failed hoặc pending quá 5 phút (BR-25) |
| Outbox Event | Có sự kiện mới chuyển sang trạng thái thất bại |

Các ngưỡng probe/API/worker trong bảng là baseline vận hành cần theo dõi và hiệu chỉnh bằng telemetry; ngưỡng Outbox 5 phút và storage 80% đã chốt tại BR-25/PC-08. Chỉ coi target đạt khi có bằng chứng runtime.

**Điều kiện nghiệm thu**

* Mỗi cảnh báo có người phụ trách, mức độ nghiêm trọng, thông tin request hoặc release liên quan và đường dẫn đến hướng dẫn xử lý sự cố (*runbook*).  
* Thử tạo tình huống lỗi để xác nhận cảnh báo thực sự đến được kênh vận hành đã cấu hình.  
* Gộp các cảnh báo thuộc cùng một sự cố để tránh gửi quá nhiều thông báo trùng lặp.  
* Cảnh báo nội bộ không tự động tạo notification cho Customer hoặc Tutor.

## OB-06 \- Ghi lại lịch sử hành động nghiệp vụ

**Yêu cầu**

Audit dùng để ghi lại các hành động quan trọng, giúp xác định ai đã làm gì, trên tài nguyên nào, vào thời điểm nào và vì sao.

Một bản ghi Audit cần có những thông tin phù hợp như:

* `actor`: Người thực hiện.  
* `action`: Hành động được thực hiện.  
* `resource`: Tài nguyên bị tác động.  
* `before/after`: Những giá trị trước và sau thay đổi được phép ghi nhận.  
* `reason`: Lý do thực hiện nếu nghiệp vụ yêu cầu.  
* `timestamp`: Thời điểm theo UTC.

Các quy tắc bắt buộc:

* Những thay đổi nghiệp vụ yêu cầu Audit phải ghi Audit trong cùng transaction với thay đổi đó.  
* Nếu ghi Audit thất bại, thay đổi nghiệp vụ phải rollback.  
* Khi thay đổi assignment, lưu thông tin người được phân công và version trước/sau.  
* Khi xem giấy tờ hoặc evidence, cần có ghi nhận truy cập theo quy định.  
* Quyền `admin.audit_logs.view` chỉ cho phép xem trong phạm vi được cấp, không cho phép sửa hoặc xóa Audit.  
* API Audit không được trả toàn bộ JSONB chứa dữ liệu riêng tư.

**Điều kiện nghiệm thu**

* Operator không thể sửa Audit thông qua API thông thường.  
* Quyền ghi Audit của ứng dụng phải được tách biệt với quyền xóa hoặc thực hiện công việc lưu trữ, hết hạn dữ liệu (*retention*) của tác vụ quản trị được kiểm soát.  
* Nếu Audit bắt buộc không ghi được, quyết định nghiệp vụ phải rollback.

## OB-07 \- Theo dõi yêu cầu gửi lại và các tình huống tranh chấp

**Yêu cầu**

Hệ thống cần phân biệt rõ một thao tác mới với một request được gửi lại, một request bị xung đột hoặc một job cần xử lý lại.

Ví dụ: Customer chấp nhận Invitation. Server đã lưu thành công nhưng phản hồi bị mất. Khi Customer gửi lại request, hệ thống phải nhận biết đây là lần gửi lại của cùng thao tác, không coi đó là một nghiệp vụ chấp nhận mới.

Cần theo dõi:

* Request lần đầu, request gửi lại (*replay*) và request bị xung đột.  
* Job xử lý thất bại và số lần thử lại.  
* Các lần đối soát dữ liệu (*reconciliation*).  
* Các sự kiện được operator yêu cầu xử lý lại.

Khi operator replay một sự kiện, cần ghi nhận người thực hiện, lý do và mã tham chiếu của sự kiện. Phải giữ nguyên event key gốc để tránh tạo notification trùng.

Không ghi nội dung Idempotency Key nhạy cảm hoặc nội dung riêng tư vào log. Chỉ lưu mã trạng thái và thông tin tham chiếu an toàn cần thiết.

**Điều kiện nghiệm thu**

Có thể truy vết các tình huống:

* Server commit thành công nhưng client bị timeout.  
* Hai request cùng chấp nhận một Invitation.  
* Submission được gửi bằng version cũ.  
* Assignment thay đổi trong khi một request đang xử lý.

Số liệu phải phân biệt replay với nghiệp vụ mới. Không bật debug quá rộng hoặc ghi nội dung riêng tư chỉ để tìm lỗi.

## OB-08 \- Công cụ giám sát và quy trình xử lý sự cố

**Yêu cầu**

TutorLink có thể dùng các công cụ giám sát để xem log, metrics và lỗi, nhưng mỗi công cụ phải được dùng đúng mục đích.

Laravel Telescope có thể hỗ trợ quan sát request, job và exception. Tuy nhiên, Telescope không thay thế PostgreSQL Outbox hoặc Audit nghiệp vụ.

Định hướng được đề xuất:

* Sử dụng log có cấu trúc và metrics làm nền tảng giám sát.  
* Chỉ bật Telescope có kiểm soát ở local và staging.  
* Mặc định tắt Telescope ở production, trừ khi xác thực quyền truy cập, che dữ liệu và thời hạn lưu trữ đã được kiểm thử đầy đủ.

**Điều kiện nghiệm thu tại G4**

Thực hiện ít nhất một buổi diễn tập xử lý sự cố theo trình tự:

1. Nhận cảnh báo.  
2. Xác định và phân loại sự cố.  
3. Dùng log, metrics và mã tham chiếu để điều tra.  
4. Khắc phục hoặc khôi phục hệ thống.  
5. Kiểm tra các chức năng chính đã hoạt động lại.  
6. Ghi lại nguyên nhân và bài học kinh nghiệm.

Bằng chứng cần lưu gồm phiên bản ứng dụng, thời gian xử lý, người phụ trách và số liệu đo được.

Không cần mở trang Admin Metrics đang chờ phê duyệt chỉ để đáp ứng yêu cầu giám sát. Có thể dùng dashboard hoặc công cụ vận hành riêng có kiểm soát quyền truy cập.

# 3\. Nghiệm thu

Để chứng minh TutorLink đáp ứng yêu cầu Observability, cần chuẩn bị các bằng chứng sau:

1. **Mẫu log đã che dữ liệu nhạy cảm:** Chứng minh có thể tìm và điều tra request mà không làm lộ thông tin riêng tư.  
2. **Dashboard hoặc báo cáo metrics:** Chứng minh có theo dõi hiệu năng, lỗi, database, storage và worker.  
3. **Cấu hình cảnh báo:** Chứng minh các ngưỡng đã chọn có thể kích hoạt cảnh báo.  
4. **Probe kiểm tra hệ thống:** Chứng minh có thể phát hiện khi các chức năng hoặc dependency gặp sự cố.  
5. **Biên bản diễn tập:** Chứng minh quy trình phát hiện, điều tra, khôi phục và xác nhận hoạt động lại đã được thử nghiệm.  
6. **Audit nghiệp vụ:** Chứng minh các hành động quan trọng được ghi nhận đầy đủ và được bảo vệ khỏi việc sửa/xóa trái phép.

Log kỹ thuật, Audit nghiệp vụ và dữ liệu kiểm thử là ba loại bằng chứng riêng biệt. Có đầy đủ một loại không có nghĩa là đã đáp ứng hai loại còn lại.

# 4. Hạng mục vận hành còn lại

Các nội dung vận hành chưa chốt riêng, không thuộc năm nhóm đã đóng:

* Ngưỡng cảnh báo.  
* Cách truyền `request_id` qua API, transaction, job và Outbox.  
* Thời gian lưu log vận hành.  
* Cơ chế heartbeat cho worker và scheduler.

BR-25 chốt ngưỡng và retention outbox/audit; BR-26 chốt request ID/error envelope. Các ngưỡng cảnh báo còn lại phải lấy từ OB-05 và được đo/hiệu chỉnh theo môi trường. Không công khai endpoint Audit hoặc evidence ngoài phạm vi quyền truy cập chỉ để thuận tiện cho việc tìm lỗi.

Các target tại NFR được chấp nhận trong phạm vi đã nêu ngày 21/10/2026; mục tiêu đã duyệt chưa phải bằng chứng runtime đạt. Chỉ cập nhật trạng thái đạt khi có kiểm thử/evidence trên môi trường xác định.

.
