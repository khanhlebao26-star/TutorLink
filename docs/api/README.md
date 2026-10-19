# API contract

`openapi.json` is generated from registered Laravel `/api/v1` routes with Scramble. Treat it as a generated release artifact and export it from code instead of editing the JSON by hand.

From `backend/`, run:

```sh
export DB_CONNECTION=sqlite
export DB_DATABASE=/tmp/tutorlink-openapi.sqlite
touch "$DB_DATABASE"
php artisan migrate --force --no-interaction
php artisan scramble:export --no-interaction
npx --yes @redocly/cli@2.60.0 lint ../docs/api/openapi.json --config ../.redocly.yaml
php scripts/verify-openapi-routes.php
```

CI validates PostgreSQL migrations separately, then exports against a migrated temporary SQLite database so schema inference produces the same artifact locally and in CI. It validates the document, compares its operations with registered routes, and checks that the committed artifact is current. Add request validation, response resource/type, authentication, summaries, and error metadata alongside each endpoint change before refreshing the artifact. Redocly keeps incomplete summaries and response details as warnings while schema and reference errors remain CI failures.

The seven conditional D23 endpoints are not registered in the current API routes, so they are absent from this contract. Keep pending endpoints out of the registered API routes, or mark their controller method with Scramble's `#[ExcludeRouteFromDocs]` attribute; the route parity check respects that exclusion. Remove the attribute only after approval.
