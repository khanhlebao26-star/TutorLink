# Issue #15 — B3 asset reuse and layout baseline

**Date:** 2026-10-13  
**Scope:** B3 frontend work before A3, as requested. This is an implementation inventory, not A3 contract approval or G1 acceptance. Issue #15 remains open; #14 remains open; #11, #18, and #22 are reported closed and verified by the coordinator.

## Reuse from the current frontend

| Area | Existing source | Reuse decision |
| --- | --- | --- |
| Brand, colors, type, focus | `frontend/src/app/globals.css` | Keep existing CSS variables and Inter typography. Main tokens: `--background #f8f7ff`, `--surface #fff`, `--surface-soft #f5f4fc`, `--foreground/--brand #071a4c`, `--brand-dark #07133f`, `--muted #7784ae`, `--line #dce2f4`, `--accent #ff0a99`, `--accent-dark #e50085`, `--accent-soft #fff0f8`, `--success #137a58`, `--warning #8c6100`, `--danger #b42318`, and `--focus #ff0a99`. Keep the existing visible keyboard outline. |
| Shared controls | `frontend/src/components/ui.tsx` | Reuse `Button` variants (`primary`, `secondary`, `outline`, `ghost`, `danger`), `Input`, `Select`, `Card`, `Field`, `Badge`, `StatePanel`, `ErrorActions`, and `DataTable`. Keep labels, associated error/hint text, busy/disabled states, and text status messages. |
| Site shell | `frontend/src/components/app-shell.tsx` | Reuse the existing public header, locale label, authenticated role shell, 61px app header, 180px sidebar, mobile menu, session/role gate, and logout flow. Marketplace pages currently use the public shell; owner pages use the authenticated Tutor shell. |
| API and loading behavior | `frontend/src/lib/api/client.ts`, `frontend/src/lib/hooks/use-async-data.ts` | Keep the existing Axios/Sanctum cookie and CSRF path, request error mapping, and stale-load protection. Marketplace remains API-backed; Auth preview fixtures do not supply listing data. |
| Marketplace presentation | `frontend/src/app/marketplace.css`, `frontend/src/app/public-detail.css`, `frontend/src/app/owner-listings.css`, `frontend/src/app/availability.css` | Extend the existing route-scoped styles and responsive layout. Do not add a separate component or state library for screenshot-only controls. |
| Marketplace DTO adapter | `frontend/src/lib/data/marketplace-types.ts`, `frontend/src/lib/data/marketplace.ts` | Existing types and requests are the B3 proposal adapter. Their comments and the proposal document state that A3 must confirm them; they are not evidence that the backend implements the routes. |

## Asset inventory

The nine PNGs listed in [the source matrix](issue-15-source-matrix.md) are design references under `docs/FE-figmaDesign/`. They are full-screen screenshots, not production image assets. Do not crop portraits or service thumbnails out of them, and do not present their sample names, counts, prices, reviews, or statuses as live data.

The app ships local Inter WOFF2 files and its OFL license under `frontend/public/fonts/`; `frontend/public/fonts/SOURCES.txt` records provenance. `frontend/public/figma/auth/` contains the student illustrations and small Auth icons. The shared locale display uses `globe.svg`. No Marketplace-specific service thumbnail, favorite, messaging, rating, or social icon asset exists in `frontend/public/`.

Public tutor rendering already accepts `avatar_url`; when the API omits it, the current UI shows initials. The listing search and owner table also use initials/text rather than fabricated cover photos. Keep this fallback. `student-man.png` and `student-woman.png` are reused only as generic marketing illustrations on `/` and `/become-a-tutor`; they are not real Tutor portraits or listing photos. Avoid adding an icon dependency just to reproduce screenshot glyphs; use the shared text/shape controls already present.

## Control boundary

Keep controls tied to existing code paths: URL-backed Marketplace filters and pagination; local selection for the “Xem nhanh” preview of an already loaded result; listing detail/profile links; owner status filters and actions gated by API `allowed_actions`; listing create/edit form submits; availability save; and reviews pagination. A local quick preview only selects a loaded listing and must not be described as a server action.

The current `/` and `/become-a-tutor` pages use native `<details>` for static informational marketing FAQs; that copy does not require a DTO. The service-detail Figma FAQ has no service FAQ field or source in the proposal, so omit it. Favorite hearts, chat/message actions, review-report menus, video/gallery controls, newsletter forms, social links, curriculum/lesson-plan accordions, testimonials, statistics, and related tutor/service rails have no corresponding B3 DTO/action. Omit unsupported data and controls rather than making them appear functional. Sample counts, testimonials, and reviews in the marketing screenshots are not live data.

The current route tree now includes both `/` and `/become-a-tutor`; the shared header/footer links to the tutor landing resolve. The root page uses the live catalog loader for category links and editorial copy for its steps, feature cards, FAQs, and calls to action. The tutor landing is also editorial. Neither page has a featured-listing/tutor, statistics, or testimonial data source, so those screenshot blocks are omitted. Marketing illustrations use the student assets described above, not API Tutor data.

## Issue #22 layout baseline is separate from B3

The full-width shell and shared layout are the #22 baseline. See [Issue #22 full-width redesign](issue-22-fullwidth-redesign.md) and its linked design specification/evidence. That baseline covers shared shell geometry and Auth/onboarding layout; it does not prove any B3 route, DTO, action, or Marketplace response. Keep B3 browser/evidence results separate and map them to the routes and API proposal in the source matrix. No Marketplace behavior is accepted by this asset inventory.

## Contract and backend boundary

`docs/contracts/w1-b3-marketplace-proposal.md` is the current frontend proposal, still pending A3 review. `docs/api/openapi.yaml` labels listing routes as planned for W1-A3 and says they are not implemented in the phase-2 runtime. The proposal’s additional listing filters, owner counts/actions, public Tutor/review routes, availability route, and archive behavior remain unconfirmed. The coordinator’s live API check reports `GET /api/v1/catalog/categories` = 200 with one category; probed Listing, Tutor, and availability endpoints return 404. This runtime observation does not approve the proposed DTOs/actions.

In particular, the proposal treats `DELETE /me/listings/{id}` as terminal archive preserving references; the older OpenAPI text says “Delete a draft listing,” returns “Listing deleted,” and conflicts with active/referenced records. Keep this mismatch visible for A3 review. No backend behavior or acceptance is claimed here.
