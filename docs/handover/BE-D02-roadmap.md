# BE-D02 — Lộ trình tự thực hiện Issue #69

## Mục đích và cách dùng

Tài liệu này chia việc làm lại BE-D02 thành hai giai đoạn: **A — đọc và duyệt quyết định trước khi viết code**; **B — thực hiện và kiểm chứng sau khi quyết định đã được duyệt**. Đây là lộ trình, không phải xác nhận rằng bất kỳ bước nào đã được thực hiện.

Các nhãn nguồn:

- **[Issue]**: yêu cầu ghi trực tiếp trong nội dung gốc Issue #69.
- **[PRD/BR/Catalog]**: chi tiết được lấy từ PRD M05, Business Rules, workflow, thiết kế database hoặc API Route Catalog.
- **[Đề xuất]**: cách chia bước, đầu ra và cách tự kiểm tra do lộ trình này đề xuất; cần được người thực hiện duyệt trước khi áp dụng.

Issue gốc ghi phụ thuộc BE-D01 và yêu cầu API fields/state/lock phải được chốt trước. Issue nêu timebox 5 giờ cho N1–N2, gồm test/review; hãy coi đây là giới hạn kế hoạch, không phải lý do để bỏ qua quyết định hoặc kiểm thử cần thiết.

## Giai đoạn A — quyết định trước khi viết code

### A1. Chốt ranh giới công việc và điều kiện đầu vào

- **Mục tiêu:** Xác định đầu ra BE-D01 cần dùng, các quyết định API/trạng thái/khóa đã có, và phần nào của M05 thuộc BE-D02. Làm rõ ranh giới giữa phần backend BE-D02 triển khai và hợp đồng cung cấp cho Hoàng tích hợp Accept.
- **Nguồn:** **[Issue]** phụ thuộc BE-D01, API fields/state/lock; phạm vi M05; cung cấp action/contract cho Hoàng. **[PRD/BR/Catalog]** [PRD Index M05](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05%20%E2%80%94%20Program%20%26%20Invitation%20%E2%80%94%20PRD%20Index.md), [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md), [bàn giao BE-D01](BE-D01-issue-36-handover.md).
- **Kết quả cần có:** **[Đề xuất]** Danh sách ngắn gồm: BE-D02 triển khai; BE-D02 cung cấp contract cho phần tích hợp; phần ngoài phạm vi. Đặc biệt ghi rõ ai sở hữu endpoint gửi Invitation: issue yêu cầu snapshot/khóa offer nhưng không liệt kê endpoint gửi riêng trong tasks.
- **Tự kiểm tra:** Mỗi dòng trong task của issue có owner và đầu ra rõ ràng. Endpoint hoặc hành vi chưa rõ ownership được đánh dấu cần duyệt, chưa tự gán vào phạm vi.

### A2. Chốt tên trường, trạng thái và ánh xạ schema

- **Mục tiêu:** Thống nhất cách gọi và lưu Customer đích, owner, offer, version, Program status, Invitation status, snapshot và thời điểm gửi/hết hạn.
- **Nguồn:** **[Issue]** yêu cầu mapping `draft/active/paused/completed/cancelled` và schema/Resource đồng nhất. **[PRD/BR/Catalog]** [M05-F01 — Draft](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F01%20PRD%20%E2%80%94%20T%E1%BA%A1o%20v%C3%A0%20ch%E1%BB%89nh%20s%E1%BB%ADa%20Program%20Draft.md), [Enum Catalog](../Domain%20Documentation/Enum%20Catalog.md), [State Lifecycles](../Domain%20Documentation/State%20Lifecycles.md), [Database Design](../Architecture%20%26%20Technical%20Design/Database%20Design%20%E2%80%94%20TutorLink.md).
- **Kết quả cần có:** **[Đề xuất]** Bảng ánh xạ API → code/domain → database; ghi rõ giá trị trạng thái đích và mọi mapping legacy cần xử lý. Ví dụ, API `customer_id` cần được thống nhất với cách PRD mô tả Customer target và cột database.
- **Tự kiểm tra:** Mỗi field trong request/response dự kiến truy được tới một ý nghĩa và nguồn dữ liệu duy nhất. Không có hai literal trạng thái cùng được hiểu là trạng thái đích.

### A3. Duyệt ma trận quyền

- **Mục tiêu:** Chốt Tutor owner, Tutor khác, Customer đích trước Accept, Customer member và outsider được đọc/sửa gì.
- **Nguồn:** **[Issue]** yêu cầu chỉ owner sửa Program; Customer chưa là member không đọc Draft; tách đọc Invitation khỏi đọc Program Draft. **[PRD/BR/Catalog]** [M05-F01 — Draft](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F01%20PRD%20%E2%80%94%20T%E1%BA%A1o%20v%C3%A0%20ch%E1%BB%89nh%20s%E1%BB%ADa%20Program%20Draft.md), [M05-F02 — Invitation](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F02%20PRD%20%E2%80%94%20G%E1%BB%ADi%20Invitation%20v%C3%A0%20kh%C3%B3a%20snapshot.md), [WF04](../Workflow/WF04%20Program%20v%C3%A0%20Invitation.md), [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md).
- **Kết quả cần có:** **[Đề xuất]** Ma trận actor × thao tác × trạng thái cho create, read detail, list, update Draft và read Invitation. Ghi rõ Customer xem offer qua Invitation snapshot như thế nào.
- **Tự kiểm tra:** Ma trận trả lời được Customer đích có thể đọc Invitation nhưng không đọc/list Draft bằng cách nào; Customer khác và outsider nhận quyền gì. Không cần suy đoán khi viết Policy.

