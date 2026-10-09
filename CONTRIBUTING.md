# Contributing to Serfe AI Discovery

Thanks for helping improve the plugin. Please keep changes focused on public WordPress sites and explain the user-visible behavior in the change description.

## Before changing code

- Read the [README](README.md) for the current feature set and limits.
- Keep all publication options opt-in. Markdown must never expose content that an anonymous visitor cannot already view.
- Use WordPress APIs for capabilities, nonces, input validation, output escaping, URLs, and localization.
- Do not add client-specific data, credentials, telemetry, or third-party network calls.
- Keep changes compatible with the PHP minimum declared in the plugin header until that minimum is deliberately changed and tested.

## Check a change locally

Run the self-contained PHP tests:

```bash
for test_file in tests/*.php; do php "$test_file"; done
find includes tests -type f -name '*.php' -print0 | xargs -0 -n 1 php -l
php -l serfe-ai-discovery.php
bash -n tests/http-smoke.sh
composer install
vendor/bin/phpcs --standard=phpcs.xml.dist
vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --memory-limit=1G
composer audit --locked
```

For routing or response changes, also test an opted-in item on a disposable or local WordPress installation with `tests/http-smoke.sh`. The script requires an actual web server and does not create or change content.

## Submit a change

Use a [Conventional Commit](https://www.conventionalcommits.org/en/v1.0.0/) message. Update [CHANGELOG.md](CHANGELOG.md) for user-visible changes. Explain how the change was tested and any WordPress, WooCommerce, cache, or Custom Post Type configuration that matters. Do not include private vulnerability details in a public issue or change discussion.

The source checks workflow runs PHP syntax, the self-contained tests, WordPress-Extra coding standards, PHPStan level 2, and a development-dependency audit. WordPress integration, the full WordPress coding standard, deeper static analysis, security scanning, and release packaging require separate validation before publication.
