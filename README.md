# RealtimeChat

Браузерный мессенджер с доставкой сообщений в реальном времени — личные и групповые чаты, голосовые сообщения, опросы, отложенные сообщения, медиа-вложения и AI-ассистент. Построен на Symfony 7 и Vue 3 с realtime-слоем на Mercure (SSE).

```
Vue 3 SPA  ──HTTP/JSON──▶  Nginx  ──▶  Symfony 7 / PHP-FPM  ──▶  PostgreSQL 16
     ▲                       │
     └──────SSE──────────────┴──▶  Mercure hub  (push-обновления чатов)
```

| Слой | Технология |
|---|---|
| Backend | Symfony 7 · PHP 8.3 |
| Frontend | Vue 3 · Vite |
| Realtime | Mercure (Server-Sent Events) |
| База данных | PostgreSQL 16 |
| Авторизация | JWT + refresh-токены (ротация) |
| AI | Claude API (Anthropic) |
| Прокси | Nginx |
| Инфраструктура | Docker Compose |

---

## Возможности

**Сообщения**
- Личные и групповые чаты с ролями (владелец / участник)
- Realtime-доставка через SSE без перезагрузки страницы
- Статусы доставки и прочтения (✓ / ✓✓), индикатор печати, онлайн-статус
- Ответы с цитированием (клик по цитате — переход к оригиналу), редактирование, мягкое удаление
- Emoji-реакции, закрепление нескольких сообщений (multi-pin), пересылка
- Поиск: глобальный по всем чатам и внутри чата
- Cursor-based пагинация истории

**Богатый контент**
- Вложения: несколько файлов на сообщение — изображения, видео, аудио, документы (до 50 МБ)
- Перетаскивание файлов в окно чата, вставка изображений из буфера
- Полноэкранный лайтбокс с галереей, зумом и панорамированием
- Запись и воспроизведение голосовых сообщений (MediaRecorder + кастомный плеер с waveform)
- Опросы: одиночный / множественный выбор, анонимные и именные, отзыв голоса
- Отложенные сообщения с фоновой отправкой

**Платформа**
- AI-ассистент на базе Claude Haiku
- Аватары профиля и групп с историей версий
- Адаптивная вёрстка, mobile-жесты (swipe-to-reply, long-press меню), тёмная тема
- JWT-авторизация с автоматическим обновлением токенов

---

## Быстрый старт

### Требования
- Docker и Docker Compose

### 1. Настройте окружение

Создайте `.env` в корне проекта:

```env
# Обязательно
MERCURE_JWT_SECRET=минимум_32_символа_случайной_строки
MERCURE_PUBLIC_URL=http://<домен-или-ip>/.well-known/mercure

# Опционально — для AI-ассистента
ANTHROPIC_API_KEY=sk-ant-...

# Обязательно переопределить перед продакшн-деплоем
POSTGRES_PASSWORD=смените_в_продакшн     # по умолчанию "messenger"
CORS_ORIGINS=https://<ваш-домен>          # по умолчанию "*" (только для dev)
```

### 2. Запустите

```bash
docker compose up -d --build
```

Первый запуск собирает образы, ставит зависимости, генерирует JWT-ключи и применяет миграции (~3-5 минут). Откройте `http://localhost`.

---

## Локальная разработка

Frontend поднимается отдельно с hot-reload, проксируя API и Mercure на backend в Docker:

```bash
# backend в Docker
docker compose up -d

# frontend с hot-reload
cd frontend
npm install
npm run dev        # http://localhost:5173
```

`npm run dev` проксирует `/api` → localhost и `/.well-known/mercure` → localhost:3000.
`npm run build` собирает SPA в `frontend/dist/` (в продакшн раздаётся через Nginx).

---

## Тестирование

Интеграционные тесты (PHPUnit) гоняются против реальной SQLite-базы; Mercure заменяется на `NullHub`.

```bash
docker compose exec php php bin/phpunit                          # весь набор
docker compose exec php php bin/phpunit tests/Api/MessageApiTest.php
docker compose exec php php bin/phpunit --filter testAuthorCanEditOwnMessage
```

---

## Структура проекта

```
.
├── backend/app/
│   ├── src/
│   │   ├── Controller/      # один контроллер = один эндпоинт (invokable)
│   │   ├── Entity/          # User, Chat, Message, Poll, RefreshToken, …
│   │   ├── Service/         # LinkPreview, PollHelper, ReactionHelper, ScheduledMessageDispatcher
│   │   ├── Security/        # JWT login success handler, user providers
│   │   └── Command/         # app:scheduled-messages:dispatch
│   ├── config/              # security.yaml, packages/, routes/
│   └── tests/Api/           # интеграционные тесты
├── frontend/src/
│   ├── views/               # ChatView (основной экран), Login, Register, Profile
│   ├── components/          # AudioPlayer, ImageLightbox, PollMessage, EmojiPicker, …
│   ├── composables/         # useFocusTrap
│   ├── api.js               # единый HTTP-слой с авто-refresh токена
│   └── style.css            # дизайн-система (CSS-токены)
├── docker/nginx/            # конфиг reverse-proxy
└── docker-compose.yml
```

