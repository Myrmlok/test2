#!/bin/bash

# Скрипт запуска PHP API сервера для разработки

PORT=${1:-8000}
HOST="localhost"

echo "🚀 Запуск PHP API сервера..."
echo "📍 Адрес: http://$HOST:$PORT/api/"
echo "🧪 Тесты: http://$HOST:$PORT/api/test.html"
echo ""
echo "Нажмите Ctrl+C для остановки"
echo ""

cd "$(dirname "$0")"
php -S $HOST:$PORT -t api
