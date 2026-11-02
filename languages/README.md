# Translations

Serfe AI Discovery ships its own translation catalogs so supported locales do not depend on a WordPress.org language pack being approved or downloaded.

## Source catalog

`serfe-ai-discovery.pot` is the canonical source catalog. Regenerate it after changing a translatable string:

```bash
docker run --rm --user "$(id -u):$(id -g)" \
  -v "$PWD:/app" -w /app --entrypoint wp wordpress:cli \
  i18n make-pot . languages/serfe-ai-discovery.pot \
  --domain=serfe-ai-discovery --exclude=vendor,tests --no-location
```

Maintain one `.po` source file and its compiled `.mo` file for every supported locale. Files use the format `serfe-ai-discovery-<locale>.po` and `serfe-ai-discovery-<locale>.mo`.

WordPress.org language packs may override these bundled files when an approved community translation is available. The bundled files remain the fallback for every installation.
