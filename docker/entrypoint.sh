#!/bin/sh
# Starts the container in the role named by CONTAINER_ROLE:
#   web        HTTP server (FrankenPHP + Caddy, automatic HTTPS when SERVER_NAME is a domain)
#   worker     queue worker
#   scheduler  scheduled tasks, run every minute
#   migrate    one-shot database migration, then exit
set -eu

cd /app

case "${CONTAINER_ROLE:-web}" in
  web)
    exec frankenphp run --config /etc/frankenphp/Caddyfile
    ;;
  worker)
    exec php artisan queue:work --tries=3 --max-time=3600 --sleep=3
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  migrate)
    exec php artisan migrate --force
    ;;
  *)
    echo "Unknown CONTAINER_ROLE '${CONTAINER_ROLE}'. Use web, worker, scheduler or migrate." >&2
    exit 64
    ;;
esac
