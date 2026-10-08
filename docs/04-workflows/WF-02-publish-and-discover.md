# WF02 Publish và khám phá Listing

Actor: Tutor owner, Customer/public visitor, outsider Tutor, Admin nếu hide được triển khai. Tiền điều kiện: WF01, enum/money/public DTO A3 được review, seed ít nhất ba category.

1. Tutor tạo Draft Listing; cập nhật title/service/specialization/delivery/location/price_unit/VND.
2. Publish khi eligibility hợp lệ; public thấy Listing, public Tutor và availability tham khảo.
3. Customer filter/sort/page phía server; reload URL giữ filter theo contract; mở detail đúng slug.
4. Pause/archive hoặc suspend Profile/Account/hide/catalog inactive; public search và direct detail đều không lộ Listing đó.

Nhánh lỗi: owner chéo ID, chưa Approved publish, enum/action không cho phép, giá khác unit, invalid query, slug không tồn tại, private verification lộ qua DTO.

Evidence AC-G1-03/04: lưu request/response thực + kiểm count/filter và VND `200000` qua API → UI. Không chuyển sang mock khi endpoint /listings trả lỗi để quay demo.
