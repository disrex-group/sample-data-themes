# Disrex Sample Data Themes

Theme-based demo data for Magento 2 / MageOS. Replaces the dated Luma sample
data with a thematically consistent catalog (~1,000 SKUs of Scandinavian-style
furniture and lighting) in English and Dutch, with AI-generated product
photography included.

## Quick install

```bash
composer require disrex/sample-data-theme-home-living:^1.0

bin/magento module:enable \
    Disrex_SampleDataThemesCore \
    Disrex_SampleDataThemeHomeLiving \
    Disrex_SampleDataThemeHomeLivingMedia
bin/magento setup:upgrade

bin/magento sampledata:theme:deploy --theme=home-living \
    --locales=en_US,nl_NL \
    --default-locale=en_US \
    --primary-locale=nl_NL \
    --auto-create-storeviews
```

That's it. Single `composer require` pulls in all three packages
(`core`, `theme-home-living`, `theme-home-living-media`); the deploy
command provisions storeviews, imports the catalog, attaches imagery,
applies translations, regenerates URL rewrites, reindexes, and flushes
caches in one pass. ~5 minutes end-to-end.

## What you get

| Item | Count |
|---|---|
| Visible products on category pages | 329 |
| Total SKUs (incl. configurable variants) | ~1,018 |
| Categories | 5 top-level (Living, Bedroom & Dining, Decor & Outdoor, Cadeaubonnen, Producttypes) + 16 leaves |
| Product types | simple, configurable, bundle, grouped, virtual (giftcards with custom options) |
| Imagery | 100 % AI-generated (Flux 2 Pro studios, Seedream 4.5 scenes) |
| Translations | EN + NL for every product, category, attribute |
| Cross-sell / upsell / related | ~960 algorithmic links across the catalog |

## Command reference

```bash
# List registered themes
bin/magento sampledata:theme:list

# Deploy
bin/magento sampledata:theme:deploy --theme=home-living \
    --locales=en_US,nl_NL --auto-create-storeviews

# Optionally promote a non-default locale onto the default storeview
# (so visitors hit Dutch on the canonical / URL with no /nl/ prefix)
bin/magento sampledata:theme:deploy --theme=home-living \
    --locales=en_US,nl_NL --primary-locale=nl_NL \
    --auto-create-storeviews

# Remove a previously deployed theme (interactive prompt)
bin/magento sampledata:theme:remove --theme=home-living
```

The stock `bin/magento sampledata:deploy` command is also intercepted:
when a theme is registered, users are prompted to opt into the themed
data instead of Luma. Saying "no" falls through to the original behaviour.

## Compatibility

* Magento 2.4.7+ / MageOS equivalent
* PHP 8.2 / 8.3 / 8.4

## Project layout

```
packages/core/                       disrex/sample-data-themes-core
packages/theme-home-living/          disrex/sample-data-theme-home-living
packages/theme-home-living-media/    disrex/sample-data-theme-home-living-media
docs/                                authoring guides
tools/                               theme-validator + Phase-4 SQL coverage queries
.github/workflows/                   CI (PHPCS, PHPStan, PHPUnit, validator)
```

Validate a theme's on-disk integrity:

```bash
php tools/theme-validator.php packages/theme-home-living
```

## Authoring your own theme

See [`docs/creating-a-theme.md`](docs/creating-a-theme.md),
[`docs/csv-format.md`](docs/csv-format.md), and
[`docs/extending-fixtures.md`](docs/extending-fixtures.md).

## Releasing

Each composer package is versioned and published independently from this
monorepo. See [`RELEASING.md`](RELEASING.md) for the tag conventions
(`core/v1.0.0`, `theme-home-living/v1.0.0`, ...) and the GitHub Actions
pipeline that splits to per-package mirror repos and publishes on
Packagist.

## License

[MIT](LICENSE) — Disrex V.O.F.

## Contributing

Read [`CONTRIBUTING.md`](CONTRIBUTING.md). Contributions require a
[DCO](https://developercertificate.org/) sign-off (`git commit -s`) — no CLA.
