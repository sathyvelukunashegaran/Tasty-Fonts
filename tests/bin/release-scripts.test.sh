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
release_repo_from_beta="$(mktemp -d)"
archive_repo="$(mktemp -d)"

cleanup() { rm -rf "${test_repo}" "${release_repo_from_main}" "${release_repo_from_beta}" "${archive_repo}"; }
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

seed_archive_repo() {
    local repo="$1"

    mkdir -p \
        "${repo}/assets/js" \
        "${repo}/wiki" \
        "${repo}/languages" \
        "${repo}/screenshots" \
        "${repo}/tests" \
        "${repo}/.github/workflows"

    cp "${repo_root}/.gitattributes" "${repo}/.gitattributes"

    cat > "${repo}/plugin.php" <<'PHP'
<?php
/*
Plugin Name: Test Plugin
Version: 1.0.0
*/
PHP

    cat > "${repo}/uninstall.php" <<'PHP'
<?php
PHP

    cat > "${repo}/readme.txt" <<'TXT'
=== Test Plugin ===
TXT

    cat > "${repo}/README.md" <<'MD'
# Test Plugin
MD

    cat > "${repo}/CHANGELOG.md" <<'MD'
# Changelog
MD

    cat > "${repo}/SECURITY.md" <<'MD'
# Security
MD

    cat > "${repo}/CODE_OF_CONDUCT.md" <<'MD'
# Code of Conduct
MD

    cat > "${repo}/CONTRIBUTING.md" <<'MD'
# Contributing
MD

    cat > "${repo}/.editorconfig" <<'TXT'
root = true
TXT

    cat > "${repo}/assets/js/admin.js" <<'JS'
console.log('runtime');
JS

    cat > "${repo}/wiki/guide.md" <<'MD'
# Guide
MD

    cat > "${repo}/languages/tasty-fonts-en_US-tasty-fonts-admin.json" <<'JSON'
{}
JSON

    cat > "${repo}/languages/tasty-fonts.pot" <<'TXT'
msgid ""
TXT

    touch "${repo}/screenshots/.gitkeep"
    touch "${repo}/tests/run.php"
    touch "${repo}/.github/workflows/ci.yml"

    git -C "${repo}" init -b main >/dev/null 2>&1
    git -C "${repo}" config user.name "Test User"
    git -C "${repo}" config user.email "test@example.com"
    git -C "${repo}" add .
    git -C "${repo}" commit -m "Archive fixture" >/dev/null 2>&1
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

seed_plugin "6.0.1-beta.2"
if "${test_repo}/bin/set-version" "6.0.1" >/dev/null 2>&1; then
    _pass "set-version replaces pre-release dev version string"
    version_header="$(sed -n 's/^Version: //p' "${test_repo}/plugin.php")"
    const_version="$(grep "define('TASTY_FONTS_VERSION'" "${test_repo}/plugin.php" \
        | sed "s/.*'TASTY_FONTS_VERSION', '//;s/').*//")"
    if [[ "$version_header" == "6.0.1" && "$const_version" == "6.0.1" ]]; then
        _pass "set-version replaces full pre-release token including suffix"
    else
        _fail "set-version replaces full pre-release token including suffix" \
            "header=${version_header}, const=${const_version}"
    fi
else
    _fail "set-version replaces pre-release dev version string" "command exited non-zero"
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

# Write a changelog with Windows-style CRLF line endings.
printf '# Changelog\r\n\r\n## [3.0.0] - 2026-04-11\r\n\r\n### Added\r\n\r\n- CRLF feature.\r\n\r\n## [2.0.0] - 2026-01-01\r\n\r\n### Added\r\n\r\n- Earlier release.\r\n' \
    > "${test_repo}/CHANGELOG.md"

crlf_notes="$("${test_repo}/bin/release-notes" "3.0.0" 2>/dev/null)"
if [[ "$crlf_notes" == *"CRLF feature"* ]]; then
    _pass "release-notes handles Windows CRLF line endings"
else
    _fail "release-notes handles Windows CRLF line endings" "output: ${crlf_notes}"
fi

cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [4.0.0] - 2026-04-11

### Changed

- Promoted the validated `4.0.0` line to stable.

## [4.0.0-beta.2] - 2026-04-10

### Fixed

- Beta follow-up fix.

## [4.0.0-beta.1] - 2026-04-09

### Added

- Beta launch feature.
CHANGELOG

stable_notes="$("${test_repo}/bin/release-notes" "4.0.0" 2>/dev/null)"
if [[ "$stable_notes" == *"Promoted the validated \`4.0.0\` line to stable."* ]] \
    && [[ "$stable_notes" == *"Beta launch feature."* ]] \
    && [[ "$stable_notes" == *"Beta follow-up fix."* ]]; then
    _pass "release-notes expands promotion-only stable entries with matching beta sections"
else
    _fail "release-notes expands promotion-only stable entries with matching beta sections" \
        "output: ${stable_notes}"
fi

cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [5.0.0] - 2026-04-11

### Added

- Stable-only feature.

## [5.0.0-beta.1] - 2026-04-10

### Fixed

- Beta-only fix.
CHANGELOG

detailed_stable_notes="$("${test_repo}/bin/release-notes" "5.0.0" 2>/dev/null)"
if [[ "$detailed_stable_notes" == *"Stable-only feature."* ]] \
    && [[ "$detailed_stable_notes" != *"Beta-only fix."* ]]; then
    _pass "release-notes keeps detailed stable sections unchanged"
else
    _fail "release-notes keeps detailed stable sections unchanged" \
        "output: ${detailed_stable_notes}"
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

cat > "${test_repo}/CHANGELOG.md" <<'CHANGELOG'
# Changelog

## [Unreleased]

### Changed

- Stable-only follow-up.

## [2.0.0-beta.2] - 2026-04-10

### Fixed

- Beta fix two.

## [2.0.0-beta.1] - 2026-04-09

### Added

- Beta launch feature.

### Fixed

- Beta fix one.
CHANGELOG

if "${test_repo}/bin/promote-changelog" "2.0.0" "2026-04-11" >/dev/null 2>&1; then
    merged_stable="$(<"${test_repo}/CHANGELOG.md")"
    if [[ "$merged_stable" == *"## [2.0.0] - 2026-04-11"* \
        && "$merged_stable" == *"### Added"* \
        && "$merged_stable" == *"- Beta launch feature."* \
        && "$merged_stable" == *"### Changed"* \
        && "$merged_stable" == *"- Stable-only follow-up."* \
        && "$merged_stable" == *"### Fixed"* \
        && "$merged_stable" == *"- Beta fix one."* \
        && "$merged_stable" == *"- Beta fix two."* ]]; then
        _pass "promote-changelog merges same-line beta sections into stable releases"
    else
        _fail "promote-changelog merges same-line beta sections into stable releases" \
            "changelog: ${merged_stable}"
    fi
else
    _fail "promote-changelog merges same-line beta sections into stable releases" "command exited non-zero"
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

# A second promote-changelog call for the same version should fail because the
# versioned section [1.1.0] already exists in the file.
seed_changelog
"${test_repo}/bin/promote-changelog" "1.1.0" "2026-04-08" >/dev/null 2>&1 || true
if ! "${test_repo}/bin/promote-changelog" "1.1.0" "2026-04-08" >/dev/null 2>&1; then
    _pass "promote-changelog rejects duplicate versioned section"
else
    _fail "promote-changelog rejects duplicate versioned section" "should have failed on second call"
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

# ── archive export rules ──────────────────────────────────────────────────────

seed_archive_repo "${archive_repo}"
archive_entries="$(
    cd "${archive_repo}" \
        && git archive --format=tar --worktree-attributes HEAD \
        | tar -tf -
)"

