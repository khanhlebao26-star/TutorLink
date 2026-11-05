# D06-04 — Operation and case matrix

| Operation | Request/success contract | Error and behavior evidence | D06-04 result |
|---|---|---|---|
| `GET /programs` | page/per_page, max 50; paginated ProgramResource | ProgramApiTest list scope; 401/403/422 | Existing source/tests; not rerun here |
| `POST /programs` | CreateProgramRequest; 201 ProgramResource | ProgramApiTest create, wrong role, invalid payload, Listing hold | Existing source/tests; not rerun here |
| `GET /programs/{program}` | scoped ProgramResource | ProgramApiTest visible scope/member; 401/403/404 | Existing source/tests; not rerun here |
| `PATCH /programs/{program}` | UpdateProgramRequest, required expected_version; 200 | stale/locked Draft, rollback on stale after lazy expiry, ownership and invalid fields in ProgramApiTest | Existing source/tests; not rerun here |
| `POST /programs/{program}/pause` | no version guard; 200 | 401/403(non-active actor)/404/409; lifecycle transitions and no-side-effect cases | New `suspended` actor case passed on isolated PostgreSQL: 403 `account_inactive`, Program/version unchanged, no audit/outbox writes (1 test, 5 assertions). First `inactive` fixture failed setup and was superseded. |
| `POST /programs/{program}/resume` | no version guard; 200 | 401/403(inactive)/404/409; lifecycle transitions | Existing source/tests; no dedicated inactive test per action |
| `POST /programs/{program}/complete` | no version guard; 200 | blockers 409, ownership 404, inactive 403; ProgramLifecycleApiTest | Existing source/tests; no dedicated inactive test per action |
| `POST /programs/{program}/cancel` | no version guard; 200 | blockers 409; atomic pending Invitation cancellation; inactive 403 | Existing source/tests; no dedicated inactive test per action |
| `GET /invitations` | page/per_page, max 50; scoped pagination | 401/403/422 | Existing metadata and generated responses; not rerun here |
| `POST /programs/{program}/invitations` | expected_version + UUID Idempotency-Key; 201 InvitationResource | Send/replay, stale version, pending lock, lazy expiry rollback, source hold; InvitationApiTest | Existing source/tests; not rerun here |
| `GET /invitations/{invitation}` | scoped InvitationResource | 401/403/404; current effective expiry without write | Existing source/tests; not rerun here |
| `POST /invitations/{invitation}/accept` | empty body; UUID Idempotency-Key; 200 Program+Invitation data | 401/403/404/409/422; snapshot, expiry, version, hold, replay, mismatch; InvitationAcceptTest | Existing source/tests; not rerun here |
| `POST /invitations/{invitation}/reject` | optional nullable reason max 2000; 200 | inactive 403; wrong actor hidden as 404; expired/terminal 409; validation 422; InvitationDecisionApiTest | Existing source/tests; not rerun here |
| `POST /invitations/{invitation}/cancel` | optional nullable reason max 2000; 200 | same decision matrix; no Program version change or membership | Existing source/tests; not rerun here |

## Execution status

- PHP syntax checks: passed for both changed controllers and the focused test file (exit 0).
- Focused test: passed after guard in the isolated D06 runner (exit 0, 1 test, 5 assertions); see `verification.md` and `focused-test-rerun.txt`.
- Application runtime, migrations, seed, full suite, export, and HTTP smoke: not run.
- This matrix cites existing test/source coverage; it is not a claim that those tests were executed in this D06-04 pass.
