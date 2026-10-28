# M03-F05 · Lưu trữ Tin dịch vụ

## PRD chức năng · M03 Catalog & Marketplace · Trạng thái: Draft · Có chênh lệch implementation cần xử lý

# 1\. Mục tiêu

Cho Tutor chủ sở hữu lưu trữ Listing không còn cung cấp, loại Listing khỏi trải nghiệm công khai và giữ nguyên lịch sử đã phát sinh.

# 2\. Quy tắc và vòng đời đích

* Transition hợp lệ: draft → archived, active → archived, inactive → archived.  
* Archived là trạng thái terminal: không sửa nội dung, không publish, không reactivate và không restore.  
* Lưu trữ Listing không hủy Program, Invitation hoặc snapshot; dữ liệu lịch sử vẫn tham chiếu được.  
* Chỉ Tutor sở hữu Listing mới có quyền archive; thao tác phải xác nhận rõ trong UI vì không thể hoàn tác.

# 3\. Hành vi và contract đích

UI hiển thị Lưu trữ như một hành động riêng với Tạm dừng. Sau xác nhận, máy chủ chuyển Listing sang archived; public search, public detail và hồ sơ Tutor công khai không trả Listing đó. API cần dùng hành động/biểu diễn nhất quán với ListingStatus=archived.

# 4\. Tiêu chí chấp nhận

* Chủ sở hữu archive Listing ở draft/active/inactive; không thể archive Listing đã archived lần nữa nếu transition không cho phép.  
* Sau khi archive, Listing biến mất khỏi mọi bề mặt công khai và không truy cập được bằng slug công khai.  
* Program/snapshot cũ vẫn đọc được theo quyền của thành viên và giữ nguyên điều khoản cũ.  
* Archived Listing không thể sửa, kích hoạt lại hoặc khôi phục.

# 5\. Chênh lệch so với code hiện tại

State Lifecycles và Domain Model quy định target là archived. Tuy nhiên DELETE /api/v1/me/listings/{listing} hiện ánh xạ sang status legacy cancelled. cancelled không phải ListingStatus đích. Cần quyết định contract migration/API để biểu diễn archived và bảo toàn các bản ghi cũ; không được coi cancelled là enum đích.

# 6\. Câu hỏi mở

* Chọn cách tương thích API cho thao tác archive khi client hiện dùng DELETE; xác định HTTP semantics, response status và cách di trú dữ liệu cancelled cũ.  
* Xác nhận lưu trữ có yêu cầu nhập lý do nội bộ hoặc ghi audit event bổ sung không; tài liệu hiện chưa quy định.

# 7\. Nguồn

Product: Roles & Actors, Business Rules. Domain: State Lifecycles, Domain Model, Enum Catalog. Link nguồn: [Roles & Actors](<../../Product Document/Roles & Actors.md>) · [Business Rules](<../../Domain Documentation/Business Rules.md>) · [State Lifecycles](<../../Domain Documentation/State Lifecycles.md>) · [Domain Model](<../../Domain Documentation/Domain Model.md>) · [Enum Catalog](<../../Domain Documentation/Enum Catalog.md>)
