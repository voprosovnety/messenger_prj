# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## How to work (token efficiency)

**Diagnose before touching code.** When given a bug or task, first identify the root cause and state what exactly will be changed — do not edit files until the plan is clear. One targeted change beats multiple speculative iterations.

**No exploratory cycles.** Do not read files just to confirm what's already known. If the answer requires checking, read only the specific file+lines needed.

**No trailing summaries.** Do not summarize what was just done at the end of a response — the diff speaks for itself.

## Branch strategy and deployment

Two long-lived branches, each with its own CI/CD pipeline:

| Branch | Environment | URL | Pipeline |
|--------|-------------|-----|----------|
| `dev`  | Dev (staging) | `https://dev.147-45-173-176.sslip.io` | `.github/workflows/deploy-dev.yml` |
| `main` | Production    | `https://147-45-173-176.sslip.io`     | `.github/workflows/deploy-prod.yml` |

**Default working branch is `dev`.** All new work goes to `dev` first.

**Workflow for a task:**
1. Work on `dev` branch locally.
2. Run backend tests before committing.
3. Commit and `git push origin dev` → CI/CD deploys to dev automatically.
4. When ready to ship to prod: `gh pr create --base main --head dev` → `gh pr merge --merge`.

**When the user says "push to dev"** — commit and push to `dev` branch only.  
**When the user says "push to prod" or "merge"** — create a PR dev → main and merge it.  
**After any fix** — always run tests if backend files changed before pushing.

Use conventional commit format: `fix:`, `feat:`, `chore:`, `refactor:`, `test:`.

Never skip hooks (`--no-verify`). Never force-push `main`. Never `git add -A`.

## Git / GitHub workflow

GitHub CLI (`gh`) is authenticated as `voprosovnety`. Use it for all GitHub operations.

```bash
git add <specific files>
git commit -m "type: description"
git push origin dev               # default — pushes to dev
```

For PRs dev → main: `gh pr create --base main --head dev --title "..." --body "..." && gh pr merge --merge --delete-branch=false`.

## Commands

### Running the project

```bash
docker compose up -d           # start all services
docker compose down            # stop
docker compose logs -f php     # backend logs
```

### Backend (run inside php container)

```bash
docker compose exec php bash

# Migrations
php bin/console doctrine:migrations:migrate

# Generate new migration after entity changes
php bin/console doctrine:migrations:diff

# Symfony cache clear
php bin/console cache:clear
```

### Backend tests

Tests run against SQLite (configured in `.env.test`), Mercure is replaced by `NullHub`.

```bash
# All tests
docker compose exec php php bin/phpunit

# Single test class
docker compose exec php php bin/phpunit tests/Api/MessageApiTest.php

# Single test method
docker compose exec php php bin/phpunit --filter testAuthorCanEditOwnMessage
```

### Frontend dev server

```bash
cd frontend
npm install
npm run dev      # http://localhost:5173
npm run build    # outputs to frontend/dist/ (served by nginx in production)
```

The Vite dev server proxies `/api` → `http://localhost` and `/.well-known/mercure` → `http://localhost:3000`, so nginx and Mercure must be running.

## Architecture

### Services (docker-compose.yml)

| Service | Port | Role |
|---|---|---|
| nginx | 80 | Reverse proxy: SPA, PHP-FPM, Mercure SSE |
| php | — | Symfony 7 / PHP-FPM |
| postgres | 5432 | Primary DB |
| mercure | 3000 | SSE hub |

Nginx serves the built Vue SPA for all non-API, non-Mercure paths. `/.well-known/mercure` is proxied to the Mercure container.

### Backend (Symfony 7)

**One controller = one file, one endpoint.** Every endpoint lives in `backend/app/src/Controller/` as a separate `final class` with a single `#[Route]` attribute. Invokable controllers use `__invoke`; multi-action controllers (e.g. `MeController`, `AuthController`) are the exception.

**Auth flow:**
- Registration: `AuthController::register` hashes the password and creates a `User`.
- Login: handled entirely by Symfony `json_login` security (the body of `AuthLoginController` is never executed). The success handler `JwtLoginSuccessHandler` creates the JWT access token + a `RefreshToken` entity and returns both.
- Login accepts `identifier` (can be `username` or `email`) + `password` — the security provider chains `app_user_provider_username` and `app_user_provider_email`.
- JWT is stateless; protected routes require `Authorization: Bearer <token>`.
- Refresh tokens are stored in PostgreSQL (`RefreshToken` entity with `revokedAt`). Redis is not used.
- `POST /api/auth/logout` and the public auth routes bypass JWT (see `access_control` in `security.yaml`).

