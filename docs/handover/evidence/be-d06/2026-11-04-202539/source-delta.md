# D06-04 — Source delta

- **Baseline:** `d157a8eeae662c01d6bd6b031ea53c26a5b05688`.
- **Scope:** 8 Program operations + 6 Invitation operations in `/api/v1`.
- **Status:** Source metadata updated; focused verification passed on isolated PostgreSQL test. No behavior/business rule changed.

## Changes

- `ProgramController`: added explicit `403 account_inactive` OpenAPI response metadata to `pause`, `resume`, `complete`, and `cancel`. `TransitionProgram` checks current actor status inside the transaction; shared `EnsureActiveApiActor` rejects inactive API actors before controller execution. These operations have no offer `expected_version` guard, consistent with BE-D06-contract §6.
- `InvitationController`: clarified existing `403` descriptions on list/detail to include the inactive-account middleware response as well as unsupported roles. Existing generated OpenAPI already has the `401` response for list/detail/reject/cancel, so no duplicate attributes were added.
- `InvitationAcceptController`, Requests, Resources, and Actions required no source changes. Existing annotations/rules describe accept errors and `Idempotency-Key`; Request validation captures Program `expected_version`, send `expected_version`, and optional nullable decision `reason`; Resources expose the allow-listed Program and Invitation snapshot fields.
- Added a focused inactive Tutor lifecycle test: expects `403 account_inactive`, unchanged active Program/version, and no audit/outbox writes.

## Contract checks from source

- Program create: `CreateProgramRequest`; owner derives from authenticated Tutor, while `customer_id` must identify an active Customer. Success is `201`.
- Program update: `UpdateProgramRequest` requires integer `expected_version` and at least one offer field. Stale version and pending unexpired Invitation lock return `409`; update is an offer edit, not a lifecycle version guard.
- Invitation send: requires UUID `Idempotency-Key` and integer `expected_version`; snapshots the offer and source Listing context, then records the pending lock transactionally. Stale version, active lock, or source hold can return `409`.
- Invitation accept: empty/empty-object body only; UUID `Idempotency-Key`; transaction locks/rechecks context and eligibility, validates snapshot/version/expiry/hold, and writes activation plus membership atomically. Existing tests cover replay, mismatch, expiry, stale version, snapshot invalidity, hold, and no-mutation failures.
- Invitation reject/cancel: `reason` remains `sometimes|nullable|string|max:2000`. M05-F04 and BE-H03 state requiredness awaits Product review; current optional/null runtime behavior is retained. **Follow-up owner: Dũng** requests and records the Product decision before release mapping is frozen. No membership is created; terminal/expired states return `409`.
- Focused test fixture status is `suspended`, a valid non-active persisted status. `EnsureActiveApiActor` returns the contract's `account_inactive` 403 for every status other than `active`; the original invalid `inactive` fixture was not a production status.
