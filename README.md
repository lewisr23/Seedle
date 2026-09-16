# Seedle

A gardening community and planning tool: pass on spare seeds, cuttings and tools to other growers, post updates and questions, follow other gardeners, and get help figuring out what to plant, when, and what not to plant next to it. Nothing is bought or sold, everything is swapped or given away.

## Features

- **Swap shelf**: search what people are offering by text, category, sun requirement, hardiness zone and availability, with faceted counts and a top-rated sort, backed by Elasticsearch
- **Plant library**: care details, hardiness ranges, planting calendar and companion relationships for every plant, filterable by type, sun, water, zone and planting month
- **Garden planner**: create beds, add plants, and get warned when you add something that fights with what's already there (real companion-planting data: tomatoes next to potatoes gets flagged, tomatoes next to basil doesn't)
- **"What can I plant right now?"**: recommendations filtered by hardiness zone and the current month
- **Guides**: 15 written guides across getting-started, soil, watering, pests, seasonal jobs, tools and composting, filterable by category and linked to specific plants where relevant
- **Social feed**: post updates, questions and tips, follow other gardeners, like and comment, pin your own posts to the top of your profile, with suggested gardeners to follow
- **Listing photos**: growers upload images when offering something; they show on cards and the product page, with the category emoji as a fallback for listings without one
- **Saved items**: heart any listing or plant to keep it on a saved page; saving something that has run out gets you a notification when the grower puts more up (only on the genuine 0 → in-stock transition, and not while the listing is paused)
- **Messaging**: you can ask a grower about anything they are offering; one thread per person and listing so asking twice continues the conversation rather than forking it, with unread counts in the navbar, the other party notified through the queue, and an open thread polling so a reply appears without a refresh
- **Notifications**: database-backed notifications for sales, new followers, comments and order status, delivered through the queue and surfaced in a navbar bell
- **Reviews**: star ratings and written notes on how a swap went, restricted to people who actually claimed the item, and never your own listing, feeding an average into the cards and a "top rated" sort
- **Your patch**: manage what you have put up (inline availability edits, pause/resume, delete) and see who has claimed what
- **Claiming**: a swap list spanning several growers, with an async fulfilment pipeline behind it

## Stack

- **Backend**: Laravel 13 / PHP 8.4. REST API, Sanctum auth, backed enums, service classes for search/checkout/garden logic
- **Frontend**: React + Vite, hand-written SCSS (no component library), Vitest + React Testing Library
- **Data**: MySQL (SQLite for local dev/tests), Elasticsearch for product search, Redis + queue workers for async jobs
- **Infra**: Docker Compose (7 services), GitHub Actions CI (PHPUnit + Pint, then frontend lint, tests and build)

## Architecture

```
backend/    Laravel 13 API: auth, swap shelf, garden logic, claims, social graph
frontend/   React + Vite SPA: talks to the API over fetch + Sanctum bearer tokens
docker/     nginx config for the containerised backend
docker-compose.yml   mysql, redis, elasticsearch, php-fpm, queue worker, nginx, frontend
```

The frontend and backend are fully decoupled: the API doesn't know about the SPA beyond CORS, and could serve a mobile app or another client unchanged.

## Distributed design

Checkout (`CheckoutService::placeOrder`, [backend/app/Services/Orders/CheckoutService.php](backend/app/Services/Orders/CheckoutService.php)) splits into two phases:

1. **Synchronous, inside a DB transaction**: lock each product row (`lockForUpdate`), check stock, decrement it, write the order and its line items. This is the only part that has to be strongly consistent: it's what stops two people simultaneously claiming the last packet. No money changes hands, but the race is exactly the one a shop has.
2. **Asynchronous, dispatched only after the transaction commits** (to avoid a queue worker picking up a job before the row it depends on actually exists): an `OrderPlaced` event fans out to three independent queued listeners (claimant confirmation, grower notification, search-index refresh), plus a separately queued `CompleteOrderJob` that simulates fulfilment and moves the order from `processing` to `completed`.

Those listeners send real database notifications rather than writing to a log, so the async pipeline is visible in the UI: claim something and the grower's notification bell updates once a worker picks the job up.

