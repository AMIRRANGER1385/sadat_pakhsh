#!/bin/bash
set -euo pipefail

# cPanel executes this from its private Git checkout, never public_html.
repo_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
account_dir="$(cd -- "$HOME" && pwd -P)"
app_dir="$account_dir/naylex-app"
web_dir="$account_dir/public_html"
source_dir="$repo_dir/php-store"

[[ -f "$source_dir/naylex-app/bootstrap.php" && -f "$source_dir/public_html/index.php" ]] || { echo 'PHP source missing'; exit 1; }
[[ "$repo_dir" != "$web_dir" && "$repo_dir" != "$web_dir/"* ]] || { echo 'Git checkout must be outside public_html'; exit 1; }
[[ -d "$app_dir" && -d "$web_dir" && ! -L "$app_dir" && ! -L "$web_dir" ]] || { echo 'Install the PHP shop first; destination directories must not be symlinks'; exit 1; }
[[ -f "$app_dir/config.php" && -f "$app_dir/bootstrap.php" && -f "$web_dir/index.php" ]] || { echo 'Expected existing PHP installation was not found'; exit 1; }
grep -q 'naylex-app\|NAYLEX_APP_PATH' "$web_dir/index.php" || { echo 'Destination does not look like this shop; refusing to overwrite another website'; exit 1; }
command -v rsync >/dev/null || { echo 'Ask hosting support to enable rsync'; exit 1; }

# Back up code and public assets outside the web root before overwriting.
# Database backups and storage/uploads backups remain separate operational tasks.
backup_dir="$account_dir/naylex-deploy-backups/$(date -u +%Y%m%dT%H%M%SZ)-$$"
mkdir -p "$backup_dir"
chmod 700 "$account_dir/naylex-deploy-backups" "$backup_dir"
rsync -a --exclude='/config.php' --exclude='/storage/' "$app_dir/" "$backup_dir/naylex-app/"
rsync -a "$web_dir/" "$backup_dir/public_html/"

# Intentionally no --delete: local settings, uploads and unrelated files survive.
rsync -a --exclude='/config.php' --exclude='/storage/' "$source_dir/naylex-app/" "$app_dir/"
rsync -a --exclude='/.well-known/' --exclude='/.env' "$source_dir/public_html/" "$web_dir/"
echo "Deployment finished. Code backup: $backup_dir"
echo 'Check /products, /products/, /sitemap.xml and login on the live domain.'
