# Adding a locale to an existing theme

Translations are static CSVs under `_files/i18n/<locale>/`. Adding a new
locale is a content-only change — no PHP edits, no schema changes.

## Steps

1. **Copy the source locale's directory.**

   ```bash
   cp -r packages/theme-home-living/_files/i18n/en_US \
         packages/theme-home-living/_files/i18n/de_DE
   ```

2. **Translate the strings.** Keep the join columns (`sku`, `path`,
   `attribute_code`, `option_code`) untouched — they are stable identifiers,
   not user-facing text.

   - `categories.csv`: `name`, `description`, `url_key` (must be unique per
     storeview), `meta_*`
   - `products.csv`: `name`, `description`, `short_description`, `url_key`,
     `meta_*`
   - `attributes.csv`: `frontend_label`, `option_label`

3. **Add the locale to `getSupportedLocales()`.**

   ```php
   protected const LOCALES = ['en_US', 'nl_NL', 'de_DE'];
   ```

4. **Validate parity.** From the repo root:

   ```bash
   php tools/theme-validator.php packages/theme-home-living
   ```

   The validator reports:
   - **Missing rows** — entities present in the base layer but not in your
     locale CSV. The framework falls back, so this is a warning, not an
     error.
   - **Extra rows** — keys in your locale CSV that don't exist in the base
     layer. This is an error — they would never be applied and probably
     indicate a typo.

5. **Test it locally.**

   ```bash
   bin/magento sampledata:theme:remove --theme=home-living --no-confirm
   bin/magento sampledata:theme:deploy --theme=home-living \
       --locales=en_US,nl_NL,de_DE --auto-create-storeviews
   ```

6. **Open a PR.** A maintainer with the language can sanity-check
   translations.

## URL keys

Each locale gets its own slug — Dutch storeviews can have `/banken` while
English ones have `/sofas-couches`. URL keys are scoped per storeview in
Magento, so the same `url_key` is allowed on multiple storeviews as long
as it is unique within each.

## Currency, pricing, formatting

Locale CSVs are translation-only. Pricing lives in the base CSV and
applies to every storeview. If a theme wants per-locale prices in the
future, the framework can be extended with `i18n/<locale>/pricing.csv`;
this is explicitly out of scope for v1.

## Right-to-left languages

Adding Arabic or Hebrew works the same way; the framework does not need
RTL-specific knowledge. Stores wanting RTL frontends configure that in
their Magento theme.
