#!/bin/bash
# Fix permissions for secrets directory
# This script makes the secrets directory writable by the web server

SECRETS_DIR="/opt/lampp/htdocs/abbis3.2/config/secrets"
CONFIG_DIR="/opt/lampp/htdocs/abbis3.2/config"

echo "Fixing permissions for secrets directory..."
echo "Directory: $SECRETS_DIR"

# Create directory if it doesn't exist
if [ ! -d "$SECRETS_DIR" ]; then
    echo "Creating secrets directory..."
    mkdir -p "$SECRETS_DIR"
fi

# Set permissions - make it writable by owner and group
chmod 775 "$CONFIG_DIR" 2>/dev/null || echo "Warning: Could not change config directory permissions"
chmod 775 "$SECRETS_DIR" 2>/dev/null || echo "Warning: Could not change secrets directory permissions"

# Try to change ownership to daemon (XAMPP default web server user)
# This may require sudo
if [ -w "$SECRETS_DIR" ]; then
    echo "✓ Directory is writable"
    ls -la "$SECRETS_DIR"
else
    echo "✗ Directory is not writable"
    echo "You may need to run: sudo chmod 775 $SECRETS_DIR"
    echo "Or: sudo chown -R daemon:daemon $SECRETS_DIR"
fi

echo "Done!"

