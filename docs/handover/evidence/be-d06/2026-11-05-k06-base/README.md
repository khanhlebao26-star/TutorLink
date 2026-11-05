# BE-D06 verification on the K06 baseline

Verified 05/11/2026 (Asia/Ho_Chi_Minh) after restoring the D06 branch to the dependency branch the task started from.

## Source and branch

- GitHub `be-k06` points to `d157a8eeae662c01d6bd6b031ea53c26a5b05688`; GitHub `main` points to `fb54886617524e43727705f7f6ee86bed4135ef9` and is behind this task baseline.
- The D06 source commit `ec0640d01f02b731d54082adfb08611a53105c1c` has `be-k06` as its parent. The local `be-d06` branch now contains that history.
- All 21 source-file SHA-256 entries in the release manifest match the source commit. D06 runtime PHP files and the generated OpenAPI artifact are unchanged from the earlier main-based run; this run additionally includes K06's test files.
- The main-based detour is preserved at local branch `codex/be-d06-main-20261105`; it is not the target D06 baseline.

## Database guard and suite

The runner used Compose project `tutorlink-d06-k06-verify-20261105`, the internal `testing` network, and a disposable PostgreSQL service. The safety preflight verified `pgsql`, host `test-db`, database `tutorlink_test`, and user `tutorlink_test_runner`. No application/production database was used.

| Group | Tests | Assertions | Failures | Errors | Skipped |
| --- | ---: | ---: | ---: | ---: | ---: |
| D04 migration preflight | 7 | 27 | 0 | 0 | 0 |
| Backend regression (including K06 tests) | 746 | 5,369 | 0 | 0 | 0 |
| D04 API/contract | 77 | 1,077 | 0 | 0 | 0 |
| D04 PostgreSQL race | 1 | 893 | 0 | 0 | 0 |
| **Total** | **831** | **7,366** | **0** | **0** | **0** |

Coverage: lines **93.59% (8,336/8,907)**, methods 79.97% (479/599), classes 61.78% (118/191). The Dung lane map remains 56 operations across 14 controller files, 1,805/1,895 statements (95.25%).

Evidence: [preflight log](preflight.log), [full suite log](full-suite.log), [exit code](exit-code.txt), [coverage and JUnit artifacts](coverage/), and [artifact hashes](coverage-artifact-sha256.txt).
