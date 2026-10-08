#!/bin/bash
# Builds a clean, ready-to-install WordPress plugin zip (wanotify.zip)

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

echo "📦 Packaging WaNotify WordPress Plugin..."

rm -rf /tmp/wanotify-staging wanotify.zip
mkdir -p /tmp/wanotify-staging/wanotify

# Copy plugin files
cp -r wanotify.php includes assets readme.txt /tmp/wanotify-staging/wanotify/

# Clean up OS artifacts
find /tmp/wanotify-staging/wanotify -name ".DS_Store" -delete

# Create zip archive
(cd /tmp/wanotify-staging && zip -r "$SCRIPT_DIR/wanotify.zip" wanotify)

rm -rf /tmp/wanotify-staging

if [ -f "$SCRIPT_DIR/wanotify.zip" ]; then
    echo "✅ Success! Created wanotify.zip ($(du -h "$SCRIPT_DIR/wanotify.zip" | cut -f1))"
else
    echo "❌ Failed to create zip"
fi
