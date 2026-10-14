# Draft PR — feat(frontend): prepare W1-B3 Marketplace before A3

Head: `codex/issue-15-marketplace`  
Base: `feature/W1-B1-B2-figma-layout` (baseline #22, chỉ review diff B3)  
Refs #15; depends on #14. Không dùng `Closes #15`.

Marketplace cũ lọc một page ở client và dùng mock fallback. Thay bằng API adapter và URL-backed search, nested catalog, server totals/pagination, owner list/detail/Draft/edit/lifecycle controls theo server permissions, public Tutor/reviews và weekly availability. Bổ sung Home/become-a-tutor, service-only header search và footer có route thật. Owner rows thành cards trên mobile để các nút thao tác luôn hiển thị.

B3 được triển khai trước A3 theo yêu cầu. DTO/status/query/action/DELETE archive trong PR là **đề xuất**, chưa phải contract được Khanh chốt. Backend local chỉ có catalog (200); các Listing/Tutor/availability endpoint đang 404. Không sửa backend/OpenAPI, không giả lập application data và không nghiệm thu G1 thay backend.

Validation: lint, TypeScript, API-mode production build, query/availability isolated checkers, legacy Auth mock-role checker đạt. Browser39 checks/0 page errors; 32 screenshot gồm360/768/1536, có scope fixture và live API riêng. Không mutation backend thật. Tested code `50af661`; evidence commit `671f15a`.

Review độc lập đã xác nhận sửa draft-filter loss và offset timezone validation. Visual QA đã sửa owner mobile action visibility. T01–T04 vẫn Blocked cho acceptance đầy đủ; T05/T06 Pass trong phạm vi FE. Settings prerequisite cần owner xác nhận riêng; #22 không chứng nhận route Settings chưa tồn tại.

- [Endpoint + payload/DTO handoff cho Khanh](../contracts/w1-b3-api-handoff-khanh.md)
- [Contract proposal / điểm cần chốt](../contracts/w1-b3-marketplace-proposal.md)
- [T01–T06 + screenshot evidence](issue-15-marketplace.md)
- [9 reference → route → DTO → actor/state](issue-15-source-matrix.md)
- [Token/asset/shared component handoff](issue-15-assets.md)

Publication chưa thực hiện: Git HTTPS không có credential; GitHub connector trả403 `Resource not accessible by integration`; SSH strict host verification chưa có trusted host key. Code đã commit local, chưa merge hoặc đóng issue. Người có quyền repo có thể push nhánh và dùng nội dung này để mở **draft PR**. Không bỏ kiểm tra host key để vượt lỗi SSH.
