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
| Team | TutorLink |
| Members | Dung, Khanh, Hoang |


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

See the [frontend README](frontend/README.md) for API integration, static content, and test fixtures. A registered route alone does not mean that every workflow has been accepted or fully verified.

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

The frontend uses the Laravel API exclusively. Preview role switching and hard-coded tutor statistics have been removed. Test fixtures remain available for automated checks and are excluded from the production image; deployment does not import a local database or local uploads.

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

Middleware caches successful JSON responses for allow-listed public routes for 300 seconds for catalog routes and 60 seconds for marketplace routes. Requests containing cookies or authorization headers, or associated with a user, follow a `private, no-store` path.

The cache key includes the path, filters, a generation number, and the expiry time of approved verification documents. PostgreSQL triggers update the generation in the same transaction as relevant writes, invalidating older cache keys. The generation and expiry are read in one SQL statement for marketplace routes. Catalog routes only read the generation because catalog entries do not depend on tutor-document expiry. Rebuilds use a 15-second lock with at most a one-second wait and a second cache lookup; lock/cache failure falls back to the normal controller. Explicit public catalog/listing reads use the Fetch adapter with credentials omitted, allowing browser requests to reach the anonymous cache path even on the same origin. Private reads continue using session cookies.

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

Email verification and password-reset delivery now use an encrypted `SendAccountEmail` job on the `emails` queue after the transaction commits. The worker retries up to three times, checks account/email eligibility and expiry again, and drops reset tokens that are no longer valid. Both local workers and production Compose include an email worker. Set `QUEUE_CONNECTION=database`; choosing `sync` intentionally executes jobs inline. SMTP delivery has at-least-once retry semantics and can be duplicated if a provider accepts a message before a worker failure.

**Source:** [ProcessNotificationOutbox](backend/app/Console/Commands/ProcessNotificationOutbox.php), [WorkNotificationOutbox](backend/app/Console/Commands/WorkNotificationOutbox.php), [SendAccountEmail](backend/app/Jobs/SendAccountEmail.php), [local workers](compose.workers.yml), [production Compose configuration](compose.production.yml).

### 6.6. Production Runtime Configuration

The production backend image installs dependencies with `--no-dev`, generates an optimized autoloader, and enables OPcache. At startup, the entrypoint caches configuration, routes, and views. Caddy enables zstd/gzip compression, while background workers run as separate processes with restart policies. These are deployment mechanisms, not proof of production capacity.

**Source:** [production Dockerfile](backend/Dockerfile.production), [entrypoint](backend/docker/production/entrypoint.sh), [PHP configuration](backend/docker/production/php.ini), [Caddyfile](backend/docker/production/Caddyfile).

### 6.7. Avoiding Duplicate API Requests

The marketplace client shares identical public reads that are already in progress. The homepage also retains one public candidate snapshot for its mounted service and deferred tutor sections. Polling pauses when a tab is hidden or offline, prevents overlapping polls, and uses backoff. The message badge uses a dedicated unread-count endpoint every 15 seconds; the open conversation subscribes to its session-scoped Reverb channel. Events trigger authorized HTTP resynchronization and refresh the inbox/badge. Chat reconciliation runs every 60 seconds while subscribed, returning to 10 seconds on disconnect. Reconnect uses backoff; hidden/offline tabs and logout disconnect the socket. Inbox/badge polling remains available outside the open conversation. These mechanisms reduce repeated requests to the server without changing the visual design.

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

When data is refreshed from the same source after an action, the frontend keeps the current content visible and shows an updating state. A changed data source/scope or authorization error clears the old result. Session-scoped reads also clear on logout; public reads explicitly remain independent of the guest `/me` probe. Workspace reads pass an `AbortSignal` so requests can be cancelled when no longer needed. GET and HEAD requests have a 15-second default timeout; callers can override it, and write requests are not automatically retried.

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

### 6.12. Realtime and Email Setup

Local Docker frontend configuration supplies `NEXT_PUBLIC_REVERB_APP_KEY` and `NEXT_PUBLIC_REVERB_URL` (default `ws://localhost:8080`). For a separately deployed frontend, set these before building; use `wss://` with HTTPS. Only the public app key belongs in frontend configuration. The Reverb secret remains on the backend, and the backend origin allow-list must match the frontend host.

Start the backend stack and the workers:

~~~sh
docker compose up -d --build
docker compose -f docker-compose.yml -f compose.workers.yml up -d
~~~

Keep `SESSION_DRIVER=database` and `QUEUE_CONNECTION=database`. Start Reverb, the broadcasts worker, and the emails worker in the deployment environment. Without frontend realtime settings, chat uses polling. Session channel authorization still uses Laravel CSRF and the session cookie; no message content is trusted directly from the WebSocket event.

