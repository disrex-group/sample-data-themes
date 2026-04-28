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
- Entity importers: `ProductImporter`, `CategoryImporter`, `AttributeImporter`.
- PHPUnit, PHPStan (level 8) and PHPCS (PSR-12) tooling, plus GitHub Actions
  CI matrix across PHP 8.2 / 8.3 / 8.4.