if [[ "${archive_entries}" == *"plugin.php"* \
    && "${archive_entries}" == *"uninstall.php"* \
    && "${archive_entries}" == *"assets/js/admin.js"* \
    && "${archive_entries}" == *"languages/tasty-fonts-en_US-tasty-fonts-admin.json"* ]]; then
    _pass "git archive keeps runtime plugin files in the distributable"
else
    _fail "git archive keeps runtime plugin files in the distributable" \
        "entries=${archive_entries}"
fi

if [[ "${archive_entries}" != *"README.md"* \
    && "${archive_entries}" != *"CHANGELOG.md"* \
    && "${archive_entries}" != *"SECURITY.md"* \
    && "${archive_entries}" != *"CONTRIBUTING.md"* \
    && "${archive_entries}" != *"CODE_OF_CONDUCT.md"* \
    && "${archive_entries}" != *"readme.txt"* \
    && "${archive_entries}" != *"wiki/guide.md"* \
    && "${archive_entries}" != *"screenshots/.gitkeep"* \
    && "${archive_entries}" != *"languages/tasty-fonts.pot"* \
    && "${archive_entries}" != *".github/workflows/ci.yml"* \
    && "${archive_entries}" != *"tests/run.php"* ]]; then
    _pass "git archive excludes repository-only files from the distributable"
