# Changelog

Notable user-facing changes in public releases of Serfe AI Discovery.

## [Unreleased]

## [1.0.13] - 2026-10-20

### Added
- Expanded WooCommerce Markdown output with public product pricing and variation details.
- Added optional Content Signals integration with WordPress virtual `robots.txt`, plus configuration guidance for supported SEO plugins.
- Added `llms.txt` starter templates based on eligible public site content, with scheduled regeneration support.
- Added page-cache detection, cache diagnostics, and an administrator-initiated cache purge action in Discovery Checks.

### Changed
- Improved compatibility with popular page-cache plugins for Markdown negotiation and HTTP `Link` headers.
- Improved Content Signals controls for communicating crawler and AI-training preferences to compatible services.
- Improved canonical root discovery-file handling and administrator guidance for multilingual WordPress configurations.
- Refined local Agent Skills index validation and documentation of same-origin diagnostic requests.
- Simplified manual and advanced file-management workflows for `robots.txt` and `llms.txt`.

## [1.0.12] - 2026-10-15

### Added
- Added direct Settings and Community Support links from the WordPress Plugins screen.
- Added manual Content Signals options for `robots.txt`, including workflows involving supported SEO plugins (Yoast, RankMath, AIOSEO) and common AI bots.

### Changed
- Improved WordPress Multisite support with per-site virtual `robots.txt` and `llms.txt` responses, without writing to the shared filesystem.
- Refined the controls for managing physical discovery files.

### Fixed
- Improved request validation for Markdown content negotiation.
- Improved `.md` URL handling on WordPress Multisite subdirectory installations.

## [1.0.11] - 2026-10-14

### Added
- First public release of Serfe AI Discovery.
- Opt-in Markdown for eligible public pages, posts, archives, WooCommerce products, and supported custom post types, available through `.md` URLs and `Accept: text/markdown` negotiation.
- HTTP `Link` headers for Markdown alternatives and verified discovery resources.
- Discovery Checks covering `robots.txt`, `llms.txt`, XML sitemaps, Markdown, the optional API Catalog, and supported agent-facing discovery resources.
- Administration tools for `robots.txt` and `llms.txt`, including validation and safeguards for managing existing files.
- An optional RFC 9727 API Catalog for public WordPress REST API discovery.
- Publication controls and exclusions to keep private, restricted, and transactional content out of Markdown responses.
- Server-side Markdown caching and automatic invalidation on relevant public-content changes.
- Documentation, GitHub community support, and administrator guidance.
