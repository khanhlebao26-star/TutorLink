# Issue #15 — FE readiness và evidence chờ A3

Ngày 13/10/2026. Branch `codex/issue-15-marketplace`; baseline `5871441`; code được kiểm tra tương ứng commit `50af661` (bao gồm `b109f48`). Base PR là `feature/W1-B1-B2-figma-layout` để giữ nền #22 và chỉ review diff B3. Người dùng yêu cầu làm B3 trước A3. **FE readiness không phải nghiệm thu backend/G1. Issue #15 giữ mở.**

## Dependency và phạm vi

GitHub live: C2/#11, #18 và #22 đã closed; A3/#14 open, không có branch A3 được tìm thấy lúc bắt đầu. Checkout có catalog API nhưng không có Listing/public Tutor/reviews/availability routes. GET local: catalog 200 (một category); các endpoint mới được probe trả 404. Đề xuất DTO/action/status/query được ghi riêng, không sửa OpenAPI/backend và không suy diễn contract đã được phê duyệt.

Auth/onboarding layout baseline nằm ở [#22 full-width redesign](issue-22-fullwidth-redesign.md). B3 bổ sung smoke screenshot login sau thay đổi header, không dùng nó để tái chứng nhận toàn bộ luồng Auth/approval. Settings chưa có route/reference trong checkout; phần prerequisite này cần owner xác nhận riêng trước đóng G1, không suy ra Done chỉ vì #22 closed.

## Kết quả W1-B3-T01–T06

| Test | Status đầy đủ | Actual result / giới hạn | Evidence |
| --- | --- | --- | --- |
| T01 public/owner lifecycle | **Blocked — A3** | FE gửi Draft payload không status; publish PATCH theo quyền server. Public/owner dùng API, không mock fallback. Fixture kiểm payload/permissions; publish/pause/archive visibility và Program history trên BE thật chưa kiểm được. | `browser-report.json`: owner publish/Draft; [API handoff](../contracts/w1-b3-api-handoff-khanh.md) |
| T02 quyền/deep link | **Blocked — A3** | Owner edit/detail deep link tải `/me/listings/{id}`; route key theo ID. Anonymous `/me` thật bị chặn trước mount; fixture Customer/Admin không mở owner, Hidden/Archived không có action. Cross-owner disclosure và giữ FK/snapshot Program cần BE. | Live guest screenshot; fixture role checks; owner form screenshots |
| T03 search state | **Blocked — A3** | URL/Back/Forward, reset category child/page, price unit, typed-filter retention, server count/empty và late-response suppression qua fixture đều Pass. Query/unit whitelist và search/pagination thực tế cần A3. | Query checker + browser report; Marketplace screenshots |
| T04 Figma MVP | **Blocked — nghiệm thu tích hợp** | FE layout của chín reference đã đối chiếu, CTA/header/footer/preview hoạt động trong phạm vi nguồn hiện có; owner table chuyển thẻ ở mobile để action luôn thấy. Các khối Future/thiếu nguồn được bỏ có chủ đích. Luồng save/public thật chưa chạy vì A3 404; reviewer giao diện cần sign-off. | 27 ảnh B3 (9 route × 3 viewport); [source matrix](issue-15-source-matrix.md) |
| T05 lỗi/responsive/a11y | **Pass — FE scope** | 422 field errors, 409 refetch giữ draft, 401/419 re-auth tab giữ input, network error, 429 retry; keyboard search/FAQ/add availability; owner action nằm trong viewport360; không body overflow ở360/768/1536. Lint/typecheck/build và 39 browser checks đạt, 0 page errors. API error cases dùng fixture ngoại trừ live anonymous /me và public 404. | [browser report](evidence/issue-15/browser-report.json); isolated checkers; screenshots |
| T06 data mapping | **Pass — FE scope** | Đủ mapping chín ảnh/route/DTO/actor/state; review histogram chỉ từ aggregate server, zero bars bằng0; missing aggregate/availability bị ẩn. Source/source absence và đề xuất A3 ghi rõ; rating/count production chưa có nguồn để chứng nhận. | [source matrix](issue-15-source-matrix.md), [assets](issue-15-assets.md), report aggregate/privacy checks |

Không có test Fail còn lại trong FE run cuối. Không coi fixture là bằng chứng backend quyền/visibility/lifecycle. Không tạo tài khoản, gửi mail, mutation DB hoặc chạy destructive backend tests trong B3 run này.

## Validation reproducible

Từ `frontend`:

```sh
npm run lint
npx tsc --noEmit --incremental false
node scripts/marketplace-query-check.mjs
node scripts/availability-check.mjs
npm run check:mock-roles
TUTORLINK_NEXT_DIST_DIR=.next-issue15-build NEXT_PUBLIC_DATA_MODE=api npm run build
PLAYWRIGHT_MODULE=/path/to/playwright/index.mjs TUTORLINK_URL=http://localhost:3000 node scripts/issue-15-browser.mjs
```

Tất cả đạt. `--incremental false` tránh file buildinfo của server Docker hiện hữu. Thư mục Next build riêng tránh lock `.next` đang dùng và được ignore khỏi Git/ESLint. Browser dùng Chrome đã cài, locale `vi-VN`, timezone `Asia/Ho_Chi_Minh`, frontend3000/API8000; URL3015 không nằm trong CORS local nên chỉ được dùng cho dev/build riêng. Mode mặc định đã chuyển API; legacy Auth/onboarding/Admin preview vẫn opt-in, Marketplace không dùng preview data. Mock-role checker còn kiểm phần legacy này.

Browser suite chỉ intercept các DTO tổng hợp trong test, không đặt fixture vào app services. Data mang tên “kiểm thử”, không có credential/token trong report/ảnh. 39 checks gồm 2 checks có live local API và các checks còn lại dựa trên fixture. 32 captures: 27 B3, 3 Auth baseline smoke, 2 live error/guest. Dev badge được ẩn bằng screenshot capture style; không sửa product state.

Review độc lập (`gpt-6.1-sol`, medium) phát hiện mất draft filter và validator chấp nhận offset timezone. Cả hai đã sửa; review xác nhận và browser/isolated checker thêm regression. Visual QA còn phát hiện mobile table che action sau cuộn ngang; đã sửa responsive cards, thêm assertion bounds/focus và chụp lại.

## Screenshot index

Ảnh populated dưới đây là **fixture UI evidence**, không phải backend A3 chạy thật. Click để đối chiếu với PNG reference trong source matrix.

| Route/reference | Desktop1536 | Tablet768 | Mobile360 |
| --- | --- | --- | --- |
| `/` — Home | [Ảnh](evidence/issue-15/home-1536.png) | [Ảnh](evidence/issue-15/home-768.png) | [Ảnh](evidence/issue-15/home-360.png) |
| `/become-a-tutor` — Tutor home | [Ảnh](evidence/issue-15/become-tutor-1536.png) | [Ảnh](evidence/issue-15/become-tutor-768.png) | [Ảnh](evidence/issue-15/become-tutor-360.png) |
| `/marketplace` — Tutor market | [Ảnh](evidence/issue-15/marketplace-1536.png) | [Ảnh](evidence/issue-15/marketplace-768.png) | [Ảnh](evidence/issue-15/marketplace-360.png) |
| `/marketplace/math-fixture` — Service detail | [Ảnh](evidence/issue-15/service-detail-1536.png) | [Ảnh](evidence/issue-15/service-detail-768.png) | [Ảnh](evidence/issue-15/service-detail-360.png) |
| `/tutors/tutor-fixture` — Tutor profile | [Ảnh](evidence/issue-15/tutor-profile-1536.png) | [Ảnh](evidence/issue-15/tutor-profile-768.png) | [Ảnh](evidence/issue-15/tutor-profile-360.png) |
| `/tutors/tutor-fixture/reviews` — Tutor comments | [Ảnh](evidence/issue-15/tutor-reviews-1536.png) | [Ảnh](evidence/issue-15/tutor-reviews-768.png) | [Ảnh](evidence/issue-15/tutor-reviews-360.png) |
| `/me/listings` — Owner list | [Ảnh](evidence/issue-15/owner-list-1536.png) | [Ảnh](evidence/issue-15/owner-list-768.png) | [Ảnh](evidence/issue-15/owner-list-360.png) |
| `/me/listings/501/edit` — Owner form | [Ảnh](evidence/issue-15/owner-form-1536.png) | [Ảnh](evidence/issue-15/owner-form-768.png) | [Ảnh](evidence/issue-15/owner-form-360.png) |
| `/me/availability` — Tutor schedule | [Ảnh](evidence/issue-15/availability-1536.png) | [Ảnh](evidence/issue-15/availability-768.png) | [Ảnh](evidence/issue-15/availability-360.png) |
| `/auth/login` — baseline smoke | [Ảnh](evidence/issue-15/auth-baseline-login-1536.png) | [Ảnh](evidence/issue-15/auth-baseline-login-768.png) | [Ảnh](evidence/issue-15/auth-baseline-login-360.png) |

Live API error evidence: [Marketplace404](evidence/issue-15/marketplace-live-unavailable.png), [owner anonymous](evidence/issue-15/owner-live-guest.png).

## Bàn giao

- Khanh: [endpoint/request/DTO checklist](../contracts/w1-b3-api-handoff-khanh.md), [đề xuất contract](../contracts/w1-b3-marketplace-proposal.md). Chốt enum/actions, Draft eligibility, DELETE archive giữ Program, money/unit/query/sort, public slug/privacy/aggregate, weekly timezone/clear/conflict trước tích hợp.
- Hoàng: shared `AppShell`, `PublicFooter`, `ui.tsx` controls (`DataTable` thêm `data-label` cho mobile cards), `useAsyncData`; CSS token giữ từ nền #22. Owner role guard là UX, enforcement vẫn server. Sau A3, chạy lại T01–T06 trên actor thật, không fixture, rồi sign-off G1.
- Phân quyền sửa file khi triển khai: Owner agent sở hữu owner components/routes/CSS; Search agent sở hữu query/search route/CSS/checker; Public agent sở hữu detail/tutor/reviews; Landing agent sở hữu home/become/footer/shell; support sở hữu asset/source docs; coordinator sở hữu DTO/adapter/availability, tích hợp và evidence; reviewer đọc độc lập.

Không merge/close #15 trong lượt này. Không mở chat, Favorites, gallery/video, booking, featured/stats/testimonials hoặc newsletter không có contract. Nội dung dịch vụ dùng description; marketing FAQ là copy tĩnh và native accordion, không phải FAQ service từ backend.
