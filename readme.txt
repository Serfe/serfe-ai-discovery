=== Serfe AI Discovery ===
Tags: markdown, llms-txt, robots-txt, api-discovery, ai
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.0.11
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Help your website publish clear discovery signals and AI-friendly public content formats.

== Description ==

AI Discovery helps your website communicate with AI-powered search engines and agents through clear crawling signals, structured discovery information, and AI-friendly content formats.

As AI becomes another way people find information online, you can make your public content easier for compatible systems to discover and interpret. Serfe AI Discovery gives you practical, opt-in controls for the technical foundations: `robots.txt`, `llms.txt`, Markdown versions of eligible content, and HTTP Link headers for verified discovery resources.

This can help AI-powered services and agents, including systems associated with ChatGPT, Claude, Gemini, Perplexity, and others, find the public information you choose to expose. Each provider decides what it crawls, supports, and uses, so the plugin cannot guarantee indexing, citations, traffic, rankings, or inclusion in AI-generated answers.

= Start with the foundations =

You do not have to enable every capability. Start with the parts that suit your website, review the public result, and add more only when they are useful for your goals.

* `robots.txt` — Review crawler instructions and sitemap references at your public site root. These directives communicate preferences to compatible crawlers; they are not a security or access-control mechanism.
* `llms.txt` — Publish a concise, human-reviewed guide to your current public content and key URLs. It is an emerging convention, not a universally adopted standard.
* Markdown — Offer eligible public pages, posts, archives, products, and supported custom content in a cleaner Markdown representation. Your regular HTML pages remain canonical for visitors.
* Link headers — When your public `llms.txt` and XML sitemap are both verified, the plugin advertises them with RFC 8288 Link headers on public site responses.
* API Catalog — Optionally describe the public WordPress REST API at a standard discovery URL. This does not create new endpoints or permissions.
* Discovery Checks — Review the current public state of your discovery files, Markdown negotiation, Link headers, API Catalog, and optional agent-facing resources.

= Advanced capabilities require your implementation =

Some capabilities depend on your website, products, and security model. The plugin does not create an MCP server, OAuth authorization system, Agent Skills index, or Agentic Commerce integration for you. After you implement one of these resources, Discovery Checks can help you identify whether its expected public endpoint or explicitly registered integration is available.

This approach lets you make those capabilities visible when they are ready without granting new access to your content, business data, customer accounts, carts, checkout, or payments.

= You stay in control =

Markdown is disabled by default. The plugin publishes only eligible public content and excludes private, draft, password-protected, trashed, restricted, cart, checkout, account, and payment content. You can exclude supported content types or exact URL paths before enabling Markdown.

For `robots.txt` and `llms.txt`, you review the text and explicitly publish it. If a physical file or a virtual response from WordPress or an SEO plugin already exists, the plugin requires you to inspect it and confirm takeover before replacing it. It keeps one previous version for restoration and offers validated text for manual deployment when root writes are unavailable.

The plugin does not send content or telemetry to Serfe. Discovery Checks make bounded same-site requests only after an administrator starts them. If you use the robots.txt validator, it can also check the sitemap URLs that you explicitly include in that file.

= Markdown publication =

When you enable Markdown, eligible public content has an explicit `.md` URL and can also respond to `Accept: text/markdown`. Generated Markdown keeps an HTML canonical URL. The plugin caches generated content on your server and invalidates it when relevant public content or settings change. HTTP responses use a private, no-store policy so shared caches do not retain them.

= Discovery files =

Use the Robots.txt and AI Files (llms.txt) tabs to review, validate, and publish files at your public site root. Without a physical file, WordPress or an SEO plugin such as Yoast SEO or Rank Math may still serve a virtual `robots.txt` response. Review the live response before publishing and confirm takeover if you want the plugin to replace it.

= API Catalog and diagnostics =

Enable or disable the optional API Catalog from its own tab. The Discovery Checks tab reports local checks after you start them, including optional MCP server, OAuth protected resource, Agent Skills, and Agentic Commerce states. MCP Server Card publication is not available in this version.

== Installation ==

1. Install and activate Serfe AI Discovery through the WordPress Plugins screen.
2. Open the top-level AI Discovery menu.
3. Review Overview to see what your site already exposes and what needs attention.
4. Review your existing `robots.txt` response before choosing whether to publish a managed file.
5. Draft and review `llms.txt` with current, public, canonical URLs only; publish it when it represents your site accurately.
6. Enable Markdown only when you want to offer it for eligible public content. Use Advanced Settings to exclude content types or exact URL paths.
7. Run Discovery Checks and confirm the public responses. Request a public URL with `.md` or `Accept: text/markdown` and verify that the normal URL still returns HTML.

== Frequently Asked Questions ==

= What is AI Discovery, and why does my website need it? =

AI Discovery makes your website's public content and capabilities easier for AI-powered search engines and agents to find and interpret. As AI becomes another way people discover information online, clear discovery signals and accessible content formats can help your website communicate more effectively with compatible systems.

= Will this plugin improve my visibility in ChatGPT, Claude, Gemini, Perplexity, or other AI platforms? =

