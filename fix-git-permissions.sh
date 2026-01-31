#!/bin/bash

# Fix Git repository permissions on server
# Run this on the server via SSH or Ploi console

echo "=== Fixing Git Repository Permissions ==="

# Get the current working directory (should be your app root)
APP_DIR=$(pwd)
echo "Application directory: $APP_DIR"

# Get the PHP-FPM user (the one that runs your app)
PHP_USER=$(ps aux | grep php-fpm | grep -v grep | head -1 | awk '{print $1}')
echo "PHP-FPM user: $PHP_USER"

# Fix ownership of .git directory
echo "Fixing .git ownership..."
sudo chown -R $PHP_USER:$PHP_USER .git

# Fix permissions
echo "Fixing .git permissions..."
sudo chmod -R 775 .git

# Fix any lock files
echo "Removing Git lock files..."
sudo rm -f .git/index.lock
sudo rm -f .git/HEAD.lock
sudo rm -f .git/refs/heads/*.lock

# Fix Git objects directory
echo "Fixing Git objects directory..."
sudo chmod -R 775 .git/objects
sudo chown -R $PHP_USER:$PHP_USER .git/objects

# Git garbage collection to clean up any corruption
echo "Running Git garbage collection..."
git gc --prune=now

echo ""
echo "=== Done! ==="
echo "Try deploying again from Ploi."
