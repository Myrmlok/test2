@echo off
REM Скрипт запуска PHP API сервера для Windows

set PORT=8000
if not "%1"=="" set PORT=%1

echo.
echo 🚀 Запуск PHP API сервера...
echo 📍 Адрес: http://localhost:%PORT%/api/
echo 🧪 Тесты: http://localhost:%PORT%/api/test.html
echo.
echo Нажмите Ctrl+C для остановки
echo.

cd /d "%~dp0"
php -S localhost:%PORT% -t api
