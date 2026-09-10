# GrowGuide

A social marketplace and planning tool for gardeners: buy and sell seeds, plants and tools; post updates, questions and tips; and get help figuring out what to plant, when, and what not to plant next to it.

Built as a portfolio project targeting a PHP/e-commerce SWE role, covering:

| Requirement | Where |
|---|---|
| Modern PHP | Laravel 13 / PHP 8.3, backed enums, constructor property promotion, PSR-4, typed everything — [backend/app](backend/app) |
| HTML5, SCSS, JavaScript, ReactJS | [frontend/](frontend) — Vite + React + React Router, hand-written SCSS (no UI kit) |
| MySQL / large databases | [backend/database/migrations](backend/database/migrations), indexed foreign keys, seeded to ~3,000 products / 150 users / 900 posts via chunked bulk inserts — [DemoDataSeeder.php](backend/database/seeders/DemoDataSeeder.php) |
| Cloud computing / containerised apps | [docker-compose.yml](docker-compose.yml) — 7 services (nginx, php-fpm, mysql, redis, elasticsearch, queue worker, frontend) |
| Scalable distributed systems | Checkout is synchronous only for the consistency-critical part (stock lock + order write); everything else (notifications, search reindex, fulfilment) is event-driven and queued — see [Distributed design](#distributed-design) below |
| E-commerce background | Full marketplace: multi-seller listings, cart, checkout, stock management, per-seller order splitting |
| Software performance | See [Performance notes](#performance-notes) |
| Composer, Docker, Github, CI, PHPUnit, Elasticsearch | Composer throughout backend; Dockerfiles + compose; [.github/workflows/ci.yml](.github/workflows/ci.yml); 25 PHPUnit tests; real Elasticsearch client with graceful DB fallback |

## What it actually does

- **Marketplace** — search seeds/plants/tools/fertiliser by text, category, price and (for plant-linked listings) hardiness zone, via Elasticsearch with faceted counts
- **Garden planner** — create beds, add plants, and get warned when you add something that fights with what's already there (real companion-planting data: tomatoes next to potatoes gets flagged, tomatoes next to basil doesn't)
- **"What can I plant right now?"** — filtered by hardiness zone and the current month
- **Social feed** — post updates/questions/tips, follow other gardeners, like and comment
- **Checkout** — multi-seller cart, async order fulfilment pipeline

## Architecture

```
backend/    Laravel 13 API (PHP 8.3) — auth, marketplace, garden logic, orders, social graph
frontend/   React 19 + Vite SPA — talks to the API over fetch + Sanctum bearer tokens
docker/     nginx config for the containerised backend
docker-compose.yml   mysql, redis, elasticsearch, php-fpm, queue worker, nginx, frontend
```

The frontend and backend are fully decoupled — the API has no knowledge of the SPA beyond CORS, and could serve a mobile app or another client unchanged.

## Distributed design

Checkout (`CheckoutService::placeOrder`, [backend/app/Services/Orders/CheckoutService.php](backend/app/Services/Orders/CheckoutService.php)) splits cleanly into two phases:

1. **Synchronous, inside a DB transaction**: lock each product row (`lockForUpdate`), check stock, decrement it, write the order and its line items. This is the only part that must be strongly consistent — it's what stops two simultaneous buyers overselling the last unit.
2. **Asynchronous, dispatched only after the transaction commits** (avoiding the classic "queue picks up the job before the row exists" race): an `OrderPlaced` event fans out to three independent queued listeners (buyer confirmation, seller notification, search-index refresh), plus a separately queued `CompleteOrderJob` that simulates fulfilment and moves the order from `processing` to `completed`.

In `docker-compose.yml` the queue worker is its own container, so it scales independently of the web tier (`docker compose up -d --scale queue-worker=3`) — the point being that fulfilment throughput isn't coupled to request throughput.

## Performance notes

- **Search** goes through Elasticsearch rather than `LIKE` queries against MySQL once real traffic is involved — full-text relevance, faceted category/sun-requirement counts in a single query, and it doesn't degrade as the catalogue grows the way an unindexed `LIKE '%term%'` does. [ProductSearchService](backend/app/Services/Search/ProductSearchService.php) still has a plain-MySQL fallback path (used automatically if the ES cluster is unreachable), exercised directly by the test suite since CI doesn't run a live cluster.
- **Bulk seeding** uses chunked `DB::table()->insert()` rather than `Model::factory()->create()` in a loop — the difference is one query per 500 rows instead of one per row (visible seeding ~3,000 products + 900 posts + thousands of follows/likes in a few seconds).
- **N+1s** are avoided deliberately, not by accident — e.g. the post feed eager-loads `likes:id,post_id,user_id` alongside `withCount(['likes', 'comments'])` so "did I like this" can be checked in memory per post instead of one query per post.
- **Indexes** are on every foreign key plus the columns actually filtered on (`products.category`, `products.is_active`, `plants.(min_zone, max_zone)`, `orders.status`) — see the migrations.
- Elasticsearch profiling: for a real deployment, `xhprof` (or a hosted equivalent) would sit in front of `/api/products` and `/api/checkout` specifically, since those are the two endpoints doing the most work per request; not wired up here since there's no traffic to profile against.

## Running it

### Without Docker (fastest for local dev)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Open http://localhost:5173. The marketplace, garden planner and social feed all work immediately. Elasticsearch-backed search silently falls back to a MySQL/SQLite query if no cluster is running (you'll see a small banner on the marketplace page saying so) — everything still works, just without facet counts.

To get real search, either run Elasticsearch separately and point `ELASTICSEARCH_HOST` at it, or use Docker (below).

To process the async order pipeline (order confirmation, seller notification, moving orders from `processing` to `completed`), run a queue worker alongside the app:

```bash
php artisan queue:work
```

### With Docker

```bash
docker compose up --build
```

This brings up MySQL, Redis, Elasticsearch, the Laravel API (behind nginx on :8000), a dedicated queue worker, and the frontend dev server on :5173. Then, one-time setup inside the app container:

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan products:reindex
```

> Note: the Docker setup was written carefully but not run end-to-end in the environment this was built in (no Docker available there) — the non-Docker path above was fully exercised, including manual and automated checkout/garden-planner/social flows through a real browser. Worth a first run with `docker compose logs -f` open before relying on it.

### Tests

```bash
cd backend
php artisan test
```

25 tests covering auth, checkout (including the insufficient-stock and multi-seller-split cases), product search filters, garden-bed companion-conflict detection, and the social feed/follow graph.

## Not covered, and why

The job spec's tech list is "any of" for several items — Kubernetes, Google Cloud, xhprof weren't built here on top of Docker/CI/Elasticsearch/PHPUnit/Composer/Github, since adding untested Kubernetes manifests for a single-developer portfolio project would be cargo-culting infrastructure rather than demonstrating judgment. The natural next step for a real deployment would be GKE: the same containers in `docker-compose.yml` map fairly directly onto Deployments (app, queue-worker, frontend), a StatefulSet or managed Cloud SQL for MySQL, and a managed Elasticsearch (or Elastic Cloud) rather than running it in-cluster.

## Login

The seeder creates `test@example.com` / `password` plus 150 random users (all with password `password`) with realistic product listings, posts, and order history — enough to browse a populated app immediately rather than an empty one.