**Mercure publishing pattern:**
Every controller that publishes to Mercure follows the same pattern:
1. Verify the user is a `ChatMember` of the relevant chat.
2. Compute the topic as `/chats/{chatId}/messages` for chat events, or `/users/{userId}` for personal events.
3. Call `$hub->publish(new Update($topic, $payload, true))` (third arg = private topic).

Clients obtain a subscription cookie via `POST /api/chats/mercure-subscribe` (all chats at once), which sets the `mercureAuthorization` cookie covering both `/chats/{id}/messages` topics and the personal `/users/{userId}` topic.

**Mercure event types:**
- `message.created` — new message (includes `reply_to` if it's a reply)
- `message.edited` — message content updated
- `message.deleted` — soft delete applied
- `user.typing` — typing indicator
- `chat.read` — user marked messages as read
- `chat.delivered` — messages delivered to user
- `chat.created` — published to `/users/{userId}` when a new chat is created or a member is added; triggers SSE reconnect on the client

**Message pagination cursor format:**

```
GET /api/chats/{id}/messages?before={cursor}&limit={n}
```

Cursor is `{created_at_ISO8601}|{uuid}`, e.g. `2026-03-17T09:15:37+00:00|019c...`. Pagination fetches `limit + 1` rows and checks for `hasMore`. Results are returned oldest→newest; `next_cursor` points to the oldest message on the current page.

**`ListChatsController`** uses raw DBAL SQL with PostgreSQL `LATERAL` joins (peer username for DMs, last message, unread count) in a single query. Do not convert this to DQL/ORM.

**Chat roles:** `ChatMember.role` is `OWNER` or `MEMBER`. The creator of a chat always gets `OWNER`. Only `OWNER` can add/remove members and delete a group chat. `MEMBER` can leave a group chat via `POST /api/chats/{id}/leave`; `OWNER` cannot leave (must delete instead).

**Soft delete:** `Message.deletedAt` is set instead of removing the row. Deleted messages are still returned by `ListMessagesController` (with `deleted_at` populated) so clients can render "message deleted" placeholders.

**Reply to message:** `Message.replyTo` is a self-referential ManyToOne with `onDelete: SET NULL`. `CreateMessageController` accepts optional `reply_to_id`. `ListMessagesController` eager-loads `replyTo` and its sender via LEFT JOIN. The `reply_to` field is included in all message payloads (HTTP response and Mercure).

### Frontend (Vue 3)

`frontend/src/api.js` is the single HTTP layer. All API calls go through the `request()` wrapper which automatically retries with a refreshed token on HTTP 401.

**Router** (`frontend/src/router.js`) — routes: `/login`, `/register`, `/` (redirects to `/chats/ai`), `/chats/:chatId` (ChatView), `/profile`. A navigation guard redirects unauthenticated users to `/login`.

**`ChatView.vue`** contains both the sidebar (chat list) and the main message area. It manages the Mercure `EventSource`, handles all incoming event types, and calls `api.markDelivered` / `api.markRead` as messages arrive/are seen.

**SSE reconnection:** A module-level `chatSseGen` counter prevents stale async `attempt()` closures from creating duplicate EventSource connections. `stopChatSse()` increments the counter; every `attempt()` checks `chatSseGen !== gen` after each `await` and aborts if superseded. The `chat.created` event stops SSE synchronously before any `await` to avoid race conditions.

**Reply UI:** `replyingTo` ref holds `{ id, sender, content, deleted }`. `startReply(m)` populates it and focuses the composer. `cancelReply()` clears it. `send()` passes `replyingTo.value?.id` to `api.sendMessage`. Esc key calls `cancelReply`. The reply bar renders above the composer; the quote block renders inside the message bubble.

The `UserAvatar` component renders initials as a fallback when `avatar_url` is not set.

### Tests

`ApiTestCase` (base class for all API tests):
- Drops and recreates the SQLite schema before each test (`resetDatabase`).
- Replaces the Mercure hub with `NullHub` to prevent real SSE publishing.
- `createUser`, `createGroupChat`, `createMessage` are helpers for test data setup.
- `createAuthenticatedClient` injects a real JWT into the browser client.

Test env DB is SQLite (`backend/app/var/test.db`). Production/dev use PostgreSQL.