### A4. Duyệt version, snapshot, khóa Draft và lỗi

- **Mục tiêu:** Chốt khi nào version tăng, snapshot được tạo, Draft bị khóa và request stale bị từ chối.
- **Nguồn:** **[Issue]** yêu cầu snapshot/version, khóa điều khoản và lỗi stale theo contract. **[PRD/BR/Catalog]** [M05-F01 — Draft](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F01%20PRD%20%E2%80%94%20T%E1%BA%A1o%20v%C3%A0%20ch%E1%BB%89nh%20s%E1%BB%ADa%20Program%20Draft.md), [M05-F02 — Invitation](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F02%20PRD%20%E2%80%94%20G%E1%BB%ADi%20Invitation%20v%C3%A0%20kh%C3%B3a%20snapshot.md), BR-18 và BR-26 trong [Business Rules](../Domain%20Documentation/Business%20Rules.md), [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md).
- **Kết quả cần có:** **[Đề xuất]** Bảng tình huống đã duyệt: edit Draft thành công tăng version; sai `expected_version` trả 409 và không ghi; send chụp snapshot/version; Invitation pending khóa offer; Invitation terminal cho phép sửa Draft theo version hiện tại. Chốt response lỗi theo quy ước API.
- **Tự kiểm tra:** Có thể mô tả một chuỗi từ version 1 qua chỉnh sửa và gửi Invitation, rồi giải thích request cũ sẽ nhận gì và vì sao không thể ghi đè.

### A5. Duyệt lifecycle và contract Accept

- **Mục tiêu:** Chốt các chuyển trạng thái Program, điều kiện hoàn thành/hủy và giao diện nghiệp vụ mà phần Accept sẽ gọi.
- **Nguồn:** **[Issue]** yêu cầu quản lý `draft/active/paused/completed/cancelled` và cung cấp action/contract cho Hoàng. **[PRD/BR/Catalog]** [M05-F03 — Accept](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F03%20PRD%20%E2%80%94%20Accept%20Invitation%20v%C3%A0%20k%C3%ADch%20ho%E1%BA%A1t%20Program.md), [M05-F06 — Lifecycle](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F06%20PRD%20%E2%80%94%20Qu%E1%BA%A3n%20l%C3%BD%20lifecycle%20Program.md), [State Lifecycles](../Domain%20Documentation/State%20Lifecycles.md), BR-18 trong [Business Rules](../Domain%20Documentation/Business%20Rules.md).
- **Kết quả cần có:** **[Đề xuất]** Transition matrix; điều kiện blocker cho complete/cancel; contract Accept gồm đầu vào, quyền/eligibility/state/version/expiry checks, kết quả thành công và lỗi, cùng các thay đổi phải nguyên tử. Đánh dấu các quyết định locking/isolation cần kiểm chứng bằng test PostgreSQL.
- **Tự kiểm tra:** Hoàng có thể tích hợp theo contract mà không cần đoán ai kiểm expiry, ai tạo membership, hoặc thao tác nào chuyển Program thành Active. Quy tắc complete/cancel nêu rõ blocker Session/Task và không tự hoàn tất/hủy lịch sử.

**Cổng duyệt trước code — [Đề xuất]:** Chỉ chuyển sang Giai đoạn B sau khi A1–A5 được duyệt. Quyết định chưa chốt phải giữ trạng thái “chưa quyết định”; không tự biến đề xuất thành hành vi sản phẩm.

## Giai đoạn B — thực hiện sau khi duyệt quyết định

### B1. Triển khai create/read/list/update Program

- **Mục tiêu:** Xây Action, Policy, Request, Resource và route Program theo contract đã duyệt.
- **Nguồn:** **[Issue]** task về create/read/update và danh sách của owner. **[PRD/BR/Catalog]** [M05-F01 — Draft](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F01%20PRD%20%E2%80%94%20T%E1%BA%A1o%20v%C3%A0%20ch%E1%BB%89nh%20s%E1%BB%ADa%20Program%20Draft.md), [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md).
- **Kết quả cần có:** Các thao tác Program thuộc phạm vi đã duyệt hoạt động; response trả đúng status/version và field mapping.
- **Tự kiểm tra:** Đối chiếu với ma trận A3 và field map A2; thử owner, Tutor khác, Customer và outsider trên từng thao tác.
- **Kết quả hiện tại (27/10/2026):** Đã đăng ký đủ bốn route create/list/detail/update. `ProgramApiTest`, `InvitationAcceptTest` và `NotificationOutboxWriterTest` cùng pass 33 test/169 assertion; kiểm thử PostgreSQL concurrency vẫn thuộc B3 và chưa được thực hiện.

