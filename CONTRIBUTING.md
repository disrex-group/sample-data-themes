# Contributing

Thanks for considering a contribution! This project is MIT licensed and
community-maintained.

## Sign-off

We use the [Developer Certificate of Origin](https://developercertificate.org/)
instead of a CLA. Every commit must be signed off:

```bash
git commit -s -m "Your message"
```

That appends a `Signed-off-by: Your Name <you@example.com>` trailer, asserting
that you have the right to submit the change under the project's licence.

## Branching and PRs

* Fork, then branch from `main`.
* One topic per PR. Keep diffs reviewable.
* Reference an issue if one exists. If not, open one first for non-trivial
  changes so we can agree on the approach before code is written.

## Code quality

Before pushing, run:

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan level 8
composer cs-check   # PHPCS / PSR-12
```

CI runs the same checks against every PR; please make sure they pass locally
first.

## Adding a new locale to an existing theme

Themes ship locale data under `_files/i18n/<locale>/`. To add e.g. German to
`theme-home-living`:

1. Copy `_files/i18n/en_US/` to `_files/i18n/de_DE/` and translate.
2. Add `'de_DE'` to the theme's `getSupportedLocales()` return value.
3. Run `php tools/theme-validator.php` to confirm parity with the base layer.
4. Open a PR.

No code changes in `core` are needed.

## Releasing

Maintainers tag releases with semantic versioning. Each package versions
independently — `core` 1.2 can ship while `theme-home-living` is at 2.4.
The full process (tag formats, monorepo split, Packagist publishing) is
documented in [RELEASING.md](RELEASING.md). Contributors don't need to
read it; maintainers do.
