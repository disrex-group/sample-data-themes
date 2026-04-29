# Changelog

All notable changes to this project will be documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Each composer package is versioned independently; the entries below are
grouped by package.

## [Unreleased]

### `disrex/sample-data-themes-core`

#### Added

- Initial core framework: `ThemeInterface`, `FixtureInterface`, `ThemeRegistry`
  and `FixtureRunner` for pluggable theme packages.
- Multi-locale support via `LocaleResolver`, `StoreviewManager` and
  `TranslationLoader` (loads `_files/i18n/<locale>/*.csv` with fallback to a
  configured source locale).
- Console commands: `sampledata:theme:list`, `sampledata:theme:deploy`,
  `sampledata:theme:remove`.
- Plugin on `Magento\SampleData\Console\Command\SampleDataDeployCommand` that
  offers the themed alternative when at least one theme is registered.
- `AbstractCsvFixture` base class and `CsvParser` helper.
- Entity importers: `ProductImporter`, `CategoryImporter`, `AttributeImporter`,
  `AttributeSetImporter`, `ProductLinker`.
- Type-specific builders: `ConfigurableProductBuilder`,
  `GroupedProductBuilder`, `BundleProductBuilder`.
- PHPUnit, PHPStan and PHPCS (PSR-12) tooling, plus GitHub Actions CI
  matrix across PHP 8.2 / 8.3 / 8.4.

### `disrex/sample-data-theme-home-living`

#### Added

- First reference theme: scandinavian Home & Living catalog.
- 22 categories across Living Room, Bedroom, Dining, Lighting, Decor and
  Outdoor.
- 28 frontend products: 18 simple, 6 configurable (18 variants), 2 grouped,
  2 bundle. SKUs prefixed `DRX-HL-`.
- 7 custom attributes (material, color_family, fabric, room, style,
  dimensions, weight_capacity), including visual and text swatches.
- 4 attribute sets: furniture, lighting, textiles, decor.
- Full EN_US + NL_NL translations for every category, product and
  attribute / option.
- `Test/Unit/CsvIntegrityTest` — sanity checks the on-disk content
  parses, hits expected counts, and has no dangling SKU references.

### `disrex/sample-data-theme-home-living-media`

#### Added

- Skeleton companion package: composer + registration + module.xml. Drop
  product photography into `_files/images/` (filenames listed in the theme's
  base CSVs); kept separate so the theme code package stays small.

### `tools/`

#### Added

- `theme-validator.php` — drift checker that compares each i18n CSV against
  the base layer and reports missing rows (warnings) and extra rows
  (errors). Runs against any theme package; intended for use in CI.
