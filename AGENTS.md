# laraBB

A phpBB3-style forum built on Laravel 13 (PHP 8.3+). Read this before changing code.

## Non-negotiable rules

1. **100% test coverage, always.** Every change ships with tests. `composer coverage` must report 100% and CI fails below that. Do not add coverage-ignore annotations without asking. Do not claim coverage without running it.
2. **Tests are Pest**, in `tests/Feature` (uses `RefreshDatabase`) and `tests/Unit`.
3. **Run `composer check`** (Pint, Larastan level 8, coverage) before saying work is done.
4. **Name**: the product is "laraBB" in UI copy, docs and namespaces.

## Commands

| Task | Command |
|---|---|
| Tests | `composer test` |
| Coverage with the 100% gate | `composer coverage` (loads Herd's Xdebug for the run if no driver is loaded) |
| Format check | `composer lint` (fix with `vendor/bin/pint`) |
| Static analysis | `composer analyse` |
| Everything | `composer check` |

## Architecture decisions

- **Frontend**: Blade + Livewire 3 + Tailwind. Server-rendered first, mobile first (unprefixed classes are phone layouts).
- **ACL**: own engine modelled on phpBB3, not spatie/permission. Values are YES, NO (default) and NEVER. Local (forum) beats global, user beats group, NEVER beats YES. Founders bypass it. Option types: `a_`, `m_`, `u_`, `f_`.
- **Themes**: uploadable packages of tokens, CSS and assets only, never executable code. Every theme has a light and a dark mode. Site default is set by the admin, and members may pick a theme and mode if the admin allows it.
- **Editors**: BBCode and Markdown behind an `EditorDriver` contract. Each post stores its source, its `format`, and a cached sanitized HTML render.
- **Services**: everything is configured by environment variables so DB, Redis, file storage and mail can each be a local container or a managed service.
- **Installer**: a web wizard guarded by a one-time setup token, locked and removed after use.
- **Health**: `/healthz` is liveness, `/readyz` checks the dependencies in `config/health.php` and returns 503 with no error details when one fails.

## Docker

One image, four roles chosen by `CONTAINER_ROLE`: `web`, `worker`, `scheduler`, `migrate`. See `compose.yaml` and `docker/entrypoint.sh`. The image runs as a non-root user.
