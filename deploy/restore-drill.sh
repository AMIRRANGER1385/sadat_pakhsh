#!/bin/bash
set -euo pipefail
umask 077

backup="${1:?Usage: restore-drill.sh /absolute/path/to/backup}"
MYSQL_CNF="${NAYLEX_MYSQL_CNF:-$HOME/.naylex-backup.cnf}"
test -d "$backup" && test -r "$backup/database.sql.gz" && test -r "$backup/SHA256SUMS" || { echo "Incomplete backup" >&2; exit 2; }
(cd "$backup" && sha256sum -c SHA256SUMS)
drill_db="naylex_restore_drill_$(date -u +%Y%m%d%H%M%S)_$$"
[[ "$drill_db" =~ ^naylex_restore_drill_[0-9_]+$ ]] || exit 2
cleanup(){ mysql --defaults-extra-file="$MYSQL_CNF" -e "DROP DATABASE IF EXISTS \`$drill_db\`"; }
trap cleanup EXIT
mysql --defaults-extra-file="$MYSQL_CNF" -e "CREATE DATABASE \`$drill_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gzip -dc "$backup/database.sql.gz" | mysql --defaults-extra-file="$MYSQL_CNF" "$drill_db"
tables="$(mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$drill_db" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('ns_products','ns_users','ns_orders','ns_order_items','ns_settings')")"
test "$tables" = "5" || { echo "Restore missing required tables" >&2; exit 1; }
mysql --defaults-extra-file="$MYSQL_CNF" -N -B "$drill_db" -e "SELECT CONCAT('products=',COUNT(*)) FROM ns_products; SELECT CONCAT('orders=',COUNT(*)) FROM ns_orders;"
echo "Restore drill passed in isolated database $drill_db"
