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

# Переопредели перед первым деплоем в продакшн:
POSTGRES_PASSWORD=смени_в_продакшн   # по умолчанию "messenger"
CORS_ORIGINS=https://<твой-домен>    # по умолчанию "*" (только dev)
```

Запусти:

```bash
docker compose up -d --build
```

При первом запуске Docker соберёт образы, установит зависимости, сгенерирует JWT-ключи и применит миграции. Занимает 3–5 минут. Открывай `http://localhost`.

## Возможности

**Чаты и сообщения**
- Личные и групповые чаты с ролями (владелец / участник)
- Реал-тайм доставка сообщений через SSE без перезагрузки страницы
- Статусы доставки и прочтения (✓ / ✓✓), индикатор печати, онлайн-статус
- Ответы на сообщения с цитированием, клик по цитате прыгает к оригиналу
- Редактирование сообщений в стиле Telegram (бар редактирования над полем ввода)
- Мягкое удаление сообщений (placeholder "message deleted")
- Emoji-реакции на сообщения с Mercure-обновлением в реальном времени
- Закреп нескольких сообщений (multi-pin, Telegram-style) с навигационной панелью
- Опросы (polls) в чате: одиночный и множественный выбор, анонимные и именные
- Отложенные сообщения (Telegram-style): выбор даты/времени, управление очередью, фоновая отправка каждые 30 с
- Поиск сообщений: глобальный по всем чатам (`GET /api/messages/search`) и внутри чата

**Медиа и файлы**
- Вложения: несколько файлов на одно сообщение (изображения, видео, аудио, документы до 50 МБ)
- Перетаскивание файлов в окно чата (drag-and-drop)
- Полноэкранный просмотр изображений (лайтбокс) со слайдером между всеми фото в чате, зумом и панорамированием
- Запись и воспроизведение голосовых сообщений (MediaRecorder API + кастомный плеер)

**Профили и UI**
- Аватарки профиля и групп с историей (можно вернуть прошлый аватар)
- Просмотр профиля пользователя по клику на аватарку или имя
- Панель онлайн-пользователей
- Скрываемая боковая панель, адаптивная вёрстка для узких экранов

**Прочее**
- AI-ассистент на базе Claude Haiku (Anthropic)
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
| GET | `/api/users/{username}` | Профиль пользователя |
| PATCH | `/api/chats/{id}` | Переименовать чат / сменить аватарку |
| GET | `/api/chats/{id}/messages` | История сообщений |
| POST | `/api/chats/{id}/messages` | Отправить сообщение |
| PATCH | `/api/chats/{id}/messages/{mid}` | Редактировать сообщение |
| DELETE | `/api/chats/{id}/messages/{mid}` | Удалить сообщение |
| POST | `/api/chats/{id}/typing` | Индикатор печати |
| POST | `/api/chats/{id}/pin` | Закрепить / открепить сообщение |
| POST | `/api/chats/{id}/messages/{mid}/reactions` | Поставить / убрать реакцию |
| POST | `/api/chats/mercure-subscribe` | Подписка на SSE (все чаты) |
| GET | `/api/users/online` | Список онлайн-пользователей |
| GET | `/api/me/avatar-history` | История аватаров профиля |
| DELETE | `/api/me/avatar-history/{id}` | Удалить запись истории аватара |
| GET | `/api/chats/{id}/avatar-history` | История аватаров группы |
| DELETE | `/api/chats/{id}/avatar-history/{id}` | Удалить запись истории аватара группы |
| GET | `/api/chats/{id}` | Детали чата (пины, аватар, участники) |
| GET | `/api/chats/{id}/media` | Медиагалерея чата (изображения и видео) |
| GET | `/api/chats/{id}/messages/{mid}/read-by` | Кто прочитал сообщение (группа) |
| POST | `/api/upload` | Загрузить файл (до 50 МБ) |
| POST | `/api/me/ping` | Обновить онлайн-статус (раз в ~60 с) |
| POST | `/api/ai/chat` | AI-ассистент (Claude Haiku) |
| GET | `/api/chats/{id}/messages/search` | Поиск сообщений внутри чата |
| GET | `/api/messages/search` | Глобальный поиск по всем чатам |
| POST | `/api/chats/{chatId}/messages/poll` | Создать опрос |
| POST | `/api/chats/{chatId}/messages/{mid}/poll/vote` | Проголосовать / снять голос |
| GET | `/api/chats/{chatId}/scheduled-messages` | Список отложенных сообщений |
| POST | `/api/chats/{chatId}/scheduled-messages` | Запланировать сообщение |
| PATCH | `/api/scheduled-messages/{id}` | Изменить отложенное сообщение |
| DELETE | `/api/scheduled-messages/{id}` | Отменить отложенное сообщение |
