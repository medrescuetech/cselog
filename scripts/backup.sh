#!/usr/bin/env bash
# Production backup script for CSEM
# Backs up the MySQL database and sitemap / static map layers
# Recommended cron: 0 2 * * * /home/USER/csem-app/scripts/backup.sh >/dev/null 2>&1

set -euo pipefail

BACKUP_DIR="${HOME}/backups"
APP_DIR="${HOME}/csem-app"
DATE="$(date +%Y-%m-%d_%H%M%S)"
KEEP_DAYS=30

mkdir -p "${BACKUP_DIR}"

# 1. Source DB credentials from production .env if available
if [ -f "${APP_DIR}/.env" ]; then
    DB_USER=$(grep -E '^DB_USERNAME=' "${APP_DIR}/.env" | cut -d= -f2- | tr -d '"'\'' ')
    DB_PASS=$(grep -E '^DB_PASSWORD=' "${APP_DIR}/.env" | cut -d= -f2- | tr -d '"'\'' ')
    DB_NAME=$(grep -E '^DB_DATABASE=' "${APP_DIR}/.env" | cut -d= -f2- | tr -d '"'\'' ')
    DB_HOST=$(grep -E '^DB_HOST=' "${APP_DIR}/.env" | cut -d= -f2- | tr -d '"'\'' ')
    
    echo "[$(date)] Dumping database: ${DB_NAME}..."
    mysqldump --single-transaction --quick -h "${DB_HOST:-127.0.0.1}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" | gzip > "${BACKUP_DIR}/csem-db-${DATE}.sql.gz"
fi

# 2. Archive sitemap images and generated layers
if [ -d "${APP_DIR}/sitemap" ]; then
    echo "[$(date)] Archiving sitemap assets..."
    tar -czf "${BACKUP_DIR}/csem-sitemap-${DATE}.tar.gz" -C "${APP_DIR}" sitemap
fi

# 3. Clean up older backups
echo "[$(date)] Removing backups older than ${KEEP_DAYS} days..."
find "${BACKUP_DIR}" -type f -name "csem-*" -mtime "+${KEEP_DAYS}" -delete

echo "[$(date)] Backup completed successfully."
