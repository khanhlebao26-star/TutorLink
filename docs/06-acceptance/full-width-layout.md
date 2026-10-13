# Full-width page layout

Status: Verified locally on 2026-10-13. Implementation/audit: GPT-6 Luna extra high sub-agents; browser/quality checks: GPT-6 Luna medium; final visual review: parent coordinator. Reviewer acceptance remains separate from local verification.

## Scope and route coverage

The original screenshot showed an authenticated page constrained by two centered wrappers: `.authenticated-frame` was capped at 1773px with a 20px auto margin, and `.app-main .page-content` was capped at 1360px. Generic `.page-content` also had a 1200px maximum and auto margins. The implementation removes the authenticated frame's outer inset, border, radius and shadow, and removes page/main width caps and centering while retaining responsive content padding. These shared rules affect:

| Page state | Routes | Prior layout constraints removed |
| --- | --- | --- |
| Public shell | `/`, `/marketplace`; guest views of `/profile` and `/admin` | `.app-frame` → `.page-content` (1200px cap) |
| Authenticated shell | `/`, `/marketplace`, `/marketplace/[slug]`, `/profile`, `/admin` | `.authenticated-frame` (1773px cap) → sidebar + `.app-main .page-content` (1360px cap) |
| Auth scene | `/auth/login`, `/auth/register`, `/auth/forgot-password`, `/auth/reset-password`, `/auth/verify-email` | `.auth-page-content` clears the page cap; `AuthLayout` centers the form within the full-width scene |

Page and main containers now use the available viewport width while retaining deliberate padding. The auth scene remains full width with its form centered and readable. The 180px role sidebar remains in the desktop grid at 768px and wider; at 700px and below it becomes a menu overlay with width `min(290px, 86vw)`. Role navigation is Customer: Marketplace; Tutor: Profile and Marketplace; Admin: approval.

## Container and component decisions

- `.detail-card` no longer has its 780px cap. Listing details and the selected Admin tutor profile use the full main content row. Admin loaded-data geometry was verified; listing data is unavailable through the existing API, as noted below.
- `.lead` is capped at 700px for paragraph readability. `.narrow` (520px) and `.access-state` (600px) constrain focused forms and access messages.
- Onboarding document-type and experience-year fields are capped at 420px and 380px. These are field widths, not page wrappers.
- Marketplace cards use a two-column grid on desktop and one column at 700px and below. Admin tables scroll inside `.table-wrap`; that local overflow behavior should remain intact.

## Acceptance checks

Run these checks after implementation at 360px, 768px, 1536px, and 2560px viewport widths:

| Width | Expected layout |
| --- | --- |
| 360px | No page-level horizontal overflow. Shell and content span the viewport with mobile padding. Role sidebar opens as an overlay. Auth forms remain centered and usable. |
| 768px | The 180px role sidebar remains visible; main content fills the remaining column without a centered max-width gap or horizontal overflow. |
| 1536px | Authenticated shell fills the viewport with no outer inset or boxed frame; main content expands after the sidebar. Public home and Marketplace content also use the available width. |
| 2560px | Shell and main content continue to use the available width rather than stopping at 1773px/1360px; preserve comfortable page padding. |

Check the authenticated shell for Customer, Tutor, and Admin roles. Cover `/`, `/marketplace`, `/marketplace/[slug]`, `/profile`, and `/admin`, plus one auth route to confirm its centered form was not stretched. Verify that tables stay locally scrollable and the page itself does not gain horizontal scrolling.

## Differences and boundaries

The reference screenshot captures `/marketplace` in the authenticated shell with the existing 404 for `api/v1/listings`. Treat that API response as existing environment behavior outside this layout scope; it is not evidence about width acceptance. Keep the existing issue 22 changes and screenshots under `outputs/issue-22-figma-layout` as historical evidence.

Only `frontend/src/app/globals.css` changed in product code for this follow-up. AppShell markup, API, backend, schema and authentication flows were preserved. Documentation and a portable browser runner were added/updated separately. Prior issue #22 screenshots stay unchanged.

## Verification

| Check | Actual result |
| --- | --- |
| Browser layout matrix | 485/485 PASS, 0 failures; 360/768/1536/2560px |
| Role and route coverage | Guest, Customer, Tutor and Admin; home, marketplace/listing error state, profile, Admin, all five Auth routes |
| Full-width geometry | Outer frame at viewport edges; page content fills parent; no unintended horizontal document overflow |
| Sidebar / menu | Desktop 180px column and mobile overlay/menu behavior verified |
| Admin detail panel | Loaded profile fills inner content row at 768/1536/2560; table scroll stays local |
| Frontend lint | `docker compose exec -T frontend npm run lint` — PASS |
| Production build | `docker compose exec -T frontend npm run build` — PASS |
| TypeScript after build | `docker compose exec -T frontend ./node_modules/.bin/tsc --noEmit` — PASS |

The parent coordinator directly reviewed the Customer Marketplace and Tutor Profile at 2560px, the mobile Tutor menu at 360px, the loaded Admin detail panel at 2560px, and closed mobile login. The former outer gutters are gone, panels fill their content rows, and Auth controls remain readable. No additional visual defects were found in these captures.

Evidence:

- [Before/after comparison](../../outputs/full-width-layout-2026-10-13/comparison.html).
- [Browser report](../../outputs/full-width-layout-2026-10-13/report.md) and [geometry/check JSON](../../outputs/full-width-layout-2026-10-13/report.json).
- [Customer Marketplace 2560px](../../outputs/full-width-layout-2026-10-13/customer-_marketplace-ultrawide.png).
- [Tutor Profile 2560px](../../outputs/full-width-layout-2026-10-13/tutor-_profile-ultrawide.png).
- [Admin loaded detail 2560px](../../outputs/full-width-layout-2026-10-13/admin-selected-detail-ultrawide.png).
- [Mobile login 360px](../../outputs/full-width-layout-2026-10-13/login-mobile-closed.png).

Runner: `frontend/scripts/full-width-browser.mjs`, launched from the repository root. Set `PLAYWRIGHT_MODULE` / `TUTORLINK_CHROME_PATH` when using an externally supplied Playwright/browser; `TUTORLINK_URL` defaults to `http://localhost:3000`. No frontend dependency was added. Credentials are supplied through environment variables or a local fixture file and are not recorded in reports.

Limitations: captures use the development server and may include Next.js's dev indicator. Marketplace's existing `/api/v1/listings` 404 remains outside this layout change; real listing content was not claimed as verified. Production build was verified separately. No commit/push/PR was performed for this follow-up.
