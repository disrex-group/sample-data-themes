# Contributing rules — for Claude (and humans)

This file is loaded by Claude Code on every session in this repo. Humans
will get the same content via [`CONTRIBUTING.md`](CONTRIBUTING.md). Both
boil down to one idea: **commits drive the changelog and the release
notes**, so every commit needs to be parseable by `git-cliff`.

## Commit-message format

We use [Conventional Commits](https://www.conventionalcommits.org/) plus
a small set of project-specific rules. The `cliff.toml` config in the
repo root parses commits with these prefixes:

| Prefix | Goes into | Example |
|---|---|---|
| `feat:` | 🚀 Features | `feat(home-living): add Product Types category` |
| `fix:` | 🐛 Bug Fixes | `fix(core): cast rating_id to int` |
| `refactor:` | 🚜 Refactor | `refactor(core): extract ReassignsProductTypeCategory trait` |
| `docs:` | 📚 Documentation | `docs(readme): rewrite as install-focused quick-start` |
| `perf:` | ⚡ Performance | `perf(core): bulk-insert configurable variants` |
| `style:` | 🎨 Styling | `style(core): split long writeIfChanged() calls` |
| `test:` | 🧪 Testing | `test(core): cover FixtureRunner failure path` |
| `chore:` / `ci:` | ⚙️ Miscellaneous | `chore(tools): drop one-shot bootstrap scripts` |
| `revert:` | ◀️ Revert | `revert: feat(...)` |

Anything else lands in 💼 Other — usable but discouraged.

**Scope** in parentheses is required for code changes. Use the package
or area name: `core`, `home-living`, `home-living-media`, `tools`, `ci`,
`readme`. The release-notes template groups by scope, so consistent
scope names produce a cleaner changelog.

**Subject line**: imperative, no trailing period, ≤ 72 chars. Capital
first letter is auto-applied by the changelog template; the raw commit
can use either.

**Breaking changes**: prefix subject with `!` or add a `BREAKING CHANGE:`
footer. Both are picked up by git-cliff and rendered with a [breaking]
badge in the release notes.

```
feat(core)!: drop PHP 8.1 support

BREAKING CHANGE: minimum required PHP is now 8.2.
```

**Body**: optional but useful for explaining *why*. The body of every
commit gets rendered into the changelog if it includes "security" (which
moves the commit into the 🛡️ Security group), so use that word
deliberately.

**Skip prefixes** — these never appear in the changelog:
- `chore(release): prepare for ...`
- `chore(deps...)` (Dependabot bumps)
- `chore(pr): ...` / `chore(pull): ...`
- `ignore: ...`

## Releasing

The release flow is **two-stage** so package publication happens only
after a human-reviewed step:

1. **Tag & draft** (workflow_dispatch) — auto-bumps version from
   commits, generates notes from `git-cliff`, creates a DRAFT GitHub
   Release. No packages published yet.
2. **Publish** (manual click in GitHub UI) — fires `release.yml` which
   runs the CI gate, splits to the 3 mirror repos, and Packagist auto-
   pulls the new version.

Two equivalent paths to create a release. Both end at the same place:
a draft GitHub Release that you click **Publish** on to trigger the
package split + Packagist publish.

### Path A — GitHub UI (workflow_dispatch)

1. Go to **Actions → "Tag & Draft Release" → Run workflow**
2. Leave the version field empty to auto-bump via
   `git cliff --bumped-version`, or type a specific version
   (`v1.2.0` / `theme-home-living/v1.1.0`)
3. Workflow tags `master`, updates `CHANGELOG.md`, creates a draft
   release at github.com/.../releases
4. Review the draft → click **Publish release**
5. `release.yml` fires, splits to mirrors, Packagist publishes

### Path B — Local CLI (tools/release.sh)

```bash
# Auto-bump from commits:
tools/release.sh

# Or pin an explicit version:
tools/release.sh v1.2.0
tools/release.sh theme-home-living/v1.1.0
```

The script does the same work as the workflow:
- Refuses to run on a dirty tree or against an existing tag
- Updates `CHANGELOG.md` and commits the bump
- Pushes master and the annotated tag
- Generates notes for just the new version
- Creates a draft GitHub Release

Then review at github.com/.../releases and click **Publish release**.

Underlying one-liner if you'd rather not use the script:
```bash
git tag -a $(git cliff --bumped-version) \
        -m "Release $(git cliff --bumped-version)"
git push origin "$(git cliff --bumped-version)"
git cliff --current --strip header | \
    gh release create $(git describe --tags --abbrev=0) \
        --draft --notes-file -
```

### Tag conventions

- `v1.2.3` → every package gets this version (whole-monorepo release)
- `theme-home-living/v1.2.3` → publishes only `packages/theme-home-living`
- `core/v1.2.3` → publishes only `packages/core`
- `theme-home-living-media/v1.2.3` → media only

### Tag rules

- **Annotated tags only** (`git tag -a`), not lightweight (`git tag`)
- **Tags are immutable**: never force-push a tag that's already on
  Packagist or on a mirror repo. If a release has a bug, ship a patch
  release (v1.0.1, v1.0.2) instead.
- **Semver**: bump major for breaking changes; minor for new features;
  patch for fixes only. The prefix in the commit subject (feat/fix/etc.)
  is what `git-cliff` reads to make its bump suggestion.

### Tag rules

- **Annotated tags only** (`git tag -a`), not lightweight (`git tag`)
- **Tags are immutable**: never force-push a tag that's already on
  Packagist or on a mirror repo. If a release has a bug, ship a patch
  release (v1.0.1, v1.0.2) instead.
- **Semver**: bump major for breaking changes; minor for new features;
  patch for fixes only. The prefix in the commit subject (feat/fix/etc.)
  is what `git-cliff` reads to bump suggestion.

## CI gate

Every commit on `master` and every pull request runs:

- `composer cs-check` — PHPCS (PSR-12)
- `composer analyse` — PHPStan
- `composer test` — PHPUnit
- `php tools/theme-validator.php packages/theme-home-living` — i18n drift

All four must pass before a release tag will split. Run them locally
before pushing:

```bash
composer cs-check && composer analyse && composer test \
    && php tools/theme-validator.php packages/theme-home-living
```

## Docker dev environment notes

This repo can be developed standalone (composer + phpunit on the host)
or against a real Magento install. When using a Docker-based stack like
RollDev:

- Replace `bin/magento` with `roll magento`
- Replace `composer` with `roll composer`
- Use `roll db connect` for database access
