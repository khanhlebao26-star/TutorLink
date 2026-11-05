# D06-01 — Source baseline

- Captured: 2026-11-04T19:37:18+07:00 (Asia/Ho_Chi_Minh)
- Branch: `be-d06`
- HEAD: `d157a8eeae662c01d6bd6b031ea53c26a5b05688` (`git log -1`: docs(be-k06): finalize handover and commit references)
- Parent: `b9e73e323762fa44d44610651b1037917d0ab4af`
- Remote: `origin` points to `https://github.com/khanhlebao26-star/TutorLink.git`; `origin/main` is an old ref and is **not** used to infer whether this branch has pulled current work.
- Working tree was already dirty before this evidence run. Its exact porcelain listing, tracked diff SHA-256, and per-file hashes are in `source-manifest.json`. Existing D04 untracked fixtures were kept as found.

## Source artifact

`docs/api/openapi.json`: OpenAPI `3.1.2`, API info version `1.0.0`, **123 operations**, 60 schemas, 1086888 bytes, SHA-256 `83717e94538ac04fa6e4b7ec1865001f1dc5c810b67c5562e446ddce9b1c7940`. The count is the checked-in artifact count only; no fresh export/parity/freshness or runtime claim is made.

## Locked versions

- `dedoc/scramble`: `v0.13.49`
- `laravel/framework`: `v13.35.0`
- `pestphp/pest`: `v4.7.8`
- `backend/composer.lock`: SHA-256 `56b6094f45759aa5667f6c16378c59862eca00a6d7fdd5b1d28931395d09bb0b`
- `frontend/package-lock.json`: lockfile v3, SHA-256 `3a30ab82cd559848f375c70741c215b170d6070cc52e827ff0e5d479c3043f2a`

Versions identify lock contents; they do not establish installed vendor/runtime parity.

## Result

D06-01 baseline captured. Historical source dependencies and their ancestry checks are recorded in `dependency-refs.md`; missing/currently broken integration references and unresolved items are recorded in `gap-register.md`. No tests, export, or HTTP smoke were run for D06-01.
