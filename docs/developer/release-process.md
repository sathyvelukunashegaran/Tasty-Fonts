# Release Process

Create, verify, tag, and publish stable, beta, and nightly plugin builds.

## Use This Page When

- you are cutting or maintaining the stable, beta, or nightly rails
- you need the expected changelog and tag flow
- you want to understand how GitHub releases and WordPress updates fit together

## Steps

### 1. Understand The Rails

- `main` stays on an `X.Y.Z-dev` base version (currently `1.9.0-dev` after the `1.8.0` stable tag).
- every push to `main` publishes a fresh nightly prerelease package stamped as `X.Y.Z-dev.YYYYMMDDHHMM`
- `release/X.Y` branches stabilize one line at a time
- beta tags use `X.Y.Z-beta.N`
- stable tags use `X.Y.Z`

### 2. Start From A Clean Branch

The release helpers assume:

- the worktree is clean
- the index is clean
- `CHANGELOG.md` has meaningful `Unreleased` notes ready when you are cutting a beta or stable tag

Branch expectations:

- `bin/release branch ...` runs from `main`
- `bin/release beta ...` runs from `main` when you are promoting the current dev state for the first beta, or from the matching `release/X.Y` for later beta tags
- `bin/release stable ...` runs from the matching `release/X.Y`

### 3. Cut A Release Branch

Use this only when you want to prepare a release branch before tagging beta:

```bash
bin/release branch 1.8.0
```

This helper:

- creates `release/1.8`
- updates the new branch to `1.8.0-beta.1`
- commits that beta-line start on `release/1.8`
- leaves `main` on its existing `1.8.0-dev` line
- pushes only the new release branch unless `--no-push` is used

### 4. Cut A Beta Or Stable Tag

First beta from the current dev state on `main`:

```bash
bin/release beta 1.8.0-beta.1
```

Later beta from the existing release branch:

```bash
git switch release/1.8
bin/release beta 1.8.0-beta.2
```

Stable example:

```bash
bin/release stable 1.8.0
```

For beta and stable tags, the helper:

- updates version markers in `plugin.php`
- promotes `Unreleased` notes into a dated changelog section
- runs the PHP syntax sweep
- runs `php tests/run.php`
- runs `node --test tests/js/*.test.cjs`
- creates the release commit
- creates the annotated tag
- pushes the current `release/X.Y` branch and the tag unless `--no-push` is used

The intended release lane is:

- keep `main` on the active `X.Y.Z-dev` line
- let pushes to `main` publish stamped nightly builds
- when you explicitly bless the latest dev state, run `bin/release beta <X.Y.Z-beta.N>` from `main`
- continue beta fixes on `release/X.Y`
- run `bin/release stable <X.Y.Z>` from `release/X.Y` when the beta line is ready

### 5. Let GitHub Actions Publish The Build

GitHub Actions now uses four workflows:

- `.github/workflows/quality.yml` runs the shared PHP lint, PHP tests, and JS contract tests
- `.github/workflows/release-stable.yml` publishes stable GitHub releases from `X.Y.Z` tags
- `.github/workflows/release-beta.yml` publishes GitHub prereleases from `X.Y.Z-beta.N` tags
- `.github/workflows/release-nightly.yml` publishes a prerelease on every push to `main`

Release-note sources:

- stable and beta releases use `CHANGELOG.md` through `bin/release-notes`
- nightly releases use commit messages since the previous nightly tag

Nightly publishing also prunes older nightly prereleases so the rail keeps only the most recent builds.

## GitHub Updater Implications

- The plugin advertises the GitHub repository as its `Update URI`
- the bundled updater now supports three channels: `Stable`, `Beta`, and `Nightly`
- installs choose the channel from `Settings -> Behavior -> Update Channel`
- normal WordPress update checks continue to handle upgrades
- when a selected channel points to an older version than the one installed now, the Behavior tab exposes a rollback reinstall action
- release asset naming still matters because the updater expects `tasty-fonts-<version>.zip`
- the packaged plugin directory remains `etch-fonts/` for update continuity

## Notes

- If user-facing behavior changes, update the docs alongside the changelog before tagging.
- Stable releases should be published as GitHub releases, not prereleases.
- Beta and nightly builds should be published as GitHub prereleases.
- The release workflows are the source of the installable GitHub ZIP assets used by the updater.
- Nightly builds require `plugin.php` on `main` to remain on an `X.Y.Z-dev` base version.

## Related Docs

- [Testing](testing.md)
- [Translations](translations.md)
- [Changelog](../../CHANGELOG.md)
