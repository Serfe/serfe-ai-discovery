# Changelog

All notable changes to Serfe AI Discovery are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- Grouped the Discovery Checks guidance, current status, validation controls, and results in one panel with a primary Run checks button.
- Moved the optional Is It Agent-Ready button from the discovery-file editors to below the Discovery Checks table.
- Simplified Markdown publication to one global switch; Advanced Settings now exclude only public content types and exact URL paths.
- Moved API Catalog publication to its own settings tab with explicit enable and disable actions.
- Renamed the visible plugin name to "Serfe WordPress AI Discovery" in the plugin header, admin screens, and documentation. The slug, text domain, folder, and stored option names are unchanged.

### Added

- Added an immediate AJAX toggle for Markdown support, with separate saving for Advanced Settings exclusions.
- Added automatic RFC 8288 Link headers for verified public llms.txt and XML sitemap resources, with Link-header status in Overview and Discovery Checks.
- Added Markdown responses for the configured WordPress Posts page archive, categories, tags, and public post taxonomies, including pagination. These follow the Posts individual-content setting.
- Added explicit inspection of virtual public discovery-file responses, including robots.txt served by WordPress or SEO plugins.
- Added a generation-keyed server-side Markdown cache with automatic invalidation on content, visibility, price, stock, taxonomy, opt-in and setting changes.
- Added enabled URL table and Markdown cache status controls to the Serfe AI Discovery → Markdown tab.
- Added expandable admin guidance for Markdown, robots.txt, llms.txt, and XML Sitemaps, including their limits.
- Added an administrator-initiated Is It Agent-Ready link that omits local and private site hosts.
- Added a tabbed administrator overview with local feature states and guidance for Markdown, discovery files, and support.
- Added protected editors for public-root robots.txt and llms.txt, with independent backup, restore, takeover review, a starter template, and a manual-copy fallback.
- Added explicit public-file validation and non-blocking robots.txt sitemap and crawler-policy warnings.
- Added opt-in Markdown for WooCommerce shop, product category, and product tag archives with approved public product listings.
- Added opt-in Markdown for public Custom Post Type archives and their pagination, listing only individually approved public items.
- Added administrator settings and per-item controls for Markdown publication.
- Added an opt-in switch for the public API Catalog.
- Added opt-in Markdown for individual public, publicly queryable Custom Post Types.
- Added the GPLv2-or-later license file and plugin header metadata.
- Added separate, default-off choices for public post-type archives and WooCommerce product categories and tags.

### Changed

- Separated Robots.txt and llms.txt into dedicated top-level tabs on the Serfe AI Discovery settings screen.
- Clarified the server-side cache and private, no-store HTTP response policy in the WordPress.org readme.
- Restored the Serfe roundel as a 20-pixel admin menu SVG, matching WordPress's menu icon size.
- Rendered the bundled admin menu SVG as a centered WordPress background icon using a base64 data URI.
- Completed the Help & Support contact card with the starter-kit scope and a direct Serfe contact link.
- Moved the Serfe logo to Help & Support, selected its blue or white variant from the admin color scheme, and linked it to the Serfe website with the plugin's UTM source.
- Moved the plugin settings to a top-level WordPress admin menu using the shipped gray Serfe SVG as its icon.
- Moved Agent Readiness diagnostics into the Discovery tab on the plugin settings screen.
- Markdown and alternate links are disabled until both the content type and item are enabled.
- Markdown responses now include a canonical Link header and use a no-store cache policy.
- Product Markdown now uses WooCommerce's public short and full descriptions, visible variations, and the site's configured currency.
- Migrated v1 settings to v2 without enabling any archive; existing individual-content choices remain in effect.

### Fixed

- Allowed saved WordPress core heading and list markup in Markdown responses when WordPress registers those blocks with render callbacks.
- Kept the full-publication setting when WordPress saves the plugin options.
- Corrected the Overview and Help & Support text to describe the existing Markdown cache and the unavailable Server Card settings accurately.
- Marked the documented HTTP smoke-check script as executable so contributors can run it directly.

