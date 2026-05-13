# RealtimeChat

Браузерный мессенджер с доставкой сообщений в реальном времени.

## Стек

| Слой | Технология |
|---|---|
| Backend | Symfony 7 / PHP 8.3 |
| Frontend | Vue 3 + Vite |
| Realtime | Mercure (SSE) |
| База данных | PostgreSQL 16 |
| Авторизация | JWT + Refresh Token |
| AI | Claude API (Anthropic) |
| Прокси | Nginx |
| Контейнеризация | Docker Compose |

## Быстрый старт

Создай `.env` в корне проекта:

```env
MERCURE_JWT_SECRET=минимум_32_символа_любая_случайная_строка
MERCURE_PUBLIC_URL=http://<домен-или-ip>/.well-known/mercure
ANTHROPIC_API_KEY=sk-ant-...   # опционально, для AI-ассистента
```

Запусти:

```bash
docker compose up -d --build
```

При первом запуске Docker соберёт образы, установит зависимости, сгенерирует JWT-ключи и применит миграции. Занимает 3–5 минут. Открывай `http://localhost`.

## Возможности

- Личные и групповые чаты с ролями (владелец / участник)
- Реал-тайм доставка сообщений через SSE без перезагрузки страницы
- Статусы доставки и прочтения (✓ / ✓✓), индикатор печати, онлайн-статус
- Ответы на сообщения с цитированием, редактирование и мягкое удаление
- Вложения: изображения, видео, аудио, документы до 50 МБ
- Запись и воспроизведение голосовых сообщений
- AI-ассистент на базе Claude (Anthropic)
- JWT-авторизация с автоматическим обновлением токенов
- Cursor-based пагинация истории сообщений

## API

| Метод | URL | Описание |
|---|---|---|
| POST | `/api/auth/register` | Регистрация |
| POST | `/api/auth/login` | Логин |
| POST | `/api/auth/refresh` | Обновить токен |
| POST | `/api/auth/logout` | Логаут |
| GET | `/api/me` | Текущий пользователь |
| PATCH | `/api/me` | Изменить профиль |
| GET | `/api/chats` | Список чатов |
| POST | `/api/chats` | Создать чат |
| DELETE | `/api/chats/{id}` | Удалить чат |
| POST | `/api/chats/{id}/members` | Добавить участника |
| DELETE | `/api/chats/{id}/members/{uid}` | Удалить участника |
| POST | `/api/chats/{id}/leave` | Покинуть чат |
| PATCH | `/api/chats/{id}` | Переименовать чат |
| GET | `/api/chats/{id}/messages` | История сообщений |
| POST | `/api/chats/{id}/messages` | Отправить сообщение |
| PATCH | `/api/chats/{id}/messages/{mid}` | Редактировать сообщение |
| DELETE | `/api/chats/{id}/messages/{mid}` | Удалить сообщение |
| POST | `/api/chats/{id}/typing` | Индикатор печати |
| POST | `/api/chats/mercure-subscribe` | Подписка на SSE |
| POST | `/api/ai/chat` | AI-ассистент |
