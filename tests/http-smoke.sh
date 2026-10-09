#!/usr/bin/env bash
# Run against a published, opted-in item on a disposable or local WordPress site.
set -euo pipefail

if [[ $# -ne 1 ]]; then
	echo "Usage: SMOKE_CURL_RESOLVE=host:port:ip $0 https://example.com/eligible-item/" >&2
	exit 2
fi

canonical_url="${1%/}/"
markdown_url="${canonical_url%/}.md"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

curl_args=( --silent --show-error --max-time 20 )
if [[ -n "${SMOKE_CURL_RESOLVE:-}" ]]; then
	curl_args+=( --resolve "$SMOKE_CURL_RESOLVE" )
fi

fetch() {
	local name="$1" url="$2" accept="${3:-}"
	local args=( "${curl_args[@]}" --dump-header "$tmp_dir/$name.headers" --output "$tmp_dir/$name.body" )
	if [[ -n "$accept" ]]; then
		args+=( --header "$accept" )
	fi
	curl "${args[@]}" "$url"
	tr -d '\r' < "$tmp_dir/$name.headers" > "$tmp_dir/$name.clean-headers"
	grep -Eq '^HTTP/[0-9.]+ 200 ' "$tmp_dir/$name.clean-headers" || { echo "$name did not return HTTP 200" >&2; exit 1; }
}

require_header() {
	local name="$1" pattern="$2"
	grep -Eiq "$pattern" "$tmp_dir/$name.clean-headers" || { echo "$name missing header: $pattern" >&2; exit 1; }
}

fetch html "$canonical_url"
require_header html '^Content-Type: text/html;'
require_header html '^Link: <[^>]+\.md>; rel="alternate"; type="text/markdown"'
grep -Eiq '<link rel="alternate" type="text/markdown"' "$tmp_dir/html.body" || { echo 'HTML alternate element missing' >&2; exit 1; }

fetch explicit "$markdown_url"
require_header explicit '^Content-Type: text/markdown; charset=utf-8'
require_header explicit '^X-Content-Type-Options: nosniff'
require_header explicit '^X-Robots-Tag: noindex'
require_header explicit '^Cache-Control: private, no-store'
require_header explicit '^Link: <[^>]+>; rel="canonical"'
grep -q '^---$' "$tmp_dir/explicit.body" || { echo 'Explicit Markdown body missing front matter' >&2; exit 1; }

fetch negotiated "$canonical_url" 'Accept: text/markdown'
require_header negotiated '^Content-Type: text/markdown; charset=utf-8'
require_header negotiated '^Vary: Accept'
require_header negotiated '^Cache-Control: private, no-store'
cmp -s "$tmp_dir/explicit.body" "$tmp_dir/negotiated.body" || { echo 'Markdown response bodies differ' >&2; exit 1; }

fetch html_after "$canonical_url"
require_header html_after '^Content-Type: text/html;'
grep -Eiq '<!doctype html' "$tmp_dir/html_after.body" || { echo 'HTML was not served after Markdown requests' >&2; exit 1; }

echo 'HTTP smoke checks passed: HTML, explicit Markdown, negotiated Markdown, and HTML after Markdown.'
