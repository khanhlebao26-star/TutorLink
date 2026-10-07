# W1-C1 — Business state policy and contracts

**Ticket:** TL-XXX<br>
**Plan:** W1-C1<br>
**Epic:** B00, B09, B11, B12<br>
**Sprint:** W01<br>
**Fix version:** G1<br>
**Owner:** Hoàng<br>
**Review:** Khanh — constraint/transaction; Dũng — UI fields<br>
**Branch:** `feature/TL-XXX-business-contracts`

## Decision status

This is the contract-level source of truth for W1-C1. It is ready for review, but
it is not a migration or a complete feature implementation. W1-A1's ERD and API
contract are not present in this checkout. The 2026-10-07 runtime audit found
only the Laravel infrastructure schema and `/api/user`; no Approval,
Conversation, Program, Invitation, Customer membership, or lesson-session API
exists yet.

The wire contract uses:

- Positive integer identifiers, matching the current PostgreSQL `users.id`
  (`bigint`). IDs remain opaque to the UI and must not be arithmetically
  interpreted.
- ISO-8601 UTC timestamps (`Z`).
- Lowercase `snake_case` enum values on the wire; the UI may render title-case
  labels.
- Server-owned status transitions. A client may request an action, but it may
  not submit an arbitrary next status.
- Integer minor currency units plus an ISO-4217 currency code. Never use a
  floating-point amount in a request or response.

## 1. Audit decisions

| ID | Decision | Rationale / audit consequence |
| --- | --- | --- |
| AD-01 | Status is an aggregate invariant owned by the backend. | Prevents a client from jumping from `pending` to `active` or reviving a terminal record. |
| AD-02 | Every transition records actor, old status, new status, reason, and time. | Provides an auditable decision trail for approval, invitation, cancellation, and expiry. |
| AD-03 | Business records are not hard-deleted as part of these flows. | Historical approvals, invitations, and conversations must remain explainable. Redaction/retention is a separate policy. |
| AD-04 | A terminal state is not reopened in place. | A new approval or invitation is created when a retry is allowed; the old decision remains immutable. |
| AD-05 | Snapshot fields are separate from live profile/program relations. | Profile edits must not rewrite an offer that a customer already received or accepted. |
| AD-06 | `pending` means “requested but not yet committed to the next business milestone.” | It is not visible as active, not billable, and does not authorize session creation by itself. |
| AD-07 | Commands are idempotent by client/request key. | Retries must not create duplicate approvals, invitations, conversations, or snapshots. |
| AD-08 | Authorization and membership validity are checked inside the transaction. | UI visibility checks alone are insufficient and can race with suspension or cancellation. |

## 2. State vocabulary

| Wire state | Meaning | UI meaning | General rule |
| --- | --- | --- | --- |
| `pending` | Request exists, but the next required approval/acceptance/activation has not completed. | Đang chờ | No active entitlement, session creation, or billable execution. |
| `active` | The business relationship or decision is currently effective. | Đang hoạt động / Đã chấp nhận | The record may be used within its scope and validity dates. |
| `rejected` | An authorized actor declined the request before activation. | Bị từ chối | Terminal for this record; retry creates a new record. |
| `cancelled` | An authorized actor intentionally stopped the request/relationship. | Đã huỷ | Terminal; never silently changes to `active`. |
| `expired` | The allowed response or validity window ended. | Hết hạn | Terminal; a new request/version is required. |

`rejected`, `cancelled`, and `expired` are distinct audit outcomes. Rejection is
an explicit negative decision; cancellation is an intentional stop; expiry is a
deadline or validity-window result without a successful completion.

## 3. Policy matrix