In `docker-compose.yml` the queue worker is its own container, so it scales independently of the web tier (`docker compose up -d --scale queue-worker=3`): fulfilment throughput isn't coupled to request throughput.

## Media storage

Uploaded listing photos go to the `public` disk and are served back through a
Laravel route rather than a `public/storage` symlink. `storage:link` is awkward
across the Docker bind mount on Windows, and a route behaves identically in
every environment. The route is deliberately unauthenticated, because an `<img>`
tag cannot send a bearer token, and it refuses any path outside the upload
directory. Only the bare storage path is persisted, so the hostname isn't baked
into the data: the full URL is built per request.

## Performance notes

- **Search** goes through Elasticsearch rather than `LIKE` queries against MySQL: full-text relevance and faceted category/sun-requirement counts in a single query, without degrading as the catalogue grows the way an unindexed `LIKE '%term%'` does. [ProductSearchService](backend/app/Services/Search/ProductSearchService.php) has a plain-MySQL fallback path used automatically if the ES cluster is unreachable, exercised directly by the test suite since CI doesn't run a live cluster.
- **Bulk seeding** uses chunked `DB::table()->insert()` rather than looping `Model::factory()->create()`: one query per 500 rows instead of one per row, seeding ~3,000 products + 900 posts + thousands of follows/likes in a few seconds.
- **N+1s** are avoided deliberately: e.g. the post feed eager-loads `likes:id,post_id,user_id` alongside `withCount(['likes', 'comments'])` so "did I like this" can be checked in memory per post instead of one query per post.
- **Indexes** on every foreign key plus the columns actually filtered on (`products.category`, `products.is_active`, `plants.(min_zone, max_zone)`, `orders.status`). See the migrations.
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

Open http://localhost:5173. The swap shelf, garden planner and social feed all work immediately. Elasticsearch-backed search falls back to a MySQL/SQLite query if no cluster is running (a small banner on the swap shelf says so). Everything still works, just without facet counts.

To get real search, either run Elasticsearch separately and point `ELASTICSEARCH_HOST` at it, or use Docker (below).

To process the async order pipeline (order confirmation, seller notification, moving orders from `processing` to `completed`), run a queue worker alongside the app:

```bash
php artisan queue:work
```

### With Docker

```bash
docker compose up --build
```

