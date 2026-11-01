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
  wpcode-loan-guide.php
  wpcode-car-detail-ui.php
  wpcode-staff-shop.php
  wpcode-extra-basic.php
  wpcode-shop-checklist.php
  wpcode-shop-bulk-assign.php
  wpcode-staff-manual.php
  wpcode-form-redirect.js
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
