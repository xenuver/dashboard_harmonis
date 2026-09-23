#!/bin/sh
set -e

MARIADB_SOCK="/var/run/mariadb/mariadb.sock"
MYSQL_DEFAULT_SOCK="/var/run/mysqld/mysqld.sock"

# Maximum wait time in seconds for the service to start
TIMEOUT=30
ELAPSED=0

echo "Waiting for MariaDB socket at ${MARIADB_SOCK}..."

while [ ! -S "$MARIADB_SOCK" ]; do
  sleep 1
  ELAPSED=$((ELAPSED + 1))
  if [ "$ELAPSED" -ge "$TIMEOUT" ]; then
    echo "Timeout waiting for MariaDB socket at ${MARIADB_SOCK}."
    exit 1
  fi
done

# Ensure parent directory for target socket exists
sudo mkdir -p "$(dirname "$MYSQL_DEFAULT_SOCK")"

# Remove existing stale socket or broken symlink
sudo rm -f "$MYSQL_DEFAULT_SOCK"

# Create symbolic link
sudo ln -s "$MARIADB_SOCK" "$MYSQL_DEFAULT_SOCK"

echo "Symlink created: ${MYSQL_DEFAULT_SOCK} -> ${MARIADB_SOCK}"