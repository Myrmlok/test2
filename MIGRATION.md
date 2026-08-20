# Миграция на PHP Backend — Инструкция

## ✅ Что сделано

### Backend (PHP + SQLite)
- ✅ Полная миграция с Node.js/PostgreSQL на PHP/SQLite
- ✅ Все API endpoints работают и совместимы с фронтендом
- ✅ JWT авторизация через cookies
- ✅ SQLite база данных с автоматической инициализацией
- ✅ Демо-данные создаются автоматически
- ✅ CORS настроен для работы с фронтендом

### Структура проекта
```
api/
├── index.php       # Главный файл со всеми routes
├── config.php      # Конфигурация (JWT, CORS, БД)
├── database.php    # SQLite + автоинициализация схемы
├── auth.php        # JWT encode/decode, middleware
├── router.php      # HTTP роутер
├── catalog.php     # Статические данные (лоты, темы)
├── forum.php       # Forum helpers
├── seed.php        # Демо-пользователи и данные
├── .htaccess       # Apache URL rewrite
├── test.html       # Тестовая страница API
├── README.md       # Документация
└── database.sqlite # БД (создается автоматически)
```

## 🚀 Запуск

### Вариант 1: Встроенный PHP сервер (для разработки)

```bash
cd /c/Users/Дима/Documents/GitHub/test2
php -S localhost:8000 -t api
```

API будет доступен по адресу: `http://localhost:8000/api/`

### Вариант 2: Apache/Nginx (для production на Timeweb)

1. Загрузите содержимое папки `api/` в корень сайта
2. Убедитесь что `.htaccess` загружен (для Apache)
3. API автоматически доступен по `/api/`

### Вариант 3: Nginx конфигурация

```nginx
location /api/ {
    try_files $uri /api/index.php$is_args$args;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 🧪 Тестирование API

### Через браузер
Откройте в браузере: `http://localhost:8000/api/test.html`

### Через curl

```bash
# Health check
curl http://localhost:8000/api/health

# Получить тематики
curl http://localhost:8000/api/catalog/themes

# Логин
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"login":"dealer_ivanov","password":"123"}' \
  -c cookies.txt

# Текущий пользователь
curl http://localhost:8000/api/auth/me -b cookies.txt

# Список лотов
curl http://localhost:8000/api/lots?section=auction
```

## 🔗 Подключение фронтенда

### Способ 1: Фронтенд на том же домене (рекомендуется)

Если фронтенд и API на одном домене (например, оба на Timeweb), ничего менять не нужно — фронтенд уже использует `/api/`.

### Способ 2: Фронтенд на другом домене

Если фронтенд на другом домене (например, Vercel), настройте proxy или CORS:

**Вариант A: Vite proxy (для разработки)**

В `artifacts/4bor-club/vite.config.ts` добавьте:

```typescript
server: {
  proxy: {
    '/api': {
      target: 'http://localhost:8000',
      changeOrigin: true
    }
  }
}
```

**Вариант B: Настройка CORS на бэкенде**

В `api/config.php` измените:

```php
define('CORS_ALLOW_ORIGIN_REFLECT', true);  // Уже стоит
```

Это автоматически разрешает запросы с любого origin.

## 📝 Демо-аккаунты

Создаются автоматически при первом запуске:

| Логин | Пароль | Роль |
|-------|--------|------|
| `admin` | `admin123` | Администратор |
| `dealer_ivanov` | `123` | Дилер |
| `collector_petrov` | `123` | Коллекционер |

## 📋 Все API Endpoints

### Auth
- `POST /api/auth/login` — вход
- `POST /api/auth/register` — регистрация  
- `POST /api/auth/logout` — выход
- `GET /api/auth/me` — текущий пользователь
- `PATCH /api/auth/me` — сменить роль (демо)

### Catalog & Lots
- `GET /api/catalog/themes` — список тематик
- `GET /api/catalog/themes/:id` — одна тематика
- `GET /api/catalog/themes/:id/groups` — группы
- `GET /api/catalog/groups/:id` — одна группа
- `GET /api/lots` — лоты (query: section, themeId, groupId)
- `GET /api/lots/:id` — один лот
- `GET /api/lots/:id/bids` — история ставок
- `POST /api/lots/:id/bid` — сделать ставку
- `GET /api/catalog/news` — новости
- `GET /api/activity` — активность

