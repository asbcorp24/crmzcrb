@echo off
chcp 65001 >nul
cd /d %~dp0
php artisan serve --host=0.0.0.0 --port=8085
pause
