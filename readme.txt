=== Serfe AI Discovery ===
Tags: markdown, llms-txt, robots-txt, api-discovery, ai
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.0.13
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Help your website publish clear discovery signals and AI-friendly public content formats.

== Description ==

AI Discovery helps your website communicate with AI-powered search engines and agents through clear crawling signals, structured discovery information, and AI-friendly content formats.

As AI becomes another way people find information online, you can make your public content easier for compatible systems to discover and interpret. Serfe AI Discovery gives you practical, opt-in controls for `robots.txt`, `llms.txt`, Markdown versions of eligible content, and HTTP Link headers for verified discovery resources.

This can help compatible services and agents, including systems associated with ChatGPT, Claude, Gemini, and Perplexity, find the public information you choose to expose. Each provider decides what it crawls, supports, and uses; the plugin cannot guarantee indexing, citations, traffic, rankings, or inclusion in AI-generated answers.

= What you can configure =

You do not have to enable every capability. Start with what suits your website, review the public result, and add more when useful.

* `robots.txt` — Review crawler instructions and sitemap references. They are preferences, not access control.
* `llms.txt` — Publish a concise, human-reviewed guide to public content and key URLs. It is an emerging convention, not a universal standard.
* Markdown — Offer eligible public pages, posts, archives, products, and supported custom content in a cleaner representation; HTML remains canonical for visitors.
* Link headers — Advertise verified public `llms.txt` and XML sitemap resources with RFC 8288 Link headers.
* API Catalog — Optionally describe the public WordPress REST API without creating endpoints or permissions.
* Discovery Checks — Review discovery files, Markdown, Link headers, the API Catalog, and optional agent-facing resources.

= Advanced capabilities are yours to implement =

The plugin does not create an MCP server, OAuth authorization system, Agent Skills index, or Agentic Commerce integration. After you implement one, Discovery Checks can help verify its expected public endpoint or registered integration.

Markdown is disabled by default and only eligible public content is published. You control file publication and can review existing virtual or physical responses before replacing them. The plugin does not send content or telemetry to Serfe.

== External services ==

This plugin does not require, contact, or send site content to any third-party service. Discovery Checks make bounded loopback requests only to public URLs on your own website, such as `robots.txt`, `llms.txt`, sitemaps, Markdown responses, and any same-origin agent endpoint that you explicitly configure.

The optional Is It Agent-Ready button opens that external website in a new browser tab only after you select it. The plugin does not make that request or transmit data to the service; review that website's own terms and privacy policy before using it.

== Installation ==

1. Install and activate Serfe AI Discovery through the WordPress Plugins screen.
2. Open the top-level AI Discovery menu.
3. Review Overview and your existing `robots.txt` response.
4. Draft and review `llms.txt` using current, public, canonical URLs before publishing it.
5. Enable Markdown only for eligible public content; use Advanced Settings to exclude content types or paths.
6. Run Discovery Checks and confirm the public responses, including a `.md` URL or `Accept: text/markdown` request.

== Frequently Asked Questions ==

= What is AI Discovery, and why does my website need it? =

AI Discovery makes your website's public content and capabilities easier for AI-powered search engines and agents to find and interpret. As AI becomes another way people discover information online, clear discovery signals and accessible content formats can help your website communicate more effectively with compatible systems.

= Will this plugin improve my visibility in ChatGPT, Claude, Gemini, Perplexity, or other AI platforms? =

The plugin helps establish technical foundations. No plugin can guarantee appearance in AI-generated answers or recommendations; visibility also depends on content quality, relevance, accessibility, and each platform's policies.

= What is llms.txt, and why is it useful? =

`llms.txt` is an emerging convention for a structured, curated guide to a website and relevant public content. The plugin lets you review and publish it in a machine-readable format. Support varies between platforms, so treat it as a useful signal rather than a universal requirement.

= How does the plugin work with robots.txt? =

The plugin helps you review, validate, and publish crawler instructions through `robots.txt`. They complement your SEO configuration, each crawler interprets them independently, and they are not a security or access-control mechanism.

On WordPress Multisite, each site stores and serves its own virtual `robots.txt` and `llms.txt` response without writing to the shared filesystem. Markdown and API Catalog settings also remain configurable per site.

= What are AI discovery signals? =

They are machine-readable files, headers, and public endpoints that help automated systems find and understand your website. This plugin supports `robots.txt`, `llms.txt`, Markdown alternatives, Link headers, and an optional API Catalog. Provider support varies.

= Why does the plugin provide Markdown versions of website content? =

Markdown can offer compatible tools a cleaner, structured representation of public content than complex HTML. When enabled, eligible content is available in Markdown while HTML remains canonical for visitors.

= Can I control whether AI platforms use my content for training? =

The plugin can publish AI-related crawler preferences in `robots.txt` for compatible crawlers. They are not universal enforcement mechanisms. Use authentication and access controls for sensitive information.

= Does this plugin replace Yoast SEO, Rank Math, or another SEO plugin? =

No. It complements existing SEO tools. Those focus on search optimization, metadata, and sitemaps; AI Discovery adds controls for AI crawler communication, public-content accessibility, and machine-readable discovery information.

= Is Serfe AI Discovery compatible with WooCommerce? =

Yes. It can provide Markdown for eligible public products and supported catalog archives. It does not intentionally expose private orders, customer information, carts, checkout, account pages, payment endpoints, hidden products, or business data.

= Does the plugin expose private or restricted website content? =

No. Public Markdown requires public, published content and an unauthenticated request. Administrators can review an explicit `.md` URL while logged in without publishing private or unpublished content. Review your configuration and permissions before enabling features.

= Does the plugin support MCP or Agentic Commerce? =

It focuses on foundational AI Discovery capabilities and does not implement an MCP server, OAuth authorization system, Agent Skills index, or Agentic Commerce solution. After you implement one, Discovery Checks can help verify its expected public discovery state.

= How can I check whether my website is ready for AI agents? =

Start with Discovery Checks to confirm public discovery files, Link headers, Markdown responses, and optional resources. You can also use [Is It Agent-Ready](https://isitagentready.com/) for a publicly deployed website; external tools cannot inspect local or private environments.

= Where can I get support or professional assistance? =

Serfe AI Discovery is free and open source. Visit the [GitHub repository](https://github.com/Serfe/serfe-ai-discovery) to read documentation, report issues, suggest improvements, or contribute. Serfe also offers GEO Assessments for broader AI-readiness evaluations. Visit [Serfe](https://www.serfe.com/) to learn more.

== Screenshots ==

1. Overview shows the current state of public discovery features and optional agent-facing resources after Discovery Checks run.
2. Markdown lets you opt in to Markdown publication for eligible public content and review generated HTML and Markdown URLs.
3. Robots.txt lets you review crawler instructions, sitemaps, existing-file takeover, and supported content signals before publishing.
4. AI Files (llms.txt) lets you draft, review, and explicitly publish a concise guide to your current public content.
