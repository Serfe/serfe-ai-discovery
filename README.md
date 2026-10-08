# Serfe AI Discovery

Expose published WordPress content as clean Markdown for AI agents and other Markdown consumers.

## What it does

Appending `.md` to an eligible canonical URL, or requesting that URL with `Accept: text/markdown`, returns its Markdown representation with `Content-Type: text/markdown; charset=utf-8`.

For example:

```text
https://example.com/privacy-policy/    → HTML
https://example.com/privacy-policy.md  → Markdown
```

Eligible HTML pages also announce their Markdown version through both mechanisms below:

- `<link rel="alternate" type="text/markdown">` in the document head.
- An HTTP `Link` response header with `rel="alternate"` and `type="text/markdown"`.

The homepage additionally advertises machine-readable service discovery resources using RFC 8288 Link headers:

- `rel="api-catalog"` points to `/.well-known/api-catalog`, an RFC 9727 Linkset catalog.
- `rel="service-desc"` points to the WordPress REST API index.

## Supported content

- Published WordPress posts.
- Published WordPress pages.
- Published WooCommerce products that meet the plugin's exposure rules.

Drafts, private content, archives, feeds, embeds, and unsupported post types are not exposed.

## Usage

After activation, request the `.md` version of any eligible public URL.

```text
https://wordpress.localhost/privacy-policy.md
https://wordpress.localhost/hello-world.md
```

Agents can request the canonical HTML URL directly through content negotiation while browsers continue receiving HTML by default:

```bash
curl -H 'Accept: text/markdown' https://wordpress.localhost/privacy-policy/
```

Negotiated Markdown responses include `Vary: Accept` and `X-Markdown-Tokens`, a deterministic estimate used when no model tokenizer is installed.

The Markdown response includes YAML front matter such as title, canonical URL, language when available, publication date, and excerpt when available.

## Agent readiness

Under **Tools → Agent Readiness**, administrators can run local, read-only capability checks. The panel checks the live public response for `robots.txt`, a sitemap, the RFC 9727 API catalog, Markdown negotiation, OAuth Protected Resource Metadata, and an Agent Skills index. It uses no third-party scanner and caches results for five minutes; use **Run checks again** to clear the cache.

MCP and Agentic Commerce are never inferred from a plugin name or a static card. An implementation must register its real service through these filters:

```php
add_filter( 'serfe_agent_readiness_mcp', function () {
    return array(
        'endpoint'   => 'https://example.com/mcp',
        'health_url' => 'https://example.com/mcp/health',
    );
} );

add_filter( 'serfe_agent_readiness_oauth_protected_resource', function () {
    return array(
        'metadata_url' => 'https://example.com/.well-known/oauth-protected-resource',
    );
} );

add_filter( 'serfe_agent_readiness_agentic_commerce', function () {
    return array(
        'endpoint'   => 'https://example.com/agentic-commerce',
        'health_url' => 'https://example.com/agentic-commerce/health',
    );
} );
```

A `health_url` is optional but required for a green check: it must be a read-only endpoint that returns a 2xx response. OAuth is shown as not applicable when the site has no declared protected agent resource; public content does not need OAuth.
The footer contains two complete local PNG assets: the blue Serfe logo for light surfaces (the default) and the white logo for dark surfaces. Each image retains its native aspect ratio; the footer merely selects one or the other. A theme integration can choose the dark surface with:

```php
add_filter( 'serfe_ai_discovery_footer_surface', function () {
    return 'dark';
} );
```



## Installation

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Upload the Serfe AI Discovery ZIP package.
3. Activate the plugin.
4. Visit an eligible URL ending in `.md` to verify the response.

## Safety and routing

The plugin never treats the following internal paths as Markdown content routes:

- `/wp-admin`
- `/wp-json`
- `/wp-content`
- `/wp-includes`
- `/xmlrpc.php`

Markdown is generated only for published, eligible singular content. Markdown responses include `X-Robots-Tag: noindex, follow`.

## Development

The test suite is self-contained and covers routing, discovery, Markdown generation, and WooCommerce product-plan grouping.

```bash
php tests/run.php
php tests/discovery.php
php tests/product-plan-grouping.php
```

Run the tests with the project's configured PHP container when a local PHP runtime is unavailable.

## Version

Current version: `1.0.10`.

### 1.0.10 — 2026-09-24

- Replaced the readiness footer's composed wordmark with complete local blue and white logo assets; no masking, cropping, or text overlay is used.

### 1.0.9 — 2026-09-24

- Added blue-on-light and white-on-dark Serfe logo variants for the Agent Readiness footer.

### 1.0.8 — 2026-09-24

- Fixed the Agent Readiness refresh redirect and added a commercial introduction with a Serfe footer.
- Added local validation for the RFC 9727 API catalog.

### 1.0.7 — 2026-09-24

- Added **Tools → Agent Readiness**, a local, read-only diagnostic panel for agent-facing capabilities.
- Added deterministic checks for robots, sitemaps, Markdown negotiation, MCP, OAuth metadata, Agent Skills, and Agentic Commerce.

### 1.0.6 — 2026-09-24

- Fixed the homepage alternate Markdown URL to use `/.md` instead of an invalid hostname suffix.

### 1.0.5 — 2026-09-24

- Added homepage `Link` headers for the API catalog and WordPress REST API description.
- Added an RFC 9727 `application/linkset+json` API catalog at `/.well-known/api-catalog`.

### 1.0.4 — 2026-09-23

- Added `Accept: text/markdown` negotiation for eligible public posts, pages, and products.
- Added `Vary: Accept` and an estimated `X-Markdown-Tokens` response header for negotiated responses.

### 1.0.3 — 2026-09-22

- Added conversion for nested and task lists, strikethrough, definition lists, keyboard input, link and image titles, and fenced code blocks with language hints.
- Extended table conversion with escaped separators and left, centre, and right alignment markers.

### 1.0.2 — 2026-09-22

- Added GitHub-flavored Markdown table conversion for Gutenberg table blocks.

### 1.0.1 — 2026-09-22

- Added Markdown representations and discovery links for published WordPress pages.

### 1.0.0 — 2026-09-21

- Initial Serfe release with Markdown routing, discovery, tests, and documentation.
