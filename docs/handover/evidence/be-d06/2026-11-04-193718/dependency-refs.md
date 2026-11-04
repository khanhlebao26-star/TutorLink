# D06-01 — Dependency/source references

Every listed source/implementation commit is present in local Git and is an ancestor of the D06 baseline HEAD. The historical BE-D01–D03 handover files are readable from Git at `8a34aa2` but are absent from the current worktree; their old claims are not carried forward as runtime evidence.

| Dependency | Implementation ref | Source ref | Baseline relation / current availability |
| --- | --- | --- | --- |
| BE-D01 (#36) | `3512fd74f27cb1c7f7992d6d319f2706c5308d90` | `8a34aa2` | ancestor of HEAD; historical handover in Git; no longer in worktree |
| BE-D02 (#69) | `b272d2cc5dd5e77374e149620e508dc34aab7970` | `8a34aa2` | ancestor of HEAD; historical contract and implementation in Git; no longer in worktree |
| BE-D03 (#74) | `9c9d7468853aadbc2c32928fa55598e945d04d4c` | `8a34aa2` | ancestor of HEAD; historical report/source in Git; no longer in worktree |
| BE-D04 (D04 handover) | `8a34aa29a981f8d16cf9852a82cc1d0064590bd0` | `8a34aa29a981f8d16cf9852a82cc1d0064590bd0` | ancestor of HEAD; tracked handover/evidence present |
| BE-D05 / BE-H05 (D05-H05) | `a24ba746500724f1445e6ae48475d9f2c6ea7e4b` | `a24ba746500724f1445e6ae48475d9f2c6ea7e4b` | ancestor of HEAD; tracked handover/evidence present |
| BE-K04 (K04.7) | `ad2df0f852f3e0b4944f0a1abf502921592291fc` | `17cc0df25c7d61313981d38bc9751b80b395a325` | ancestor of HEAD; tracked handover/evidence present |
| BE-H03 (#75) | `2026c996400137da31d21b1e7064cb4d4823b51e` | `2026c996400137da31d21b1e7064cb4d4823b51e` | ancestor of HEAD; tracked handover/evidence present |
| BE-H04 (H04 report) | `11a03d58569237819a708b2c89221da7ea6aadd9` | `11a03d58569237819a708b2c89221da7ea6aadd9` | ancestor of HEAD; tracked handover/evidence present |
| BE-K06 (K06 report / baseline) | `d157a8eeae662c01d6bd6b031ea53c26a5b05688` | `d157a8eeae662c01d6bd6b031ea53c26a5b05688` | ancestor of HEAD; current source baseline; evidence remains historical |

### Verification scope

- `git merge-base --is-ancestor` passed for every implementation/source ref above.
- D01–D03 handover snapshots can be read from Git at `8a34aa2`; current checkout intentionally lacks those files. The report/contract content must be rechecked against current source before reuse.
- D04 initially untracked fixture directory is preserved unchanged; its original five files and hashes are recorded in `source-manifest.json`.
- Dependency source refs are covered; missing integration destinations are gaps rather than absent Git objects.
