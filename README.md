# laraBB

A phpBB3-style forum for Laravel: full permission system, swappable themes, mobile-first design, and a Docker-first deployment.

> Status: early development. The scaffold, Docker image, CI, web installer and accounts (registration, login, groups, profiles) are in place. The permission system and the forums themselves are not built yet.

## Goals

- **Full ACL like phpBB3.** Yes, No and Never values, roles, groups, per-forum overrides.
- **Themes.** Upload theme packages, each with a light and a dark mode. The admin sets the site default and can let members choose.
- **Mobile first.** Every screen starts as a phone layout.
- **Two editors.** BBCode and Markdown, switchable per site, per member and per post.
- **Runs anywhere.** One Docker image, with a local or managed database, Redis, file storage and mail.
- **Secure installer.** Upload, open the site, follow the wizard. It locks itself when done.

## Install

Deploy the code or the Docker image, then open the site in a browser. Every page sends you to the installer until laraBB is installed.

1. **Setup token.** Proves you control the server. It is written to the application log (`docker compose logs web`) and to `storage/app/install-token`, and expires after 30 minutes. Wrong guesses are rate limited.
2. **Server check.** PHP version, extensions, writable paths, HTTPS.
3. **Database.** MySQL, MariaDB, or SQLite for development. Must be empty. There is a test button, and TLS with a CA certificate is supported for managed databases.
4. **Services.** Redis or the database for cache, sessions and queue. SMTP or log for mail. Local disk or S3-compatible storage such as DigitalOcean Spaces. Each has a test button.
5. **Board and founder account.**
6. **Review and install.** Creates the tables, the founder account and the settings, writes the configuration, and locks the installer. If anything fails it rolls back and leaves the database empty.

Afterwards `/install` returns 404. In production the installer views are also deleted. The lock is the file `storage/app/installed`.

Plain HTTP is refused in production. Behind a load balancer that ends TLS, set `LARABB_INSTALLER_ALLOW_HTTP=true`.

In Docker the finished configuration is saved to `storage/app/.env` on the storage volume (`LARABB_ENV_FILE`), so it survives the container being replaced. The worker and scheduler containers wait until the install has finished.

To install again, empty the database and delete `storage/app/installed` and `storage/app/.env`.

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
