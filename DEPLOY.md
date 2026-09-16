# Deploying Seedle

A single VPS running the stack in `docker-compose.prod.yml`. Written for a
fresh Contabo box on Ubuntu 24.04, but nothing here is Contabo-specific.

Caddy handles TLS, so there is no certbot step. It requests a Let's Encrypt
certificate on first boot and renews it on its own.

## Before you start

You need three things in place:

1. A server with a public IPv4 address, and root or sudo access.
2. `seedle.uk` registered, with an **A record pointing at that IP**.
3. Ports 80 and 443 reachable from the internet.

**Do the DNS first and let it propagate.** Caddy asks Let's Encrypt to verify
the domain over HTTP on first boot. If the record does not resolve to this
server yet, issuance fails and Caddy retries with a growing backoff, so the
site will sit unreachable for a while even after DNS is fixed. Check it
resolves before bringing the stack up:

```bash
dig +short seedle.uk
```

## 1. Server setup

SSH in as root, then create a user to work as rather than staying root:

```bash
adduser --gecos "" lewis && usermod -aG sudo lewis
```

Copy your SSH key across, then log back in as that user for everything below.

Install Docker and the compose plugin:

```bash
curl -fsSL https://get.docker.com | sudo sh
```

```bash
sudo usermod -aG docker $USER && newgrp docker
```

Lock the firewall down to SSH and web:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 80,443/tcp && sudo ufw --force enable
```

## 2. Get the code

```bash
git clone <your repo url> seedle && cd seedle
```

## 3. Environment

Generate an app key. Run this **on your own machine** where PHP is available,
and copy the output:

```bash
cd backend && php artisan key:generate --show
```

On the server, create `.env` next to `docker-compose.prod.yml`:

```bash
cat > .env <<'ENV'
APP_DOMAIN=seedle.uk
APP_KEY=base64:PASTE_THE_KEY_HERE
DB_DATABASE=seedle
DB_USERNAME=seedle
DB_PASSWORD=CHANGE_ME_long_random
DB_ROOT_PASSWORD=CHANGE_ME_different_long_random
ENV
```

```bash
chmod 600 .env
```

The compose file refuses to start if `APP_KEY`, `APP_DOMAIN`, `DB_PASSWORD` or
`DB_ROOT_PASSWORD` are missing, rather than booting with defaults.

`ELASTICSEARCH_HOST` is deliberately unset. Search falls back to MySQL on its
own, so the stack runs without a cluster to pay for. Facet counts are the only
thing you lose.

## 4. Bring it up

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

First build takes a few minutes: it compiles the PHP extensions and builds the
SPA. Watch Caddy get its certificate:

```bash
docker compose -f docker-compose.prod.yml logs -f web
```

## 5. Database

Schema only:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Then the reference content, meaning the plant library and the written guides:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

**This does not create fake users or listings.** `DemoDataSeeder` throws if it
is ever run with `APP_ENV=production`, and `DatabaseSeeder` seeds only the
plant and guide reference data in that environment. The site starts empty of
people and fills up with real ones. `tests/Feature/SeedingSafetyTest.php`
enforces this, so it fails loudly if anyone removes the guard.

## 6. Check it

```bash
curl -sI https://seedle.uk | head -1
```

```bash
curl -s https://seedle.uk/api/products | head -c 200
```

Expect `HTTP/2 200` and a JSON payload with an empty `data` array, since
nobody has listed anything yet.

## Updating

```bash
git pull && docker compose -f docker-compose.prod.yml up -d --build
```

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

The SPA is baked into the web image at build time, so a redeploy rebuilds it.
Caddy's certificates live in a named volume and survive redeploys, which
matters because Let's Encrypt rate-limits repeat issuance.

## Backups

The database is the only thing you cannot rebuild. Uploaded images live in the
`app-storage` volume and are worth including.

```bash
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysqldump -uroot -p"$DB_ROOT_PASSWORD" seedle | gzip > ~/seedle-$(date +%F).sql.gz
```

Put that in a cron job and copy the output off the box. A backup that only
exists on the machine it is backing up is not a backup.

## If something breaks

Caddy cannot get a certificate: DNS is not pointing here yet, or port 80 is
blocked. Both must be true before issuance works.

502 from the API: the `app` container is down or still starting. Check
`docker compose -f docker-compose.prod.yml logs app`.

Out of memory during the build: the SPA build is the heaviest step. Add swap
if the box is small.

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
```
