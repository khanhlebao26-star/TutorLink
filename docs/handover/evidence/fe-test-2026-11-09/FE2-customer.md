# FE2 — Customer FE test report

**Date:** 2026-11-09 (Asia/Ho_Chi_Minh)  
**Branch / baseline:** `codex/2w-fe-integration` / `5a0e722`  
**Initial worktree:** clean.  
**Outcome:** First pass completed. Customer FE is not accepted: one Customer profile implementation gap and one live login navigation failure remain; the focused conflict test still fails at runtime.

## Commands and results

| Check | Exit | Result |
|---|---:|---|
| `npm test -- src/test/contracts.test.ts src/test/workspace-form.test.tsx src/test/workspace-phase2.test.ts src/test/workspace-surfaces.test.tsx src/test/collaboration-ui.test.tsx` before the harness edit | 1 | 4/5 files passed; 20/21 tests passed. `keeps input on conflict and requires a deliberate reload` failed with `ApiClientError: Changed`. |
| Same focused Vitest command after replacing two unsupported `exact` options with `/^Lưu$/` | 1 | Same result: 4/5 files and 20/21 tests passed. The test fails before its assertions with `ApiClientError: Changed`; see [Vitest log](FE2-focused-vitest.log). |
| `npm run typecheck` before the harness edit | 2 | Two TS2769 errors at `workspace-form.test.tsx:29,34`: `exact` is not part of the installed `ByRoleOptions`. Log: `/private/tmp/tutorlink-fe-typecheck-20261109.log`. |
| Shared `npm run typecheck` after the harness edit | 0 | Root reported pass. |
| Shared `npm run lint` | 0 | Root reported pass. |
| Shared `npm run test:coverage` | 1 | Root reported 29 passed / 1 failed across 8 files; the same conflict test failed. Log: `/private/tmp/tutorlink-fe-coverage-20261109.log`. |
| Account Mailpit Playwright, default browser launch | 1 | Chrome aborted under the restricted process launch (`SIGABRT`; cleanup reported `EPERM`); no page or account was created. [Log](FE2-account-playwright.log). |
| Account Mailpit Playwright, host launch | 1 | Chrome launched, but Playwright could not create the context because the configured retained video requires the missing ffmpeg binary. [Log](FE2-account-playwright-retry.log). |
| Account Mailpit Playwright with temporary config setting `video: "off"` | 1 | Registration and signed email verification succeeded. API login and `/me` both returned 200, but the browser remained on `/auth/login` at the 5-second navigation assertion. [Log](FE2-account-playwright-final.log), [screenshot](/private/tmp/tutorlink-fe-test-20261109/FE2-account/test-results/account-mailpit-register---27aa8-on---login---password-reset-chromium/test-failed-1.png), [trace](/private/tmp/tutorlink-fe-test-20261109/FE2-account/test-results/account-mailpit-register---27aa8-on---login---password-reset-chromium/trace.zip). The isolated DB now contains the unique test Customer created by this run; no cleanup/reset was performed. |
| Guest-only workspace Playwright: `npm run test:e2e -- --config=playwright.fe2.config.ts e2e/workspace.spec.ts --grep guest` | 0 | `guest cannot open a workspace` passed (1/1). [Log](FE2-guest-playwright.log). |

The temporary Playwright config used only to bypass missing ffmpeg was removed. The shared `playwright.config.ts` was not changed by FE2.

## Findings

### FE2-01 — Customer profile is absent from the routed UI

