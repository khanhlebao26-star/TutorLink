# PRD Catalog and Marketplace

FR-03/04, BR-02/05/14. Schema có Listing; routes hiện chỉ catalog read. Thực hiện owner list/detail/create/update/archive; public search/detail/Tutor summary/profile; availability chỉ tham khảo.

Trước code chốt Listing enum/action mapping, Draft eligibility, public Tutor slug/avatar policy, price_unit và filter whitelist trong OpenAPI. Public chỉ trả Active Listing + Approved unsuspended Profile + active User + visible catalog. Archive giữ Program lịch sử; Hidden là Admin action, không dùng owner PATCH để bypass.

Query mục tiêu: q/category/subcategory/specialization/delivery_mode/city/min_fee/max_fee/currency/price_unit/sort/page/per_page; per_page giới hạn theo contract. Count là toàn bộ kết quả sau filter; không FE lọc một page rồi báo total giả. VND exponent 0; không so sánh giá khác price_unit.

Seed idempotent ít nhất ba category mục tiêu Education/Fitness/Sports, tái dùng Languages/English hiện có qua mapping được duyệt, không sinh hai nhánh Languages trùng. Public DTO không lộ verification/object key/secret; gallery/rating chưa có dữ liệu không bịa sample trong API mode.

AC-G1-03/04: owner CRUD/archive không truy cập chéo, unapproved không publish; suspend/hide/catalog inactive biến mất khỏi search/detail; money 200000 round-trip đúng; server filter/sort/page + reload/deep link; availability start<end/no overlap; public private-field exclusion. FE đủ loading/empty/errors, bàn giao contract/types/test cùng slice.
