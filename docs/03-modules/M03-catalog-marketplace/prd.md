# PRD Catalog and Marketplace

FR-03/04, BR-02/05/14. W1 closeout đã có owner list/detail/create/update/archive; public search/detail/Tutor/profile/reviews GET và availability tham khảo. Functional Verified local; Accepted vẫn chờ reviewers/CI theo báo cáo closeout.

Wire W1 giữ draft/active/inactive/cancelled; inactive=paused, cancelled=archived mềm terminal, không Hidden/Admin hide. Chỉ Tutor verified/approved/account active/profile không suspended được quản lý Listing. Public chỉ trả active Listing + approved unsuspended Profile + active User + active catalog. Public DTO có tutor_slug; archive giữ Program lịch sử. Owner counts/actions do server cung cấp; không FE suy quyền từ status.

Query mục tiêu: q/category/subcategory/specialization/delivery_mode/city/min_price/max_price/currency/price_unit/sort/page/per_page; per_page giới hạn theo contract. Count là toàn bộ kết quả sau filter; không FE lọc một page rồi báo total giả. VND exponent 0; không so sánh giá khác price_unit.

Seed idempotent ít nhất ba category mục tiêu Education/Fitness/Sports, tái dùng Languages/English hiện có qua mapping được duyệt, không sinh hai nhánh Languages trùng. Public DTO không lộ verification/object key/secret; gallery/rating chưa có dữ liệu không bịa sample trong API mode.

AC-G1-03/04: owner CRUD/archive không truy cập chéo, unapproved không quản lý Listing; suspension/catalog inactive biến mất khỏi search/detail; money 200000 round-trip đúng; server filter/sort/page + reload/deep link; availability start<end/no overlap; public private-field exclusion. Public reviews Completed-only hiện rỗng tới W3, không fake rating. FE đủ loading/empty/errors, bàn giao contract/types/test cùng slice. [Scope và evidence](../../06-acceptance/issue-16-closeout.md).
