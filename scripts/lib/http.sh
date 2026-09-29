# Helpers shared by the scripts that drive the web installer. Source this file after setting
# $base (the site URL) and $jar (a cookie jar file).

# Git Bash on Windows rewrites "database=/app/..." into a Windows path. Harmless elsewhere.
export MSYS2_ARG_CONV_EXCL="database="

csrf() {
  local html; html="$(curl -fsS -b "$jar" -c "$jar" "$base$1")"
  [[ $html =~ name=\"_token\"\ value=\"([^\"]+)\" ]] || { echo "no CSRF token on $1" >&2; return 1; }
  printf '%s' "${BASH_REMATCH[1]}"
}

status() { curl -s -o /dev/null -w '%{http_code}' -b "$jar" -c "$jar" "$@"; }

# post <path> <expected-status> [curl --data-urlencode args...]
# Set CSRF_PAGE to fetch the form token from another page than the one being posted to.
post() {
  local path="$1" expected="$2"; shift 2
  local out code where
  out="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' -b "$jar" -c "$jar" -X POST "$base$path" --data-urlencode "_token=$(csrf "${CSRF_PAGE:-$path}")" "$@")"
  code="${out%% *}"; where="${out#* }"
  [ "$code" = "$expected" ] || { echo "POST $path returned $code, expected $expected" >&2; exit 1; }
  echo "ok  POST $path -> $code $where"
}
