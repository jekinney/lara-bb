#!/usr/bin/env bash
# Installs a local board without clicking through the wizard. Uses SQLite, the log mailer and
# local file storage, so it needs nothing but the running container.
#
#   scripts/dev-install.sh <container> <base-url> <username> <email> <password>
#
# To try the installer by hand again afterwards:
#   docker exec <container> php artisan larabb:uninstall --force
set -euo pipefail

container="${1:?container name}"
base="${2:?base url, for example http://localhost:8080}"
username="${3:?founder username}"
email="${4:?founder email}"
password="${5:?founder password, 12 characters or more}"
jar="$(mktemp)"
trap 'rm -f "$jar"' EXIT

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/http.sh
source "$here/lib/http.sh"

csrf /install/token >/dev/null   # opening the token page is what issues the token
token="$(docker exec "$container" cat storage/app/install-token | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')"
[ -n "$token" ] || { echo "no setup token was issued. Is the board already installed?" >&2; exit 1; }

post /install/token 302 --data-urlencode "token=$token"
post /install/requirements 302
post /install/database 302 -d driver=sqlite -d database=/app/storage/app/board.sqlite -d action=continue
post /install/services 302 -d cache_driver=database -d mail_mailer=log -d mail_from=noreply@example.com -d storage=local -d action=continue
post /install/site 302 -d board_name=laraBB -d "board_url=$base" -d timezone=UTC -d theme=default -d editor=markdown \
  --data-urlencode "founder_username=$username" --data-urlencode "founder_email=$email" \
  --data-urlencode "founder_password=$password" --data-urlencode "founder_password_confirmation=$password"
CSRF_PAGE=/install/review post /install/run 200

echo "Installed. Log in at $base/login as $username."
