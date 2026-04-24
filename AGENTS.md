# Tasty Fonts Agent Guide

Use this file as the shared repo-level instruction guide for AI coding agents working in this plugin. Keep it concise and repo-specific. Put deep architecture detail in the wiki, not here.

## Project Overview

- Single WordPress plugin
- PHP 8.1+, Node.js 22+, WordPress 6.5+
- No build step. `composer install` and `npm install`/`npm ci` are only for dev tooling and CI, not runtime plugin use.
- Entry point: `plugin.php`
- Main PHP code: `includes/`
- Admin UI: PHP templates in `includes/Admin/Renderer/templates/` plus vanilla JS in `assets/js/`
- Tests: PHP harness via `tests/run.php`, JS contract tests via `tests/js/*.test.cjs`
- Quality and release helpers: `bin/run-jscpd`, `bin/setup-git-hooks`, `bin/release`, `bin/nightly-version`, `tests/bin/release-scripts.test.sh`

## Principles

- Read broadly before editing. Understand the surrounding service flow, not just one method.
- Keep changes small, readable, and easy to verify.
- Verify current behavior before deciding something is broken.
- Match existing WordPress-oriented patterns and naming.
- Prefer the simplest change that preserves the current architecture.
- Do not add dependencies casually.
- Update contributor-facing docs when commands, workflows, or behavior change.

## Communication

- Be concise and specific.
- State what changed, how it was verified, and any remaining risk.
- Do not make the user reconstruct intent from a diff.
- If a request conflicts with the current architecture or expected behavior, say so clearly.

## Workflow

1. Read the relevant code paths and tests first.
2. Check `.agents/lessons.md` before larger changes or when revisiting a familiar problem.
3. For longer or multi-step work, create an optional note in `.agents/tasks/todo-(name).md`.
4. Make the smallest practical change.
5. Run the most targeted verification first, then broader checks if the change warrants it.
6. Expect `composer phpstan`, optional Stylelint when npm dependencies are installed, and `jscpd` to run on commit through the shared pre-commit hook once `bin/setup-git-hooks` has been run in the clone.
7. Summarize behavior changes and verification before handing work back.

## Verification

- Start with the narrowest useful check.
- Prefer the existing harnesses over inventing one-off scripts.
- For broader or release-sensitive changes, run the full pre-release sequence.
- For admin, runtime, provider, storage, or updater changes, verify behavior in WordPress terms, not just by static inspection.
- For structural refactors or repeated code movement, run `bin/run-jscpd` explicitly even if the hook will also catch it on commit.

Targeted checks:

```bash
composer install
npm ci
composer phpstan
php tests/run.php
node --test tests/js/*.test.cjs
npm run lint:css
bash tests/bin/release-scripts.test.sh
bin/run-jscpd
find . -name '*.php' -not -path './output/*' -not -path './tmp/*' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

Full pre-release sequence:

```bash
composer install && npm ci && composer phpstan && npm run lint:css && find . -name '*.php' -not -path './output/*' -not -path './tmp/*' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l && php tests/run.php && node --test tests/js/*.test.cjs && bash tests/bin/release-scripts.test.sh && bin/run-jscpd
```

## Repo-Specific Hotspots

- `Plugin` wires services manually. There is no DI container. When changing construction or lifecycle behavior, inspect `plugin.php` and `includes/Plugin.php` together.
- The delivery-profile model is central. Families can hold multiple delivery profiles, and `active_delivery_id` controls what runtime serves.
- Generated CSS and runtime planning are common regression zones. Import, storage, publish-state, and provider changes can affect generated CSS, preload/preconnect behavior, and external stylesheet enqueue behavior together.
- Provider changes often span multiple layers: client fetch/parsing, import persistence, catalog output, generated CSS, runtime planning, and Block Editor sync.
- Admin UI changes usually span templates, renderer helpers, admin JS, and REST/controller behavior together.
- Release and updater changes must stay aligned with `plugin.php`, `CHANGELOG.md`, `bin/release`, and the release script tests.
- The packaged plugin directory used for updates is stable; do not casually change packaging assumptions.

## Where To Look First

- Provider/import work:
  - `includes/Google/`, `includes/Bunny/`, `includes/Adobe/`
  - `tests/cases/provider-clients.php`
  - `tests/cases/imports-library.php`
  - `tests/cases/settings-assets-runtime.php`
- Generated CSS/runtime/storage work:
  - `includes/Fonts/AssetService.php`
  - `includes/Fonts/CssBuilder.php`
  - `includes/Fonts/RuntimeAssetPlanner.php`
  - `includes/Support/Storage.php`
  - `tests/cases/css-storage-catalog.php`
  - `tests/cases/settings-assets-runtime.php`
- Admin/rendering/API work:
  - `includes/Admin/`
  - `includes/Api/RestController.php`
  - `assets/js/admin.js`
  - `assets/js/admin-contracts.js`
  - `tests/cases/admin-renderer.php`
  - `tests/cases/admin-controller-rest.php`

## Test Harness Notes

- Add PHP tests by appending closures to the relevant `tests/cases/*.php` file.
- If you add a new PHP case file, `require_once` it from `tests/run.php`.
- Use the existing harness helpers instead of building ad hoc test bootstraps.
- JS contract tests use Node's built-in test runner. Keep shared helpers DOM-free when they are meant to run there.

## Commands

Quality and hooks:

```bash
composer install
npm ci
composer phpstan
npm run lint:css
bin/run-jscpd
bin/setup-git-hooks
```

Release helpers:

```bash
bin/nightly-version
bin/release beta <X.Y.Z-beta.N>
bin/release stable <X.Y.Z>
```

Translations:

```bash
wp i18n make-json languages/ languages/
```

## References

- `wiki/Architecture.md`
- `wiki/Testing.md`
- `wiki/Local-Setup.md`
- `wiki/Release-Process.md`
- `wiki/Provider-Google-Fonts.md`
- `wiki/Provider-Bunny-Fonts.md`
- `wiki/Provider-Adobe-Fonts.md`

## Notes For Other Agent Systems

- `CLAUDE.md` imports this file so Claude Code can reuse the same project instructions.
- `.agents/tasks/` is optional support for larger tasks, not a required workflow for every edit.
- `.agents/lessons.md` is a short shared pitfalls file, not a replacement for deeper docs.
