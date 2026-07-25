@echo off
rem ==== necorope 猫グッズハブ Windows 起動スクリプト ====
rem このファイルをダブルクリックすると、頭脳(API)と画面(web)が起動し、
rem ブラウザで管理画面が開きます。（初回は先に「pnpm install」が必要）

cd /d "%~dp0"

echo 頭脳(API)を起動しています...
start "necorope API" cmd /k "pnpm dev:api"

timeout /t 4 >nul

echo 画面(web)を起動しています...
start "necorope Web" cmd /k "set HUB_API_URL=http://127.0.0.1:3001&& pnpm dev:web"

timeout /t 10 >nul

echo ブラウザを開きます...
start "" "http://localhost:3000"

echo.
echo === 起動しました ===
echo 2つの黒い窓（API と Web）は閉じないでください。
echo 管理画面: http://localhost:3000
echo 終了するときは、この窓と2つの黒い窓を閉じてください。
echo.
pause
