# RealtimeChat

Браузерный мессенджер с доставкой сообщений в реальном времени. Личные и групповые чаты, статусы прочтения, индикатор печати, ответы на сообщения.

## Стек

| Слой | Технология |
|---|---|
| Backend | Symfony 7 / PHP 8.3 |
| Frontend | Vue 3 + Vite |
| Realtime | Mercure (SSE) |
| База данных | PostgreSQL 16 |
| Авторизация | JWT + Refresh Token |
| Прокси | Nginx |
| Контейнеризация | Docker Compose |

## Быстрый старт

### 1. Переменные окружения

Создай `.env` в корне проекта:

```env
MERCURE_JWT_SECRET=минимум_32_символа_любая_случайная_строка
MERCURE_PUBLIC_URL=http://<твой-домен-или-ip>/.well-known/mercure
```

`MERCURE_PUBLIC_URL` — адрес, по которому браузер достучится до сервера. Для локальной разработки можно не указывать (по умолчанию `http://localhost/.well-known/mercure`).

### 2. Запуск

```bash
docker compose up -d --build
```

При первом запуске Docker соберёт образы, установит зависимости, сгенерирует JWT-ключи и применит миграции автоматически. Занимает 3–5 минут.

### 3. Открыть в браузере

```
http://localhost
```

## Функциональность

- Регистрация и вход по email / username + пароль
- JWT access token (короткий TTL) + refresh token с ротацией
- Личные и групповые чаты
- Отправка, редактирование и удаление сообщений (soft delete)
- Ответы на сообщения (цитирование)
- Доставка новых сообщений всем участникам через SSE без перезагрузки страницы
- Новый чат мгновенно появляется у добавленных участников
- Статусы доставки и прочтения (✓ / ✓✓)
- Индикатор печати
- Онлайн-статус участников
- Cursor-based пагинация истории сообщений (scroll up to load more)
- Роли в групповом чате: `OWNER` / `MEMBER`
- Только `OWNER` может добавлять/удалять участников и удалять чат
- Участник с ролью `MEMBER` может покинуть групповой чат
- Редактирование профиля: username и avatar URL

## Архитектура realtime

```
Vue → POST /api/chats/{id}/messages
        ↓
   Symfony сохраняет в PostgreSQL
        ↓
   Symfony публикует в Mercure Hub
        ↓
   EventSource у каждого участника получает событие
```

Подписка на приватные Mercure topics выдаётся через `POST /api/chats/mercure-subscribe`, который устанавливает cookie `mercureAuthorization`.

## REST API

Полная документация доступна через Swagger UI:

```
http://localhost/api/doc
```

### Основные эндпоинты

| Метод | URL | Описание |
|---|---|---|
| POST | `/api/auth/register` | Регистрация |
| POST | `/api/auth/login` | Логин |
| POST | `/api/auth/refresh` | Обновление access token |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/me` | Текущий пользователь |
| PATCH | `/api/me` | Изменить username / avatar_url |
| POST | `/api/me/ping` | Обновить last_seen_at |
| GET | `/api/chats` | Список чатов с непрочитанными |
| POST | `/api/chats` | Создать чат |
| GET | `/api/chats/{id}` | Детали чата |
| DELETE | `/api/chats/{id}` | Удалить чат (OWNER) |
| POST | `/api/chats/{id}/members` | Добавить участника |
| DELETE | `/api/chats/{id}/members/{uid}` | Удалить участника |
| POST | `/api/chats/{id}/leave` | Покинуть чат (MEMBER) |
| GET | `/api/chats/{id}/messages` | История сообщений |
| POST | `/api/chats/{id}/messages` | Отправить сообщение |
| PATCH | `/api/chats/{id}/messages/{mid}` | Редактировать сообщение |
| DELETE | `/api/chats/{id}/messages/{mid}` | Удалить сообщение |
| POST | `/api/chats/{id}/read` | Отметить прочитанными |
| POST | `/api/chats/{id}/delivered` | Отметить доставленными |
| POST | `/api/chats/{id}/typing` | Typing indicator |
| POST | `/api/chats/mercure-subscribe` | Mercure cookie (все чаты) |

## Разработка

### Backend

```bash
docker compose exec php bash

php bin/console doctrine:migrations:migrate   # применить миграции
php bin/console doctrine:migrations:diff      # сгенерировать новую миграцию
php bin/console cache:clear                   # очистить кэш
php bin/phpunit                               # запустить тесты
```

### Frontend

```bash
cd frontend
npm install
npm run dev     # dev-сервер на http://localhost:5173
npm run build   # сборка в frontend/dist/
```

Dev-сервер проксирует `/api` → `http://localhost` и `/.well-known/mercure` → `http://localhost:3000`.

### Тесты

```bash
docker compose exec php php bin/phpunit
```

Тесты используют SQLite (`.env.test`) и `NullHub` вместо реального Mercure. База пересоздаётся перед каждым тестом.

## Структура проекта

```
messenger_prj/
├── backend/
│   ├── app/
│   │   ├── src/
│   │   │   ├── Controller/   # один файл = один endpoint
│   │   │   ├── Entity/
│   │   │   └── Repository/
│   │   ├── migrations/
│   │   ├── tests/
│   │   └── config/
│   └── Dockerfile
├── frontend/
│   └── src/
│       ├── views/            # ChatView.vue, ChatsView.vue, ...
│       ├── components/       # UserAvatar.vue
│       ├── api.js            # единый HTTP-слой
│       └── router.js
├── docker/
│   └── nginx/
├── docs/
└── docker-compose.yml
```
