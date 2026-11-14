#!/usr/bin/env bash
# Release tagging script for CSEM
# Usage: ./scripts/tag-release.sh v0.1.0

set -euo pipefail

VERSION="${1:-}"

if [ -z "$VERSION" ]; then
    echo "Usage: $0 <version> (e.g. $0 v0.1.0)"
    exit 1
fi

# Ensure working directory is clean
if [ -n "$(git status --porcelain)" ]; then
    echo "Error: Working directory has uncommitted changes. Commit or stash them first."
    git status -s
    exit 1
fi

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "main" ]; then
    echo "Warning: You are currently on branch '$CURRENT_BRANCH', not 'main'."
    read -p "Do you want to continue tagging? (y/N) " confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

echo "Creating tag: $VERSION"
git tag -a "$VERSION" -m "Release $VERSION"

echo "Tag $VERSION created successfully."
echo "To push to GitHub, run:"
echo "  git push origin $VERSION"
