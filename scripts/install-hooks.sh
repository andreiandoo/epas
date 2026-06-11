#!/bin/bash

# Install git hooks for auto-versioning
# Run this script after cloning the repository

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
HOOKS_SOURCE="$PROJECT_ROOT/hooks"
HOOKS_TARGET="$PROJECT_ROOT/.git/hooks"

echo "Installing git hooks for auto-versioning..."

# Check if we're in a git repository
if [ ! -d "$PROJECT_ROOT/.git" ]; then
    echo "Error: Not a git repository"
    exit 1
fi

# Check if hooks source directory exists
if [ ! -d "$HOOKS_SOURCE" ]; then
    echo "Error: Hooks source directory not found: $HOOKS_SOURCE"
    exit 1
fi

# Create hooks directory if it doesn't exist
mkdir -p "$HOOKS_TARGET"

# Install each hook
for hook in "$HOOKS_SOURCE"/*; do
    if [ -f "$hook" ]; then
        hook_name=$(basename "$hook")
        target="$HOOKS_TARGET/$hook_name"

        # Backup existing hook if present
        if [ -f "$target" ] && [ ! -L "$target" ]; then
            echo "Backing up existing $hook_name to $hook_name.backup"
            mv "$target" "$target.backup"
        fi

        # Create symlink or copy
        if [ -L "$target" ]; then
            rm "$target"
        fi

        cp "$hook" "$target"
        chmod +x "$target"
        echo "Installed: $hook_name"
    fi
done

echo ""
echo "Git hooks installed successfully!"
echo ""
echo "Available commands:"
echo "  php artisan version:show              - Display all versions"
echo "  php artisan version:show --service=X  - Show specific service version"
echo "  php artisan version:bump core         - Bump core version"
echo "  php artisan version:bump ServiceName  - Bump service version"
echo "  php artisan version:auto              - Auto-detect and bump versions"
echo "  php artisan version:auto --dry-run    - Preview what would be bumped"
echo ""
echo "The post-commit hook will automatically bump versions when you commit changes."
