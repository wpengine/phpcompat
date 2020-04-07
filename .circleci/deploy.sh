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

SVN_DIR="/tmp/artifacts"
PROJECT_DIR=$(pwd)

echo "Preparing for version $CIRCLE_TAG release..."

# Checkout just trunk and assets for efficiency.
# Tagging will be handled on the SVN level.
echo "Checking out svn repository..."
svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
cd "$SVN_DIR"
svn update --set-depth infinity assets
svn update --set-depth infinity trunk

echo "Copying files..."

if [[ -f "$PROJECT_DIR/.distignore" ]]; then
    rsync -rc --exclude-from="$PROJECT_DIR/.distignore" "$PROJECT_DIR/" trunk/ --delete --delete-excluded
fi

# Copy assets to /assets.
if [[ -d "$PROJECT_DIR/assets/" ]]; then
    rsync -rc "$PROJECT_DIR/assets/" assets/ --delete
fi

# Add everything and commit to SVN.
# The force flag ensures we recurse into subdirectories even if they are already added.
# Suppress stdout in favor of svn status later for readability.
echo "Preparing files..."
svn add . --force > /dev/null

# SVN delete all deleted files and suppress stdout.
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm %@ > /dev/null

# Copy trunk into the current tag directory.
echo "Copying tag..."
svn cp "trunk" "tags/$CIRCLE_TAG"

svn status

echo "Committing files..."
svn commit -m "Release version $CIRCLE_TAG." --no-auth-cache --non-interactive --username "$SVN_USERNAME" --password "$SVN_PASSWORD"

echo "Plugin version $CIRCLE_TAG deployed."