### Сервисы (docker-compose)

| Сервис | Порт | Роль |
|---|---|---|
| nginx | 80 | Reverse-proxy: SPA, PHP-FPM, Mercure SSE |
| php | — | Symfony 7 / PHP-FPM |
| postgres | 5432 | Основная БД |
| mercure | 3000 | SSE-хаб |
| scheduler | — | Фоновая отправка отложенных сообщений (каждые 30 с) |

---

## Архитектурные детали

- **Один контроллер = один файл = один эндпоинт.** Каждый эндпоинт — отдельный `final class` с единственным `#[Route]` и `__invoke`.
- **Realtime.** Контроллеры публикуют события в Mercure на топики `/chats/{id}/messages` и `/users/{id}` (private). Клиент подписывается через `EventSource` и получает cookie-подписку на свои чаты. Типы событий: `message.created/edited/deleted`, `message.reaction`, `message.pinned`, `poll.voted`, `user.typing`, `chat.read/delivered/created/updated`.
- **Авторизация.** Stateless JWT в заголовке `Authorization: Bearer`; refresh-токены хранятся в PostgreSQL с ротацией. Логин принимает username или email.
- **Пагинация.** Keyset-курсор `{created_at}|{uuid}`, выборка `limit+1` строк для определения `hasMore`.

---

## API

<details>
<summary>Полная таблица эндпоинтов</summary>

| Метод | URL | Описание |
|---|---|---|
| POST | `/api/auth/register` | Регистрация |
| POST | `/api/auth/login` | Логин (username или email) |
| POST | `/api/auth/refresh` | Обновить токен |
| POST | `/api/auth/logout` | Логаут |
| GET | `/api/me` | Текущий пользователь |
| PATCH | `/api/me` | Изменить профиль |
| POST | `/api/me/ping` | Обновить онлайн-статус |
| GET | `/api/me/avatar-history` | История аватаров профиля |
| DELETE | `/api/me/avatar-history/{id}` | Удалить запись истории |
| GET | `/api/chats` | Список чатов |
| POST | `/api/chats` | Создать чат |
| GET | `/api/chats/{id}` | Детали чата (пины, аватар, участники) |
| PATCH | `/api/chats/{id}` | Переименовать / сменить аватар |
| DELETE | `/api/chats/{id}` | Удалить чат |
| POST | `/api/chats/{id}/members` | Добавить участника |
| DELETE | `/api/chats/{id}/members/{uid}` | Удалить участника |
| POST | `/api/chats/{id}/leave` | Покинуть чат |
| GET | `/api/chats/{id}/messages` | История сообщений |
| POST | `/api/chats/{id}/messages` | Отправить сообщение |
| PATCH | `/api/chats/{id}/messages/{mid}` | Редактировать |
| DELETE | `/api/chats/{id}/messages/{mid}` | Удалить |
| POST | `/api/chats/{id}/typing` | Индикатор печати |
| POST | `/api/chats/{id}/pin` | Закрепить / открепить |
| POST | `/api/chats/{id}/messages/{mid}/reactions` | Реакция |
| GET | `/api/chats/{id}/messages/{mid}/read-by` | Кто прочитал (группа) |
| GET | `/api/chats/{id}/media` | Медиагалерея чата |
| GET | `/api/chats/{id}/messages/search` | Поиск внутри чата |
| GET | `/api/chats/{id}/avatar-history` | История аватаров группы |
| DELETE | `/api/chats/{id}/avatar-history/{aid}` | Удалить запись |
| POST | `/api/chats/mercure-subscribe` | Подписка на SSE |
| POST | `/api/chats/{id}/messages/poll` | Создать опрос |
| POST | `/api/chats/{id}/messages/{mid}/poll/vote` | Проголосовать / снять голос |
| GET | `/api/chats/{id}/scheduled-messages` | Список отложенных |
| POST | `/api/chats/{id}/scheduled-messages` | Запланировать |
| PATCH | `/api/scheduled-messages/{id}` | Изменить отложенное |
| DELETE | `/api/scheduled-messages/{id}` | Отменить отложенное |
| GET | `/api/messages/search` | Глобальный поиск |
| GET | `/api/users/{username}` | Профиль пользователя |
| GET | `/api/users/online` | Онлайн-пользователи |
| POST | `/api/upload` | Загрузить файл (до 50 МБ) |
| POST | `/api/ai/chat` | AI-ассистент (Claude Haiku) |

</details>
