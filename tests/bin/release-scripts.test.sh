#!/usr/bin/env bash
# Tests for the release bin scripts: set-version, release-notes,
# promote-changelog, and nightly-version.
#
# Each script determines its repo root from its own location on disk.
# To keep tests isolated, we create a temp directory with a real `bin/`
# sub-directory whose entries are symlinks to the actual scripts.  Bash
# resolves BASH_SOURCE[0] to the symlink path, so every script computes
# repo_root = <temp-dir>, and reads/writes only files inside that directory.

set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"

# ── Test harness ─────────────────────────────────────────────────────────────

pass_count=0
fail_count=0

_pass() { echo "[PASS] $1"; pass_count=$(( pass_count + 1 )); }
_fail() { echo "[FAIL] $1"; fail_count=$(( fail_count + 1 )); echo "       $2" >&2; }

# ── Isolated test repo ────────────────────────────────────────────────────────

test_repo="$(mktemp -d)"
mkdir "${test_repo}/bin"
for script in "${repo_root}/bin/"*; do
    ln -s "${script}" "${test_repo}/bin/$(basename "${script}")"
done

release_repo_from_main="$(mktemp -d)"
release_repo_branch_flow="$(mktemp -d)"

cleanup() { rm -rf "${test_repo}" "${release_repo_from_main}" "${release_repo_branch_flow}"; }
trap cleanup EXIT

# ── Fixtures ──────────────────────────────────────────────────────────────────

seed_plugin() {
    local version="$1"
    cat > "${test_repo}/plugin.php" <<PHP
<?php
/*
Plugin Name: Test Plugin
Version: ${version}
*/
if (!defined('TASTY_FONTS_VERSION')) {
    define('TASTY_FONTS_VERSION', '${version}');
}
PHP
}

seed_changelog() {
    cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [Unreleased]

### Added

- Test feature.

## [1.0.0] - 2026-01-01

### Added

- Initial release.
CHANGELOG
}

seed_empty_unreleased_changelog() {
    cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [Unreleased]

## [1.0.0] - 2026-01-01

### Added

- Initial release.
CHANGELOG
}

seed_no_unreleased_changelog() {
    cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [1.0.0] - 2026-01-01

### Added

- Initial release.
CHANGELOG
}

seed_release_repo() {
    local repo="$1"
    local version="$2"

    mkdir -p "${repo}/bin" "${repo}/tests/bin" "${repo}/tests/js"

    for script in "${repo_root}/bin/"*; do
        ln -s "${script}" "${repo}/bin/$(basename "${script}")"
    done

    cat > "${repo}/plugin.php" <<PHP
<?php
/*
Plugin Name: Test Plugin
Version: ${version}
*/
if (!defined('TASTY_FONTS_VERSION')) {
    define('TASTY_FONTS_VERSION', '${version}');
}
PHP

    cat > "${repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [Unreleased]

### Fixed

- Test release note.
CHANGELOG

    cat > "${repo}/tests/run.php" <<'PHP'
<?php
exit(0);
PHP

    cat > "${repo}/tests/bin/release-scripts.test.sh" <<'SH'
#!/usr/bin/env bash
exit 0
SH
    chmod +x "${repo}/tests/bin/release-scripts.test.sh"

    cat > "${repo}/tests/js/smoke.test.cjs" <<'JS'
const test = require('node:test');

test('smoke', () => {});
JS

    git -C "${repo}" init -b main >/dev/null 2>&1
    git -C "${repo}" config user.name "Test User"
    git -C "${repo}" config user.email "test@example.com"
    git -C "${repo}" add .
    git -C "${repo}" commit -m "Initial dev state" >/dev/null 2>&1
}

# ── set-version ───────────────────────────────────────────────────────────────

seed_plugin "1.2.0-dev"
if "${test_repo}/bin/set-version" "1.2.0-beta.1" >/dev/null 2>&1; then
    _pass "set-version accepts beta format"
    version_header="$(sed -n 's/^Version: //p' "${test_repo}/plugin.php")"
    const_version="$(grep "define('TASTY_FONTS_VERSION'" "${test_repo}/plugin.php" \
        | sed "s/.*'TASTY_FONTS_VERSION', '//;s/').*//")"
    if [[ "$version_header" == "1.2.0-beta.1" && "$const_version" == "1.2.0-beta.1" ]]; then
        _pass "set-version updates both Version header and constant"
    else
        _fail "set-version updates both Version header and constant" \
            "header=${version_header}, const=${const_version}"
    fi
else
    _fail "set-version accepts beta format" "command exited non-zero"
fi

