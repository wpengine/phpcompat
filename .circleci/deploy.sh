#!/bin/bash

set -eo

# SVN_USERNAME and SVN_PASSWORD should be saved as a private environment variables in CircleCI.
# See https://circleci.com/docs/2.0/env-vars/#setting-an-environment-variable-in-a-project
# See https://circleci.com/blog/keep-environment-variables-private-with-secret-masking/
if [[ -z "$SVN_USERNAME" ]]; then
    echo "Missing SVN_USERNAME environment variable!"
    exit 1
fi

if [[ -z "$SVN_PASSWORD" ]]; then
    echo "Missing SVN_PASSWORD environment variable!"
    exit 1
fi

if [[ -z "$SVN_URL" ]]; then
    echo "Missing SVN_URL environment variable!"
    exit 1
fi

# Extra check to ensure CircleCI provided the CIRCLE_TAG environment variable.
# See https://circleci.com/docs/2.0/env-vars/#built-in-environment-variables
if [[ -z "$CIRCLE_TAG" ]]; then
    echo "Missing CIRCLE_TAG environment variable!"
    exit 1
fi

VERSION="$CIRCLE_TAG"
SVN_DIR="/tmp/svn/${CIRCLE_PROJECT_REPONAME}"

echo "Preparing for version $VERSION release..."

if [[ ! -d "$SVN_DIR" ]]; then
    mkdir -p "$SVN_DIR"
    echo "SVN directory $SVN_DIR created."
fi

# Checkout just trunk and assets for efficiency.
# Tagging will be handled on the SVN level.
echo "Checking out svn repository..."
svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
cd "$SVN_DIR"
svn update --set-depth infinity assets
svn update --set-depth infinity trunk

echo "Copying files..."

if [[ -f "$CIRCLE_WORKING_DIRECTORY/.distignore" ]]; then
    # Copy from current branch to /trunk, excluding assets.
    # The --delete flag will delete anything in destination that no longer exists in source.
    rsync -rc --exclude-from="$CIRCLE_WORKING_DIRECTORY/.distignore" "$CIRCLE_WORKING_DIRECTORY/" trunk/ --delete --delete-excluded
fi

# Copy assets to /assets as this was skipped in the previous step.
if [[ -d "$CIRCLE_WORKING_DIRECTORY/assets/" ]]; then
    rsync -rc "$CIRCLE_WORKING_DIRECTORY/assets/" assets/ --delete
fi

# Add everything and commit to SVN.
# The force flag ensures we recurse into subdirectories even if they are already added.
# Suppress stdout in favor of svn status later for readability.
echo "Preparing files..."
svn add . --force > /dev/null

# SVN delete all deleted files and suppress stdout.
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm %@ > /dev/null

# Copy tag locally from trunk.
echo "Copying tag..."
svn cp "trunk" "tags/$VERSION"

svn status

echo "Committing files..."
svn commit -m "Release version $VERSION." --no-auth-cache --non-interactive --username "$SVN_USERNAME" --password "$SVN_PASSWORD"

echo "Plugin release $VERSION deployed to $SVN_URL"
