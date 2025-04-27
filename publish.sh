#!/bin/bash

# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
PACKAGE_JSON="package.json"
REMOTE_NAME="origin"
# --- End Configuration ---

# 1. Check for jq
if ! command -v jq &> /dev/null
then
    echo "Error: jq is not installed. Please install it (e.g., 'brew install jq') and try again."
    exit 1
fi

# 2. Check for clean Git working directory
if ! git diff --quiet HEAD --; then
    echo "Error: Your Git working directory is not clean. Please commit or stash changes."
    exit 1
fi

# 3. Get current version from package.json
if [ ! -f "$PACKAGE_JSON" ]; then
    echo "Error: $PACKAGE_JSON not found in the current directory."
    exit 1
fi

current_version=$(jq -r .version "$PACKAGE_JSON")
if [ -z "$current_version" ]; then
    echo "Error: Could not read version from $PACKAGE_JSON."
    exit 1
fi

echo "Current version: $current_version"

# 4. Calculate next versions
IFS='.' read -r -a version_parts <<< "$current_version"
major=${version_parts[0]}
minor=${version_parts[1]}
patch=${version_parts[2]}

next_patch_version="$major.$minor.$((patch + 1))"
next_minor_version="$major.$((minor + 1)).0"
next_major_version="$((major + 1)).0.0"

# 5. Prompt user for version
echo "Select the next version:"
echo "  1) Patch: $next_patch_version"
echo "  2) Minor: $next_minor_version"
echo "  3) Major: $next_major_version"
echo "  4) Custom"

read -p "Enter choice (1-4): " choice

case $choice in
    1)
        new_version=$next_patch_version
        ;;
    2)
        new_version=$next_minor_version
        ;;
    3)
        new_version=$next_major_version
        ;;
    4)
        read -p "Enter custom version (e.g., 1.2.3): " custom_version
        # Basic validation for custom version format
        if [[ ! "$custom_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-_+].+)?$ ]]; then
             echo "Error: Invalid version format. Use format X.Y.Z (e.g., 1.2.3)."
             exit 1
        fi
        new_version=$custom_version
        ;;
    *)
        echo "Invalid choice."
        exit 1
        ;;
esac

echo "Selected version: $new_version"
read -p "Proceed with version $new_version? (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 0
fi

# 6. Update version, commit, tag, and push
echo "Updating version to $new_version..."
# Use npm version to update package.json and package-lock.json (if exists)
# --no-git-tag-version prevents npm from creating its own tag; we'll do it manually.
npm version "$new_version" --no-git-tag-version --allow-same-version

# Get the exact version string npm put in the file (handles potential pre-release tags)
actual_new_version=$(jq -r .version "$PACKAGE_JSON")
git_tag="v$actual_new_version" # Add 'v' prefix for the tag

echo "Committing version bump..."
git add $PACKAGE_JSON package-lock.json # Add lockfile if it exists
git commit -m "chore: bump version to $git_tag"

echo "Creating Git tag $git_tag..."
git tag "$git_tag"

echo "Pushing commit and tag to $REMOTE_NAME..."
git push "$REMOTE_NAME"
git push "$REMOTE_NAME" --tags

echo "Successfully published version $actual_new_version and tagged as $git_tag."
echo "The GitHub Action should now trigger the publish job."