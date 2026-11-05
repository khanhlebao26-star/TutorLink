# D06 OpenAPI lint warning triage

**Artifact:** `docs/api/openapi.json`, 123 operations. **Lint:** Redocly CLI 2.60.0, local run. The schema/reference lint passed with zero errors; 52 warnings remain.

| Warnings | Rule | Triage |
| ---: | --- | --- |
| 47 | `operation-summary` | Missing editorial summaries on the listed operations. Their `operationId`, tag, path, parameter/request/response schemas and source-declared status/error metadata remain present. The inventory marks the unconfirmed owner lanes; operation IDs and paths are listed in [`openapi-missing-summary-operations.tsv`](openapi-missing-summary-operations.tsv). Add owner-approved summaries when those lanes are confirmed. |
| 2 | `operation-4xx-response` | `GET /catalog/categories` (`catalog.categories`) and `GET /catalog/specializations` (`catalog.specializations`) are public lookup operations with no request parameters, authentication, or input validation. They return the catalog data envelope; no client-caused 4xx response is defined by the current runtime contract. |
| 1 | `info-license` | No license metadata or repository license source is available to support a value. Do not invent one. |
| 1 | `no-server-example.com` | The exported `http://localhost:8000/api/v1` server is the configured local development URL. A production API URL is not present in the checked-in deployment configuration, so no external URL is fabricated. |
| 1 | `no-unused-components` | `PublicTutorResource` is emitted by Scramble but not referenced by the generated operations; the response schema is inlined. This is an unused generated component, not an unresolved `$ref` or missing response schema. |

The detailed lint output is in [`redocly-lint-smoke-final.log`](redocly-lint-smoke-final.log). The `npx` retry encountered a temporary npm registry DNS error; the same pinned CLI was run successfully from the existing local npm cache, and its output is the recorded final lint result.

The export was run twice against the guarded PostgreSQL test runner. Both exports produced SHA-256 `2a75f1d738dd40f5e946d916bbdaa9c14067b10af4253406a2e88ab96d899046`. Laravel route parity passed 123/123. Export, parity and hash evidence are in this run directory. Remote CI remains pending until the final commit is published in a PR.
