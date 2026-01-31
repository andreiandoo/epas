#!/bin/bash

# Watch the Laravel log and filter for cookie/session debug messages
# Usage: ./watch-cookie-debug.sh

echo "=== Watching Laravel log for cookie/session debug messages ==="
echo "Press Ctrl+C to stop"
echo ""

tail -f storage/logs/laravel.log | grep --line-buffered -E "(COOKIE DEBUG|SESSION ID CHANGED|ADMIN ACCESS DEBUG|ADMIN RESPONSE)"
