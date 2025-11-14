#!/usr/bin/env bash
#
# Post deployment helper for ABBIS releases.
# - Imports database schema/data if present
# - Updates application URL in config/app.php
# - Clears caches and fixes permissions
#

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DB_DIR="$APP_ROOT/db"
CONFIG_FILE="$APP_ROOT/config/app.php"

echo "== ABBIS Post Deployment =="

read -rp "New base URL (e.g. https://portal.example.com): " NEW_URL

if [[ -z "$NEW_URL" ]]; then
  echo "No URL provided. Aborting."
  exit 1
fi

DEFAULT_DB_HOST="localhost"
DEFAULT_DB_NAME="abbis_3_2"
DEFAULT_DB_USER="root"

if [[ -d "$DB_DIR" ]]; then
  read -rp "Database host [$DEFAULT_DB_HOST]: " DB_HOST
  DB_HOST=${DB_HOST:-$DEFAULT_DB_HOST}

  read -rp "Database name [$DEFAULT_DB_NAME]: " DB_NAME
  DB_NAME=${DB_NAME:-$DEFAULT_DB_NAME}

  read -rp "Database user [$DEFAULT_DB_USER]: " DB_USER
  DB_USER=${DB_USER:-$DEFAULT_DB_USER}

  read -srp "Database password (leave blank for none): " DB_PASS
  echo ""

  read -rp "Import database dumps from ./db/? [y/N]: " DO_IMPORT
  if [[ "$DO_IMPORT" =~ ^[Yy]$ ]]; then
    echo "Importing schema..."
    if [[ -n "$DB_PASS" ]]; then
      mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DB_DIR/schema.sql"
      echo "Importing data..."
      mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DB_DIR/data.sql"
    else
      mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$DB_DIR/schema.sql"
      echo "Importing data..."
      mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$DB_DIR/data.sql"
    fi
  else
    echo "Skipping database import."
  fi
else
  echo "No ./db directory found in release. Skipping DB import."
fi

echo "Updating APP_URL in $CONFIG_FILE"
perl -pi -e "s#(define\\('APP_URL',\\s*')(.+?)('\\);)#\${1}$NEW_URL\${3}#;" "$CONFIG_FILE"

echo "Clearing cache directories..."
rm -rf "$APP_ROOT/storage/cache/"*

echo "Setting permissions..."
chmod -R 755 "$APP_ROOT"
find "$APP_ROOT" -type f -name "*.sh" -exec chmod +x {} \;

echo "Deployment tasks completed. Please verify the application manually."

