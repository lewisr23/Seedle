# Porting Seedle to Spring Boot and PostgreSQL

The Laravel API in `../backend` is being replaced endpoint for endpoint. Both
backends run side by side until this one reaches parity, so behaviour can be
compared rather than guessed at.

The React frontend is not being rewritten. It talks HTTP and JSON, so as long
as routes and response shapes match, the only thing that changes for it is the
API base URL.

## Stack

| | Choice | Why |
|---|---|---|
| Language | Java 17 | What is installed (Temurin 17.0.19) |
| Framework | Spring Boot 4.1.1 | Latest stable, verified to compile on 17 |
| Build | Maven 3.9.16 | Installed, no Gradle present |
| Database | PostgreSQL 18 | Running locally on 5432 |
| Migrations | Flyway | Schema is owned by SQL, Hibernate only validates |
| Auth | Spring Security, JWT bearer tokens | Frontend already stores a bearer token, so it needs no change |
| Search | Postgres full-text search | Replaces Elasticsearch, see below |
| Tests | JUnit 5, Testcontainers | Real Postgres in tests, not H2 |

### Elasticsearch is not being ported

The swap shelf search moves to Postgres FTS. `products` has a generated
`search_vector` with weighted title and description, a GIN index over it, and a
trigram index on `title` for fuzzy matching. At this data size (around 2,700
listings) that comfortably covers text search, facet counts and ranking, and it
removes a container, a reindex listener and a console command from the stack.

### One behaviour change, deliberate

Sanctum stored a row per issued token and logout deleted it. Access tokens here
are stateless JWTs, so logout writes the token's `jti` to `revoked_tokens`
until it would have expired anyway. Logout still means what it meant before.

## Local setup

Postgres needs a dev role and database. Run this once, as a user who can create
roles. It will prompt for your existing `postgres` password:

```
psql -U postgres -h 127.0.0.1 -c "CREATE ROLE seedle LOGIN PASSWORD 'seedle';" -c "CREATE DATABASE seedle OWNER seedle;"
```

Then the API starts with Flyway applying `V1__initial_schema.sql` on boot:

```
mvn spring-boot:run
```

It listens on **8081**, deliberately not 8000, so the Laravel API can stay up
alongside it. To point the frontend at this backend instead, set
`VITE_API_URL=http://localhost:8081/api` in `frontend/.env`.

## Conventions

| Laravel | Spring |
|---|---|
| Eloquent model | JPA entity plus a repository |
| Form Request | record with Bean Validation annotations |
| API Resource | response record, mapped explicitly |
| Policy | `@PreAuthorize` or a check in the service |
| Service class | `@Service`, same boundaries as the originals |
| Notification | row in `notifications`, written by a service |
| Queued job | `@Async` or an application event |
| Scheduled command | `@Scheduled` |
| Feature test | `@SpringBootTest` with Testcontainers Postgres |

Response shapes must match the Laravel originals exactly, including the
`data` envelope on resource collections and the `source`, `total`, `page`,
`per_page`, `facets`, `data` shape on `GET /products`. The React client depends
on all of it.

## Progress

74 endpoints. Schema and project skeleton are done, no endpoints yet.

- [x] Project skeleton, Maven build verified
- [x] Full schema as Flyway `V1` (21 domain tables)
- [ ] Security config, JWT issue and validate, CORS
- [ ] Auth: `register`, `login`, `logout`, `me` (4)
- [ ] Plants: index, recommendations, show (3)
- [ ] Garden beds and plot planner (8)
- [ ] Harvests (4)
- [ ] Sowing calendar (1)
- [ ] Products and swap shelf search, including FTS (8)
- [ ] Product images (2)
- [ ] Reviews (3)
- [ ] Users and follows (8)
- [ ] Posts, feed, comments, likes, pins (10)
- [ ] Conversations and messages (5)
- [ ] Saved items (6)
- [ ] Wants (4)
- [ ] Guides (2)
- [ ] Orders and checkout (4)
- [ ] Notifications (3)
- [ ] Garden reminders scheduled job
- [ ] Data migration from the SQLite/MySQL database
- [ ] Docker Compose service for the Java API

Suggested order: security first, then plants and the garden domain, because
those are self-contained and exercise the whole stack end to end. Products and
search last of the big ones, since FTS is the most novel part.

## Carried-over notes

The Laravel bug where `$request->user()` returns null on public routes, which
silently disables the "near me" radius filter, does not exist here. Spring
Security resolves the principal the same way on every route. The port must
still keep that filter working, so it needs a test that calls
`GET /products?radius_km=5` with a real bearer token and asserts the result set
actually narrows.
