#!/bin/bash
set -euo pipefail
umask 077

: "${NAYLEX_APP_DIR:?Set NAYLEX_APP_DIR to the private naylex-app directory}"
: "${NAYLEX_DB_NAME:?Set NAYLEX_DB_NAME}"
: "${NAYLEX_BACKUP_ROOT:?Set NAYLEX_BACKUP_ROOT outside public_html}"
MYSQL_CNF="${NAYLEX_MYSQL_CNF:-$HOME/.naylex-backup.cnf}"

case "$NAYLEX_BACKUP_ROOT" in "$HOME"|"/"|"") echo "Unsafe backup root" >&2; exit 2;; esac
test -r "$MYSQL_CNF" || { echo "Missing private MySQL option file" >&2; exit 2; }
test -d "$NAYLEX_APP_DIR/storage" || { echo "Storage directory not found" >&2; exit 2; }
mkdir -p "$NAYLEX_BACKUP_ROOT"
chmod 700 "$NAYLEX_BACKUP_ROOT"
exec 9>"$NAYLEX_BACKUP_ROOT/.backup.lock"
flock -n 9 || { echo "Backup already running" >&2; exit 1; }

stamp="$(date -u +%Y%m%dT%H%M%SZ)"
tmp="$NAYLEX_BACKUP_ROOT/.tmp-$stamp-$$"
final="$NAYLEX_BACKUP_ROOT/$stamp"
mkdir -p "$tmp"
trap 'rm -rf -- "$tmp"' EXIT

mysqldump --defaults-extra-file="$MYSQL_CNF" --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 "$NAYLEX_DB_NAME" | gzip -9 > "$tmp/database.sql.gz"
tar -C "$NAYLEX_APP_DIR/storage" -czf "$tmp/uploads.tar.gz" uploads
if test -f "$NAYLEX_APP_DIR/storage/two-factor.key"; then cp -p "$NAYLEX_APP_DIR/storage/two-factor.key" "$tmp/two-factor.key"; fi
(cd "$tmp" && sha256sum ./* > SHA256SUMS)
mv "$tmp" "$final"
trap - EXIT
chmod -R go-rwx "$final"

# Local retention is not the off-server copy. Configure rclone for that copy.
find "$NAYLEX_BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -name '20????????T??????Z' -mtime +35 -exec rm -rf -- {} +
if test -n "${NAYLEX_RCLONE_REMOTE:-}"; then
 rclone copy "$final" "$NAYLEX_RCLONE_REMOTE/$stamp" --checksum
fi
echo "Backup completed: $final"
