# Disrex Sample Data Themes

Theme-based sample data for Magento 2 / MageOS. Replaces the stale Luma demo
content with thematically consistent catalogs (Home & Living, Technology,
Clothing, ...) chosen by the user at install time.

> Status: **Phase 1 + 2** — the core framework plus the first reference
> theme (`disrex/sample-data-theme-home-living`, EN + NL). The optional
> media bundle ships as a skeleton package; populate `_files/images/`
> with photography or generated assets.

## Installation

```bash
composer require disrex/sample-data-themes-core
bin/magento module:enable Disrex_SampleDataThemesCore
bin/magento setup:upgrade
```

By itself the core package does nothing visible — it provides the framework
that theme packages plug into.

## Usage (once at least one theme is installed)

```bash
# List registered themes
bin/magento sampledata:theme:list

# Deploy a specific theme (interactive prompt if --theme is omitted)
bin/magento sampledata:theme:deploy --theme=home-living \
    --locales=en_US,nl_NL --auto-create-storeviews

# Remove a previously deployed theme
bin/magento sampledata:theme:remove --theme=home-living

bin/magento setup:upgrade
bin/magento indexer:reindex
```

The stock `bin/magento sampledata:deploy` command is also intercepted: when a
theme is registered, users are prompted to opt into the themed alternative
instead of the Luma sample data. Saying "no" falls through to the original
behaviour.

## Project layout

```
packages/core/                       disrex/sample-data-themes-core
packages/theme-home-living/          disrex/sample-data-theme-home-living
packages/theme-home-living-media/    disrex/sample-data-theme-home-living-media (optional)
docs/                                contributor documentation
tools/                               schema validators and helper scripts
.github/workflows/                   CI: PHPUnit, PHPStan, PHPCS
```

### Home & Living theme

* 6 top-level categories, 22 categories total
* 28 frontend-visible products: 18 simple, 6 configurable (with 18 variants),
  2 grouped, 2 bundle
* 7 custom attributes including visual swatches (colour) and text swatches
  (fabric)
* 4 attribute sets (furniture, lighting, textiles, decor)
* Full EN + NL translations for every category, product and attribute label

Verify the on-disk integrity of any theme package with:

```bash
php tools/theme-validator.php packages/theme-home-living
```

## Authoring a theme

See [`docs/creating-a-theme.md`](docs/creating-a-theme.md) for a walkthrough,
[`docs/csv-format.md`](docs/csv-format.md) for the on-disk format, and
[`docs/extending-fixtures.md`](docs/extending-fixtures.md) to add new fixture
types.

## Compatibility

* Magento 2.4.7+ / MageOS equivalent
* PHP 8.2 / 8.3 / 8.4

## License

[MIT](LICENSE) — Disrex V.O.F.

## Contributing

Please read [`CONTRIBUTING.md`](CONTRIBUTING.md). Contributions require a
[DCO](https://developercertificate.org/) sign-off (`git commit -s`) — no CLA.
