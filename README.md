# RealtimeChat

A browser-based messenger with realtime delivery — private and group chats, voice messages,
polls, scheduled messages, media attachments and a built-in AI assistant. Built on Symfony 7
and Vue 3, with a realtime layer on Mercure (SSE).

```
Vue 3 SPA  ──HTTP/JSON──▶  Nginx  ──▶  Symfony 7 / PHP-FPM  ──▶  PostgreSQL 16
     ▲                       │
     └──────SSE──────────────┴──▶  Mercure hub  (chat push updates)
```

| Layer | Technology |
|---|---|
| Backend | Symfony 7 · PHP 8.3 |
| Frontend | Vue 3 · Vite |
| Realtime | Mercure (Server-Sent Events) |
| Database | PostgreSQL 16 |
| Auth | JWT + refresh tokens (rotating) |
| AI | Claude API (Anthropic) |
| Proxy | Nginx |
| Infrastructure | Docker Compose |

---

## Features

**Messaging**
- Private and group chats with roles (owner / member)
- Realtime delivery over SSE, no page reloads
- Delivery and read receipts (✓ / ✓✓), typing indicator, online status
- Quoted replies (click a quote to jump to the original), editing, soft delete
- Emoji reactions, multi-pin, forwarding
- Search: globally across all chats and within a single chat
- Cursor-based history pagination

**Rich content**
- Attachments: multiple files per message — images, video, audio, documents (up to 50 MB)
- Drag-and-drop files into the chat window, paste images from the clipboard
- Fullscreen lightbox with gallery, zoom and panning
- Recording and playback of voice messages (MediaRecorder + custom waveform player)
- Polls: single / multiple choice, anonymous or named, vote retraction
- Scheduled messages with background dispatch

**Platform**
- AI assistant powered by Claude Haiku
- Profile and group avatars with version history
- Responsive layout, mobile gestures (swipe-to-reply, long-press menu), dark theme
- JWT auth with automatic token renewal

---

## Quick start

### Requirements
- Docker and Docker Compose

### 1. Configure the environment

Create a `.env` file in the project root:

```env
# Required
MERCURE_JWT_SECRET=at_least_32_characters_of_random_string
MERCURE_PUBLIC_URL=http://<domain-or-ip>/.well-known/mercure

# Optional — for the AI assistant
ANTHROPIC_API_KEY=sk-ant-...

# Must be overridden before a production deploy
POSTGRES_PASSWORD=change_in_production     # defaults to "messenger"
CORS_ORIGINS=https://<your-domain>         # defaults to "*" (dev only)
```

### 2. Start it

```bash
docker compose up -d --build
```

The first run builds the images, installs dependencies, generates JWT keys and applies
migrations (~3–5 minutes). Then open `http://localhost`.

---

## Local development

The frontend runs separately with hot-reload, proxying API and Mercure to the backend in
Docker:

```bash
# backend in Docker
docker compose up -d

# frontend with hot-reload
cd frontend
npm install
npm run dev        # http://localhost:5173
```

`npm run dev` proxies `/api` → localhost and `/.well-known/mercure` → localhost:3000.
`npm run build` bundles the SPA into `frontend/dist/` (served through Nginx in production).

---

## Testing

Integration tests (PHPUnit) run against a real SQLite database; Mercure is replaced with
`NullHub`.

```bash
docker compose exec php php bin/phpunit                          # full suite
docker compose exec php php bin/phpunit tests/Api/MessageApiTest.php
docker compose exec php php bin/phpunit --filter testAuthorCanEditOwnMessage
```

---

## Project structure

```
.
├── backend/app/
│   ├── src/
│   │   ├── Controller/      # one controller = one endpoint (invokable)
│   │   ├── Entity/          # User, Chat, Message, Poll, RefreshToken, …
│   │   ├── Service/         # LinkPreview, PollHelper, ReactionHelper, ScheduledMessageDispatcher
│   │   ├── Security/        # JWT login success handler, user providers
│   │   └── Command/         # app:scheduled-messages:dispatch
│   ├── config/              # security.yaml, packages/, routes/
│   └── tests/Api/           # integration tests
├── frontend/src/
│   ├── views/               # ChatView (main screen), Login, Register, Profile
│   ├── components/          # AudioPlayer, ImageLightbox, PollMessage, EmojiPicker, …
│   ├── composables/         # useFocusTrap
│   ├── api.js               # single HTTP layer with automatic token refresh
│   └── style.css            # design system (CSS tokens)
├── docker/nginx/            # reverse-proxy config
└── docker-compose.yml
```

