# Serfe WordPress AI Discovery

Expose published WordPress content as clean Markdown for AI agents and other Markdown consumers.

## Plugin Objective

This plugin is designed as an essential complement to your existing SEO strategy. While standard SEO plugins optimize your site for traditional search engines (Google, Bing), **Serfe WordPress AI Discovery** ensures your content is perfectly structured and discoverable by **AI Agents, LLMs, and semantic search engines**. 

By natively serving `.md` versions of your public posts, pages, and products, along with providing technical endpoints like `robots.txt`, `llms.txt`, and API Catalogs, this plugin bridges the gap between WordPress and the Agentic Web.

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

When public `llms.txt` and XML sitemap responses are valid, the plugin automatically adds RFC 8288 Link headers to public site responses:

- `rel="describedby"` points to `llms.txt` as `text/plain`.
- `rel="related"` points to the detected XML sitemap as `application/xml`.

The Discovery Checks tab reports the two resources and the Link-header state. Create `llms.txt` or an XML sitemap when either resource is unavailable.

## Supported content

- Published WordPress posts.
- Published WordPress pages.
- The WordPress Posts page archive, categories, tags, and public post taxonomies, including pagination.
- Published WooCommerce products that meet the plugin's exposure rules, including their public WooCommerce descriptions, price, and availability.
- WooCommerce shop, product category, and product tag archives, listing public products with their public price and availability.
- Public, publicly queryable Custom Post Types and their archives.

Drafts, private content, WooCommerce cart, checkout and account pages, feeds, embeds, and unsupported post types are not exposed.
Editorial Markdown uses saved static content. Items containing shortcodes, unknown blocks, or dynamically rendered blocks are not eligible; content filters are not run for Markdown.

## Usage

After activation, open the top-level **WP AI Discovery** menu and use the Markdown toggle to enable or disable Markdown support. Its state is saved immediately. Use Advanced Settings below it to exclude content types or exact URL paths, then select **Save Advanced Settings**. Markdown is disabled by default.
The Overview tab summarizes local publication switches and previously requested validation results. The Markdown tab contains the publication control and exclusions. The API Catalog tab controls catalog publication. The Discovery tab contains the read-only Agent Readiness checks; opening the settings screen does not run those checks.

The **Robots.txt & llms.txt** tab edits and publishes those two files at the verified public site root. A missing physical `robots.txt` does not mean the public response is empty: WordPress, Yoast SEO, Rank Math, or another plugin may generate it virtually. Select **Validate public file** to inspect that response. Publishing a physical file over a virtual response requires explicit takeover and a matching checksum; the previous public text is saved as the one backup. A physical file can supersede the virtual response, so coordinate edits with the plugin that currently owns the policy. Existing or externally changed physical files also require takeover. If the public root is not writable, the panel shows validated text to copy for manual deployment. The plugin never deletes these files on uninstall.
Publishing or selecting **Validate public file** compares the live public response with the root file. For `robots.txt`, it also warns about missing or malformed sitemap declarations, a possible site-wide crawl block, and unreachable sitemap URLs. Validation is read-only and checks at most five declared sitemap URLs with safe HTTP requests; simply opening the panel does not request them.
When no physical file exists, validation shows the live virtual response separately from any draft text. The Overview tab reports **No physical file** until one is published; it does not assert that the public URL is empty.
The **Discovery Checks** tab offers an optional link to Is It Agent-Ready below the validation table. It sends no request until an administrator clicks it. For a public site, the link contains only the hostname from the site's public home URL; local and private environments open the scanner homepage without a hostname.

The settings let administrators exclude entire content types and exact site URL paths. Enter one path per line. Exclusions take priority over Markdown publication.

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

## Agents Discovery

Under **WP AI Discovery → Discovery**, administrators can start local, read-only capability checks. Opening the page does not send HTTP requests. Selecting **Run checks again** checks the live public response for `robots.txt`, `llms.txt`, an XML sitemap, the automatic agent-discovery Link headers, the RFC 9727 API catalog, Markdown negotiation, OAuth Protected Resource Metadata, and an Agent Skills index. It uses no third-party scanner and caches results for five minutes.

Developers can extend the plugin's readiness signals by integrating their own endpoints (such as Model Context Protocol (MCP) or Agentic Commerce functionality). MCP and Agentic Commerce are never inferred from a plugin name or a static card. An implementation must register its real service through these filters:

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

## Installation

1. In WordPress, go to **Plugins → Add New → Upload Plugin**.
2. Upload the Serfe WordPress AI Discovery ZIP package.
3. Activate the plugin.
4. Enable Markdown in **WP AI Discovery → Markdown**.
5. Visit a public URL ending in `.md` to verify the response.

## Safety and routing

The plugin never treats the following internal paths as Markdown content routes:

- `/wp-admin`
- `/wp-json`
- `/wp-content`
- `/wp-includes`
- `/xmlrpc.php`

Markdown is generated only for eligible public content and archives. Markdown responses include `X-Robots-Tag: noindex, follow`.

## Collaboration

We welcome contributions from the developer community! If you find a bug, have a feature request, or want to submit a pull request, please get involved.
See [CONTRIBUTING.md](CONTRIBUTING.md) for contribution rules and local verification guidelines.
See [SUPPORT.md](SUPPORT.md) for community support channels. Report vulnerabilities privately as described in [SECURITY.md](SECURITY.md).

## Development

The test suite is self-contained and covers routing, discovery, Markdown generation, and WooCommerce product output.

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

## About Serfe

Serfe is a software development company that specializes in building high-quality, scalable web applications and integrations. We are committed to open-source and helping organizations embrace modern web standards, including the emerging Agentic Web. 
Learn more about us at [serfe.com](https://www.serfe.com).

## License

The plugin source is licensed under GPL version 2 or any later version. See [LICENSE](LICENSE). The Serfe name and logos remain subject to their trademark rights.
