# Releasing

This monorepo publishes one composer package per `packages/*` directory.
Releases are **tag-driven**: pushing a tag in the right shape triggers
[`.github/workflows/release.yml`](.github/workflows/release.yml), which
gates on CI, splits the affected package into its read-only mirror repo,
and lets the Packagist GitHub-app integration publish the new version
automatically.

## One-time setup (per published package)

For each package you intend to publish:

1. **Create the mirror repository** under the `disrex-group` org. The
   repo name follows the package directory name with the `sample-data-`
   prefix that Packagist users see:

   | Source directory                       | Mirror repository                                    |
   |----------------------------------------|------------------------------------------------------|
   | `packages/core`                        | `disrex-group/sample-data-themes-core`               |
   | `packages/theme-home-living`           | `disrex-group/sample-data-theme-home-living`         |
   | `packages/theme-home-living-media`     | `disrex-group/sample-data-theme-home-living-media`   |

   The mirror repos contain only the files inside that package directory.
   Do **not** push to them by hand — the release workflow is the only
   writer.

2. **Submit the mirror repo to Packagist** (one-time per package). On
   packagist.org choose *Submit*, paste the mirror's GitHub URL, and let
   Packagist crawl it. Then enable the GitHub-app integration so future
   tags auto-update.

3. **Configure secrets and variables** on the *monorepo* (this repo) under
   *Settings → Secrets and variables → Actions*:

   | Kind     | Name                  | Required | Purpose                                                                  |
   |----------|-----------------------|----------|--------------------------------------------------------------------------|
   | Secret   | `DISREX_BOT_TOKEN`    | yes      | GitHub PAT with `repo` scope; used by the split action to push to mirrors. |
   | Variable | `PACKAGIST_USERNAME`  | optional | If you want an explicit Packagist API ping as a backup to the GitHub app.|
   | Secret   | `PACKAGIST_API_TOKEN` | optional | Pairs with `PACKAGIST_USERNAME`.                                         |

   The `DISREX_BOT_TOKEN` should belong to a dedicated bot user that has
   write access on every mirror repo — not a maintainer's personal token.

## Cutting a release

Each composer package versions independently (per the spec in §2). Tag
and push from `master` (the default branch) after CI is green.

### Per-package release (recommended)

```bash
# Update the relevant CHANGELOG entry on a PR, merge it.
git checkout master
git pull --ff-only

# Tag the package and version. The directory name in `packages/`
# is the prefix; the version is a strict semver tag.
git tag core/v1.0.0
git push origin core/v1.0.0

# theme-home-living can ship at its own pace:
git tag theme-home-living/v1.0.0
git push origin theme-home-living/v1.0.0
```

### All-packages release

For a coordinated release where every package gets the same version:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The workflow will split every `packages/*` into its mirror with the same
`v1.0.0` tag. Use this sparingly — most of the time individual packages
move on their own schedules.

## What runs

When you push a release tag:

1. **`gate` job** — re-runs the same CI checks the PR went through:
   `composer validate` per package, PHPCS (PSR-12), PHPStan, PHPUnit,
   and the theme-validator. If any fail, the release does not proceed.
2. **`resolve` job** — parses the tag and outputs the version + the list
   of affected package directories.
3. **`split` job** (matrix, one per package) — uses
   [`danharrin/monorepo-split-github-action`](https://github.com/danharrin/monorepo-split-github-action)
   to write a clean subtree of `packages/<name>/` into the corresponding
   mirror repo and push the tag.
4. **`notify-packagist` job** (optional) — explicit ping to Packagist's
   update API if you set the variables above. Redundant when the
   GitHub-Packagist app is connected; useful as a backup.

## Yanking a bad release

If a published version turns out to be broken:

1. Open an issue describing the breakage.
2. Mark the affected version as abandoned on Packagist (Packagist UI
   → *Mark as abandoned* on the broken release).
3. Cut a new patch release that fixes the issue. Don't delete tags from
   the mirror — composer caches break that.

## Versioning rules

* SemVer 2.0.0 across every package.
* Breaking changes to public PHP API (anything `@api` annotated, the
  `ThemeInterface` / `FixtureInterface` contracts, or the CSV column
  layouts under `_files/base`) require a major bump.
* Adding a new locale, fixture type, or theme is a minor bump.
* Translation tweaks, doc fixes, internal refactors are patches.

When in doubt, run `composer install --update-with-all-dependencies` from
a downstream project before tagging — anything that surprises composer
deserves a major bump.
