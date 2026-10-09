# Changelog

All notable changes to Serfe AI Discovery are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- Added administrator settings and per-item controls for Markdown publication.
- Added an opt-in switch for the public API Catalog.
- Added opt-in Markdown for individual public, publicly queryable Custom Post Types.
- Added the GPLv2-or-later license file and plugin header metadata.
- Added separate, default-off choices for public post-type archives and WooCommerce product categories and tags.

### Changed

- Markdown and alternate links are disabled until both the content type and item are enabled.
- Markdown responses now include a canonical Link header and use a no-store cache policy.
- Product Markdown now uses WooCommerce's public short and full descriptions, visible variations, and the site's configured currency.
- Migrated v1 settings to v2 without enabling any archive; existing individual-content choices remain in effect.

### Removed

- Removed site-specific product fields, shortcode integration, currency mutation, and plan grouping from product output.
- Removed the optional multilingual-plugin language lookup; front matter now uses the active WordPress site language.

### Security

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