### Services (docker-compose)

| Service | Port | Role |
|---|---|---|
| nginx | 80 | Reverse proxy: SPA, PHP-FPM, Mercure SSE |
| php | — | Symfony 7 / PHP-FPM |
| postgres | 5432 | Main database |
| mercure | 3000 | SSE hub |
| scheduler | — | Background dispatch of scheduled messages (every 30 s) |

---

## Architecture notes

- **One controller = one file = one endpoint.** Every endpoint is a separate `final class`
  with a single `#[Route]` and an `__invoke` method.
- **Realtime.** Controllers publish events to Mercure on the topics `/chats/{id}/messages`
  and `/users/{id}` (private). The client subscribes via `EventSource` and receives a
  cookie-based subscription to its own chats. Event types: `message.created/edited/deleted`,
  `message.reaction`, `message.pinned`, `poll.voted`, `user.typing`,
  `chat.read/delivered/created/updated`.
- **Auth.** Stateless JWT in the `Authorization: Bearer` header; refresh tokens are stored in
  PostgreSQL with rotation. Login accepts either a username or an email.
- **Pagination.** Keyset cursor `{created_at}|{uuid}`, fetching `limit+1` rows to determine
  `hasMore`.

---

## API

<details>
<summary>Full endpoint table</summary>

| Method | URL | Description |
|---|---|---|
| POST | `/api/auth/register` | Register |
| POST | `/api/auth/login` | Log in (username or email) |
| POST | `/api/auth/refresh` | Refresh token |
| POST | `/api/auth/logout` | Log out |
| GET | `/api/me` | Current user |
| PATCH | `/api/me` | Update profile |
| POST | `/api/me/ping` | Update online status |
| GET | `/api/me/avatar-history` | Profile avatar history |
| DELETE | `/api/me/avatar-history/{id}` | Delete a history entry |
| GET | `/api/chats` | List chats |
| POST | `/api/chats` | Create a chat |
| GET | `/api/chats/{id}` | Chat details (pins, avatar, members) |
| PATCH | `/api/chats/{id}` | Rename / change avatar |
| DELETE | `/api/chats/{id}` | Delete a chat |
| POST | `/api/chats/{id}/members` | Add a member |
| DELETE | `/api/chats/{id}/members/{uid}` | Remove a member |
| POST | `/api/chats/{id}/leave` | Leave a chat |
| GET | `/api/chats/{id}/messages` | Message history |
| POST | `/api/chats/{id}/messages` | Send a message |
| PATCH | `/api/chats/{id}/messages/{mid}` | Edit |
| DELETE | `/api/chats/{id}/messages/{mid}` | Delete |
| POST | `/api/chats/{id}/typing` | Typing indicator |
| POST | `/api/chats/{id}/pin` | Pin / unpin |
| POST | `/api/chats/{id}/messages/{mid}/reactions` | React |
| GET | `/api/chats/{id}/messages/{mid}/read-by` | Who has read it (groups) |
| GET | `/api/chats/{id}/media` | Chat media gallery |
| GET | `/api/chats/{id}/messages/search` | Search within a chat |
| GET | `/api/chats/{id}/avatar-history` | Group avatar history |
| DELETE | `/api/chats/{id}/avatar-history/{aid}` | Delete a history entry |
| POST | `/api/chats/mercure-subscribe` | Subscribe to SSE |
| POST | `/api/chats/{id}/messages/poll` | Create a poll |
| POST | `/api/chats/{id}/messages/{mid}/poll/vote` | Vote / retract a vote |
| GET | `/api/chats/{id}/scheduled-messages` | List scheduled messages |
| POST | `/api/chats/{id}/scheduled-messages` | Schedule a message |
| PATCH | `/api/scheduled-messages/{id}` | Edit a scheduled message |
| DELETE | `/api/scheduled-messages/{id}` | Cancel a scheduled message |
| GET | `/api/messages/search` | Global search |
| GET | `/api/users/{username}` | User profile |
| GET | `/api/users/online` | Online users |
| POST | `/api/upload` | Upload a file (up to 50 MB) |
| POST | `/api/ai/chat` | AI assistant (Claude Haiku) |

</details>