| Aggregate | Allowed states | `pending` starts when | `active` means | Snapshot lock | Rejected valid? | Cancelled by | Expired when |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Approval | `pending`, `active`, `rejected`, `cancelled`, `expired` | A review request is submitted. | The reviewer approved the target and the approval is still valid. | The reviewed target version is recorded at decision time; the decision cannot be rewritten. | Yes, only before activation. | Applicant or reviewer according to policy; admin may override with audit reason. | Review SLA or approval validity deadline passes without a valid active decision. |
| Conversation | `pending`, `active`, `cancelled`, `expired` | A conversation is requested but participant access is not yet confirmed. | Both participants may read/write within the conversation scope. | Participant/program context is locked when the conversation becomes active. | No. A denied request is `cancelled` with a denial reason. | Participant with permission or admin. | A configured inactivity/relationship deadline passes; exact retention window is a W1-A1 dependency. |
| Program | `pending`, `active`, `rejected`, `cancelled`, `expired` | A program proposal exists and awaits invitation acceptance/required approval. | The accepted program version is effective and may materialize planned lesson occurrences. | Offer/commercial snapshot locks when the first invitation is issued; activation confirms that snapshot. | Yes, before activation. | Program owner, counterparty, or admin according to action policy. | End date/validity window passes while not successfully active, or an active program reaches its defined end date. |
| Invitation | `pending`, `active`, `rejected`, `cancelled`, `expired` | The offer is created and delivered to the invitee. | The invitee accepted the exact immutable offer snapshot. | Locks at invitation creation before delivery; response cannot mutate the snapshot. | Yes, by the invitee before activation. | Inviter or admin before acceptance only. After acceptance, cancel the Program instead. | `expires_at` passes while still `pending`. |

### Contract-specific notes

- `Approval.active` is an approval decision, not proof that a profile or
  program is currently active. The target aggregate must still satisfy its own
  state rules.
- `Conversation` intentionally has no `rejected` state. A denied conversation
  request is a cancelled request with an auditable reason.
- `Invitation.active` is the wire representation of “accepted/confirmed”. The
  UI may display **Accepted** while keeping `status: active` in API payloads.
  It is a terminal decision record; ending the relationship later transitions
  the linked Program, not the accepted Invitation.
- `Program.active` is reached only after the invitation acceptance and required
  approvals succeed in one transaction. A pending program may be edited until
  its snapshot is locked.

## 4. Valid and invalid transitions

### Approval

| From | Valid next state | Invalid examples |
| --- | --- | --- |
| `pending` | `active`, `rejected`, `cancelled`, `expired` | Client directly setting `active`; decision without reviewer permission. |
| `active` | `cancelled`, `expired` | `active -> rejected`; editing the original decision to `pending`. |
| `rejected` | None | Reopening the same approval; changing the reviewer decision in place. |
| `cancelled` | None | Reviving a cancelled approval. |
| `expired` | None | Treating an expired approval as active. |

### Conversation

| From | Valid next state | Invalid examples |
| --- | --- | --- |
| `pending` | `active`, `cancelled`, `expired` | Activating without two valid participants; `pending -> rejected`. |
| `active` | `cancelled`, `expired` | Sending after cancellation/expiry; changing participant IDs. |
| `cancelled` | None | Reopening; deleting the audit trail. |
| `expired` | None | Reusing the expired conversation for a new relationship. |

### Program

| From | Valid next state | Invalid examples |
| --- | --- | --- |
| `pending` | `active`, `rejected`, `cancelled`, `expired` | Activating without accepted invitation; editing locked commercial fields. |
| `active` | `cancelled`, `expired` | Rejecting an already active program; changing tutor/customer or price in place. |
| `rejected` | None | Reopening the same program; creating lesson sessions from it. |
| `cancelled` | None | Reopening; silently converting cancellation to expiry. |
| `expired` | None | Creating new lesson sessions against the expired version. |

### Invitation

| From | Valid next state | Invalid examples |
| --- | --- | --- |
| `pending` | `active`, `rejected`, `cancelled`, `expired` | Accepting after `expires_at`; accepting a changed snapshot. |
| `active` | None | Rejecting/cancelling after acceptance; changing the accepted offer in place. Cancel the linked Program instead. |
| `rejected` | None | Accepting later; editing the rejection into an acceptance. |
| `cancelled` | None | Reviving; delivering a cancelled invitation. |
| `expired` | None | Accepting after expiry; extending expiry without a new invitation/version. |

## 5. End-to-end state flow

```text
User (active, eligible role)
  -> Tutor Profile (pending)
  -> Approval (pending -> active)
  -> Program (pending)
  -> Invitation (pending, immutable offer snapshot)
  -> Invitation (active = accepted)
  -> Program (active, same confirmed snapshot)
  -> Conversation (pending -> active, only after valid participants)
  -> program_sessions (planned occurrences)
  -> lesson_sessions (materialized business occurrences)
```

The following are required gates:

1. The actor is authenticated and has permission for the command.
2. The tutor profile is approved and belongs to an active, non-suspended user.
3. The customer is a valid customer member (see Section 6).
4. The program and invitation are in the expected current state.
5. The snapshot/version supplied by the server still matches the row being
   changed.

