#!/usr/bin/env bash
#
# AIS Corporate テーマのローカル実機検証環境を構築するスクリプト。
# WordPress 本体 + SQLite ドロップイン + 当テーマを用意し、PHP ビルトインサーバーで起動します。
# MySQL 不要・root 権限不要（PHP / curl / unzip があれば動作）。
#
# 使い方:
#   bash wordpress-theme/dev/setup-local-wp.sh           # 構築して起動
#   WP_PORT=8090 bash wordpress-theme/dev/setup-local-wp.sh
#
set -euo pipefail

THEME_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/../ais-corporate" && pwd)"
WORKDIR="${WP_DIR:-/tmp/ais-wp}"
PORT="${WP_PORT:-8089}"
URL="http://localhost:${PORT}"
WP_VERSION="${WP_VERSION:-6.5}"
WP_CLI="${WORKDIR}/wp-cli.phar"
ALLOW_ROOT=""
[ "$(id -u)" = "0" ] && ALLOW_ROOT="--allow-root"

wp() { php "$WP_CLI" --path="$WORKDIR/wp" $ALLOW_ROOT "$@"; }

echo "==> 作業ディレクトリ: $WORKDIR"
mkdir -p "$WORKDIR"
cd "$WORKDIR"

# wp-cli
if [ ! -f "$WP_CLI" ]; then
  echo "==> wp-cli を取得"
  curl -fsSL -o "$WP_CLI" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi

# WordPress 本体（wordpress.org → 失敗時 GitHub ミラー）
if [ ! -d "$WORKDIR/wp" ]; then
  echo "==> WordPress $WP_VERSION を取得"
  if curl -fsSL -o wp.zip "https://wordpress.org/wordpress-${WP_VERSION}.zip" 2>/dev/null; then
    unzip -q wp.zip && mv wordpress wp
  else
    echo "   wordpress.org 不可。GitHub ミラーから取得"
    curl -fsSL -o wp.zip "https://codeload.github.com/WordPress/WordPress/zip/refs/tags/${WP_VERSION}"
    unzip -q wp.zip && mv "WordPress-${WP_VERSION}" wp
  fi
fi

# SQLite データベース統合ドロップイン
if [ ! -f "$WORKDIR/wp/wp-content/db.php" ]; then
  echo "==> SQLite ドロップインを設定"
  curl -fsSL -o sqlite.zip https://codeload.github.com/WordPress/sqlite-database-integration/zip/refs/heads/main
  unzip -q -o sqlite.zip
  PLUGDIR="$WORKDIR/wp/wp-content/plugins/sqlite-database-integration"
  mkdir -p "$WORKDIR/wp/wp-content/plugins"
  cp -r sqlite-database-integration-main "$PLUGDIR"
  sed "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#${PLUGDIR}#g; s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
    "$PLUGDIR/db.copy" > "$WORKDIR/wp/wp-content/db.php"
fi

# wp-config & インストール
if ! wp core is-installed 2>/dev/null; then
  echo "==> WordPress をインストール"
  wp config create --dbname=wp --dbuser=root --dbpass=root --dbhost=localhost --skip-check --force
  wp core install --url="$URL" --title="合同会社アイズ" \
    --admin_user=admin --admin_password=admin123 --admin_email=info@aisjaltd.com --skip-email
fi

wp option update home "$URL"
wp option update siteurl "$URL"
wp rewrite structure '/%postname%/' --hard

# テーマを配置（毎回コピーして最新化）して有効化
echo "==> テーマを配置・有効化"
rm -rf "$WORKDIR/wp/wp-content/themes/ais-corporate"
cp -r "$THEME_SRC" "$WORKDIR/wp/wp-content/themes/ais-corporate"
wp theme activate ais-corporate
wp rewrite flush --hard

# ルーター
cat > "$WORKDIR/router.php" <<'PHP'
<?php
$root = getenv('AIS_WP_ROOT');
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) { return false; }
require $root . '/index.php';
PHP

cat <<EOF

==============================================================
 セットアップ完了。次のコマンドで起動します:

   AIS_WP_ROOT="$WORKDIR/wp" php -S localhost:${PORT} -t "$WORKDIR/wp" "$WORKDIR/router.php"

 サイト    : $URL
 管理画面  : $URL/wp-admin/  (admin / admin123)

 AIチャットを試す場合は wp-admin → 設定 → AIチャット で
 OpenRouter APIキーを設定するか、wp-config.php に
   define('AIS_OPENROUTER_API_KEY', 'sk-or-...');
 を追記してください。
==============================================================
EOF
