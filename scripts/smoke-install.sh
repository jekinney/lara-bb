#!/usr/bin/env bash
# Runs the web installer against a fresh laraBB container and checks that it locks itself.
#
#   scripts/smoke-install.sh <container-name> <base-url>
#
# The container must have LARABB_INSTALLER_ALLOW_HTTP=true when the URL is plain HTTP.
set -euo pipefail

container="${1:?container name}"
base="${2:?base url, for example http://127.0.0.1:8080}"
jar="$(mktemp)"
trap 'rm -f "$jar"' EXIT

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/http.sh
source "$here/lib/http.sh"

[ "$(status "$base/")" = "302" ] || { echo "/ should redirect to the installer" >&2; exit 1; }

csrf /install/token >/dev/null   # opening the token page is what issues the token
token="$(docker exec "$container" cat storage/app/install-token | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')"
[ -n "$token" ] || { echo "no setup token was issued" >&2; exit 1; }

post /install/token 302 --data-urlencode "token=AAAA-AAAA-AAAA"   # wrong token is refused, wizard stays on the token step
[ "$(status "$base/install/requirements")" = "302" ] || { echo "wizard must not skip the token step" >&2; exit 1; }
post /install/token 302 --data-urlencode "token=$token"
post /install/requirements 302
post /install/database 302 -d driver=sqlite -d database=/app/storage/app/board.sqlite -d action=continue
post /install/services 302 -d cache_driver=database -d mail_mailer=log -d mail_from=noreply@example.com -d storage=local -d action=continue
post /install/site 302 -d board_name=laraBB -d "board_url=$base" -d timezone=UTC -d theme=default -d editor=markdown \
  -d founder_username=mira -d founder_email=mira@example.com \
  --data-urlencode "founder_password=a-long-passphrase-42" --data-urlencode "founder_password_confirmation=a-long-passphrase-42"

curl -fsS -b "$jar" -c "$jar" "$base/install/review" | grep -q "Ready to install"
CSRF_PAGE=/install/review post /install/run 200

for path in /install /install/token /install/run; do
  code="$(status "$base$path")"
  [ "$code" = "404" ] || { echo "$path should be 404 after installing, got $code" >&2; exit 1; }
done
[ "$(status "$base/")" = "200" ] || { echo "the board should serve after installing" >&2; exit 1; }
curl -fsS "$base/readyz" | grep -q '"status":"ok"'
docker exec "$container" test -f storage/app/installed
! docker exec "$container" grep -q "a-long-passphrase-42" storage/app/.env

# The installed board takes registrations and logins, and the founder can log in.
post /register 302 -d name=visitor1 -d email=visitor1@example.com -d timezone=UTC -d website_url= \
  --data-urlencode "password=another-long-passphrase" --data-urlencode "password_confirmation=another-long-passphrase"
[ "$(status "$base/members/visitor1")" = "200" ] || { echo "the new member's profile should exist" >&2; exit 1; }
[ "$(status "$base/account")" = "200" ] || { echo "a registered member should be logged in" >&2; exit 1; }
CSRF_PAGE=/account post /logout 302
[ "$(status "$base/account")" = "302" ] || { echo "logging out should end the session" >&2; exit 1; }
post /login 302 -d login=VISITOR1 --data-urlencode "password=another-long-passphrase"
[ "$(status "$base/account")" = "200" ] || { echo "the member should be back in after logging in" >&2; exit 1; }
CSRF_PAGE=/account post /logout 302
post /login 302 -d login=mira --data-urlencode "password=a-long-passphrase-42"
[ "$(status "$base/account")" = "200" ] || { echo "the founder should be able to log in" >&2; exit 1; }

echo "Installer smoke test passed."
