#!/usr/bin/env bash
# seed-wiki.sh — one-time script to push the wiki/ staging directory to the
# GitHub Wiki repository.
#
# Prerequisites:
#   1. Enable the Wiki on the repository (Settings → Features → Wikis).
#   2. Create at least one page through the GitHub UI so the wiki git
#      repository is initialised before this script runs.
#   3. Export a personal access token (or use GITHUB_TOKEN in CI) that has
#      write access to this repository:
#
#         export GITHUB_TOKEN=<your-token>
#
# Usage:
#   bash bin/seed-wiki.sh
#
# After this script runs, the GitHub Wiki becomes the source of truth.
# Edit pages directly at:
#   https://github.com/sathyvelukunashegaran/Tasty-Custom-Fonts/wiki

set -euo pipefail

REPO="sathyvelukunashegaran/Tasty-Custom-Fonts"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
WIKI_STAGING="${SCRIPT_DIR}/../wiki"

if [ -z "${GITHUB_TOKEN:-}" ]; then
    echo "Error: GITHUB_TOKEN environment variable is not set." >&2
    exit 1
fi

if [ ! -d "$WIKI_STAGING" ]; then
    echo "Error: wiki/ staging directory not found at ${WIKI_STAGING}" >&2
    exit 1
fi

TMPDIR_WORK="$(mktemp -d)"
trap 'rm -rf "$TMPDIR_WORK"' EXIT

echo "Cloning wiki repository..."
git clone \
    "https://x-access-token:${GITHUB_TOKEN}@github.com/${REPO}.wiki.git" \
    "${TMPDIR_WORK}/wiki"

echo "Syncing wiki pages..."
# Remove all existing .md files in the wiki (clean slate)
find "${TMPDIR_WORK}/wiki" -maxdepth 1 -name '*.md' -delete

# Copy the staged wiki pages
cp "${WIKI_STAGING}"/*.md "${TMPDIR_WORK}/wiki/"

cd "${TMPDIR_WORK}/wiki"
git config user.name  "wiki-seed"
git config user.email "wiki-seed@users.noreply.github.com"
git add -A

if git diff --cached --quiet; then
    echo "Wiki is already up to date. Nothing to push."
else
    git commit -m "Seed wiki from docs/ (initial migration)"
    git push
    echo "Wiki seeded successfully."
    echo "Visit: https://github.com/${REPO}/wiki"
fi
