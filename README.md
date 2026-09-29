# laraBB

A phpBB3-style forum for Laravel: full permission system, swappable themes, mobile-first design, and a Docker-first deployment.

> Status: early development. Phase 0 (scaffold, health checks, Docker, CI) is in place. Forum features are not built yet.

## Goals

- **Full ACL like phpBB3.** Yes, No and Never values, roles, groups, per-forum overrides.
- **Themes.** Upload theme packages, each with a light and a dark mode. The admin sets the site default and can let members choose.
- **Mobile first.** Every screen starts as a phone layout.
- **Two editors.** BBCode and Markdown, switchable per site, per member and per post.
- **Runs anywhere.** One Docker image, with a local or managed database, Redis, file storage and mail.
- **Secure installer.** Upload, open the site, follow the wizard. It locks itself when done.

## Develop

Requires PHP 8.3+, Composer, and Node 22+. On Windows, Laravel Herd works out of the box.

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer test
```

Quality gates, all of which CI runs:

```sh
composer lint       # Pint
composer analyse    # Larastan, level 8
composer coverage   # Pest, fails below 100% coverage
composer check      # all three
```

`composer coverage` needs a coverage driver. If none is loaded it uses the Xdebug build that Herd ships, for that run only.

## Run with Docker

```sh
cp .env.example .env        # set APP_KEY, DB_PASSWORD and the rest
docker compose --profile local-db --profile local-redis up -d
```

Leave off the profiles to use a managed database and Redis instead, and set `DB_HOST`, `REDIS_HOST` and friends in `.env`.

For a public domain on a droplet, set `SERVER_NAME=forum.example.com` in `.env`. The web container gets a TLS certificate automatically.

For local work: `docker compose -f compose.yaml -f compose.dev.yaml --profile local-db --profile local-redis up` serves the app on port 8080 and adds Mailpit on 8025.

### Roles

One image, chosen at start with `CONTAINER_ROLE`:

| Role | Does |
|---|---|
| `web` | HTTP server, with automatic HTTPS for a real domain |
| `worker` | Queue worker |
| `scheduler` | Scheduled tasks. Run exactly one. |
| `migrate` | Runs migrations once, then exits |

### Health

- `GET /healthz`: liveness.
- `GET /readyz`: readiness. Returns 503 if the database or cache is unreachable, without leaking details.

## License

MIT
