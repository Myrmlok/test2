# PHP Backend Migration

## Stack
- PHP 7.4+
- SQLite (файловая БД)
- JWT авторизация через cookies
- REST API совместимый с существующим фронтендом

## Структура

```
api/
├── index.php       # Главный роутер и все endpoints
├── config.php      # Конфигурация
├── database.php    # SQLite подключение и инициализация схемы
├── auth.php        # JWT и авторизация
├── router.php      # HTTP роутер
├── catalog.php     # Статические данные каталога
├── forum.php       # Forum helpers
├── seed.php        # Демо-данные
├── .htaccess       # URL rewrite для Apache
└── database.sqlite # SQLite БД (создается автоматически)
```

## Установка

1. Убедитесь что PHP 7.4+ установлен
2. Убедитесь что Apache с mod_rewrite или nginx настроен
3. Скопируйте содержимое `api/` в корень веб-сервера или настройте виртуальный хост
4. БД и демо-данные создаются автоматически при первом запросе

## Endpoints

Все endpoints начинаются с `/api`:

### Auth
- `POST /api/auth/login` - вход
- `POST /api/auth/register` - регистрация
- `POST /api/auth/logout` - выход
- `GET /api/auth/me` - текущий пользователь
- `PATCH /api/auth/me` - сменить роль (демо)

### Catalog
- `GET /api/catalog/themes` - список тематик
- `GET /api/catalog/themes/:id` - одна тематика
- `GET /api/catalog/themes/:id/groups` - группы тематики
- `GET /api/catalog/groups/:id` - одна группа
- `GET /api/lots` - список лотов (query: section, themeId, groupId)
- `GET /api/lots/:id` - один лот
- `GET /api/lots/:id/bids` - история ставок
- `POST /api/lots/:id/bid` - сделать ставку
- `GET /api/catalog/news` - новости
- `GET /api/activity` - активность

### Cart
- `GET /api/cart` - корзина пользователя
- `POST /api/cart` - добавить в корзину
- `DELETE /api/cart/:lotId` - удалить из корзины
- `DELETE /api/cart` - очистить корзину

### Stickers
- `GET /api/stickers` - список стикеров
- `POST /api/stickers` - создать стикер
- `DELETE /api/stickers/:id` - удалить стикер

### Forum
- `GET /api/forum/categories` - список категорий
- `GET /api/forum/categories/:id` - одна категория
- `GET /api/forum/categories/:id/threads` - темы категории
- `POST /api/forum/categories/:id/threads` - создать тему
- `GET /api/forum/threads/:id` - одна тема
- `GET /api/forum/threads/:id/posts` - посты темы
- `POST /api/forum/threads/:id/posts` - создать пост
- `PATCH /api/forum/threads/:id` - изменить тему (admin)
- `DELETE /api/forum/threads/:id` - удалить тему (admin)
- `PATCH /api/forum/posts/:id` - изменить пост
- `DELETE /api/forum/posts/:id` - удалить пост
- `POST /api/forum/posts/:id/like` - лайк/анлайк поста
- `POST /api/forum/threads/:id/bookmark` - добавить/убрать закладку

### Admin
- `GET /api/invites` - список инвайтов
- `POST /api/invites` - создать инвайт
- `DELETE /api/invites/:id` - удалить инвайт
- `GET /api/admin/users` - список пользователей
- `PATCH /api/admin/users/:id` - изменить роль
- `DELETE /api/admin/users/:id` - удалить пользователя

### Health
- `GET /api/health` - статус API

## Демо-аккаунты

Создаются автоматически при первом запуске:

- Администратор: `admin` / `admin123`
- Дилер: `dealer_ivanov` / `123`
- Коллекционер: `collector_petrov` / `123`

## Конфигурация фронтенда

Фронтенд использует относительный путь `/api`, поэтому:

1. Если фронтенд на том же домене: работает сразу
2. Если фронтенд на другом домене: настройте CORS в `config.php`

## Переменные окружения (опционально)

```
JWT_SECRET=your-secret-key
```

Если не указано, используется дефолтное значение (только для разработки).

## Миграция с Node.js

1. Все API endpoints сохранили совместимость
2. JWT cookies работают так же
3. Схема БД идентична Drizzle схеме
4. Статические данные (лоты, темы) остались те же

## Production

Для production:

1. Установите `JWT_SECRET` через переменную окружения
2. Отключите `display_errors` в `config.php`
3. Настройте HTTPS
4. Настройте backup для `database.sqlite`
5. Проверьте права доступа к файлу БД (должен быть доступен на запись)
