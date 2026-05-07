#!/usr/bin/env bash
#
# Local release helper — equivalent to running the "Tag & Draft
# Release" workflow from GitHub Actions, but driven from your shell.
#
# Both flows produce the same outcome:
#   - master is up-to-date with a CHANGELOG.md bump for the new version
#   - an annotated tag points at that commit
#   - a draft GitHub Release exists at github.com/.../releases
#   - clicking "Publish" on the draft fires release.yml which splits
#     packages to their mirrors and triggers Packagist publication
#
# Usage:
#   tools/release.sh                  # auto-bump from commits
#   tools/release.sh v1.2.0           # explicit version
#   tools/release.sh theme-home-living/v1.1.0  # per-package release
#
# Requires: git, git-cliff, gh (GitHub CLI), authenticated to push.

set -euo pipefail

# Resolve script's repo root regardless of where the script is invoked.
ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# ---- 1. Determine version ---------------------------------------------------
if [ $# -ge 1 ] && [ -n "${1:-}" ]; then
    VERSION="$1"
    echo "› Using user-supplied version: $VERSION"
else
    VERSION=$(git cliff --config cliff.toml --bumped-version)
    echo "› Auto-bumped from commits: $VERSION"
fi

# Refuse to clobber an existing tag.
if git rev-parse "refs/tags/$VERSION" >/dev/null 2>&1; then
    echo "✗ Tag $VERSION already exists. Pick a higher version." >&2
    exit 1
fi

# ---- 2. Sanity checks -------------------------------------------------------
if [ -n "$(git status --porcelain)" ]; then
    echo "✗ Working tree is not clean. Commit or stash first." >&2
    git status --short >&2
    exit 1
fi

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "master" ]; then
    echo "⚠ You're on '$CURRENT_BRANCH', not master. Continue? [y/N]"
    read -r REPLY
    [[ "$REPLY" =~ ^[Yy]$ ]] || exit 1
fi

# ---- 3. Sync cross-package require constraints -----------------------------
# When core is bumped to a new minor (e.g. v1.1.0), every sibling
# package whose runtime depends on a feature added in that minor must
# bump its `disrex/sample-data-themes-core: ^X.Y` constraint to match —
# otherwise end-users running `composer update` get the new theme +
# old core, which fails at autoload time.
#
# Strategy: parse the new version's MAJOR.MINOR, walk every
# packages/*/composer.json that requires a sibling disrex/* package,
# and replace `^X.Y` with the bumped `^MAJOR.MINOR`. Patch-only
# bumps (X.Y.Z, same MAJOR.MINOR) are no-ops.
if [[ "$VERSION" =~ ^v?([0-9]+)\.([0-9]+)\.[0-9]+ ]]; then
    NEW_MAJOR_MINOR="${BASH_REMATCH[1]}.${BASH_REMATCH[2]}"
    echo "› Syncing cross-package require constraints to ^${NEW_MAJOR_MINOR}…"
    BUMPED=0
    for pkg_json in packages/*/composer.json; do
        # Skip the core package — only sibling packages need bumping
        if grep -q '"name": "disrex/sample-data-themes-core"' "$pkg_json"; then
            continue
        fi
        # In-place rewrite: any "disrex/sample-data-*": "^N.M" → "^$NEW_MAJOR_MINOR"
        if grep -qE '"disrex/sample-data-[^"]+": "\^[0-9]+\.[0-9]+"' "$pkg_json"; then
            python3 -c "
import json, sys
path = '$pkg_json'
new = '^$NEW_MAJOR_MINOR'
data = json.load(open(path))
changed = False
for k, v in data.get('require', {}).items():
    if k.startswith('disrex/sample-data-') and v.startswith('^') and v != new:
        data['require'][k] = new
        changed = True
        print(f'  {path}: {k} → {new}')
if changed:
    json.dump(data, open(path, 'w'), indent=4)
    open(path, 'a').write('\n')
"
            BUMPED=1
        fi
    done
    if [ $BUMPED -eq 1 ] && ! git diff --quiet packages/*/composer.json; then
        git add packages/*/composer.json
        git commit -m "chore(deps): bump disrex/* sibling constraints to ^$NEW_MAJOR_MINOR"
        git push origin "$CURRENT_BRANCH"
    else
        echo "  No sibling constraints needed bumping."
    fi
fi

# ---- 4. Update CHANGELOG.md ------------------------------------------------
echo "› Updating CHANGELOG.md…"
git cliff --config cliff.toml --tag "$VERSION" --output CHANGELOG.md
if ! git diff --quiet CHANGELOG.md; then
    git add CHANGELOG.md
    git commit -m "docs(changelog): bump for $VERSION"
    git push origin "$CURRENT_BRANCH"
else
    echo "  CHANGELOG.md unchanged — skipping commit."
fi

# ---- 5. Generate release notes (BEFORE tagging) ----------------------------
# git-cliff's `--unreleased` reads commits past the latest tag. If we tag
# first, those commits become "released" and the notes come back empty.
NOTES_FILE=$(mktemp)
trap 'rm -f "$NOTES_FILE"' EXIT
git cliff --config cliff.toml --tag "$VERSION" --unreleased --strip header \
    > "$NOTES_FILE"
if [ ! -s "$NOTES_FILE" ]; then
    echo "✗ Generated release notes are empty — aborting before tagging." >&2
    exit 1
fi
echo "› Release notes preview:"
sed 's/^/    /' "$NOTES_FILE"

# ---- 6. Tag and push --------------------------------------------------------
echo "› Tagging $VERSION at HEAD…"
git tag -a "$VERSION" -m "Release $VERSION"
git push origin "$VERSION"

# ---- 7. Create the draft release -------------------------------------------
echo "› Creating draft release…"
gh release create "$VERSION" \
    --draft \
    --title "$VERSION" \
    --notes-file "$NOTES_FILE"

# ---- 8. Summary -------------------------------------------------------------
REPO=$(gh repo view --json nameWithOwner -q .nameWithOwner)
echo
echo "✓ Draft release created: https://github.com/$REPO/releases/tag/$VERSION"
echo
echo "Next: review the notes, then click 'Publish release' to fire"
echo "the package split + Packagist publish."