The plugin helps you establish technical foundations that can make your public website easier for AI crawlers and agents to discover. No plugin can guarantee that your website will appear in AI-generated answers or recommendations. Visibility depends on content quality, relevance, accessibility, each platform's policies, and how that platform discovers and processes information.

= What is llms.txt, and why is it useful? =

`llms.txt` is an emerging convention for providing AI systems with a structured, curated guide to a website, including descriptions and links to relevant public content. The plugin lets you review and publish this guide in a machine-readable format. Support varies between AI platforms, so treat it as a useful signal rather than a universal requirement.

= How does the plugin work with robots.txt? =

The plugin helps you review, validate, and publish crawler-related instructions through `robots.txt`. These directives complement your existing crawling and SEO configuration. Each crawler decides how to interpret them, and they should never be used as a security or access-control mechanism.

= What are AI discovery signals? =

AI discovery signals are machine-readable files, headers, and public endpoints that help automated systems find and understand your website. This plugin supports selected signals such as `robots.txt`, `llms.txt`, Markdown alternatives, Link headers, and an optional API Catalog. Their interpretation and enforcement depend on the individual provider.

= Why does the plugin provide Markdown versions of website content? =

Markdown can offer a cleaner, structured representation of public content that is easier for compatible tools to process than a complex HTML page. When you enable it, the plugin can make eligible public content available in Markdown while your regular HTML pages remain the canonical visitor experience.

= Can I control whether AI platforms use my content for training? =

The plugin can help you publish AI-related crawler preferences in `robots.txt` for compatible crawlers. These signals are not universal enforcement mechanisms: whether a platform honors them depends on its policies and technical implementation. For sensitive or restricted information, use proper authentication and access controls.

= Does this plugin replace Yoast SEO, Rank Math, or another SEO plugin? =

No. Serfe AI Discovery complements your existing SEO tools. SEO plugins focus on established search optimization, metadata, and sitemaps; AI Discovery adds controls for AI crawler communication, public-content accessibility, and machine-readable discovery information. The plugin also asks you to review existing virtual `robots.txt` responses before taking over a file managed by WordPress or another plugin.

= Is Serfe AI Discovery compatible with WooCommerce? =

Yes. The plugin can provide Markdown for eligible public products and supported catalog archives. It does not intentionally expose private orders, customer information, carts, checkout, account pages, payment endpoints, hidden products, or business data.

= Does the plugin expose private or restricted website content? =

No. Public Markdown publication requires public, published content and an unauthenticated request. Administrators can review an explicit `.md` URL while logged in, but that does not publish private or unpublished content. As with any public endpoint, review your configuration and site permissions before enabling features.

= Does the plugin support MCP or Agentic Commerce? =

The plugin focuses on foundational AI Discovery capabilities. It does not implement a complete Model Context Protocol (MCP) server, OAuth authorization system, Agent Skills index, or Agentic Commerce solution. These technologies may be useful when your website needs agents to use tools, access protected resources, or support transactions. After you implement them, Discovery Checks can help you verify their expected public discovery state.

= How can I check whether my website is ready for AI agents? =

Start with Discovery Checks and confirm that your public discovery files, Link headers, Markdown responses, and optional resources are correctly configured. You can also use [Is It Agent-Ready](https://isitagentready.com/) to assess additional aspects of a publicly deployed website. External tools cannot inspect local or private environments.

= Where can I get support or professional assistance? =

Serfe AI Discovery is free and open source. Visit the GitHub repository to read documentation, report issues, suggest improvements, or contribute. If you need a broader AI-readiness evaluation, Serfe offers GEO Assessments that can review technical SEO, content accessibility, discovery capabilities, and implementation opportunities. Visit [Serfe](https://www.serfe.com/) to learn more.

== Screenshots ==

1. Overview shows the current state of public discovery features and optional agent-facing resources after Discovery Checks run.
2. Markdown lets you opt in to Markdown publication for eligible public content and review generated HTML and Markdown URLs.
3. Robots.txt lets you review crawler instructions, sitemaps, existing-file takeover, and supported content signals before publishing.
4. AI Files (llms.txt) lets you draft, review, and explicitly publish a concise guide to your current public content.

== Changelog ==

= 1.0.11 =

* Expanded the WordPress.org readme with an educational AI Discovery guide, implementation boundaries, FAQ, and four annotated screenshots.
* Renamed the visible plugin name to Serfe AI Discovery and the top-level administration menu to AI Discovery. The slug, text domain, folder, and stored option names are unchanged.
* Added Agent Skills, OAuth protected resource, and Agentic Commerce states to the Overview after administrators run Discovery Checks.
* Refreshed Help & Support with dedicated Community Support (GitHub) and Professional Services GEO Assessment guidance.
* Added Content Signals blocks and blog AI training protection options in the robots.txt editor.
* Hardened output escaping and i18n translation comments according to WordPress.org standards.
* Declared and tested compatibility across WordPress 6.2 to 7.1 and PHP 7.4+.

= 1.0.10 =

* First public-source candidate with opt-in Markdown, guarded discovery-file publication, an optional public API Catalog, and local discovery diagnostics. See CHANGELOG.md in the source repository for development history.
