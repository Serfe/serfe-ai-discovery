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

When API Catalog publication is enabled in the settings, the homepage additionally advertises machine-readable service discovery resources using RFC 8288 Link headers:

- `rel="api-catalog"` points to `/.well-known/api-catalog`, an RFC 9727 Linkset catalog.
- `rel="service-desc"` points to the WordPress REST API index.

## Supported content

- Published WordPress posts.
- Published WordPress pages.
- Published WooCommerce products that meet the plugin's exposure rules, including their public WooCommerce descriptions, price, and availability.
- Enabled WooCommerce shop, product category, and product tag archives, listing approved public products with their public price and availability.
- Selected public, publicly queryable Custom Post Types, enabled one by one.

Drafts, private content, WooCommerce cart, checkout and account pages, feeds, embeds, and unsupported post types are not exposed. Public Custom Post Type and WooCommerce catalog archives can be enabled separately.
Editorial Markdown uses saved static content. Items containing shortcodes, unknown blocks, or dynamically rendered blocks are not eligible; content filters are not run for Markdown.

## Usage

After activation, open the top-level **Serfe AI Discovery** menu, enable Markdown, and select the supported content types. Then open each eligible item and enable its **Serfe AI Discovery** checkbox. All publication settings and items are disabled by default.
The Overview tab summarizes local publication switches and previously requested validation results. The Markdown tab contains the current publication controls. The Discovery tab contains the read-only Agent Readiness checks; opening the settings screen does not run those checks.

The settings store individual content types and public archives separately. Existing settings from schema v1 retain their individual-content selections when migrated to schema v2; all archive selections start disabled. Enabled public archives list only published, publicly viewable items approved with the item checkbox. Shop, product category, and product tag archives have independent switches.

The **Robots.txt & llms.txt** tab edits and publishes those two files at the verified public site root. Existing or externally changed files require an explicit takeover after reviewing the current content. Each file keeps one previous version for restoration. If the public root is not writable, the panel shows validated text to copy for manual deployment. The plugin never deletes these files on uninstall.
Publishing or selecting **Validate public file** compares the live public response with the root file. For `robots.txt`, it also warns about missing or malformed sitemap declarations, a possible site-wide crawl block, and unreachable sitemap URLs. Validation is read-only and checks at most five declared sitemap URLs with safe HTTP requests; simply opening the panel does not request them.

The settings also let administrators exclude entire content types, exact site URL paths, and public taxonomy terms such as `category:123` or `product_cat:456`. Enter one path or taxonomy term per line. Exclusions take priority over content and item opt-ins.

Request the `.md` version of an enabled public URL:

```text
https://example.com/privacy-policy.md
https://example.com/hello-world.md
```

Agents can request the canonical HTML URL directly through content negotiation while browsers continue receiving HTML by default:

```bash
curl -H 'Accept: text/markdown' https://example.com/privacy-policy/
```

Negotiated Markdown responses include `Vary: Accept` and `X-Markdown-Tokens`, a deterministic estimate used when no model tokenizer is installed. Markdown responses use `Cache-Control: private, no-store` until cache behavior has been validated across supported hosts and CDNs.

The Markdown response includes YAML front matter such as title, canonical URL, language when available, publication date, and excerpt when available.

## Agent readiness

Under **Serfe AI Discovery → Discovery**, administrators can start local, read-only capability checks. Opening the page does not send HTTP requests. Selecting **Run checks again** checks the live public response for `robots.txt`, a sitemap, the RFC 9727 API catalog, Markdown negotiation, OAuth Protected Resource Metadata, and an Agent Skills index. It uses no third-party scanner and caches results for five minutes.

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
The Help & Support tab has a Serfe contact card describing the starter kit's limits, with a website link on the logo and a separate contact link. It shows the blue logo with WordPress's light admin color scheme and the white logo on a dark card with other admin color schemes. A theme integration can override the card surface with:

```php
add_filter( 'serfe_ai_discovery_footer_surface', function () {
    return 'dark';
} );
```



## Installation

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Upload the Serfe AI Discovery ZIP package.
3. Activate the plugin.
4. Enable Markdown for a content type and an individual item in the **Serfe AI Discovery** menu and the item editor.
5. Visit that item's URL ending in `.md` to verify the response.

## Safety and routing

The plugin never treats the following internal paths as Markdown content routes:

- `/wp-admin`
- `/wp-json`
- `/wp-content`
- `/wp-includes`
- `/xmlrpc.php`

Markdown is generated only for published, eligible singular content. Markdown responses include `X-Robots-Tag: noindex, follow`.

## Development

The test suite is self-contained and covers routing, discovery, Markdown generation, and WooCommerce product output.
See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution and local verification guidelines.

```bash
php tests/run.php
php tests/discovery.php
php tests/editorial-content.php
php tests/product-generator.php
php tests/readiness.php
php tests/settings.php
```

Run the tests with the project's configured PHP container when a local PHP runtime is unavailable.
Run `composer install`, then `vendor/bin/phpcs --standard=phpcs.xml.dist` for the current WordPress-Extra source rules and `composer audit --locked` for development dependencies. Composer packages are development tools; the plugin has no bundled runtime vendor directory.
Run `vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --memory-limit=1G` for the current WordPress and WooCommerce type checks.

For an HTTP smoke check, enable an eligible published item on a local WordPress installation and run `tests/http-smoke.sh https://example.com/eligible-item/`. The script checks HTML, explicit `.md`, negotiated Markdown, and HTML again after Markdown. Set `SMOKE_CURL_RESOLVE=host:port:ip` when the local hostname needs a direct address. This checks the live web server; test a production-like cache or CDN separately before release.

## Version

Current internal version: `1.0.10`. See [CHANGELOG.md](CHANGELOG.md) for changes.

## License

The plugin source is licensed under GPL version 2 or any later version. See [LICENSE](LICENSE). The Serfe name and logos remain subject to their trademark rights.
