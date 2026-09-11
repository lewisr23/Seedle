# GrowGuide

A social marketplace and planning tool for gardeners: buy and sell seeds, plants and tools, post updates and questions, follow other gardeners, and get help figuring out what to plant, when, and what not to plant next to it.

## Features

- **Marketplace** — search seeds/plants/tools/fertiliser by text, category, price, sun requirement, hardiness zone and stock, with price sorting and faceted counts, backed by Elasticsearch
- **Plant library** — care details, hardiness ranges, planting calendar and companion relationships for every plant, filterable by type, sun, water, zone and planting month
- **Garden planner** — create beds, add plants, and get warned when you add something that fights with what's already there (real companion-planting data: tomatoes next to potatoes gets flagged, tomatoes next to basil doesn't)
- **"What can I plant right now?"** — recommendations filtered by hardiness zone and the current month
- **Guides** — 15 written guides across getting-started, soil, watering, pests, seasonal jobs, tools and composting, filterable by category and linked to specific plants where relevant
- **Social feed** — post updates, questions and tips, follow other gardeners, like and comment, pin your own posts to the top of your profile, with suggested gardeners to follow
- **Notifications** — database-backed notifications for sales, new followers, comments and order status, delivered through the queue and surfaced in a navbar bell
- **Reviews** — star ratings and written reviews, restricted to verified buyers (you can only review something you actually ordered, and never your own listing), feeding an average rating into product cards and a "top rated" sort
- **Seller dashboard** — manage your own listings (inline price/stock edits, pause/resume, delete) and see orders containing your products, with revenue totals
- **Checkout** — multi-seller cart with an async order fulfilment pipeline

## Stack

- **Backend**: Laravel 13 / PHP 8.3 — REST API, Sanctum auth, backed enums, service classes for search/checkout/garden logic
- **Frontend**: React + Vite, hand-written SCSS (no component library)
- **Data**: MySQL (SQLite for local dev/tests), Elasticsearch for product search, Redis + queue workers for async jobs
- **Infra**: Docker Compose (7 services), GitHub Actions CI (PHPUnit + Pint + frontend build)

## Architecture

```
backend/    Laravel 13 API — auth, marketplace, garden logic, orders, social graph
frontend/   React + Vite SPA — talks to the API over fetch + Sanctum bearer tokens
docker/     nginx config for the containerised backend
docker-compose.yml   mysql, redis, elasticsearch, php-fpm, queue worker, nginx, frontend
```

The frontend and backend are fully decoupled — the API doesn't know about the SPA beyond CORS, and could serve a mobile app or another client unchanged.

## Distributed design

Checkout (`CheckoutService::placeOrder`, [backend/app/Services/Orders/CheckoutService.php](backend/app/Services/Orders/CheckoutService.php)) splits into two phases:

1. **Synchronous, inside a DB transaction**: lock each product row (`lockForUpdate`), check stock, decrement it, write the order and its line items. This is the only part that has to be strongly consistent — it's what stops two simultaneous buyers overselling the last unit.
2. **Asynchronous, dispatched only after the transaction commits** (to avoid a queue worker picking up a job before the row it depends on actually exists): an `OrderPlaced` event fans out to three independent queued listeners (buyer confirmation, seller notification, search-index refresh), plus a separately queued `CompleteOrderJob` that simulates fulfilment and moves the order from `processing` to `completed`.

Those listeners send real database notifications rather than writing to a log, so the async pipeline is visible in the UI: place an order and the seller's notification bell updates once a worker picks the job up.

In `docker-compose.yml` the queue worker is its own container, so it scales independently of the web tier (`docker compose up -d --scale queue-worker=3`) — fulfilment throughput isn't coupled to request throughput.

## Performance notes

- **Search** goes through Elasticsearch rather than `LIKE` queries against MySQL — full-text relevance and faceted category/sun-requirement counts in a single query, without degrading as the catalogue grows the way an unindexed `LIKE '%term%'` does. [ProductSearchService](backend/app/Services/Search/ProductSearchService.php) has a plain-MySQL fallback path used automatically if the ES cluster is unreachable, exercised directly by the test suite since CI doesn't run a live cluster.
- **Bulk seeding** uses chunked `DB::table()->insert()` rather than looping `Model::factory()->create()` — one query per 500 rows instead of one per row, seeding ~3,000 products + 900 posts + thousands of follows/likes in a few seconds.
- **N+1s** are avoided deliberately — e.g. the post feed eager-loads `likes:id,post_id,user_id` alongside `withCount(['likes', 'comments'])` so "did I like this" can be checked in memory per post instead of one query per post.
- **Indexes** on every foreign key plus the columns actually filtered on (`products.category`, `products.is_active`, `plants.(min_zone, max_zone)`, `orders.status`) — see the migrations.
- For a real deployment, `xhprof` (or a hosted equivalent) would sit in front of `/api/products` and `/api/checkout` specifically, since those do the most work per request.

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

Open http://localhost:5173. The marketplace, garden planner and social feed all work immediately. Elasticsearch-backed search falls back to a MySQL/SQLite query if no cluster is running (a small banner on the marketplace page says so) — everything still works, just without facet counts.

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

> The Docker setup hasn't been run end-to-end yet — worth a first pass with `docker compose logs -f` open before relying on it. The non-Docker path above has been, including checkout, the garden planner's conflict warnings, and the social feed, through a real browser session.

### Tests

```bash
cd backend
php artisan test
```

67 tests covering auth, checkout (including insufficient-stock and multi-seller-split cases), product search filters and sorting, reviews and the verified-buyer rule, plant browsing and companion data, guides, garden beds and conflict detection, the social feed/follow graph, pinned posts, seller listings, and the notification pipeline.

## Not covered, and why

Kubernetes and a Google Cloud deployment aren't included — adding untested manifests for a project that's only ever run locally would be more cargo-culting than useful. The natural next step for a real deployment would be GKE: the same containers in `docker-compose.yml` map fairly directly onto Deployments (app, queue-worker, frontend), a managed Cloud SQL instance for MySQL, and a managed Elasticsearch rather than running it in-cluster.

## Login

The seeder creates `test@example.com` / `password` plus 150 random users (all with password `password`) with realistic product listings, posts, and order history, so the app is populated from the first run rather than empty.
