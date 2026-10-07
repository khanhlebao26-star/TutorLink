# TutorLink API conventions

## Base URL and versioning

- Local base URL: `http://localhost:8000`
- Versioned API prefix: `/api/v1`
- Sanctum CSRF bootstrap: `/sanctum/csrf-cookie`
- JSON media type: `application/json`

## Naming and identifiers

- Paths use lowercase kebab-case where a word boundary is needed.
- JSON fields use `snake_case`.
- IDs are opaque positive integers; clients must not infer meaning from them.
- Timestamps are ISO-8601 UTC strings ending in `Z`.
- Enum values are lowercase `snake_case`.

## Responses

Successful single resources use:

```json
{
  "data": {}
}
```

Collections use:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

Errors use:

```json
{
  "message": "Validation failed.",
  "errors": {
    "email": ["The email field is invalid."]
  }
}
```

The frontend must handle `401`, `403`, `404`, `409`, `422`, and `429` without
assuming that every error has validation fields.

## Authentication

TutorLink uses Laravel Sanctum's first-party SPA cookie flow:

1. `GET /sanctum/csrf-cookie`.
2. Send the login/register request with credentials and the XSRF cookie.
3. Send subsequent API requests with credentials.

The browser must never receive database credentials or server-side secrets.

## Mutation rules

- Status transitions are commands owned by the backend.
- Clients send an action or command, not an arbitrary next status.
- Retry-sensitive mutations should accept an idempotency key.
- Money is an integer minor amount and a three-letter currency code.
