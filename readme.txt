=== Serfe WordPress AI Discovery ===
Tags: markdown, llms-txt, robots-txt, api-discovery, ai
Requires at least: 7.1
Tested up to: 7.1
Stable tag: 1.0.10
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish public WordPress content as Markdown and manage selected discovery signals for automated clients.

== Description ==

Serfe WordPress AI Discovery is an opt-in starter kit for public content. When Markdown is enabled, it returns Markdown for supported public posts, pages, archives, taxonomies, WooCommerce products and catalog archives, and public Custom Post Types. Browsers continue to receive canonical HTML.

An administrator can also review and publish root robots.txt and llms.txt files, enable a catalog of existing public APIs, and run bounded local checks for discovery resources. These features do not create an MCP server, agent tools, authentication, or new API permissions.

When valid public llms.txt and XML sitemap responses are detected, the plugin automatically adds RFC 8288 Link headers to public site responses. The Discovery tab reports the resources and Link-header state; create llms.txt or an XML sitemap if either is unavailable.

The plugin does not guarantee crawling, indexing, search rankings, AI citations, traffic, or business results. It does not send content or telemetry to Serfe. Local checks make same-site requests only when an administrator runs them.

= Markdown publication =

Markdown is disabled by default. Use the master toggle to publish Markdown for all supported public content; it saves immediately. Advanced Settings can exclude content types or exact URL paths and are saved separately. Eligible pages have explicit .md URLs and respond to Accept: text/markdown. Published Markdown has an HTML canonical URL. Generated Markdown can be cached on the WordPress server and is invalidated when relevant public content or settings change; HTTP responses use a private, no-store policy so shared caches do not keep them.

= Discovery files =

The Robots.txt & llms.txt tab can validate and publish these files at the public site root. Without a physical file, WordPress or an SEO plugin such as Yoast SEO or Rank Math may still serve a virtual robots.txt response. Administrators can inspect the live response before publishing; replacing it with a physical file requires explicit takeover and a matching checksum. The plugin keeps one previous public version for restoration. If safe root writes are unavailable, it offers validated content for manual deployment.

= API Catalog and diagnostics =

The optional API Catalog describes existing public APIs; it does not enable any API. Enable or disable it from its own API Catalog tab. The Discovery tab reports local checks for public discovery resources after an administrator starts them. MCP Server Card publication is not available yet.

== Installation ==

1. Install and activate Serfe WordPress AI Discovery through the WordPress Plugins screen.
2. Open the top-level Serfe WordPress AI Discovery menu.
3. Review the Overview and Help & Support tabs before enabling publication.
4. To publish Markdown, enable the master switch. Use Advanced Settings only when you need to exclude a content type or exact URL path.
5. Request a public URL with .md or Accept: text/markdown and confirm that the normal URL still returns HTML.

== Frequently Asked Questions ==

= Does the plugin make private content available to AI agents? =

No. Public Markdown publication requires public, published content and an unauthenticated request. Administrators can review an explicit .md URL while logged in, but that does not publish private or unpublished content.

= Does it generate an llms.txt file automatically from site content? =

No. An administrator writes, reviews, and explicitly publishes the file.

= Does it provide an MCP server or improve search rankings? =

No. It does not implement an MCP server and cannot guarantee crawling, indexing, rankings, or citations.

== Changelog ==

= 1.0.10 =

* First public-source candidate with opt-in Markdown, guarded discovery-file publication, an optional public API Catalog, and local discovery diagnostics. See CHANGELOG.md in the source repository for development history.
