=== Serfe AI Discovery ===
Tags: markdown, llms-txt, robots-txt, api-discovery, ai
Requires at least: 7.1
Tested up to: 7.1
Stable tag: 1.0.10
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish approved public WordPress content as Markdown and manage selected discovery signals for automated clients.

== Description ==

Serfe AI Discovery is an opt-in starter kit for public content. It returns Markdown for approved posts, pages, WooCommerce products and catalog archives, and selected public Custom Post Types. Browsers continue to receive canonical HTML.

An administrator can also review and publish root robots.txt and llms.txt files, enable a catalog of existing public APIs, and run bounded local checks for discovery resources. These features do not create an MCP server, agent tools, authentication, or new API permissions.

The plugin does not guarantee crawling, indexing, search rankings, AI citations, traffic, or business results. It does not send content or telemetry to Serfe. Local checks make same-site requests only when an administrator runs them.

= Markdown publication =

Markdown is disabled by default. Enable the master switch, select a supported content type or public archive, and approve individual content where required. Eligible pages have explicit .md URLs and respond to Accept: text/markdown. Published Markdown has an HTML canonical URL. Generated Markdown can be cached on the WordPress server and is invalidated when relevant public content or settings change; HTTP responses use a private, no-store policy so shared caches do not keep them.

= Discovery files =

The Robots.txt & llms.txt tab can validate and publish these files at the public site root. Existing or externally changed files require an explicit takeover. The plugin keeps one previous version of each file for restoration. If safe root writes are unavailable, it offers validated content for manual deployment.

= API Catalog and diagnostics =

The optional API Catalog describes existing public APIs; it does not enable any API. The Discovery tab reports local checks for public discovery resources after an administrator starts them. MCP Server Card publication is not available yet.

== Installation ==

1. Install and activate Serfe AI Discovery through the WordPress Plugins screen.
2. Open the top-level Serfe AI Discovery menu.
3. Review the Overview and Help & Support tabs before enabling publication.
4. To publish Markdown, enable the master switch and supported content types, then approve individual items in their editors.
5. Request an approved public URL with .md or Accept: text/markdown and confirm that the normal URL still returns HTML.

== Frequently Asked Questions ==

= Does the plugin make private content available to AI agents? =

No. Markdown publication requires public, published, approved content and an unauthenticated request. Review settings and public responses before using the feature on a production site.

= Does it generate an llms.txt file automatically from site content? =

No. An administrator writes, reviews, and explicitly publishes the file.

= Does it provide an MCP server or improve search rankings? =

No. It does not implement an MCP server and cannot guarantee crawling, indexing, rankings, or citations.

== Changelog ==

= 1.0.10 =

* First public-source candidate with opt-in Markdown, guarded discovery-file publication, an optional public API Catalog, and local discovery diagnostics. See CHANGELOG.md in the source repository for development history.
