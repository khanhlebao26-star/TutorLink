# M08-F01 · Quản lý Fee Record

## PRD chức năng · M08 Fee, Announcement & Notification · Draft

# 1\. Mục tiêu

Tutor ghi nhận khoản phí đã thỏa thuận trong Program theo tháng; sản phẩm không xử lý thanh toán.

# 2\. Quy tắc nghiệp vụ

* Tối đa một Fee Record mỗi Program/tháng `YYYY-MM`, xác định theo lịch `Program.timezone`; `period_start` là ngày đầu tháng và `period_end` là ngày cuối tháng. Chỉ tạo khi Program Active, từ tháng kích hoạt đến tháng hiện tại; cho khai báo bù, không tạo tháng tương lai. Amount là số nguyên VND không âm; 0 đồng nghĩa là miễn phí, còn không có record là chưa khai báo.
* Tutor owner nhập tay. Không tính từ Session, không gọi cổng thanh toán và không tuyên bố đã thanh toán.  
* UI/response luôn ghi rõ “Do Tutor khai báo, không phải bằng chứng thanh toán”. Customer member thấy kỳ, số tiền, status, payment mark, thời điểm cập nhật và lý do điều chỉnh. Copy của payment mark phải nói rõ “Tutor đánh dấu đã thu/chưa thu”, không nói Customer đã thanh toán thành công.
* FeeRecordStatus: declared → voided, không mở lại. TutorPaymentMark độc lập: unmarked → marked\_paid/marked\_unpaid; paid ↔ unpaid cần audit và reason khi đổi paid sang unpaid. Amount 0 giữ mark unmarked và không cho mark paid/unpaid; chỉ cho sửa amount về 0 khi mark hiện tại là unmarked, không reset mark đã ghi. Voided là trạng thái cuối: không sửa amount, mark hoặc void lần nữa.
* Void giữ nguyên payment mark, giữ kỳ tháng và record để lịch sử không bị ghi đè; void bắt buộc lý do và record voided vẫn chiếm kỳ. PATCH đổi amount cũng bắt buộc lý do.
* Program Paused cấm tạo Fee Record mới nhưng cho sửa/mark/void record có sẵn theo policy; terminal readonly. Chỉ sửa `amount_minor`/reason khi status `declared` và payment mark chưa `marked_paid`; không đổi kỳ tháng và không sửa record `voided`.
* Fee Record có integer `version` bắt đầu từ 1. PATCH/mark/void yêu cầu `expected_version`; mismatch trả 409 và không ghi một phần. Mỗi mutation có thay đổi thực tế tăng version một lần; đổi `marked_paid` sang `marked_unpaid` vẫn cần reason và audit. Mọi mutation nhận Idempotency-Key; sau khi kiểm lại authorization, cùng actor/key/method/path/hash trả receipt đã commit; cùng key nhưng request khác trả 409. Replay không tăng version/audit/notification. No-op đúng version (PATCH cùng amount (chỉ khi Fee chưa marked_paid) với reason hợp lệ, mark lại cùng trạng thái khi Program/resource còn writable) trả 200 hiện tại; key mới lưu receipt nhưng không đổi metadata/version/audit/outbox. Stale version/state guard vẫn trả 409. Mutation có thay đổi thực tế phát `fee_record_changed` in-app cho Customer member sau commit.

# 3\. Tiêu chí chấp nhận

* Owner tạo đúng một record cho kỳ tháng; cùng key/payload retry trả record cũ, key khác cùng kỳ trả 409.
* Amount âm, float, currency khác VND hoặc kỳ sai format bị từ chối.  
* Mark paid/unpaid và void tuân state machine; voided không mutation được và vẫn chiếm kỳ unique.
* Customer member xem đúng phạm vi, disclaimer và lý do được chia sẻ; outsider bị từ chối.
* Stale PATCH/mark/void trả 409, không ghi thay đổi hoặc tạo side effect.

# 4\. Khoảng trống triển khai

* Migration `2026_11_01_233000_add_be_d04_workspace_schema` và `2026_11_02_000000_enforce_d04_fee_currency` cùng API Fee đã chạy trên `tutorlink_test`. Migration dừng nếu gặp legacy `confirmed`/`cancelled` chưa được phân loại; không suy `confirmed` thành Customer đã thanh toán. Runtime môi trường thực tế chưa được xác nhận.
* Request/response, filter/order, receipt, no-op, schema checks, audit projection, lock protocol và retention đã được chốt trong [BE-D04 contract](../../handover/BE-D04-contract.md) mục 13–17. Fee receipt `expires_at=NULL` giữ tới khi Fee và lịch sử liên quan đủ điều kiện purge theo Database Design.

# 5\. Nguồn

Product: Project Overview, Roles & Actors, Business Rules. Domain: Domain Model, State Lifecycles, Enum Catalog. Workflow: WF-05 Workspace tới Completed. Link nguồn: [Project Overview](<../../Product Document/Project Overview.md>) · [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
Đồng bộ 02/11/2026 — BE-D04 đã bổ sung API/schema và chạy regression trên test DB; trạng thái môi trường thực tế theo D04-21 trở đi. Pagination/filter/order theo BR-26 và request/response/no-op/receipt trong BE-D04 contract mục 13–17.
