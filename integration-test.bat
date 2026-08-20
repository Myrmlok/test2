@echo off
REM Интеграционный тест: Backend + Frontend

echo.
echo 🧪 Интеграционный тест 4bor.club
echo ================================
echo.

REM Проверка PHP
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ PHP не установлен
    exit /b 1
)

for /f "tokens=*" %%i in ('php -v ^| findstr /C:"PHP"') do set PHP_VERSION=%%i
echo ✅ PHP: %PHP_VERSION%

REM Проверка pnpm
where pnpm >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ❌ pnpm не установлен
    exit /b 1
)

for /f "tokens=*" %%i in ('pnpm -v') do set PNPM_VERSION=%%i
echo ✅ pnpm: %PNPM_VERSION%
echo.

REM Проверка backend
echo 📡 Проверка backend...

if not exist "api\index.php" (
    echo ❌ Backend не найден (api\index.php^)
    exit /b 1
)

echo ✅ Backend файлы найдены

REM Проверка БД
if not exist "api\database.sqlite" (
    echo ⚠️  База данных будет создана при первом запуске
) else (
    for %%A in (api\database.sqlite) do echo ✅ База данных: %%~zA bytes
)

REM Проверка frontend
echo.
echo 🎨 Проверка frontend...

if not exist "artifacts\4bor-club\package.json" (
    echo ❌ Frontend не найден
    exit /b 1
)

echo ✅ Frontend файлы найдены

REM Проверка Vite proxy
findstr /C:"proxy:" artifacts\4bor-club\vite.config.ts >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo ✅ Vite proxy настроен
) else (
    echo ⚠️  Vite proxy не настроен
)

REM Проверка зависимостей
if not exist "node_modules" (
    echo ⚠️  Зависимости не установлены
    echo.
    set /p "response=Установить зависимости? (y/n): "
    if /i "%response%"=="y" (
        pnpm install
    )
)

echo.
echo ✅ Все проверки пройдены!
echo.
echo 🚀 Для запуска:
echo.
echo   Терминал 1 (Backend^):
echo   ^> start-api.bat
echo.
echo   Терминал 2 (Frontend^):
echo   ^> pnpm --filter @workspace/4bor-club dev
echo.
echo   Затем откройте: http://localhost:3000
echo.
pause