**Source:** [realtime hook](frontend/src/lib/hooks/use-conversation-realtime.ts), [polling hook](frontend/src/lib/hooks/use-polling.ts), [session-channel authorization](backend/app/Support/RealtimeSessionChannels.php). The client implements the relevant [Pusher WebSocket protocol](https://pusher.com/docs/channels/library_auth_reference/pusher-websockets-protocol/) used by Reverb without adding dependencies.

The main chat integration keeps polling as a reconciliation path:

**Files:** [messaging.tsx](frontend/src/components/messaging/messaging.tsx), [use-conversation-realtime.ts](frontend/src/lib/hooks/use-conversation-realtime.ts).

~~~ts
const realtime = useConversationRealtime(id);
const pollingError = usePolling(poll, realtime ? 60_000 : 10_000, true, CHAT_CHANGED);
~~~

Account email dispatch uses the same job for verification and reset delivery:

**Files:** [User.php](backend/app/Models/User.php), [SendAccountEmail.php](backend/app/Jobs/SendAccountEmail.php).

~~~php
public function sendEmailVerificationNotification(): void
{
    SendAccountEmail::dispatch((int) $this->id, $this->email);
}
~~~

### 6.13. Deployment Preparation and Measured Browser Behavior

Public reads now survive an unauthenticated session-probe event, fixing a production-browser race that left guest tutor profiles loading indefinitely. Private reads retain logout clearing. Homepage hero images declare responsive sizes, marketplace links prefetch, and the below-fold tutor section loads near the viewport while reusing the homepage candidate snapshot.

**Source:** [public/private loading scope](frontend/src/lib/hooks/use-async-data.ts), [deferred tutors](frontend/src/components/marketing/deferred-featured-tutors.tsx), [homepage snapshot provider](frontend/src/components/marketing/featured-listing-provider.tsx), [production journey runner](frontend/scripts/production-journey.mjs).

Local production-browser checks completed 18/18 ready pages after the fix; baseline tutor readiness was 0/6. Hero encoded image bytes fell from 50,826 to 44,256 (about 12.9%). Browser timing changes were mixed; no general page-speed improvement is claimed. Search p95 was measured at the same 20 offered RPS/30 seconds and 5,000-listing dataset: 51 ms before and 86 ms after, with 120/120 successful searches each; this phase does not establish a search-speed gain.

**Deployment target:** Vercel frontend + Docker Compose backend on a VPS. Environment templates, cookie/domain validation, independent coverage gates, a local CPU/RAM/disk/network dashboard, alert-delivery proof, 404/500 recovery and structured server logging are prepared. See the [deployment preparation and evidence report](docs/handover/TutorLink-deploy-readiness-2026-11-19.md) for configuration, reproduction steps and pending VPS checks.

## 7. Measurements and Limitations

**Production cleanup checks on November 19, 2026:** all 148 frontend tests passed with **72.24% line coverage**; lint, typecheck and production build passed. Runtime mock branches, sample testimonials and fabricated statistics were removed. The backend app/proxy production images built successfully; config, route and Blade caches compiled with test seeders and development files excluded. These checks do not replace a live VPS deployment test.

**Deployment-preparation checks:** frontend line coverage **71.03%** (baseline 40.60%); backend line coverage **90.39%** from 857 guarded tests / 7,585 assertions. The unchanged frontend source glob includes the new runtime files; branch/function coverage is reported separately. Local lint, typecheck, coverage gate and production build passed. Cloud CI and actual VPS deployment remain pending.

**Realtime/email/cache checks on November 19, 2026:** 73 targeted backend tests / 396 assertions and 89 frontend tests passed. Frontend typecheck, changed-file lint and production build passed. WebSocket tests simulate the transport; live browser/Reverb deployment, external SMTP and new cache/load benchmarks remain unverified. See the [phase 2 report](docs/handover/TutorLink-server-optimization-2026-11-19.md#8-realtime-email-queue-and-cache-implementation).

**Earlier workspace/homepage checks on November 19, 2026 (local working tree):** backend suites passed **852 tests / 7,568 assertions** against an isolated PostgreSQL test database; the frontend passed **85/85 tests**, typecheck, lint for changed files, and a production build. OpenAPI was regenerated from the source code and retained 139 operations. These checks cover request fan-out, pagination, aggregation, and data-loading lifecycle behavior; they are not new latency/RPS measurements or a production deployment acceptance.

The [November 14–15, 2026 performance report](docs/handover/TutorLink-performance-audit-2026-11-14.md) contains earlier local synthetic-load experiments. A mixed workload offered at **100 RPS for 600 seconds** completed 60,000 requests, with **99.99 successful RPS**, p95 **55 ms**, p99 **199 ms**, no errors, and no dropped requests. Reconciliation of 3,001 Sessions, including a smoke fixture, matched receipts and audit records.

The load generator and application shared Docker resources. Higher offered-load trials did not meet their targets. **The project has not passed a 1,000 successful requests/second for 60 minutes acceptance test or a production-capacity test.** The 100-RPS result also does not prove end-to-end performance across every API or the Next.js application.

See [docker/performance](docker/performance/README.md) for the benchmark tools and acceptance conditions.

## 8. Future Optimization Work

The following items are proposals and are not reported as completed work:

1. Measure queue delivery lag and alert on failed email jobs in staging.
2. Measure cache-hit rates and lock contention with real browser traffic.
3. Measure cache-generation lookup and invalidation costs. Preserve immediate effects for hidden listings, suspended tutors, and expired verification documents.
4. Combine the two outbox-count queries if profiling shows a meaningful cost; cache dashboard metrics only after defining acceptable data freshness and permission scope.
5. Measure query plans, resource usage, and PHP-FPM queues on representative staging with an independent load generator before tuning process sizing.
6. Extend realtime subscriptions to unopened conversations if measurements justify a user-level channel; the current subscription covers the open conversation.

Do not switch sessions to Redis by changing an environment variable alone: realtime session-channel checks currently depend on database sessions. Do not remove version checks, audit records, or idempotency safeguards for speed.

## 9. Documentation and References

- [TutorLink documentation index](docs/README.md)
- [TutorLink server optimization report](docs/handover/TutorLink-server-optimization-2026-11-19.md)
- [Previously recorded performance audit](docs/handover/TutorLink-performance-audit-2026-11-14.md)
- [Daily Vocabulary reference repository](https://github.com/AkioCkist/daily_vocabulary/tree/3c0ea06ccaebda687c662fa14245afcb0829a5c6): consulted for README organization and the presentation of problems, solutions, code locations, and evidence. Optimization mechanisms and results in this README are based on TutorLink's own source and measurements.