### Cart
- `GET /api/cart` — корзина
- `POST /api/cart` — добавить лот
- `DELETE /api/cart/:lotId` — удалить лот
- `DELETE /api/cart` — очистить

### Stickers
- `GET /api/stickers` — список
- `POST /api/stickers` — создать
- `DELETE /api/stickers/:id` — удалить

### Forum
- `GET /api/forum/categories` — категории
- `GET /api/forum/categories/:id/threads` — темы
- `POST /api/forum/categories/:id/threads` — создать тему
- `GET /api/forum/threads/:id` — одна тема
- `GET /api/forum/threads/:id/posts` — посты
- `POST /api/forum/threads/:id/posts` — создать пост
- `POST /api/forum/posts/:id/like` — лайк/анлайк
- `POST /api/forum/threads/:id/bookmark` — закладка
- `PATCH /api/forum/posts/:id` — редактировать пост
- `DELETE /api/forum/posts/:id` — удалить пост
- `PATCH /api/forum/threads/:id` — изм. тему (admin)
- `DELETE /api/forum/threads/:id` — удалить тему (admin)

### Admin
- `GET /api/invites` — список инвайтов
- `POST /api/invites` — создать инвайт
- `DELETE /api/invites/:id` — удалить инвайт
- `GET /api/admin/users` — пользователи
- `PATCH /api/admin/users/:id` — изменить роль
- `DELETE /api/admin/users/:id` — удалить

## 🔧 Конфигурация

### JWT Secret (production)

Установите переменную окружения:

```bash
export JWT_SECRET="your-super-secret-key-change-in-production"
```

Или в PHP:
```php
putenv('JWT_SECRET=your-secret-key');
```

### Права доступа к БД

```bash
chmod 666 api/database.sqlite
chmod 777 api/  # папка должна быть доступна на запись
```

## 📦 Деплой на Timeweb

1. Загрузите папку `api/` в корень сайта через FTP/SFTP
2. Убедитесь что `.htaccess` на месте
3. Проверьте что PHP 7.4+ активен в панели управления
4. Откройте `https://yourdomain.ru/api/health` — должно вернуть `{"status":"ok"}`
5. БД создастся автоматически при первом запросе

## ✨ Что дальше

### Для production:
1. ✅ Установите `JWT_SECRET` через переменную окружения
2. ✅ Проверьте права доступа к `database.sqlite`
3. ✅ Включите HTTPS
4. ✅ Настройте backup базы данных
5. ✅ В `config.php` установите `display_errors = 0`

### Подключение фронтенда:
Фронтенд в `artifacts/4bor-club/` уже настроен на работу с `/api/`.

**Если фронтенд и API на одном домене:**
- Просто соберите фронтенд: `pnpm --filter @workspace/4bor-club build`
- Разместите `dist/public/` рядом с `api/`

**Если на разных доменах:**
- Настройте Vite proxy (см. выше) или
- CORS уже настроен в `api/config.php`

## 🎯 Миграция завершена

- ✅ Backend полностью переписан на PHP
- ✅ База данных мигрирована на SQLite  
- ✅ Все endpoints совместимы с фронтендом
- ✅ Авторизация работает
- ✅ Демо-данные загружаются автоматически
- ✅ Готово к деплою на Timeweb (PHP-only хостинг)

## 🐛 Отладка

### Проблема: 404 на всех /api/* роутах
**Решение:** Проверьте что `.htaccess` загружен и `mod_rewrite` включен

### Проблема: Ошибка прав доступа к БД
**Решение:** 
```bash
chmod 666 api/database.sqlite
chmod 777 api/
```

### Проблема: CORS ошибки
**Решение:** В `api/config.php` уже настроено `CORS_ALLOW_ORIGIN_REFLECT = true`

### Проблема: JWT не работает
**Решение:** Проверьте что cookies работают (должен быть HTTPS в production)