### Removed

- Removed per-item, per-content-type, per-archive, and per-term Markdown publication controls.
- Removed site-specific product fields, shortcode integration, currency mutation, and plan grouping from product output.
- Removed the optional multilingual-plugin language lookup; front matter now uses the active WordPress site language.

### Security

- Required checksum-confirmed takeover before a physical file can replace an existing virtual public response; retained its previous text as the backup.
- Sanitized file-action controls before validation while preserving discovery-file text for strict content validation.
- Added a guarded public-root file publication service with fixed filenames, UTF-8 validation, atomic replacement, previous-version backups, and checksum-based takeover checks.
- Ignored catalog child objects that are not WooCommerce product variations before reading variation-only properties.
- Verified the Agent Readiness refresh notice with a nonce and sanitized the request's `Accept` header before negotiation.
- Added `Vary: Accept` to eligible HTML responses and protected internal paths under a site URL prefix from Markdown negotiation.
- Added administrator exclusions for content types, exact URL paths, and public taxonomy terms; exclusions override Markdown opt-ins.
- Agent Readiness now sends same-site validation requests only after an administrator explicitly starts a check.
- Agent Readiness rejects external validation URLs and does not follow redirects.
- Blocked Markdown for authenticated visitors, password-protected items, and content that WordPress does not consider publicly viewable.
- Added `X-Content-Type-Options: nosniff` to Markdown responses.
- Prevented product extension filters from making hidden or unpriced products public.
- Blocked Markdown on WooCommerce cart, checkout, account, payment, and order endpoints even when a page is opted in.
- Limited editorial Markdown to saved static content; shortcodes and dynamic or unknown blocks cannot be published through Markdown.
- Removed hidden elements and forms from HTML-to-Markdown conversion.

## [1.0.10] - 2026-09-24

### Changed

- Replaced the readiness footer's composed wordmark with complete local blue and white logo assets; no masking, cropping, or text overlay is used.

## [1.0.9] - 2026-09-24

### Added

- Added blue-on-light and white-on-dark Serfe logo variants for the Agent Readiness footer.

## [1.0.8] - 2026-09-24

### Fixed

- Fixed the Agent Readiness refresh redirect and added a commercial introduction with a Serfe footer.
- Added local validation for the RFC 9727 API catalog.

## [1.0.7] - 2026-09-24

### Added

- Added **Tools → Agent Readiness**, a local, read-only diagnostic panel for agent-facing capabilities.
- Added deterministic checks for robots, sitemaps, Markdown negotiation, MCP, OAuth metadata, Agent Skills, and Agentic Commerce.

## [1.0.6] - 2026-09-24

### Fixed

- Fixed the homepage alternate Markdown URL to use `/.md` instead of an invalid hostname suffix.

## [1.0.5] - 2026-09-24

### Added

- Added homepage `Link` headers for the API catalog and WordPress REST API description.
- Added an RFC 9727 `application/linkset+json` API catalog at `/.well-known/api-catalog`.

## [1.0.4] - 2026-09-23

### Added

- Added `Accept: text/markdown` negotiation for eligible public posts, pages, and products.
- Added `Vary: Accept` and an estimated `X-Markdown-Tokens` response header for negotiated responses.

## [1.0.3] - 2026-09-22

### Added

- Added conversion for nested and task lists, strikethrough, definition lists, keyboard input, link and image titles, and fenced code blocks with language hints.
- Extended table conversion with escaped separators and left, centre, and right alignment markers.

## [1.0.2] - 2026-09-22

### Added

- Added GitHub-flavored Markdown table conversion for Gutenberg table blocks.

## [1.0.1] - 2026-09-22

### Added

- Added Markdown representations and discovery links for published WordPress pages.

## [1.0.0] - 2026-09-21

### Added

- Initial Serfe release with Markdown routing, discovery, tests, and documentation.