## 6. Valid Customer member

A customer is eligible for a new program, invitation, or conversation only if
all of the following are true at command time:

- `user.id` exists and the account is not soft-deleted or suspended.
- The user has the `customer` role; a tutor account is not implicitly a
  customer member.
- The customer membership record exists and is `active`.
- The membership is inside the same tenant/organization scope as the program,
  if W1-A1 introduces tenant scoping.
- The customer is not the same principal as the tutor for a tutor-to-customer
  relationship, unless an explicitly approved self-test policy exists.

Membership is checked again inside the transaction. A customer becoming
suspended prevents new transitions but does not erase existing historical
records; the appropriate existing program/conversation action is an explicit
cancel or expiry with an audit reason.

The current `users` table has no role, account status, suspension, soft-delete,
or customer-membership fields. Therefore, this eligibility rule is a required
W1-A1 schema/API addition and cannot currently be enforced by the backend.

## 7. Program and Invitation snapshot lock

### Program snapshot

The first invitation issuance creates and locks the program offer snapshot. The
snapshot must contain at least:

- program title, objective/description, and level;
- tutor and customer IDs plus display-name values used for the offer;
- price in minor units, currency, duration, recurrence, and timezone;
- planned session count and schedule summary;
- source program ID, source version, and `captured_at`.

After `snapshot_locked_at` is set, commercial/identity fields cannot be edited
in place. A change requires a new program version and a new invitation. The
transition to `program.active` confirms the already locked snapshot; it does
not recalculate it from live profile data.

### Invitation snapshot

An invitation stores a full copy of the offer it delivers before the first
notification is sent. The invitation snapshot is immutable in every state,
including `pending`, `rejected`, `cancelled`, and `expired`. The invitation may
refer to `program_id` and `program_version`, but rendering must use the stored
snapshot so later program/profile edits cannot change a pending or historical
offer.

## 8. `sessions`, `program_sessions`, and `lesson_sessions`

The current database already contains Laravel's `sessions` table reserved for
HTTP session storage (`id`, `user_id`, `ip_address`, `user_agent`, `payload`,
and `last_activity`). The current runtime uses `SESSION_DRIVER=file`, so this
table is not the active session store, but its schema name is still reserved for
infrastructure. It is not a lesson entity and must not be used for Program
progress, attendance, or billing.

To avoid a table/resource collision, W1-C1 uses `lesson_sessions` as the
provisional domain name for actual teaching occurrences. Khanh must either
confirm this name in W1-A1 or explicitly replace/rename the Laravel
infrastructure table before reserving `sessions` for the domain. W1-C1 does not
perform that migration.

| Entity | Purpose | Lifecycle | Can be counted as delivered/completed? |
| --- | --- | --- | --- |
| `sessions` | Laravel HTTP session storage. | Managed by authentication/session infrastructure. | No. Never expose it as a lesson resource. |
| `program_sessions` | Planned occurrence/template under a program: sequence, planned start/end, timezone, and plan-level status. | Exists as part of the program plan; can be rescheduled before materialization subject to snapshot policy. | No. It is a plan row, not proof that a lesson happened. |
| `lesson_sessions` | Actual teaching occurrence with runtime status, attendance, join data, and completion evidence. | Materialized only from an active program and a valid planned occurrence. | Yes, according to its own attendance/completion policy. |

Required invariants for Khanh to map to ERD constraints:

- Every `lesson_session` points to exactly one `program_session`.
- A `program_session` belongs to exactly one program version.
- A planned sequence number is unique within a program version.
- A rejected/cancelled/expired program cannot materialize new lesson sessions.
- Counting, progress, and billing use `lesson_sessions`, never Laravel
  `sessions` or raw `program_sessions`.

## 9. Transaction boundary and constraint review

These are the intended service-level transaction boundaries for Khanh's review.
They are not migration code.

| Command | One transaction must cover | Minimum locking/constraint review |
| --- | --- | --- |
| Decide approval | Load approval and target version; authorize reviewer; write decision and audit event. | Lock current approval/target; enforce one active approval per target version; reject stale version. |
| Create invitation | Validate participants and customer membership; lock program; create immutable invitation snapshot; write delivery/outbox event. | Unique active invitation per program/version/invitee; snapshot is non-null and immutable after insert. |
| Respond to invitation | Lock invitation and program; re-check expiry, membership, and current status; transition invitation; activate program if all gates pass; write audit/outbox. | Exactly one response wins; no acceptance after expiry; program activation and invitation acceptance are atomic; an accepted invitation is not later cancelled in place. |
| Create/activate conversation | Validate both participants and relationship; lock the natural key; create or activate conversation. | Unique conversation per program/participant pair; no activation with invalid customer membership. |
| Update program | Lock program; reject locked fields; create a new version for an offer-affecting change. | Optimistic version check; no update that changes a referenced snapshot in place. |