seed_plugin "1.2.0-dev"
if "${test_repo}/bin/set-version" "1.2.0" >/dev/null 2>&1; then
    _pass "set-version accepts stable format"
else
    _fail "set-version accepts stable format" "command exited non-zero"
fi

seed_plugin "1.2.0-dev"
if "${test_repo}/bin/set-version" "1.2.0-dev" >/dev/null 2>&1; then
    _pass "set-version accepts dev format"
else
    _fail "set-version accepts dev format" "command exited non-zero"
fi

seed_plugin "1.2.0-dev"
if "${test_repo}/bin/set-version" "1.2.0-dev.202604081200" >/dev/null 2>&1; then
    _pass "set-version accepts nightly (dev.TIMESTAMP) format"
else
    _fail "set-version accepts nightly (dev.TIMESTAMP) format" "command exited non-zero"
fi

seed_plugin "1.2.0-dev"
if ! "${test_repo}/bin/set-version" "1.2.0-rc.1" >/dev/null 2>&1; then
    _pass "set-version rejects unsupported rc format"
else
    _fail "set-version rejects unsupported rc format" "should have failed"
fi

if ! "${test_repo}/bin/set-version" "" >/dev/null 2>&1; then
    _pass "set-version rejects empty version argument"
else
    _fail "set-version rejects empty version argument" "should have failed"
fi

# ── release-notes ─────────────────────────────────────────────────────────────

seed_changelog

notes="$("${test_repo}/bin/release-notes" "1.0.0" 2>/dev/null)"
if [[ "$notes" == *"Initial release"* ]]; then
    _pass "release-notes extracts the matching version section"
else
    _fail "release-notes extracts the matching version section" "output: ${notes}"
fi

if [[ "$notes" != *"Test feature"* ]]; then
    _pass "release-notes does not include content from other sections"
else
    _fail "release-notes does not include content from other sections" "output: ${notes}"
fi

if ! "${test_repo}/bin/release-notes" "9.9.9" >/dev/null 2>&1; then
    _pass "release-notes fails when the requested version is absent"
else
    _fail "release-notes fails when the requested version is absent" "should have failed"
fi

cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [2.0.0] - 2026-04-08

## [1.0.0] - 2026-01-01

### Added

- Initial release.
CHANGELOG

if ! "${test_repo}/bin/release-notes" "2.0.0" >/dev/null 2>&1; then
    _pass "release-notes fails when the matched section body is empty"
else
    _fail "release-notes fails when the matched section body is empty" "should have failed"
fi

# ── promote-changelog ─────────────────────────────────────────────────────────

seed_changelog
if "${test_repo}/bin/promote-changelog" "1.1.0" "2026-04-08" >/dev/null 2>&1; then
    _pass "promote-changelog succeeds when Unreleased has content"
else
    _fail "promote-changelog succeeds when Unreleased has content" "command exited non-zero"
fi

promoted="$(<"${test_repo}/CHANGELOG.md")"

if [[ "$promoted" == *"## [1.1.0] - 2026-04-08"* ]]; then
    _pass "promote-changelog creates a dated versioned heading"
else
    _fail "promote-changelog creates a dated versioned heading" "changelog: ${promoted}"
fi

if [[ "$promoted" == *"## [Unreleased]"* ]]; then
    _pass "promote-changelog preserves the Unreleased heading"
else
    _fail "promote-changelog preserves the Unreleased heading" "changelog: ${promoted}"
fi

if [[ "$promoted" == *"Test feature"* ]]; then
    _pass "promote-changelog moves Unreleased body into the versioned section"
else
    _fail "promote-changelog moves Unreleased body into the versioned section" "changelog: ${promoted}"
fi

# Ensure the versioned section appears after [Unreleased], not before.
before_versioned="${promoted%%## \[1.1.0\]*}"
if [[ "$before_versioned" == *"## [Unreleased]"* ]]; then
    _pass "promote-changelog places the versioned section after [Unreleased]"
else
    _fail "promote-changelog places the versioned section after [Unreleased]" \
        "changelog: ${promoted}"
fi

seed_empty_unreleased_changelog
if ! "${test_repo}/bin/promote-changelog" "1.1.0" "2026-04-08" >/dev/null 2>&1; then
    _pass "promote-changelog fails when Unreleased section is empty"
else
    _fail "promote-changelog fails when Unreleased section is empty" "should have failed"
fi

seed_no_unreleased_changelog
if ! "${test_repo}/bin/promote-changelog" "1.1.0" "2026-04-08" >/dev/null 2>&1; then
    _pass "promote-changelog fails when there is no Unreleased section"
else
    _fail "promote-changelog fails when there is no Unreleased section" "should have failed"
fi

