# Beads Pattern Creator

Run Beads Pattern Creator locally with Nginx, PHP-FPM, PostgreSQL, and Redis using Docker Compose.

## 1. Set up the environment

Install Docker with Docker Compose V2 and Node.js 24 with npm on the host. Run all `docker compose` commands from the directory that contains `docker-compose.yaml`. That directory also needs the `docker/` configuration directory and the application in `src/`. If you have a standalone checkout of the application repository, place it in `src/` alongside the Docker files.

If the root `.env` file is missing, create it next to `docker-compose.yaml` using the settings below. Replace `replace-with-local-password` with a local database password of your choice.

```dotenv
APP_NAME=beads-pattern-creator
NGINX_PORT=8081
POSTGRES_PORT=5433
REDIS_PORT=6379
VITE_PORT=5173

DB_DATABASE=beads-pattern-creator
DB_USERNAME=beads
DB_PASSWORD=replace-with-local-password

DOCKER_SUBNET=172.35.0.0/16
PHP_IP=172.35.0.10
NGINX_IP=172.35.0.11
POSTGRES_IP=172.35.0.12
REDIS_IP=172.35.0.13
```

If `src/.env` is missing, copy `src/.env.example` to `src/.env`. Update the application, PostgreSQL, and Redis settings as shown below. Use the same `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` values in both files.

```dotenv
APP_NAME=BeadsPatternCreator
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8081

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=beads-pattern-creator
DB_USERNAME=beads
DB_PASSWORD=replace-with-local-password

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
```

If you change `NGINX_PORT`, use the same port in `APP_URL`. Inside Docker, Laravel connects to `postgres:5432` and `redis:6379`. With the settings above, PostgreSQL is available on the host at `localhost:5433`.

Live comments use Laravel Reverb. Copy the broadcasting and Reverb settings from `src/.env.example` into `src/.env` and set `REVERB_APP_SECRET` to a generated secret (for example, the output of `openssl rand -hex 32`). The Compose service runs `php artisan reverb:start` on the internal port 8080. Nginx forwards WebSocket upgrades under `/app/` to that service on the same public hostname as the site. Use the same proxy path and a supervised Reverb process when deploying outside Compose.

## 2. Start the containers and initialize the database

Run these commands from the project root.

```sh
docker compose config --quiet
docker compose up -d --build
docker compose ps
docker compose exec app composer install
docker compose exec app php artisan config:clear
```

For a fresh installation with an empty database, run the following commands.

```sh
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Generate the key only if `APP_KEY` is empty. The seeders add an administrator, languages, pages, and other initial data. See [UsersSeeder.php](database/seeders/UsersSeeder.php) for the local administrator credentials. For later schema updates, run `docker compose exec app php artisan migrate`.

## 3. Build the frontend and open the application

Run these commands on the host from the project root.

```sh
npm --prefix src ci
npm --prefix src run build
```

The build writes assets to `src/public/build/`, which the containers can access. For development with hot reload, run `npm --prefix src run dev` on the host.

- Site — [http://localhost:8081](http://localhost:8081)
- Admin panel — [http://localhost:8081/adminpannel](http://localhost:8081/adminpannel)

## Troubleshooting

- **`Connection refused` at `postgres:5433`.** Check that `src/.env` sets `DB_PORT=5432`, run `docker compose exec app php artisan config:clear`, and check the database status with `docker compose ps postgres`. Read the database logs with `docker compose logs postgres`.
- **PostgreSQL rejects the password after an `.env` change.** The PostgreSQL container uses `POSTGRES_*` when it first initializes the volume. For an existing database, use its original credentials or change the password in PostgreSQL and update both `.env` files.
- **`Vite manifest not found`.** Run `npm --prefix src run build` or start the Vite development server.
- **Git reports `dubious ownership`.** For a trusted local checkout, run `git config --global --add safe.directory "<absolute path to src>"`. Run Git commands from `src/`.

Run `docker compose down` to stop the containers. Data persists in named volumes. Adding `-v` removes those volumes and their data.