This brings up MySQL (published on :3307, since a locally installed MySQL usually already owns :3306), Redis, Elasticsearch, the Laravel API (behind nginx on :8000), a dedicated queue worker, and the frontend dev server on :5173. Then, one-time setup inside the app container:

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan products:reindex
```

`vendor/` and `node_modules/` live in anonymous volumes so the bind-mounted source doesn't hide them, which means a plain `--build` won't pick up dependency changes. After editing `composer.lock` or `package-lock.json`, renew those volumes (this leaves the MySQL and Elasticsearch data alone, unlike `down -v`):

```bash
docker compose up --build --force-recreate --renew-anon-volumes
```

> **Status**: run end to end on Docker Desktop (Windows/WSL2): `docker compose up --build`, `migrate --seed`, `products:reindex`, then a real order placed through the API. Verified: PHP 8.4 with phpredis, cache/queue/session all on Redis, MySQL holding the seeded 3,000 products, Elasticsearch serving search with live facet counts (`source: elasticsearch`, not the fallback), and the async pipeline running every listener: `ReindexOrderedProducts`, `SendOrderConfirmation`, `NewSale`, `OrderStatusChanged`, with `CompleteOrderJob` moving the order from `processing` to `completed` and the notification landing for both buyer and seller.
>
> Thirteen defects were fixed to get there. Found by inspection: a missing `frontend/Dockerfile`; no phpredis in the image though compose puts queue, cache and session on Redis; the backend bind mount hiding the image's `vendor/`; a working copy's dev-only package manifest carried into the image; no `.env`/`APP_KEY` bootstrap on a fresh clone; and a PHP 8.3 base image that can't install a `composer.lock` pinning Symfony 8 (`php >=8.4.1`). The same mismatch had been failing CI. Found only by running it: php-fpm can't reopen `/proc/self/fd/2` if the entrypoint drops privileges before exec (its master must stay root); `db:seed` calls `fake()`, which lives in the dev-only Faker, so the image needs an `INSTALL_DEV` build arg; the v9 Elasticsearch PHP client sends `compatible-with=9` headers that an 8.x server rejects; MySQL's published port collides with a local install; and nginx resolves `fastcgi_pass app:9000` once at startup, so a recreated app container turns into a 502 until it re-resolves through Docker's DNS. Two more surfaced in the test suite once a cluster was actually running: `phpunit.xml` never pinned `ELASTICSEARCH_HOST`, so the search tests were only taking their MySQL fallback because nothing happened to be listening on 9200. Start the stack and five of them fail; and running the suite inside the app container silently destroys the development database, which the Tests section explains how to avoid.
>
> The non-Docker path above has also been run end to end, including checkout, the garden planner's conflict warnings, and the social feed, through a real browser session.

### Tests

#### Backend

```bash
cd backend
php artisan test
```

95 tests covering auth, claiming (including insufficient-stock and multi-seller-split cases), search filters and sorting, reviews and the verified-claimant rule, plant browsing and companion data, guides, garden beds and conflict detection, the social feed/follow graph, pinned posts, seller listings, the notification pipeline, messaging (thread reuse, participant-only access, read receipts and unread counts), saved items (idempotent saving, products and plants kept apart in one polymorphic table, and the restock notification's edge cases), and listing images (type and size validation, generated filenames, unauthenticated serving, and refusing to serve anything outside the upload directory).

To run them inside the container instead, pass the test environment as real environment variables:

```bash
docker compose run --rm -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e QUEUE_CONNECTION=sync -e CACHE_STORE=array -e SESSION_DRIVER=array -e ELASTICSEARCH_HOST=http://127.0.0.1:1 app php artisan test
```

The `-e` flags are not optional. `phpunit.xml` can't override them: PHPUnit writes its `<env>` values to `putenv()` and `$_ENV` but never `$_SERVER`, and Laravel's config reads `$_SERVER` first, so docker-compose's `DB_CONNECTION=mysql` wins, `force="true"` included. `docker compose exec app php artisan test` therefore runs `RefreshDatabase` against the live development database and drops every seeded row.

#### Frontend

```bash
cd frontend
npm test
```

95 tests across the pieces that hold real logic rather than markup: the API client (bearer token, query-param building, Laravel 422 field errors, empty and non-JSON bodies), the swap list context (quantity merging, localStorage persistence and recovery from corrupt storage), the auth context (session restore, discarding a token the server rejects, clearing local state even when `/logout` fails), the claim flow end to end against a mocked API, `timeAgo`, the `Stars` component in both display and input modes, the "message seller" composer (own-listing and signed-out cases included), the conversation thread including its polling, driven with fake timers so the suite doesn't wait out a real interval, the saved-items context with its optimistic heart toggle and rollback on failure, and `ProductCard` (sold-out handling, ratings appearing only once reviewed, its save toggle, and photo-versus-emoji fallback).

Writing them turned up a real bug: clearing the cart's quantity field deleted the line, because `Number('')` is `0` and `updateQuantity` treats `0` as "remove", so selecting the number and pressing delete, the ordinary way to retype it, silently emptied your basket. The field now keeps a draft string while you edit. Two tests cover it.

## Not covered, and why

Kubernetes and a Google Cloud deployment aren't included. Adding untested manifests for a project that's only ever run locally would be more cargo-culting than useful. The natural next step for a real deployment would be GKE: the same containers in `docker-compose.yml` map fairly directly onto Deployments (app, queue-worker, frontend), a managed Cloud SQL instance for MySQL, and a managed Elasticsearch rather than running it in-cluster.

## Login

The seeder creates `test@example.com` / `password` (already holding saved listings and plants, plus a few conversations with unread replies waiting, so the saved page and inbox aren't empty on a first run), plus 150 random users (all with password `password`) with realistic product listings, posts, and order history, so the app is populated from the first run rather than empty.
