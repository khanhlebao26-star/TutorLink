# FE1 Tutor and Program QA

Date: 2026-11-09 (Asia/Ho_Chi_Minh)

## Environment

- FE test frontend: `http://localhost:3001` (HTTP 200)
- API test backend: `http://localhost:8001` (CSRF endpoint 204; `/me` available after authentication)
- Isolated Mailpit: `http://localhost:8026` (HTTP 200)
- Isolated PostgreSQL test database passed its guard and was seeded before this run.
- Main user browser at `http://localhost:3000` and its Docker stack were left open/running. This QA lane did not reset or reseed a database and did not edit repository files.

## Results

| Status | Case | Evidence / notes |
|---|---|---|
| Pass | Customer and Tutor fixture auth through CSRF + API login; `/me.role` matched each role | `api-auth-workspace/auth-smoke.cjs`; `customer-routes.png`, `tutor-routes.png` |
| Pass | Customer and Tutor opened `/marketplace`, `/messages`, `/programs`, `/notifications`; each route returned HTTP 200 | `api-auth-workspace/auth-smoke.cjs` |
| Fail | Seeded Customer UI login in `workspace.spec.ts` remained at `/auth/login` after submit | Test expected navigation within 5 seconds. Trace shows login POST 200, `/me` GET 200, and `/marketplace` fetch 200. Failure XML/context/screenshots/trace: `e2e.xml`, `test-results/workspace-real-recipient-i-ae693-feedback---completed-review-chromium/`. This is an observed UI test failure; API-auth journey below was used to continue. |
| Pass | New Tutor CTA opened `/auth/register?role=tutor` with Tutor radio selected; registration through UI succeeded | `api-auth-workspace/tutor-register-results.json`; `tutor-register-form.png`, `tutor-register-success.png` |
| Pass | Verification email appeared in isolated Mailpit; signed verification link completed successfully; new account UI login succeeded | Account: `fe1-tutor-1794214927195@example.test`. Same results JSON. |
| Pass | New account `/api/v1/me` returned `role=tutor` and `email_verified_at`; fresh authenticated browser rendered the Tutor profile wizard with “Bản nháp” state | `api-auth-workspace/profile-render.png`. The initial harness assertion timed out after a second API login in the same context; the fresh-context render confirmed the page itself loads. No profile was saved or uploaded. |
| Pass | Customer previewed and accepted pending Invitation #1 into Program #2 | `api-auth-workspace/journey-results.json` |
| Pass | Both roles opened all six Program tabs: Overview, Sessions, Tasks, Progress, Announcements, Fees | Journey results JSON; final screenshots `customer-final.png`, `tutor-final.png` |
| Pass | Tutor paused and resumed Program #2 | Journey results JSON |
| Pass | Tutor created/completed a session; created progress, announcement and fee records | Journey results JSON |
| Pass | Tutor created Task #2; Customer submitted version 1; Tutor sent review/feedback | Journey results JSON |
| Pass | Tutor completed Program #2; Customer submitted a 5-star review; Customer notifications page loaded | Journey results JSON |
| Not tested | Save Tutor profile draft, avatar/document uploads, submit for review | Wizard was only rendered; stopped before any additional mutation at handoff. |
| Not tested | Listing create/edit/pause/archive and availability save | Not run. |
| Not tested | Program draft creation, invitation preview/send, reject and expiry | Accept path was exercised. Reject/expiry branches were not run; no expired fixture was verified. |
| Not tested | Notification content/read action; responsive and keyboard checks; loading, empty and error states across remaining routes | Not run in this lane. |

## Fixture state after this run

Do not reset or reseed the shared isolated test database before coordinating with the root agent. Invitation #1 is now accepted. Program #2 is completed and contains the created actual session, task/submission/review, progress update, announcement, fee record, and customer review. The new Tutor account above is registered and email-verified; it has no saved profile or uploaded files. The run did not create, approve, or modify a listing or availability rule.

## Resume point

Continue with the verified account `fe1-tutor-1794214927195@example.test` if desired, starting from its draft profile wizard. Before extending Program invitation coverage, create a fresh isolated pending invitation or use a confirmed separate fixture; Program #2 is terminal. Keep the seeded Customer UI-login failure separate from API-authenticated business-flow passes. All scripts and supporting artifacts are in `api-auth-workspace/`; no harness or source was added to the repository.
