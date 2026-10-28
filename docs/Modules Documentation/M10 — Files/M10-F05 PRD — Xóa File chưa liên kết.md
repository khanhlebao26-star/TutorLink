# M10-F05 · Xóa File chưa liên kết

## PRD chức năng · M10 Files · Draft

# 1\. Mục tiêu

Cho owner xóa tệp chưa được gắn vào resource; bảo toàn mọi lịch sử và liên kết đã dùng.

# 2\. Quy tắc

* Chỉ owner được xóa File.  
* Chỉ cho xóa khi không có file\_links, verification document hoặc profile avatar reference; linked file không hard-delete.  
* File pending chưa liên kết được dọn sau 24 giờ; file complete chưa liên kết được dọn sau 7 ngày. File linked không thuộc orphan cleanup. Xóa object và row metadata cần retry/compensation: lỗi storage không để DB báo đã xóa object còn tồn tại hay giữ link tới object đã xóa.
* File được dùng trong lịch sử business record phải bảo toàn theo retention; unlink không đồng nghĩa xóa lịch sử nếu resource còn tham chiếu.

# 3\. Tiêu chí chấp nhận

* Owner xóa được File chưa liên kết; record và object storage được dọn.  
* Non-owner bị từ chối.  
* Linked File trả conflict và giữ nguyên cả metadata lẫn object.  
* Retry delete sau khi đã xóa xử lý nhất quán, không xóa file khác.

# 4\. Implementation hiện tại và câu hỏi mở

* DELETE /files/{id} hiện ngăn xóa nếu có file\_links, verification\_document hoặc avatar reference; linked file trả 409\.  
* BR-21 chốt orphan retention 24 giờ (pending) và 7 ngày (complete, chưa link); file linked theo retention của resource. Runtime cần triển khai job dọn và retry/compensation an toàn.

# 5\. Nguồn

Product: Roles & Actors, Business Rules. Domain: Domain Model, Enum Catalog, State Lifecycles. Architecture: Security Design. Workflow: WF-03, WF-05, WF-06. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>)
