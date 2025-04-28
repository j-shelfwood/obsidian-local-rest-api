#!/bin/bash
set -e

# Bump patch version, create commit and annotated tag
NEW_TAG=$(npm version patch -m "chore(release): bump version to %s")
echo "Created git tag $NEW_TAG"

# Push both commit and tag to remote
git push origin main --follow-tags