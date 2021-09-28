#!/bin/bash

set -eo

# SVN_USERNAME, SVN_PASSWORD, and SVN_URL should be saved as a private environment variables.
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
RELEASE_TAG=$(echo $CIRCLE_TAG | sed 's/[^0-9\.]*//g')

echo "Preparing for version $RELEASE_TAG release..."

# Checkout just trunk and assets for efficiency.
# Tagging will be handled on the SVN level.
echo "Checking out svn repository..."
svn co "$SVN_URL" --depth=empty "$SVN_DIR"
cd "$SVN_DIR"
svn up assets
svn up trunk
svn up tags --depth=empty
find ./trunk -not -path "./trunk" -delete

echo "Copying files..."

if [[ -f "$PROJECT_DIR/.distignore" ]]; then
    echo "doing rsync..."
    rsync -rcl --exclude-from="$PROJECT_DIR/.distignore" "$PROJECT_DIR/" trunk/ --delete --delete-excluded
fi

# Copy assets to /assets.
if [[ -d "$PROJECT_DIR/assets/" ]]; then
    rsync -rc "$PROJECT_DIR/assets/" assets/ --delete
fi
ls -lah trunk

# Add everything and commit to SVN.
# The force flag ensures we recurse into subdirectories even if they are already added.
# Suppress stdout in favor of svn status later for readability.
echo "Adding files..."
svn add . --force

# echo "svn remove deleted files..."
# SVN delete all deleted files and suppress stdout.
# svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm %@

# Copy trunk into the current tag directory.
echo "Copying tag..."
svn cp "trunk" "tags/$RELEASE_TAG"

svn status

echo "Committing files..."
# svn commit -m "Release version $RELEASE_TAG." --no-auth-cache --non-interactive --username "$SVN_USERNAME" --password "$SVN_PASSWORD"
mkdir /tmp/zip
cd /tmp/zip
tar -czvf /tmp/zip/1.5.2.tar.gz /tmp/artifacts/tags/1.5.2

echo "Plugin version $RELEASE_TAG deployed."
