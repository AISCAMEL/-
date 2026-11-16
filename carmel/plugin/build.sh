#!/usr/bin/env bash
# カーメルシステム プラグインのビルド。
# carmel/ の各スニペットを carmel-system/modules/ にコピーして zip を作る。
# 使い方: carmel/plugin/ で ./build.sh
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
SRC="$(cd "$HERE/.." && pwd)"              # carmel/
PLUGIN="$HERE/carmel-system"
MODULES="$PLUGIN/modules"

# manifest（carmel-system.php の $modules と一致させる）
FILES=(
  wpcode-image-optimize.php
  wpcode-ai-diagnose.php
  wpcode-ai-intake.php
  wpcode-inventory-receiver.php
  wpcode-inventory-selftest.php
  wpcode-site-survey.php
  wpcode-content-types.php
  wpcode-site-monitor.php
  wpcode-admin-menu.php
  wpcode-car-spec-extra.php
  wpcode-car-breakdown.php
  wpcode-car-faq.php
  wpcode-car-history-share.php
  wpcode-car-compare.php
  wpcode-car-favorite.php
  wpcode-car-inquiry.php
  wpcode-car-video.php
  wpcode-loan-guide.php
  wpcode-car-page-polish.php
)

rm -rf "$MODULES"
mkdir -p "$MODULES"
for f in "${FILES[@]}"; do
  cp "$SRC/$f" "$MODULES/$f"
done

# バージョンを carmel-system.php から取得
VER="$(grep -oE "Version:[[:space:]]*[0-9.]+" "$PLUGIN/carmel-system.php" | grep -oE "[0-9.]+")"

# zip 生成（フォルダ名 carmel-system をルートに）
cd "$HERE"
rm -f carmel-system.zip
zip -rq carmel-system.zip carmel-system -x "*.DS_Store"

echo "built carmel-system.zip (v$VER) with ${#FILES[@]} modules"

# 軽量版「カーメル 在庫連携」（在庫の受け口だけ。サイトの表示は変えない＝本番に先に入れられる）
BRIDGE="$HERE/carmel-inventory-bridge"
cp "$SRC/wpcode-inventory-receiver.php" "$BRIDGE/wpcode-inventory-receiver.php"
cp "$SRC/wpcode-inventory-selftest.php" "$BRIDGE/wpcode-inventory-selftest.php"
cp "$SRC/wpcode-site-survey.php" "$BRIDGE/wpcode-site-survey.php"
BVER="$(grep -oE "Version:[[:space:]]*[0-9.]+" "$BRIDGE/carmel-inventory-bridge.php" | grep -oE "[0-9.]+")"
rm -f carmel-inventory-bridge.zip
zip -rq carmel-inventory-bridge.zip carmel-inventory-bridge -x "*.DS_Store"
echo "built carmel-inventory-bridge.zip (v$BVER)"