### B2. Triển khai snapshot, khóa offer, lifecycle và Accept action

- **Mục tiêu:** Thực hiện các hành vi đã duyệt tại A4–A5, bao gồm đường đọc Invitation riêng và contract tích hợp Accept.
- **Nguồn:** **[Issue]** task về lifecycle/snapshot/lock và action cho Hoàng. **[PRD/BR/Catalog]** [M05-F02 — Invitation](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F02%20PRD%20%E2%80%94%20G%E1%BB%ADi%20Invitation%20v%C3%A0%20kh%C3%B3a%20snapshot.md), [M05-F03 — Accept](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F03%20PRD%20%E2%80%94%20Accept%20Invitation%20v%C3%A0%20k%C3%ADch%20ho%E1%BA%A1t%20Program.md), [M05-F06 — Lifecycle](../Modules%20Documentation/M05%20%E2%80%94%20Program%20%26%20Invitation/M05-F06%20PRD%20%E2%80%94%20Qu%E1%BA%A3n%20l%C3%BD%20lifecycle%20Program.md).
- **Kết quả cần có:** Snapshot bất biến; Draft khóa đúng lúc; transition hợp lệ; action Accept và contract tích hợp đúng phạm vi A1. Endpoint nào được triển khai phải nằm trong phạm vi đã duyệt.
- **Tự kiểm tra:** Thay đổi nguồn sau khi gửi không sửa snapshot; Draft đang pending bị từ chối sửa; transition sai bị từ chối; Customer có thể đọc Invitation nhưng không xem Draft.

### B3. Kiểm thử và chứng minh tiêu chí

- **Mục tiêu:** Cung cấp bằng chứng cho từng acceptance criterion của issue.
- **Nguồn:** **[Issue]** yêu cầu test trái quyền, stale version, pending lock và payload validation. **[PRD/BR/Catalog]** BR-18, BR-25, BR-26 trong [Business Rules](../Domain%20Documentation/Business%20Rules.md); [Database Design](../Architecture%20%26%20Technical%20Design/Database%20Design%20%E2%80%94%20TutorLink.md).
- **Kết quả cần có:** Test/evidence cho trái quyền; stale update; pending Invitation khóa offer; payload sai; đọc Invitation trước membership nhưng không đọc Draft; transition hợp lệ/không hợp lệ; audit/outbox nhất quán. Kiểm thử race PostgreSQL theo các quy tắc M05 liên quan trước khi phát hành.
- **Tự kiểm tra:** Mỗi tiêu chí issue liên kết với một hoặc nhiều test/evidence. Lỗi không để lại ghi dở; rollback/409 không phát side effect giả; race test chứng minh chỉ có một kết quả hợp lệ.

### B4. Đối chiếu API/schema và bàn giao

- **Mục tiêu:** Đảm bảo tài liệu API phản ánh code và bàn giao đủ thông tin để review.
- **Nguồn:** **[Issue]** yêu cầu PR/commit, API/schema delta nếu có, test report/evidence, known limitations. **[PRD/BR/Catalog]** [API Route Catalog](../Architecture%20%26%20Technical%20Design/API%20Route%20Catalog.md), [hướng dẫn OpenAPI](../api/README.md), [Database Design](../Architecture%20%26%20Technical%20Design/Database%20Design%20%E2%80%94%20TutorLink.md).
- **Kết quả cần có:** PR/commit; API/schema delta nếu có; test report/evidence; known limitations; cập nhật tiến độ, bước tiếp theo và blocker trên issue. Nếu thay đổi route, sinh OpenAPI từ code theo hướng dẫn và đối chiếu method/path với Catalog.
- **Tự kiểm tra:** Reviewer truy được từ acceptance criterion đến test/evidence và code tương ứng; status Catalog không được coi là “Đã đăng ký” nếu method/path chưa xuất hiện trong OpenAPI sinh từ code.

## Giới hạn phạm vi

- **[Issue]** Lộ trình bám bốn nhóm việc và tiêu chí hoàn thành ghi trong Issue #69.
- **[PRD/BR/Catalog]** Quy tắc M05 làm rõ chi tiết như hạn Invitation, state transition, blocker khi hoàn tất/hủy và kiểm thử race. Đây là tiêu chuẩn nghiệp vụ/kỹ thuật để triển khai các hành vi M05 đã nằm trong issue.
- **[Đề xuất]** Các bảng quyết định, cổng duyệt, thứ tự bước và cách tự kiểm tra là phương tiện giúp người thực hiện duyệt từng quyết định trước code.
- Các endpoint công khai reject/cancel/expire, expiry worker và hành vi khác chỉ được thêm vào kế hoạch nếu xác định thuộc phạm vi gốc đã duyệt; tài liệu này không tự đưa chúng vào BE-D02.
