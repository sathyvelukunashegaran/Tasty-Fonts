# Release Process

Create, verify, tag, and publish stable, beta, and nightly plugin builds.

## Use This Page When

- you are cutting or maintaining the stable, beta, or nightly rails
- you need the expected changelog and tag flow
- you want to understand how GitHub releases and WordPress updates fit together

## Steps

### 1. Understand The Rails

- `main` is the only branch used for normal beta and stable releases.
- `main` usually sits on an `X.Y.Z-dev` base version between release windows.
- every push to `main` publishes a fresh nightly prerelease package stamped as `X.Y.Z-dev.YYYYMMDDHHMM` when `plugin.php` is on a dev base.
- beta tags use `X.Y.Z-beta.N`
- stable tags use `X.Y.Z`
- `release/X.Y` branches are optional hotfix lanes created only if a shipped stable line needs an `X.Y.1+` patch later.

### 2. Start From A Clean Branch

The release helpers assume:

- the worktree is clean
- the index is clean
- `CHANGELOG.md` has meaningful `Unreleased` notes ready when you are cutting a beta or stable tag

Branch expectations:

- `bin/release beta ...` runs from `main`
- `bin/release stable ...` runs from `main`

### 3. Cut A Beta Or Stable Tag

First beta from the current dev state on `main`:

```bash
bin/release beta 1.8.0-beta.1
```

Later beta from the same line on `main`:

```bash
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
- pushes `main` and the tag unless `--no-push` is used

For stable tags, the helper also:

- computes the next minor dev base, such as `1.9.0 -> 1.10.0-dev`
- rewrites `plugin.php` to that next dev version
- creates a follow-up `Start X.Y.0-dev` commit on `main`

The intended release lane is:

- keep `main` on the active release line while preparing beta and stable tags
- let pushes to `main` publish stamped nightly builds whenever `plugin.php` is on an `X.Y.Z-dev` base
- when you explicitly bless the latest dev state, run `bin/release beta <X.Y.Z-beta.N>` from `main`
- continue beta fixes on `main`
- run `bin/release stable <X.Y.Z>` from `main` when the beta line is ready
- after the stable tag is cut, let the helper reopen `main` on the next minor dev line
- if you later need `X.Y.1`, create `release/X.Y` from the stable tag and patch there

### 4. Let GitHub Actions Publish The Build

GitHub Actions now uses four workflows:

- `.github/workflows/quality.yml` runs the shared PHP lint, PHP tests, and JS contract tests
- `.github/workflows/release-stable.yml` publishes stable GitHub releases from `X.Y.Z` tags
- `.github/workflows/release-beta.yml` publishes GitHub prereleases from `X.Y.Z-beta.N` tags
- `.github/workflows/release-nightly.yml` publishes a prerelease on every push to `main`

Release-note sources:

- stable and beta releases use `CHANGELOG.md` through `bin/release-notes`
- nightly releases use commit messages since the previous nightly tag

Nightly publishing also prunes older nightly prereleases so the rail keeps only the most recent builds, and it skips cleanly when `main` is intentionally on a beta or stable version during a release window.

## GitHub Updater Implications

- The plugin advertises the GitHub repository as its `Update URI`
- the bundled updater now supports three channels: `Stable`, `Beta`, and `Nightly`
- installs choose the channel from `Settings -> Behavior -> Update Channel`
- normal WordPress update checks continue to handle upgrades
- when a selected channel points to an older version than the one installed now, the Behavior tab exposes a rollback reinstall action
- release asset naming still matters because the updater expects `tasty-fonts-<version>.zip`
- the packaged plugin directory is `tasty-fonts/` to match the current product branding

## Notes

- If user-facing behavior changes, update the docs alongside the changelog before tagging.
- Stable releases should be published as GitHub releases, not prereleases.
- Beta and nightly builds should be published as GitHub prereleases.
- The release workflows are the source of the installable GitHub ZIP assets used by the updater.
- Nightly builds publish only when `plugin.php` on `main` is on an `X.Y.Z-dev` base version.

## Related Docs

- [Testing](Testing)
- [Translations](Translations)
- [Changelog](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/blob/main/CHANGELOG.md)
