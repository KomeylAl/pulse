# Pulse Docker notes
#
# Ports (intentionally avoid 8080 — Dozzle on the server):
#   nginx (Laravel API HTTP)  → 8000  → FastCGI → backend:9000
#   frontend (Next.js)        → 7000
#   php-fpm                   → 9000 (internal only, never publish)
#
# Reverse-proxy on the server should forward:
#   https://api.example.com  →  pulse-nginx:8000
#   https://pulse.example.com → pulse-frontend:7000
#
# Shared infra (Postgres/Redis) must be on the external Docker network
# named `infrastructure`, reachable as hostnames `postgres` and `redis`
# (or set DB_URL / REDIS_HOST accordingly).
#
# Before first deploy:
#   1. cp .env.example .env  && fill APP_KEY, DB_URL, NEXT_PUBLIC_API_URL
#   2. php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
#      or generate APP_KEY on any machine with `php artisan key:generate --show`
#   3. docker network create infrastructure   # if missing
#   4. docker compose build && docker compose up -d
