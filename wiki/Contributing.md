# Contributing

Thanks for contributing to Tasty Custom Fonts.

This project keeps its contributor workflow intentionally lightweight: there is no Composer install step, no npm install step, and the core verification commands run directly from the repository.

## Prerequisites

- PHP 8.1+
- Node.js 22+
- A local WordPress install for manual plugin testing

## Local Setup

1. Clone the repository.
2. Copy the repository folder to `wp-content/plugins/tasty-fonts/` inside your local WordPress install.
3. Activate `Tasty Custom Fonts` from the WordPress admin.

Any local WordPress stack is fine. Common choices:

- `wp-env`
- LocalWP
- DDEV

See [Local Setup](Local-Setup) for examples, manual install notes, and common local-environment pitfalls.

## Verification Commands

Run these before you open a pull request:

```bash
find . -name '*.php' -not -path './output/*' -print0 | xargs -0 -n1 php -l
php tests/run.php
node --test tests/js/*.test.cjs
```

What each command covers:

- `php -l` catches PHP syntax errors across the repository.
- `php tests/run.php` runs the self-contained PHP harness for repository, import, runtime, admin, and updater behavior.
- `node --test tests/js/*.test.cjs` runs the JavaScript contract tests for shared admin and canvas helpers.

The PHP and JavaScript test harnesses do not require a full WordPress install, so you can run them directly from the repo checkout.

## Branch And PR Flow

- Branch from `main`.
- Open pull requests against `main`.
- Keep changes scoped and readable.
- Update docs when behavior, workflows, or contributor-facing commands change.

## Coding And Commit Expectations

- Match the WordPress coding conventions already used in the codebase.
- Prefer small, focused changes over broad refactors.
- Use meaningful commit messages that describe the behavior change or maintenance task.
- Do not add new dependencies without a clear rationale and prior discussion.

## Open An Issue First When

Open an issue before writing code if the change is large, introduces a new feature, or changes the release flow.

For smaller fixes, docs updates, and targeted test coverage, a pull request can usually start directly.

## Good First Contributions

Well-scoped first contributions include:

- Adding test coverage for an untested utility under `includes/Support/`
- Refreshing the POT template after a string change
- Adding a troubleshooting note based on a resolved GitHub issue
- Fixing a typo or outdated example in the developer docs

Browse issues labeled [good first issue](https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/issues?q=is%3Aissue%20state%3Aopen%20label%3A%22good%20first%20issue%22) for current entry points.

## Key Developer Docs

- [Documentation Hub](Home)
- [Architecture](Architecture)
- [Testing](Testing)
- [Local Setup](Local-Setup)
- [Release Process](Release-Process)
- [Translations](Translations)

## Security

Please do not report security issues in public issues or pull requests. Follow the process in [Security Policy](Security).