- **Severity:** P2 — assigned Customer profile flow is not implemented.
- **Evidence:** [profile page](../../../../frontend/src/app/profile/page.tsx#L17) hard-codes `role="tutor"` and loads Tutor onboarding. [ProfileForm](../../../../frontend/src/components/profile-form.tsx#L14) only calls `setSaved(true)` and then displays “Đã lưu trạng thái form mẫu”; it never calls an API. `rg` found no route importing `ProfileForm`. The dormant Customer branch in [services.ts](../../../../frontend/src/lib/data/services.ts#L33) throws 404 when `user.profile` is null, while BR-13 specifies that an absent optional Customer Profile returns `profile: null`.
- **Repro / expected:** Sign in as Customer and open the profile destination; Customer should be able to view/update their optional own profile. An empty profile should be represented as null.
- **Actual:** `/profile` is Tutor-only. The standalone form is not routed and its save message is explicitly a mock success. The API-shaped helper also treats a null profile as an error.
- **Next:** Align the FE route and nullable DTO with the Customer Profile API owner before implementation; both Customer Profile routes are currently listed as planned in the documentation.

### FE2-02 — Successful login does not reach the Customer landing page in the browser run

- **Severity:** P1 candidate — core sign-in navigation failed; root cause remains open.
- **Evidence:** [AuthForm](../../../../frontend/src/components/auth-form.tsx#L120) calls `login`, fetches `/me`, then routes Customer to `/marketplace`. The Playwright trace records `POST /api/v1/auth/login` = 200 and `GET /api/v1/me` = 200 with role `customer`; the route assertion in [account-mailpit.spec.ts](../../../../frontend/e2e/account-mailpit.spec.ts#L49) still observed `/auth/login` after 5 seconds. No visible auth error appeared in the screenshot. The attempted Marketplace RSC fetch had no recorded response before the test ended.
- **Repro:** The isolated account flow registered and verified a unique test Customer, then signed in with the same credentials.
- **Expected:** Navigate to `/marketplace` after the successful login and `/me` response.
- **Actual:** Browser remained at `/auth/login`; the password reset portion did not run. This may be a Next dev navigation delay or an application navigation failure; the trace does not identify which.
- **Next:** Inspect the FE dev server output and reproduce the post-login route with the authenticated test session before changing the login handler or weakening the assertion.

### FE2-03 — Focused 409 conflict test remains red after fixing the TypeScript harness error

- **Severity:** Unclassified test failure; not yet confirmed as a product-code defect.
- **Evidence:** Before edit, TypeScript rejected `exact: true` at lines 29 and 34. The test now uses the anchored regex `/^Lưu$/`, retaining exact accessible-name matching; shared typecheck passes. Both pre- and post-edit Vitest runs fail at the mocked `ApiClientError("Changed", { status: 409 })` in [workspace-form.test.tsx](../../../../frontend/src/test/workspace-form.test.tsx#L14), before the test assertions. [WorkspaceForm](../../../../frontend/src/components/workspace-form.tsx#L56) awaits the write inside a try/catch and sets the conflict state, so current evidence cannot distinguish a Vitest/RTL async-event harness issue from a runtime component issue. Do not treat the assertion as passed.
- **Expected:** Show the conflict alert, preserve the entered draft, disable another save until deliberate reload, and call reload only after that action.
- **Actual:** Vitest reports `ApiClientError: Changed`; no assertion result is recorded. Root's coverage run reproduces the same failure.
- **Next:** Debug the async rejection delivery in this RTL test without replacing the rejected request with a success or removing the conflict assertions.

## Coverage by Customer flow

| Flow | Status | Evidence / limit |
|---|---|---|
| Marketplace search, filters, pagination, Tutor detail | **Not tested** | Source reviewed; no FE2 browser case or focused RTL covers these interactions. |
| Customer Profile | **Fail (static)** | FE2-01 above; no Customer route, save is a placeholder, null profile handling conflicts with BR-13. |
| Open text chat | **Pass (unit)** | Contract and collaboration UI suites verify text payload, retry UUID, and no attachment input. No live chat mutation was run. |
| Invitation preview, accept/reject, pre-membership access | **Partial** | Invitation snapshot acceptance and Draft-without-workspace-summary RTL tests passed. Reject and live Customer access checks were not run; the mutating cross-role workspace fixture belongs to FE1. |
| Six Customer workspace tabs | **Partial** | Navigation defines Overview, Sessions, Tasks, Progress, Announcements, and Fees. Session paused/terminal controls passed; Customer read-only behavior and the other tabs were not browser-tested. |
| Task submission/history/files/retry/conflict | **Partial / Fail** | Retry identity and generic network retry passed. The 409 conflict regression remains red (FE2-03). Live upload/history/revision flow was not run because the cross-role fixture belongs to FE1. |
| Fees, Progress, Announcements | **Partial** | Fee label and generic workspace form unit assertions passed. API reads, writes, stale versions, and Customer views were not exercised live. |
| Review, Report, Notification | **Partial** | Notification forbidden-state UI test passed. Completed-Program review, Report submission/history, and live Customer notification actions were not tested. |
| Guest access to Workspace | **Pass (browser)** | Guest cannot open `/programs/1/sessions`; 1/1 passed. |
| Registration, email verification, login, reset | **Fail (browser)** | Registration and verification pass; post-login navigation fails, so reset was not reached (FE2-02). |

## Scope and next steps

- No mutation was made to the local development DB; no seed/reset, Gmail, or Slack action was performed. The single account registration occurred only in the isolated PostgreSQL test DB and local Mailpit authorized by root. The guest case was read-only.
- The cross-role Program → Task → review scenario was not run because FE1 owns its Customer/Tutor fixtures. FE2 did not edit shared Compose/client/package files or business components, and did not commit or push.
- Root can continue with FE2-02 login navigation diagnosis and FE2-03 RTL conflict test diagnosis. Customer Profile needs API-owner alignment because the Customer Profile routes remain planned.
