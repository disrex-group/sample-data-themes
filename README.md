# Disrex Sample Data Themes

Theme-based sample data for Magento 2 / MageOS. Replaces the stale Luma demo
content with thematically consistent catalogs (Home & Living, Technology,
Clothing, ...) chosen by the user at install time.

> Status: **Phase 1 / core framework** — the registry, fixture runner, helpers
> and CLI commands. Reference themes live in sibling packages and are tracked
> separately.

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
packages/core/                 disrex/sample-data-themes-core (this package)
packages/theme-*/              theme content packages (separate, not in this branch)
docs/                          contributor documentation
tools/                         schema validators and helper scripts
.github/workflows/             CI: PHPUnit, PHPStan, PHPCS
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