else
    _fail "git archive excludes repository-only files from the distributable" \
        "entries=${archive_entries}"
fi

# ── release ───────────────────────────────────────────────────────────────────

seed_release_repo "${release_repo_from_main}" "1.2.0-dev"
if (cd "${release_repo_from_main}" && bin/release beta 1.2.0-beta.1 --no-push >/dev/null 2>&1); then
    _pass "release beta can promote the current main dev state directly"
else
    _fail "release beta can promote the current main dev state directly" "command exited non-zero"
fi

beta_branch_name="$(git -C "${release_repo_from_main}" branch --show-current)"
beta_tag="$(git -C "${release_repo_from_main}" tag --list '1.2.0-beta.1')"
beta_main_version="$(git -C "${release_repo_from_main}" show main:plugin.php | sed -n 's/^Version: //p')"
if [[ "${beta_branch_name}" == "main" && -n "${beta_tag}" && "${beta_main_version}" == "1.2.0-beta.1" ]]; then
    _pass "release beta from main keeps the tagged beta state on main"
else
    _fail "release beta from main keeps the tagged beta state on main" \
        "branch=${beta_branch_name}, tag=${beta_tag}, main=${beta_main_version}"
fi

if grep -q "## \[1.2.0-beta.1\]" "${release_repo_from_main}/CHANGELOG.md"; then
    _pass "release beta from main promotes the changelog on main"
else
    _fail "release beta from main promotes the changelog on main" \
        "changelog=$(<"${release_repo_from_main}/CHANGELOG.md")"
fi

if ! (cd "${release_repo_from_main}" && bin/release stable 1.3.0 --no-push >/dev/null 2>&1); then
    _pass "release stable rejects a mismatched release line on main"
else
    _fail "release stable rejects a mismatched release line on main" "should have failed"
fi

seed_release_repo "${release_repo_from_beta}" "1.4.0-beta.2"
if (cd "${release_repo_from_beta}" && bin/release stable 1.4.0 --no-push >/dev/null 2>&1); then
    _pass "release stable can promote the current beta line on main"
else
    _fail "release stable can promote the current beta line on main" "command exited non-zero"
fi

stable_tag="$(git -C "${release_repo_from_beta}" tag --list '1.4.0')"
release_commit_version="$(git -C "${release_repo_from_beta}" show 1.4.0:plugin.php | sed -n 's/^Version: //p')"
advanced_main_version="$(git -C "${release_repo_from_beta}" show main:plugin.php | sed -n 's/^Version: //p')"
latest_subject="$(git -C "${release_repo_from_beta}" log -1 --pretty=%s)"
previous_subject="$(git -C "${release_repo_from_beta}" log -2 --pretty=%s | tail -n 1)"
if [[ -n "${stable_tag}" \
    && "${release_commit_version}" == "1.4.0" \
    && "${advanced_main_version}" == "1.5.0-dev" \
    && "${latest_subject}" == "Start 1.5.0-dev" \
    && "${previous_subject}" == "Release 1.4.0" ]]; then
    _pass "release stable tags the stable commit and reopens main on the next dev line"
else
    _fail "release stable tags the stable commit and reopens main on the next dev line" \
        "tag=${stable_tag}, release=${release_commit_version}, main=${advanced_main_version}, latest=${latest_subject}, previous=${previous_subject}"
fi

if grep -q "## \[1.4.0\]" "${release_repo_from_beta}/CHANGELOG.md"; then
    _pass "release stable promotes the changelog before advancing main"
else
    _fail "release stable promotes the changelog before advancing main" \
        "changelog=$(<"${release_repo_from_beta}/CHANGELOG.md")"
fi

if [[ "$(git -C "${release_repo_from_beta}" show 1.4.0:CHANGELOG.md)" == *"### Fixed"* \
    && "$(git -C "${release_repo_from_beta}" show 1.4.0:CHANGELOG.md)" == *"- Test release note."* ]]; then
    _pass "release stable changelog carries the release-line notes into the stable section"
else
    _fail "release stable changelog carries the release-line notes into the stable section" \
        "changelog=$(git -C "${release_repo_from_beta}" show 1.4.0:CHANGELOG.md)"
fi

# ── Summary ───────────────────────────────────────────────────────────────────

echo
echo "${pass_count} passed, ${fail_count} failed."

if (( fail_count > 0 )); then
    exit 1
fi
