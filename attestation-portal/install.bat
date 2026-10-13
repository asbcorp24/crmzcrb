@echo off
chcp 65001 >nul
cd /d %~dp0

if not exist .env copy .env.example .env >nul
if not exist database\database.sqlite type nul > database\database.sqlite

echo [1/5] Composer...
call composer install --no-interaction
if errorlevel 1 goto :error

echo [2/5] APP_KEY...
php artisan key:generate --force
if errorlevel 1 goto :error

echo [3/5] Database...
php artisan migrate --force
if errorlevel 1 goto :error

echo [4/5] Super admin...
php artisan db:seed --force
if errorlevel 1 goto :error

echo [5/5] Cache...
php artisan optimize:clear

echo.
echo ============================================
echo Portal installed.
echo DocumentRoot: %CD%\public
echo Login: see SUPERADMIN_LOGIN in .env
echo Password: see SUPERADMIN_PASSWORD in .env
echo ============================================
pause
exit /b 0

:error
echo Installation failed.
pause
exit /b 1
