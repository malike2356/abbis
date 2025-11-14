#!/bin/bash
# Quick Update Script - One-Click Deployment
# 
# This script creates a deployment package and prepares it for upload
# Usage: ./scripts/deploy/quick-update.sh

echo "🚀 ABBIS Quick Update Package Creator"
echo "======================================"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
ROOT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
cd "$ROOT_DIR"

# Run PHP script to create package
php scripts/deploy/create-package.php

# Get the latest package
PACKAGE_DIR="$ROOT_DIR/deployment-packages"
LATEST_PACKAGE=$(ls -t "$PACKAGE_DIR"/*.zip 2>/dev/null | head -1)

if [ -f "$LATEST_PACKAGE" ]; then
    PACKAGE_NAME=$(basename "$LATEST_PACKAGE")
    PACKAGE_SIZE=$(du -h "$LATEST_PACKAGE" | cut -f1)
    
    echo ""
    echo "✅ Package Ready!"
    echo "=================="
    echo "File: $PACKAGE_NAME"
    echo "Size: $PACKAGE_SIZE"
    echo "Location: $PACKAGE_DIR"
    echo ""
    echo "📤 Next Steps:"
    echo "1. Upload $PACKAGE_NAME to your server"
    echo "2. Extract it in your ABBIS directory"
    echo "3. Run: php scripts/deploy/update-server.php"
    echo ""
    echo "💡 Tip: The package includes instructions!"
else
    echo "❌ Error: Package not found"
    exit 1
fi

