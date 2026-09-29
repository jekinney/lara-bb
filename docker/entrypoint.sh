#!/bin/sh
# Starts the container in the role named by CONTAINER_ROLE:
#   web        HTTP server (FrankenPHP + Caddy, automatic HTTPS when SERVER_NAME is a domain)
#   worker     queue worker, starts once the web installer has finished
#   scheduler  scheduled tasks, starts once the web installer has finished
#   migrate    one-shot database migration, then exit (does nothing before the installer has run)
set -eu

cd /app

INSTALLED="${LARABB_INSTALL_LOCK:-/app/storage/app/installed}"

wait_for_install() {
  until [ -f "$INSTALLED" ]; do
    echo "laraBB is not installed yet. Open the site in a browser to run the installer."
    sleep 5
  done
}

case "${CONTAINER_ROLE:-web}" in
  web)
    exec frankenphp run --config /etc/frankenphp/Caddyfile
    ;;
  worker)
    wait_for_install
    exec php artisan queue:work --tries=3 --max-time=3600 --sleep=3
    ;;
  scheduler)
    wait_for_install
    exec php artisan schedule:work
    ;;
  migrate)
    if [ -f "$INSTALLED" ]; then
      exec php artisan migrate --force
    fi
    echo "laraBB is not installed yet, so there is nothing to migrate."
    ;;
  *)
    echo "Unknown CONTAINER_ROLE '${CONTAINER_ROLE}'. Use web, worker, scheduler or migrate." >&2
    exit 64
    ;;
esac
