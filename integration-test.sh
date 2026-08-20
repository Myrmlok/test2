#!/bin/bash

# Интеграционный тест: Backend + Frontend

echo "🧪 Интеграционный тест 4bor.club"
echo "================================"
echo ""

# Проверка PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP не установлен"
    exit 1
fi

echo "✅ PHP: $(php -v | head -1)"

# Проверка Node/npm/pnpm
if command -v pnpm &> /dev/null; then
    PKG_MANAGER="pnpm"
    echo "✅ pnpm: $(pnpm -v)"
elif command -v npm &> /dev/null; then
    PKG_MANAGER="npm"
    echo "✅ npm: $(npm -v)"
else
    echo "❌ npm/pnpm не установлены"
    exit 1
fi
echo ""

# Проверка backend
echo "📡 Проверка backend..."
cd "$(dirname "$0")"

if [ ! -f "api/index.php" ]; then
    echo "❌ Backend не найден (api/index.php)"
    exit 1
fi

echo "✅ Backend файлы найдены"

# Проверка БД
if [ ! -f "api/database.sqlite" ]; then
    echo "⚠️  База данных будет создана при первом запуске"
else
    SIZE=$(du -h api/database.sqlite | cut -f1)
    echo "✅ База данных: $SIZE"
fi

# Проверка frontend
echo ""
echo "🎨 Проверка frontend..."

if [ ! -f "artifacts/4bor-club/package.json" ]; then
    echo "❌ Frontend не найден"
    exit 1
fi

echo "✅ Frontend файлы найдены"

# Проверка Vite proxy
if grep -q "proxy:" artifacts/4bor-club/vite.config.ts; then
    echo "✅ Vite proxy настроен"
else
    echo "⚠️  Vite proxy не настроен"
fi

# Проверка зависимостей
if [ ! -d "node_modules" ]; then
    echo "⚠️  Зависимости не установлены"
    echo ""
    echo "Установить зависимости? (y/n)"
    read -r response
    if [ "$response" = "y" ]; then
        $PKG_MANAGER install
    fi
fi

echo ""
echo "✅ Все проверки пройдены!"
echo ""
echo "🚀 Для запуска:"
echo ""
echo "  Терминал 1 (Backend):"
echo "  $ ./start-api.sh"
echo ""
echo "  Терминал 2 (Frontend):"
if [ "$PKG_MANAGER" = "pnpm" ]; then
    echo "  $ pnpm --filter @workspace/4bor-club dev"
else
    echo "  $ npm run dev --workspace=artifacts/4bor-club"
fi
echo ""
echo "  Затем откройте: http://localhost:3000"
echo ""
