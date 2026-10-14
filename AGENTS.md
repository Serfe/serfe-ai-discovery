# Developer and Agent Guidelines for Serfe AI Discovery

These guidelines apply to developing, maintaining, and testing **Serfe AI Discovery**. They define standards for code quality, security boundaries, testing, and contribution workflows.

---

## 1. Core Principles

1. **Public Content Only:** Never expose private, password-protected, draft, trashed, role-restricted, cart, checkout, or account content through Markdown or discovery signals.
2. **Opt-in by Default:** Markdown publication and the API Catalog require explicit administrator opt-in. Publishing `robots.txt` and `llms.txt` requires an administrator action. The plugin adds Markdown alternate links only for eligible enabled content and advertises `llms.txt` or sitemap Link headers when those public resources are verified.
3. **HTML Remains Canonical:** Markdown output is an alternative representation for compatible clients; the customer-facing HTML page remains canonical.
4. **No Write APIs or Actions:** The plugin is strictly read-only for site content and business data.
5. **No Telemetry or Required External Services:** Do not collect analytics or add third-party runtime dependencies. Discovery Checks request same-site public URLs; administrator-registered service health URLs may also be checked when an administrator starts a check.

---

## 2. Coding and Security Standards

- **Compatibility:** Target PHP 7.4+ and WordPress 6.2+. The compatibility workflow is configured to run integration checks on WordPress 6.2 through 7.1.
- **WordPress Coding Standards:** Adhere to `WordPress-Extra` rules defined in `phpcs.xml.dist`.
- **Capabilities & Nonces:** Guard all administrative actions with `manage_options`, nonces (`check_admin_referer`), and capability checks.
- **Sanitization & Escaping:** Sanitize all inputs using appropriate WordPress functions (`sanitize_text_field`, `sanitize_key`) and escape all outputs at rendering boundaries (`esc_html`, `esc_url`, `esc_attr`).
- **File Operations:**
  - Write exclusively to the resolved public root for `robots.txt` and `llms.txt`. Never write inside `/uploads` or arbitrary paths.
  - Enforce UTF-8, line ending normalization, and atomic replacement via temporary files in the same directory.
  - Require explicit takeover confirmation before overwriting existing physical files or virtual responses.

---

## 3. Testing and Verification

- **Automated Tests:** Always run all test suites before committing changes:
  ```bash
  for test in tests/*.php; do php "$test"; done
  ```
- **Syntax Checks:** Check PHP syntax across all files:
  ```bash
  find includes -type f -name '*.php' -exec php -l {} \;
  php -l serfe-ai-discovery.php
  ```
- **Verification Rule:** Never declare a feature or bug fix complete without empirical test evidence.

---

## 4. Git and Commit Conventions

- **Conventional Commits:** Use type prefixes (`feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`) for commit messages.
- **Keep a Changelog:** Update `CHANGELOG.md` under `[Unreleased]` whenever feature behavior, security boundaries, or user-facing interfaces change.
- **Submodule Isolation:** Do not include parent repository commits or non-plugin documentation inside this repository.

## Public Repository Checklist

The canonical public repository is `https://github.com/Serfe/serfe-ai-discovery`.

- Verify that the security links in both GitHub issue forms, the Help & Support tab, `SUPPORT.md`, and `README.md` resolve at the canonical URL.
- Confirm the WordPress integration matrix runs on the public default branch.
- Publish or link the immutable `1.0.11` source archive if GitHub is used as an installation channel; update the README download instructions to match the chosen release asset.
- Set the repository description, homepage, and topics on the organization-owned repository.