Every command should return the persisted server state, not a client-computed
state. Retry with the same idempotency key must return the original result.

## 10. File MIME/size policy — Khanh review required

The following is the target business-policy baseline for review, not a silently
applied storage rule. The current PHP runtime reports
`upload_max_filesize=2M` and `post_max_size=8M`, so only the 2 MiB avatar limit
is runtime-compatible today. Larger limits require a separate, reviewed runtime
configuration change; W1-C1 does not change Docker.

| File purpose | Allowed MIME types | Max size |
| --- | --- | ---: |
| Tutor/profile image | `image/jpeg`, `image/png`, `image/webp` | 2 MiB |
| Tutor certificate/document | `application/pdf`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | 10 MiB |
| Chat attachment | `image/jpeg`, `image/png`, `image/webp`, `application/pdf` | 10 MiB |
| Program learning resource | `application/pdf`, `image/jpeg`, `image/png`, `image/webp` | 20 MiB |
| Optional recorded lesson/audio | `video/mp4`, `audio/mpeg`, `audio/mp4` | 100 MiB |

All uploads must validate detected content/MIME server-side, not only the file
extension or browser-provided `Content-Type`. Executables, scripts, archives,
SVG, and ambiguous/mismatched content are rejected by default. Until the
runtime limit is raised, the backend must reject any contract payload above
2 MiB with a stable validation error rather than relying on PHP to drop the
request body. Storage disk, scan result field, retention period, and the larger
runtime limits remain Khanh's review items.

## 11. UI handover

The executable examples are in
[`w1-c1-contract-mocks.json`](./w1-c1-contract-mocks.json).

Each aggregate includes `status`, `status_label`, `status_changed_at`, version
information, timestamps, display-ready participant data where needed, and an
`allowed_actions` list. The UI must render actions from `allowed_actions` but
the backend remains authoritative.

### Handover to Khanh

- Approval contract and decision audit fields.
- Transaction boundaries and minimum uniqueness/stale-version constraints.
- Mapping of `program_version`, `program_sessions`, and `lesson_sessions` to
  W1-A1, including resolution of the Laravel `sessions` naming collision.
- Final MIME/size/storage policy.

### Handover to Dũng

- Conversation, Program, and Invitation mock payloads.
- State labels and action visibility rules.
- Snapshot fields needed to render a stable pending/active offer.
- `program_sessions` versus `lesson_sessions` display distinction; Laravel
  `sessions` is never a UI resource.

### Open acceptance items

| Item | Owner | Status |
| --- | --- | --- |
| Confirm bigint IDs and map contract fields to W1-A1 ERD/API | Khanh | Pending W1-A1 handover |
| Review transaction boundary and DB constraints | Khanh | Pending review |
| Confirm `lesson_sessions` name / resolve Laravel `sessions` collision | Khanh | Pending review |
| Confirm MIME/size/storage limits and runtime configuration | Khanh | Pending review; current per-file runtime max is 2 MiB |
| Confirm UI field sufficiency | Dũng | Pending confirmation |

No item above should be represented as completed until the named reviewer
confirms it against the actual schema/API.

## 12. Runtime audit and verification — 2026-10-07

| Check | Result |
| --- | --- |
| Runtime | PHP 8.3.35, Laravel 13.35.0, Composer 2.10.3, PostgreSQL 16.15 |
| Backend routes | `/` and `/api/user` only; no W1-C1 business endpoint exists |
| Database | 10 Laravel infrastructure tables; no W1-C1 business table exists |
| Identifier alignment | `users.id` is `bigint`; mock contract renewed to positive integers |
| Composer validation | Passed with `--strict` |
| Laravel test suite | 2 passed, 2 assertions |
| DB connection | Confirmed from backend container to PostgreSQL `tutorlink` |
| Runtime issue | `php artisan db:show` connects and reports the DB, then fails while formatting because PHP `ext-intl` is missing |
