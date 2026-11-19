<div align="center">
  <img src="frontend/src/app/icon.svg" alt="TutorLink logo" width="88" height="88" />
  <h1>TutorLink</h1>
  <p><strong>A learning platform that connects tutors and learners and supports program and learning progress management.</strong></p>
  <p>
    <img src="https://img.shields.io/badge/Backend-Laravel-071A4C?style=for-the-badge" alt="Backend: Laravel" />
    <img src="https://img.shields.io/badge/Frontend-Next.js-FF0A99?style=for-the-badge" alt="Frontend: Next.js" />
    <img src="https://img.shields.io/badge/Database-PostgreSQL-071A4C?style=for-the-badge" alt="Database: PostgreSQL" />
  </p>
</div>

TutorLink uses Laravel for its API and business logic, Next.js for its web application, and PostgreSQL for persistent data. The platform supports three roles: Learner, Tutor, and Administrator.

## Project Information

| Item | Details |
| --- | --- |
| Course | Laravel |
| Instructor | Nguyen Huu Quyen |
| Class | 23CSE |
| Team | To be added |
| Members | Add each member's full name and student ID |
| Web palette | Navy `#071A4C`, pink `#FF0A99`, and lavender `#F8F7FF` ([source tokens](frontend/src/styles/base.css)) |

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Core Features](#2-core-features)
3. [Technology Stack and Architecture](#3-technology-stack-and-architecture)
4. [Local Setup](#4-local-setup)
5. [Testing and API Documentation](#5-testing-and-api-documentation)
6. [Performance Optimization](#6-performance-optimization)
7. [Measurements and Limitations](#7-measurements-and-limitations)
8. [Future Optimization Work](#8-future-optimization-work)
9. [Documentation and References](#9-documentation-and-references)

## 1. Project Overview

TutorLink helps learners find tutors, discuss learning needs, create learning programs, and track progress in one workspace. Tutors can offer services in academic subjects, languages, fitness, and other skills.

The main workflow is: **Tutor profile → review and approval → service listing → discussion → program creation → invitation → sessions and assignments → progress and reviews.**

Availability shown on a profile is for reference. Programs are activated through invitations; the platform does not currently provide automatic booking or online payment processing. Tuition fees are recorded and tracked as program data.

## 2. Core Features

### Learners

- Register, verify email, sign in, reset passwords, and manage learning profiles.
- Search services by keyword, subject, delivery mode, city, and price; save favorite tutors.
- Send messages and files, respond to invitations, and participate in learning programs.
- View sessions, complete assignments and quizzes, track progress, read class announcements, and submit eligible reviews.

### Tutors

- Complete a profile, submit verification documents, and track the review process.
- Manage service listings, availability, learning programs, and invitations.
- Manage sessions, assignments, submissions, feedback, quizzes, and learner progress.
- Record fees, publish announcements, and communicate with learners.

### Administrators

- View dashboards within their assigned permissions; assign reviewers and review profiles or documents.
- Handle reports, moderate reviews, and inspect audit records within their access scope.
- Monitor the outbox, background jobs, and configured operational tools.

See the [frontend README](frontend/README.md) for API integration, static content, and demo data. A registered route alone does not mean that every workflow has been accepted or fully verified.

## 3. Technology Stack and Architecture

- **Backend:** PHP 8.3, Laravel 13, Sanctum, Fortify, Reverb, and Filament 5 for the Laravel admin panel.
- **Frontend:** Next.js 16, React 19, TypeScript, and Tailwind CSS.
- **Data:** PostgreSQL 16. The production sample configuration uses the database for cache, sessions, and queues.
- **Backend deployment:** Docker Compose, Caddy, PHP-FPM, and OPcache; workers and the scheduler run as separate processes.
- **Testing and API contract:** Pest/PHPUnit, a dedicated PostgreSQL test database, Vitest, Playwright, Scramble/OpenAPI, and GitHub Actions.
- **Observability:** Telescope, Pulse, and Prometheus tooling for benchmark environments.

In local development, Next.js calls the Laravel API under `/api/v1`. Laravel owns authentication, authorization, validation, and business transactions. In the production backend configuration, Caddy forwards HTTP requests to PHP-FPM and WebSocket traffic to Reverb. The frontend is deployed separately; `compose.production.yml` does not currently include a Next.js service.

~~~text
TutorLink/
├── backend/                 Laravel API, actions, policies, migrations, and tests
├── frontend/                Next.js application, API client, components, and tests
├── docs/                    Product, domain, API, and handover documentation
├── docker/performance/      Load tests, data reconciliation, and monitoring tools
├── docker-compose.yml       Local development environment
├── compose.workers.yml      Local outbox, Slack workers, and scheduler
├── compose.testing.yml      Isolated PostgreSQL and backend test environment
└── compose.production.yml   Backend production deployment
~~~

## 4. Local Setup

You need Git and Docker Compose. Run the commands from the repository root.

~~~sh
git clone https://github.com/khanhlebao26-star/TutorLink.git
cd TutorLink
cp backend/.env.docker.example backend/.env.docker
docker compose build backend
docker compose run --rm --no-deps backend php artisan key:generate --show
~~~

The final command prints a Laravel key. Set it as `APP_KEY` in `backend/.env.docker`, and configure `LOCAL_ADMIN_EMAIL` and `LOCAL_ADMIN_PASSWORD` for a local administrator account. Keep an existing environment file if you have already configured one. The local Compose configuration provides PostgreSQL connection settings to the container.

~~~sh
docker compose up -d --build
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose -f docker-compose.yml -f compose.workers.yml up -d
~~~

`DatabaseSeeder` loads catalog data and the configured local administrator account. It does not create a complete set of demo learning programs. `FrontendDemoSeeder` is intended for a protected test database and should not be used to seed a regular local database.

- Web application: [localhost:3000](http://localhost:3000)
- API: [localhost:8000/api/v1](http://localhost:8000/api/v1)
- Mailpit for local email: [localhost:8025](http://localhost:8025)
- Local pgAdmin: [localhost:5050](http://localhost:5050)

For more details, see the [backend README](backend/README.md) and [frontend README](frontend/README.md). The Filament panel requires `ADMIN_PANEL_ENABLED` and a suitable account permission. Production settings, HTTPS, and secrets must be configured separately.

## 5. Testing and API Documentation

The backend uses a dedicated PostgreSQL test database. The test runner checks the database identity before rebuilding its schema and loading fixtures:

~~~sh
docker compose -p tutorlink-tests -f compose.testing.yml run --build --rm backend-test php scripts/test-suite.php
docker compose -p tutorlink-tests -f compose.testing.yml down
~~~

To check the frontend with Node.js 22, run these commands from `frontend/`:

~~~sh
npm ci
npm run lint
npm run typecheck
npm test
npm run build
~~~

The [generated OpenAPI document](docs/api/openapi.json) contains **139 operations** at the revision reviewed on November 19, 2026. See the [API contract guide](docs/api/README.md) for the export and validation process. The [API Route Catalog](<docs/Architecture & Technical Design/API Route Catalog.md>) distinguishes registered routes from planned routes.

Test results should be tied to the revision and environment where they were run. Handover reports are historical evidence; this README does not imply that the entire test suite has been rerun on every later revision.

## 6. Performance Optimization

This section documents mechanisms present in TutorLink's source code. Proposed work is listed separately in [Section 8](#8-future-optimization-work). The [server optimization report](docs/handover/TutorLink-server-optimization-2026-11-19.md) provides additional implementation details, evidence, and limitations.

### 6.1. Eager Loading, Selected Columns, and Bounded Results

Marketplace queries eager-load tutor, user, and catalog relationships and select only the required relationship columns. The API caps each page at 50 listings. This prevents each result row from triggering separate relationship queries and bounds the number of records in a response.

**Source:** [ListingController](backend/app/Http/Controllers/Api/V1/ListingController.php), [CatalogController](backend/app/Http/Controllers/Api/V1/CatalogController.php).

### 6.2. Conditional Caching for Public Marketplace Responses

Middleware caches successful JSON responses for allow-listed public routes for 60 seconds. Requests containing cookies or authorization headers, or associated with a user, follow a `private, no-store` path.

The cache key includes the path, filters, a generation number, and the expiry time of approved verification documents. PostgreSQL triggers update the generation in the same transaction as relevant writes, invalidating older cache keys. The generation and expiry are read in one SQL statement.

**Limitation:** A cache hit still queries PostgreSQL for the current generation. Redis was tested for cache payloads in a benchmark, but it is not the default cache-payload store in the production Compose configuration.

**Source:** [MarketplaceCacheControl](backend/app/Http/Middleware/MarketplaceCacheControl.php), [PublicCatalogCache](backend/app/Support/PublicCatalogCache.php), [cache invalidation migration](backend/database/migrations/2026_11_03_135735_create_marketplace_cache_versions.php).

### 6.3. Search Indexing and Deep Pagination

A migration creates a concurrent GIN index using `pg_trgm` over active listing titles and descriptions for substring search. For offsets of 20,000 or more, the controller uses a CTE to share the matching ID set between page retrieval and the exact total count before loading relationships.

**Limitation:** Exact totals and `OFFSET` pagination still have a cost. Benefits depend on the dataset, search term, selectivity, and execution plan.

**Source:** [GIN index migration](backend/database/migrations/2026_11_14_154208_add_active_listing_text_search_index.php), [ListingController](backend/app/Http/Controllers/Api/V1/ListingController.php).

### 6.4. Database-Side Aggregation

The administrator dashboard uses SQL `GROUP BY` and `COUNT` to summarize records by status, applying actor scope and permissions before returning data. This avoids loading every record into PHP just to count it. The dashboard still performs separate queries for different resource groups and two queries for outbox counts.

**Source:** [ReadAdminDashboard](backend/app/Actions/Admin/ReadAdminDashboard.php).

### 6.5. Outbox and Background Workers

The outbox stores events within the business transaction. Workers process bounded batches using `FOR UPDATE SKIP LOCKED`, retry failed work, and prevent duplicate notifications. Broadcast and Slack jobs use separate workers; the scheduler runs separately from HTTP requests.

**Limitation:** Email verification and password-reset messages are still sent synchronously; not all email delivery is queued.

**Source:** [ProcessNotificationOutbox](backend/app/Console/Commands/ProcessNotificationOutbox.php), [WorkNotificationOutbox](backend/app/Console/Commands/WorkNotificationOutbox.php), [production Compose configuration](compose.production.yml).

### 6.6. Production Runtime Configuration

The production backend image installs dependencies with `--no-dev`, generates an optimized autoloader, and enables OPcache. At startup, the entrypoint caches configuration, routes, and views. Caddy enables zstd/gzip compression, while background workers run as separate processes with restart policies. These are deployment mechanisms, not proof of production capacity.

**Source:** [production Dockerfile](backend/Dockerfile.production), [entrypoint](backend/docker/production/entrypoint.sh), [PHP configuration](backend/docker/production/php.ini), [Caddyfile](backend/docker/production/Caddyfile).

### 6.7. Avoiding Duplicate API Requests

The marketplace client shares identical public reads that are already in progress. Polling pauses when a tab is hidden or offline, prevents overlapping polls, and uses backoff. The message badge uses a dedicated unread-count endpoint every 15 seconds; chat polling uses a 10-second default interval. These mechanisms reduce repeated requests to the server without changing the visual design.

**Source:** [marketplace client](frontend/src/lib/data/marketplace.ts), [use-polling](frontend/src/lib/hooks/use-polling.ts), [use-unread-messages](frontend/src/lib/hooks/use-unread-messages.ts).

### 6.8. One Compact Response for Homepage Cards

`GET /listings?include=tutor_card` can include a tutor headline, years of experience, and public review counts and average. The homepage's featured-service and featured-tutor sections share the same in-flight candidate request and no longer fetch a profile or review list for each card. The rating aggregate uses the same visibility rules as the public reviews API. The optional field does not change the default listing response.

The homepage still chooses cards from up to 50 candidate listings. This change reduces follow-up requests; no payload-size or latency reduction percentage has been measured.

**Source:** [ListingController](backend/app/Http/Controllers/Api/V1/ListingController.php), [ListingResource](backend/app/Http/Resources/ListingResource.php), [marketplace data module](frontend/src/lib/data/marketplace.ts), [featured services](frontend/src/components/marketing/featured-services.tsx), [featured tutors](frontend/src/components/marketing/featured-tutors.tsx).

### 6.9. Paginated Workspace Data and Summary Counts

Tasks can be filtered and sorted by the backend and are loaded five per page by the frontend. Status counts cover the authorized Program regardless of the selected page or filter. File links for the current page are fetched in one grouped query rather than one query per task.

The Progress tab reads task and completed-session counts from the Program Overview endpoint instead of fetching the full task and session history. Progress records remain separately paginated.

**Source:** [TaskController](backend/app/Http/Controllers/Api/V1/TaskController.php), [ProgramOverviewController](backend/app/Http/Controllers/Api/V1/ProgramOverviewController.php), [workspace data module](frontend/src/lib/data/workspace.ts), [workspace page](frontend/src/components/programs/workspace-tab-page.tsx), [progress dashboard](frontend/src/components/programs/progress/progress-dashboard.tsx).

### 6.10. Refresh, Cancellation, and Read Timeouts

When data is refreshed from the same source after an action, the frontend keeps the current content visible and shows an updating state. A changed data source or scope, logout, or authorization error clears the old result. Workspace reads pass an `AbortSignal` so requests can be cancelled when no longer needed. GET and HEAD requests have a 15-second default timeout; callers can override it, and write requests are not automatically retried.

**Source:** [use-async-data](frontend/src/lib/hooks/use-async-data.ts), [API client](frontend/src/lib/api/client.ts), [API state component](frontend/src/components/shared/api-state.tsx).

### 6.11. Representative Code Samples

The snippets below are shortened excerpts from TutorLink's implementation. Follow the linked source files for complete validation, authorization, and response handling.

#### Aggregate review data for a page of listings

**File:** [ListingController.php](backend/app/Http/Controllers/Api/V1/ListingController.php)

~~~php
if (($filters['include'] ?? null) === 'tutor_card') {
    $ratings = PublicTutorReviewController::visibleReviews(
        $listings->pluck('tutor_profile_id')->unique()->all()
    )
        ->selectRaw('reviews.tutor_profile_id, COUNT(*) AS total, AVG(reviews.rating) AS average')
        ->groupBy('reviews.tutor_profile_id')
        ->get()
        ->keyBy('tutor_profile_id');

    foreach ($listings as $listing) {
        $rating = $ratings->get($listing->tutor_profile_id);
        $listing->setAttribute('tutor_card', [
            'headline' => $listing->tutorProfile?->headline,
            'experience_years' => (int) ($listing->tutorProfile?->experience_years ?? 0),
            'rating_count' => (int) ($rating->total ?? 0),
            'rating_average' => $rating ? round((float) $rating->average, 2) : null,
        ]);
    }
}
~~~

This calculates review aggregates for the current page as a grouped query instead of issuing a separate reviews request for each homepage card.

#### Paginate tasks and batch-load their file links

**File:** [TaskController.php](backend/app/Http/Controllers/Api/V1/TaskController.php)

~~~php
$tasks = $query->paginate($page['per_page'], ['*'], 'page', $page['page']);
$fileIds = DB::table('file_links')
    ->where('resource_type', 'task')
    ->whereIn('resource_id', $tasks->getCollection()->pluck('id'))
    ->get()
    ->groupBy('resource_id');

$response = $this->paginated(
    $tasks,
    fn ($task) => $this->taskData(
        $task,
        ($fileIds->get($task->id) ?? collect())->pluck('file_id')->all()
    )
);
~~~

The page limit and authorization checks are applied before this excerpt. The grouped lookup replaces per-task file-link reads on this list endpoint.

#### Apply a bounded timeout to read requests

**File:** [client.ts](frontend/src/lib/api/client.ts)

~~~ts
export async function request<T>(config: AxiosRequestConfig, requiresCsrf = false) {
  if (requiresCsrf) await initializeCsrf();
  const method = (config.method ?? "GET").toUpperCase();
  return api.request<T>({
    ...(method === "GET" || method === "HEAD" ? { timeout: 15_000 } : {}),
    ...config,
  });
}
~~~

The timeout applies to reads by default. Explicit caller configuration can override it, while this code does not automatically retry mutations.

## 7. Measurements and Limitations

**Implementation checks on November 19, 2026 (local working tree):** backend suites passed **852 tests / 7,568 assertions** against an isolated PostgreSQL test database; the frontend passed **85/85 tests**, typecheck, lint for changed files, and a production build. OpenAPI was regenerated from the source code and retained 139 operations. These checks cover request fan-out, pagination, aggregation, and data-loading lifecycle behavior; they are not new latency/RPS measurements or a production deployment acceptance.

The [November 14–15, 2026 performance report](docs/handover/TutorLink-performance-audit-2026-11-14.md) contains earlier local synthetic-load experiments. A mixed workload offered at **100 RPS for 600 seconds** completed 60,000 requests, with **99.99 successful RPS**, p95 **55 ms**, p99 **199 ms**, no errors, and no dropped requests. Reconciliation of 3,001 Sessions, including a smoke fixture, matched receipts and audit records.

The load generator and application shared Docker resources. Higher offered-load trials did not meet their targets. **The project has not passed a 1,000 successful requests/second for 60 minutes acceptance test or a production-capacity test.** The 100-RPS result also does not prove end-to-end performance across every API or the Next.js application.

See [docker/performance](docker/performance/README.md) for the benchmark tools and acceptance conditions.

## 8. Future Optimization Work

The following items are proposals and are not reported as completed work:

1. Queue email verification and password-reset delivery; ensure the producer and worker use the same queue, with retries and token-expiry checks.
2. Separate cache policies for slowly changing catalog data and frequently changing marketplace data; consider protection against many requests rebuilding an expired key at once.
3. Measure cache-generation lookup and invalidation costs. Preserve immediate effects for hidden listings, suspended tutors, and expired verification documents.
4. Combine the two outbox-count queries if profiling shows a meaningful cost; cache dashboard metrics only after defining acceptable data freshness and permission scope.
5. Measure query plans, resource usage, and PHP-FPM queues on representative staging with an independent load generator before tuning process sizing.
6. Integrate Reverb into the frontend for chat and unread badges, with reconnect/resynchronization and polling fallback.

Do not switch sessions to Redis by changing an environment variable alone: realtime session-channel checks currently depend on database sessions. Do not remove version checks, audit records, or idempotency safeguards for speed.

## 9. Documentation and References

- [TutorLink documentation index](docs/README.md)
- [TutorLink server optimization report](docs/handover/TutorLink-server-optimization-2026-11-19.md)
- [Previously recorded performance audit](docs/handover/TutorLink-performance-audit-2026-11-14.md)
- [Daily Vocabulary reference repository](https://github.com/AkioCkist/daily_vocabulary/tree/3c0ea06ccaebda687c662fa14245afcb0829a5c6): consulted for README organization and the presentation of problems, solutions, code locations, and evidence. Optimization mechanisms and results in this README are based on TutorLink's own source and measurements.
