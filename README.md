# 4bor.club

Закрытый клуб для дилеров и коллекционеров монет.

## 🎯 Stack

### Backend
- **PHP 8+** — основной язык бэкенда
- **SQLite** — файловая база данных
- **JWT** — авторизация через cookies
- **REST API** — полностью совместимый с фронтендом

### Frontend
- **React 19** + **TypeScript**
- **Vite** — сборка
- **Tailwind CSS** + **Radix UI**
- **TanStack Query** — работа с API
- **Wouter** — роутинг

## 🚀 Быстрый старт

### Запуск backend (PHP API)

```bash
# Через bash (Linux/Mac/Git Bash)
./start-api.sh

# Через bat (Windows CMD)
start-api.bat

# Или напрямую через PHP
php -S localhost:8000 -t api
```

API будет доступен: `http://localhost:8000/api/`

### Запуск frontend

```bash
# Установка зависимостей
pnpm install

# Запуск dev-сервера
pnpm --filter @workspace/4bor-club dev

# Сборка для production
pnpm --filter @workspace/4bor-club build
```

Frontend будет доступен: `http://localhost:3000` (или другой порт из .env)

## 📁 Структура проекта

```
.
├── api/                    # PHP Backend
│   ├── index.php          # Главный роутер + endpoints
│   ├── database.php       # SQLite + схема
│   ├── auth.php           # JWT авторизация
│   ├── config.php         # Конфигурация
│   └── ...
├── artifacts/
│   └── 4bor-club/         # React Frontend
│       ├── src/
│       │   ├── pages/     # Страницы
│       │   ├── components/# Компоненты
│       │   ├── contexts/  # React contexts
│       │   └── lib/       # API client
│       └── ...
└── lib/                    # Shared библиотеки
```

## 🔑 Демо-аккаунты

| Логин | Пароль | Роль |
|-------|--------|------|
| `admin` | `admin123` | Администратор |
| `dealer_ivanov` | `123` | Дилер |
| `collector_petrov` | `123` | Коллекционер |

## 🧪 Тестирование

### Backend API
Откройте в браузере: `http://localhost:8000/api/test.html`

Или через curl:
```bash
# Health check
curl http://localhost:8000/api/health

# Логин
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"dealer_ivanov","password":"123"}' \
  -c cookies.txt

# Получить текущего пользователя
curl http://localhost:8000/api/auth/me -b cookies.txt
```

## 📦 Деплой

### Backend на Timeweb (PHP хостинг)

1. Загрузите содержимое папки `api/` в корень сайта
2. Убедитесь что `.htaccess` загружен
3. Проверьте права доступа:
   ```bash
   chmod 777 api/
   chmod 666 api/database.sqlite
   ```
4. Откройте `https://yourdomain.ru/api/health`

### Frontend на Vercel/Netlify

1. Соберите frontend:
   ```bash
   pnpm --filter @workspace/4bor-club build
   ```

2. Настройте переменные окружения:
   ```
   BASE_PATH=/
   PORT=3000
   ```

3. Деплой из `artifacts/4bor-club/dist/public/`

## 📖 Документация

- [MIGRATION.md](./MIGRATION.md) — подробная инструкция по миграции
- [api/README.md](./api/README.md) — документация API
- [replit.md](./replit.md) — архитектурные решения

## 🛠 Разработка

### Добавление нового API endpoint

В файле `api/index.php`:

```php
$router->get('/api/your-endpoint', function() {
    $user = Auth::requireAuth(); // Если нужна авторизация
    $db = Database::getInstance();
    
    // Ваша логика
    
    Response::json(['data' => 'result']);
});
```

### Добавление новой страницы

1. Создайте компонент в `artifacts/4bor-club/src/pages/`
2. Добавьте роут в `artifacts/4bor-club/src/App.tsx`:
   ```tsx
   <Route path="/your-page">
     <MainLayout><YourPage /></MainLayout>
   </Route>
   ```

## 🔧 Конфигурация

### Backend (api/config.php)

```php
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'change-this');
define('COOKIE_SECURE', true);  // HTTPS only
define('CORS_ALLOW_CREDENTIALS', true);
```

### Frontend (artifacts/4bor-club/.env)

```env
PORT=3000
BASE_PATH=/
```

## 🐛 Известные проблемы

### CORS ошибки
CORS уже настроен в `api/config.php` для отражения любого origin.

### 404 на /api/* роутах
Проверьте что `.htaccess` загружен и `mod_rewrite` включен в Apache.

### Ошибки прав доступа к БД
```bash
chmod 666 api/database.sqlite
chmod 777 api/
```

## 📝 Changelog

### v2.0 (2026-08-20)
- ✅ Миграция backend с Node.js на PHP
- ✅ Миграция БД с PostgreSQL на SQLite
- ✅ Сохранена 100% совместимость API
- ✅ Готово к деплою на Timeweb (PHP-only хостинг)

### v1.0 (2024)
- React frontend
- Node.js + Express backend
- PostgreSQL database

## 📄 Лицензия

MIT

## 👥 Разработка

Этот проект создан для демонстрации полнофункционального веб-приложения с аутентификацией, форумом, аукционами и административной панелью.