# ── nightly-version ───────────────────────────────────────────────────────────

seed_plugin "1.2.0-dev"
nightly_version="$("${test_repo}/bin/nightly-version" 2>/dev/null)"
if [[ "$nightly_version" =~ ^1\.2\.0-dev\.[0-9]{12}$ ]]; then
    _pass "nightly-version produces X.Y.Z-dev.TIMESTAMP from a dev base"
else
    _fail "nightly-version produces X.Y.Z-dev.TIMESTAMP from a dev base" \
        "output: ${nightly_version}"
fi

seed_plugin "1.2.0"
if ! "${test_repo}/bin/nightly-version" >/dev/null 2>&1; then
    _pass "nightly-version rejects a stable base version"
else
    _fail "nightly-version rejects a stable base version" "should have failed"
fi

seed_plugin "1.2.0-beta.1"
if ! "${test_repo}/bin/nightly-version" >/dev/null 2>&1; then
    _pass "nightly-version rejects a beta base version"
else
    _fail "nightly-version rejects a beta base version" "should have failed"
fi

# ── release ───────────────────────────────────────────────────────────────────

seed_release_repo "${release_repo_from_main}" "1.2.0-dev"
if (cd "${release_repo_from_main}" && bin/release beta 1.2.0-beta.1 --no-push >/dev/null 2>&1); then
    _pass "release beta can promote the current main dev state directly"
else
    _fail "release beta can promote the current main dev state directly" "command exited non-zero"
fi

if [[ "$(git -C "${release_repo_from_main}" branch --show-current)" == "release/1.2" ]] \
    && [[ -n "$(git -C "${release_repo_from_main}" tag --list '1.2.0-beta.1')" ]]; then
    _pass "release beta from main creates the release branch and tag"
else
    _fail "release beta from main creates the release branch and tag" \
        "branch=$(git -C "${release_repo_from_main}" branch --show-current), tag=$(git -C "${release_repo_from_main}" tag --list '1.2.0-beta.1')"
fi

release_branch_version="$(git -C "${release_repo_from_main}" show release/1.2:plugin.php | sed -n 's/^Version: //p')"
main_branch_version="$(git -C "${release_repo_from_main}" show main:plugin.php | sed -n 's/^Version: //p')"
if [[ "${release_branch_version}" == "1.2.0-beta.1" && "${main_branch_version}" == "1.2.0-dev" ]]; then
    _pass "release beta from main keeps main on the original dev version"
else
    _fail "release beta from main keeps main on the original dev version" \
        "release=${release_branch_version}, main=${main_branch_version}"
fi

seed_release_repo "${release_repo_branch_flow}" "1.4.0-dev"
if (cd "${release_repo_branch_flow}" && bin/release branch 1.4.0 --no-push >/dev/null 2>&1); then
    _pass "release branch prepares a beta branch without advancing main"
else
    _fail "release branch prepares a beta branch without advancing main" "command exited non-zero"
fi

prepared_release_version="$(git -C "${release_repo_branch_flow}" show release/1.4:plugin.php | sed -n 's/^Version: //p')"
prepared_main_version="$(git -C "${release_repo_branch_flow}" show main:plugin.php | sed -n 's/^Version: //p')"
prepared_tag="$(git -C "${release_repo_branch_flow}" tag --list '1.4.0-beta.1')"
if [[ "${prepared_release_version}" == "1.4.0-beta.1" && "${prepared_main_version}" == "1.4.0-dev" && -z "${prepared_tag}" ]]; then
    _pass "release branch leaves main untouched and does not tag yet"
else
    _fail "release branch leaves main untouched and does not tag yet" \
        "release=${prepared_release_version}, main=${prepared_main_version}, tag=${prepared_tag}"
fi

if (cd "${release_repo_branch_flow}" && bin/release beta 1.4.0-beta.1 --no-push >/dev/null 2>&1); then
    _pass "release beta still works from an existing release branch"
else
    _fail "release beta still works from an existing release branch" "command exited non-zero"
fi

if [[ -n "$(git -C "${release_repo_branch_flow}" tag --list '1.4.0-beta.1')" ]] \
    && grep -q "## \[1.4.0-beta.1\]" "${release_repo_branch_flow}/CHANGELOG.md"; then
    _pass "release beta from release branch tags and promotes the changelog"
else
    _fail "release beta from release branch tags and promotes the changelog" \
        "tag=$(git -C "${release_repo_branch_flow}" tag --list '1.4.0-beta.1')"
fi

# ── Summary ───────────────────────────────────────────────────────────────────

echo
echo "${pass_count} passed, ${fail_count} failed."

if (( fail_count > 0 )); then
    exit 1
fi
