# UX Improvements Backlog

Living backlog of UX/UI ideas for the messenger. This file is maintained automatically by the agent pipeline (see CLAUDE.md → "UX backlog maintenance"). Do not delete the section headers — agents key off them.

## How this file is used

- **Planned / In progress** — ideas waiting to be picked up, or actively being worked on. New ideas (from the user, or surfaced by any agent during work) are appended here.
- **Completed** — items that have shipped to `dev`. Each line records the version (from `/VERSION`) in which it was delivered.
- **Reference** — design intent, things deliberately left alone, and the "what's already good" notes. Not actionable; not pruned automatically.

---

## Planned / In progress

<!-- Audit 2026-06-03: due-diligence pass (Telegram/Discord/Linear-grade bar).
     DD-1..DD-6 shipped in v1.6.7. DD-7..DD-11 documented (higher risk / larger scope). -->

### Audit 2026-06-03 — Due diligence

#### Shipped (low/medium risk) — v1.6.7

- [x] **DD-1 · Race condition: параллельные 401 запускают несколько refresh, ротация токена инвалидирует сама себя → внезапный логаут** — shipped in v1.6.7
  _Что было:_ `api.js tryRefresh()` не имел защиты от конкурентного вызова. Refresh-токен ротируется на каждый `/auth/refresh`; при двух одновременных 401 (типично: открытие чата делает много запросов сразу) каждый забирал токен и инвалидировал чужой → `!res.ok` → удаление токенов → выкидывает на /login на ровном месте.
  _Фикс:_ единый in-flight промис (`refreshPromise`) — все конкурентные вызовы ждут один refresh; токен ротируется ровно один раз. Плюс try/catch вокруг fetch, чтобы сетевая ошибка не разлогинивала.
  _Файлы:_ `frontend/src/api.js`

- [x] **DD-2 · Перф: `renderContent()` пересчитывается для каждого сообщения на каждом ререндере** — shipped in v1.6.7
  _Что было:_ `v-html="renderContent(m.content)"` — вызов функции в шаблоне (Vue не кэширует), а функция делает `document.createElement` + 6 regex-проходов. В длинном чате любой реактивный апдейт прогонял это по всем видимым сообщениям.
  _Фикс:_ мемоизация по строке контента (`Map`, cap 1000, эвикт старейшего). Вывод — чистая функция входа, кэш всегда валиден.
  _Файлы:_ `frontend/src/views/ChatView.vue`

- [x] **DD-3 · Перф: изображения-вложения без `loading="lazy"` / `decoding="async"`** — shipped in v1.6.7
  _Фикс:_ добавлены `loading="lazy"` + `decoding="async"` на сетку вложений; `decoding="async"` на превью композера и в лайтбоксе. Меньше блокировки главного потока при прокрутке истории.
  _Файлы:_ `frontend/src/views/ChatView.vue`, `frontend/src/components/ImageLightbox.vue`

- [x] **DD-4 · A11y: изображения без `alt`** — shipped in v1.6.7
  _Фикс:_ `alt` (имя файла / fallback) на изображениях вложений, превью композера, лайтбоксе.
  _Файлы:_ `frontend/src/views/ChatView.vue`, `frontend/src/components/ImageLightbox.vue`

- [x] **DD-5 · Визуальная дешевизна: скроллбар захардкожен тёмным, не адаптируется к светлой теме** — shipped in v1.6.7
  _Что было:_ `::-webkit-scrollbar-thumb { background: #2a313a }` — в светлой теме оставался тёмным.
  _Фикс:_ токены `--scrollbar-thumb` / `--scrollbar-thumb-hover` в `:root` и `:root[data-theme="light"]`.
  _Файлы:_ `frontend/src/style.css`

- [x] **DD-6 · Онбординг: нет `.editorconfig`** — shipped in v1.6.7
  _Фикс:_ добавлен корневой `.editorconfig` (2 пробела для `.vue`/css/yaml, 4 для js/php). Zero-dependency, уважается всеми IDE.
  _Файлы:_ `.editorconfig`

#### Documented (отложено — выше риск / больший объём)

- [x] **DD-7 · Архитектурный долг: ChatView.vue — decomposed into useChatSse, useComposer, useVoiceRecorder, useMessageActions, useSwipeReply, ChatSidebar (3884→2531 lines)** — shipped in v1.6.9

- [ ] **DD-8 · Перф: нет виртуализации списка сообщений** _(HIGH risk)_
  Длинный чат рендерит все DOM-ноды. Виртуализация конфликтует со свайпом-в-ответ, `jumpToMessage` (ищет DOM до 8 страниц), sticky-датами и lightbox-галереей. Требует аккуратного проектирования. Отложено.

- [ ] **DD-9 · Качество/онбординг: нет ESLint + Prettier + Vitest на фронте** _(MEDIUM)_
  `package-lock.json` закоммичен → добавление devDependencies без регенерации lock сломает `npm ci` в CI. Нужно: добавить `eslint` (flat config + `eslint-plugin-vue`), `prettier`, `vitest`, обновить lock через `npm install` локально, добавить скрипты `lint`/`format`/`test` и шаг в `deploy-dev.yml`. Сделать отдельным PR, где можно прогнать `npm install`.

- [ ] **DD-10 · Тесты: нулевое покрытие фронтенда** _(MEDIUM)_
  Бэкенд — 23 интеграционных теста; фронт — 0. Минимум: Vitest-смоук на `renderContent` (XSS/markdown), `highlightText`, утилиты времени, и логику `tryRefresh` (мьютекс). Зависит от DD-9.

- [ ] **DD-11 · `request()`: повторный 401 после refresh не обрабатывается явно** _(LOW)_
  После успешного refresh повторный запрос может снова вернуть 401 (напр. отозванный доступ) — вернётся сырой Response, вызывающий код бросит общую ошибку. Желательно при втором 401 чистить токены и редиректить на /login. Низкий приоритет (редкий кейс), задокументировано.

<!-- Audit 2026-05-22: full product-polish audit. Items marked with P0–P3. -->

### P0 — Критические баги (ломают базовый UX)

- [x] **P0-1 · Отправка сообщения падает без каких-либо уведомлений** — shipped in v1.1.1  
  _Что не так:_ `api.sendMessage()` делает `return res.json()` без проверки `res.ok`. Вызывающий код делает `.catch(() => {})`. При ошибке (400/403/500 от сервера) пользователь нажимает «Отправить», поле очищается, сообщение не появляется — и никакого сигнала ошибки нет.  
  _Почему важно:_ Пользователь думает, что сообщение отправлено, хотя оно исчезло в пустоту.  
  _Что делать:_ Добавить `if (!res.ok) throw new Error(...)` в `api.sendMessage` и `api.sendForwardedMessage`. В `send()` / `sendVoice()` убрать `.catch(() => {})`, заменить на `catch (e) { showToast(e.message, 'error') }`.  
  _Файлы:_ `frontend/src/api.js:238-246, 249-254`, `frontend/src/views/ChatView.vue:2453, 2670`

- [x] **P0-2 · Голосовое сообщение тоже тихо проваливается** — shipped in v1.1.1  
  _Что не так:_ `api.sendMessage(...).catch(() => {})` на строке ChatView.vue:2670 — та же проблема, что P0-1.  
  _Файлы:_ `frontend/src/views/ChatView.vue:2670`

- [x] **P0-3 · Реакции, пины, голоса — ошибки проглатываются бесшумно** — shipped in v1.1.1  
  _Что не так:_ Вызовы `api.toggleReaction()`, `api.votePoll()`, `api.toggleSidebarPin()` используют `.catch(() => {})`. API-функции корректно бросают ошибку при `!res.ok`, но catch её удаляет. Пользователь нажимает реакцию — ничего не меняется, почему — непонятно.  
  _Что делать:_ Заменить `.catch(() => {})` на `catch (e) { showToast(e.message || 'Action failed', 'error') }`.  
  _Файлы:_ `frontend/src/views/ChatView.vue:2297, 1179`

---

### P1 — Заметно ухудшают качество продукта

- [x] **P1-1 · `console.error` в продакшн-коде** — shipped in v1.1.1  
  _Что не так:_ `console.error('togglePin error', e)` на строке ChatView.vue:1185 выдаёт стек-трейсы в консоль браузера. Это утечка внутренних деталей. Логика уже перехватывает ошибку — надо показывать тост вместо консоли.  
  _Что делать:_ Заменить `console.error(...)` на `showToast(e.message || 'Failed to pin chat', 'error')`.  
  _Файлы:_ `frontend/src/views/ChatView.vue:1185`

- [x] **P1-2 · Регистрация принимает любой «email» и любой пароль** — shipped in v1.1.1  
  _Что не так:_ `AuthController::register()` проверяет только `!$email`, но не валидирует формат. Любая строка проходит. Пароль тоже — минимальная длина не задана (можно зарегистрироваться с паролем `a`).  
  _Почему важно:_ Слабые пароли — дыра в безопасности. Невалидный email сломает маршруты восстановления доступа.  
  _Что делать:_ Добавить Symfony `Assert\Email` и `Assert\Length(min: 8)` на поля, или ручную валидацию. На фронте — добавить `minlength="8"` и подсказку «Minimum 8 characters» под полем пароля в `RegisterView.vue`.  
  _Файлы:_ `backend/app/src/Controller/AuthController.php:22-27`, `frontend/src/views/RegisterView.vue:44-56`

- [x] **P1-3 · Нет rate limiting на `/api/auth/login` — возможен брутфорс** — shipped in v1.1.2  
  _Что не так:_ Эндпоинт логина не ограничивает количество попыток. Атакующий может перебирать пароли неограниченно.  
  _Что делать:_ Symfony RateLimiter (`framework.rate_limiter`) на `AuthController` или через nginx `limit_req_zone`.  
  _Файлы:_ `backend/app/src/Controller/`, `backend/app/config/packages/framework.yaml`, `docker/nginx/`

- [x] **P1-4 · Пароль базы данных захардкожен в docker-compose.yml** — already fixed (uses `${POSTGRES_PASSWORD:-messenger}`)  
  _Что не так:_ `POSTGRES_PASSWORD: messenger` лежит в открытом тексте в файле репозитория. Если кто-то получит доступ к коду, получит и пароль БД.  
  _Файлы:_ `docker-compose.yml:34`

- [x] **P1-5 · Mercure `cors_origins *` — слишком широкий доступ** — shipped in v1.1.2  
  _Что не так:_ Любой origin может делать запросы к Mercure hub. В продакшн-среде это нужно ограничить.  
  _Что делать:_ Для `dev`-среды оставить `*`, для прода использовать `${CORS_ORIGINS:-*}` и документировать.  
  _Файлы:_ `docker-compose.yml` (секция mercure)

- [x] **P1-6 · `getLinkPreview` в api.js возвращает raw Response без обработки ошибок** — shipped in v1.1.1  
  _Что не так:_ `getLinkPreview: (url) => request(...)` — единственный метод в api.js, который возвращает сырой `Response` вместо распарсенного JSON. Вызывающий код в `LinkPreview.vue` вынужден самостоятельно читать `res.json()` и обрабатывать ошибки. Несовместимо с паттерном всего остального api.js.  
  _Что делать:_ Привести к общему паттерну: `const json = await res.json().catch(() => ({})); if (!res.ok) return null; return json`.  
  _Файлы:_ `frontend/src/api.js:453`

- [x] **P1-7 · `sidebarTypingTimers` не очищается при размонтировании компонента** — shipped in v1.1.1  
  _Что не так:_ Объект `sidebarTypingTimers` накапливает ID таймеров по `chatId`. В `onBeforeUnmount` он не очищается — если пользователь открыл много чатов, объект будет расти. Хотя таймеры сами фаерятся и очищают значение, объект с мёртвыми ключами остаётся в памяти.  
  _Что делать:_ В `onBeforeUnmount` добавить: `Object.values(sidebarTypingTimers).forEach(clearTimeout)`.  
  _Файлы:_ `frontend/src/views/ChatView.vue:3510-3532`

---

### P2 — Polish и визуальные улучшения

- [x] **P2-1 · `ChatsView.vue` мёртвый файл — импортирован в router.js, но ни в одном маршруте не используется** — shipped in v1.1.1  
  _Что не так:_ `router.js:3` делает `import ChatsView from './views/ChatsView.vue'`, но ни один `routes[]` не указывает на него. Это мёртвый импорт + мёртвый компонент (650+ строк устаревшего кода).  
  _Что делать:_ Удалить `ChatsView.vue` и строку импорта из `router.js`.  
  _Файлы:_ `frontend/src/views/ChatsView.vue`, `frontend/src/router.js:3`

- [x] **P2-2 · Мёртвые файлы от Vite-скаффолда** — shipped in v1.1.1  
  _Что не так:_ `frontend/src/components/HelloWorld.vue`, `frontend/src/assets/vite.svg`, `frontend/src/assets/vue.svg`, `frontend/src/assets/hero.png` — шаблонные файлы Vite, ни в одном реальном компоненте не используются (только внутри самого `HelloWorld.vue`).  
  _Что делать:_ Удалить все 4 файла.  
  _Файлы:_ `frontend/src/components/HelloWorld.vue`, `frontend/src/assets/{vite.svg,vue.svg,hero.png}`

- [x] **P2-3 · `ChatsView.vue` использует emoji 💬 вместо SVG логотипа** — N/A: ChatsView.vue удалён в P2-1  
  _Файлы:_ `frontend/src/views/ChatsView.vue:7`

- [x] **P2-4 · `App.vue` содержит пустой `<script setup>`** — shipped in v1.1.1  
  _Что не так:_ Пустой `<script setup></script>` без содержимого — лишний шум.  
  _Что делать:_ Убрать пустой script-блок.  
  _Файлы:_ `frontend/src/App.vue:1-2`

- [x] **P2-5 · Нет фокус-трапа в модальных окнах** — shipped in v1.1.2  
  _Что не так:_ При открытии любого модала (создание чата, редактирование, scheduled messages) фокус не переносится внутрь модала и не ограничивается им. Пользователь на клавиатуре может Tab'ом попасть на элементы за оверлеем — нарушение WCAG.  
  _Что делать:_ При `v-if` открытии модала вызывать `nextTick(() => modal.querySelector('input,button')?.focus())`. Перехватывать Tab/Shift+Tab на последнем/первом фокусируемом элементе.  
  _Файлы:_ `frontend/src/views/ChatView.vue` (все модалы)

- [x] **P2-6 · Нет подтверждения для массового удаления сообщений** — shipped in v1.1.2  
  _Что не так:_ В режиме bulk selection одно случайное нажатие на Delete удаляет несколько сообщений без подтверждения. Отмены нет.  
  _Что делать:_ Добавить `confirm('Delete N messages?')` или маленький инлайн-тост с отменой.  
  _Файлы:_ `frontend/src/views/ChatView.vue` (bulk delete handler)

- [x] **P2-7 · Poll c 0 голосов: непонятный пустой стейт** — shipped in v1.1.1  
  _Что не так:_ При `total_votes === 0` компонент `PollMessage.vue` показывает варианты с полосками 0% и скрытыми числами — выглядит как сломанный UI. Непонятно: голосование закрыто? Никто не голосовал? Можно ли голосовать?  
  _Что делать:_ При `total_votes === 0` показывать плейсхолдер «No votes yet» вместо пустых барів.  
  _Файлы:_ `frontend/src/components/PollMessage.vue`

- [x] **P2-8 · Link preview: нет skeleton/индикатора загрузки** — shipped in v1.1.2  
  _Что не так:_ Пока OG-метаданные загружаются, под сообщением нет никакого плейсхолдера. Превью появляется рывком.  
  _Что делать:_ Добавить skeleton-полосу (`shimmer` класс уже есть) пока `isLoading` = true в `LinkPreview.vue`.  
  _Файлы:_ `frontend/src/components/LinkPreview.vue`

- [x] **P2-9 · Форма регистрации: нет подсказки о требованиях к паролю** — shipped in v1.1.1  
  _Что не так:_ Поле пароля показывает только плейсхолдер `••••••••`. Пользователь вводит `12345`, получает ошибку с сервера без объяснения минимальной длины.  
  _Что делать:_ Добавить под полем пароля «At least 8 characters» и `minlength="8"` на input.  
  _Файлы:_ `frontend/src/views/RegisterView.vue:44-56`

- [x] **P2-10 · Нет навигационного guard для уже-авторизованных на /login и /register** — shipped in v1.1.1  
  _Что не так:_ `router.beforeEach` только редиректит _неавторизованных_ на `/login`. Авторизованный пользователь может открыть `/login` — форма загрузится, хотя должен быть редирект на чаты.  
  _Что делать:_ В guard добавить `if (access && (to.path === '/login' || to.path === '/register')) return '/chats/ai'`.  
  _Файлы:_ `frontend/src/router.js:19-22`

---

### P3 — Nice-to-have

- [x] **P3-1 · README: API-таблица неполная** — shipped in v1.1.2  
  _Что не так:_ Отсутствовали: `GET /api/chats/{id}`, `GET /api/chats/{id}/media`, `GET /api/chats/{id}/messages/{mid}/read-by`, `POST /api/me/ping`.  
  _Файлы:_ `README.md`

- [x] **P3-2 · Нет Vue error boundary** — shipped in v1.1.2  
  _Что не так:_ Если любой компонент бросит необработанное исключение в рендере, всё приложение падает. Нет глобального `onErrorCaptured` или компонента-обёртки.  
  _Что делать:_ В `App.vue` добавить `onErrorCaptured` и глобальный `app.config.errorHandler` с fallback-сообщением.  
  _Файлы:_ `frontend/src/App.vue`, `frontend/src/main.js`

- [x] **P3-3 · `POSTGRES_PASSWORD` не документирован как переменная среды** — shipped in v1.1.2  
  _Что не так:_ README не упоминал `POSTGRES_PASSWORD`. Новый разработчик не знал, что пароль нужно менять перед деплоем.  
  _Файлы:_ `README.md`

### Audio & Voice

- [ ] **Speed-persist per message** — скорость воспроизведения запоминается глобально, но хотелось бы «запомнить для голосовых» отдельно от «обычного аудио»

### Telegram-inspired ideas

- [ ] **«Typing»: имя пользователя в группе** — сейчас «typing…» в группе — анонимно. Показывать конкретно «Alice is typing…» / «Alice and Bob are typing…» (payload `user.typing` уже содержит `userId`).
- [ ] **Delete for everyone vs delete for me** — сейчас удаление — soft-delete для всех. Добавить выбор при удалении своего сообщения: «Удалить у всех» / «Удалить только у себя» (скрыть локально без Mercure-события).
- [ ] **Saved Messages** — специальный чат с самим собой (`type: saved`) для заметок/закладок. Пересылка любого сообщения → «Сохранить в Saved Messages».
- [ ] **Slow mode for groups** — настройка OWNER: минимальный интервал между сообщениями (30 с / 1 мин / 5 мин). Бэкенд возвращает 429 при нарушении.
- [ ] **Group invite links** — `POST /api/chats/{id}/invite-link` генерирует короткий токен; `GET /api/invite/{token}` присоединяет к чату. OWNER может отозвать.
- [ ] **Chat export** — «Экспортировать историю» в меню группы; возвращает JSON или plain-text файл со всеми сообщениями (без медиа).
- [ ] **Send without sound** — длинное нажатие на кнопку «Отправить» → меню «Отправить без уведомления» (поле `silent: true` в `CreateMessageController`; фронт показывает иконку 🔕 рядом с временем).
- [ ] **Message effects** — при отправке определённых эмодзи (🎉 🎊 ❤️) — CSS-конфетти-анимация поверх чата (чисто фронт, `canvas` confetti over `.messages-area`).
- [ ] **Compact message list** — тумблер в настройках: «Компактный режим» (меньший padding пузырьков, font-size 13px) для dense-информации.
- [ ] **Read receipts в DM** — «прочитано в 14:32» под последним прочитанным сообщением (timestamp из `chat.read` Mercure-события уже есть).

---

## Completed

- [x] Waveform visualization on audio playback — canvas-based, Web Audio API, 60 bars, click-to-seek — shipped in v1.2.0
- [x] Auto-play next voice message (Telegram-style) — after playback ends, next voice message starts automatically — shipped in v1.2.0
- [x] Media gallery accessible from chat info panels (GroupProfileModal, UserProfileModal) — shipped in v1.1.0
- [x] msg-in animation — `style.css:709`, `.message-row.msg-new`
- [x] Typing dots animation — `style.css:856`, three dots with stagger bounce
- [x] Mobile swipe-to-reply — `ChatView.vue:2331`, 60px threshold, translateX bubble
- [x] Modal enter/exit transition — `style.css:80` + modal-in keyframes applied everywhere
- [x] Mobile long-press context menu — `ChatView.vue:2331`, 500ms timeout, bottom sheet
- [x] Desktop right-click context menu — Telegram-style, commit `04e4707`
- [x] Scroll-to-bottom FAB — floating 36px button, badge with new message count
- [x] Reaction toggle animation — `reaction-pop` keyframes, 0.25s cubic-bezier(0.34,1.5,0.64,1)
- [x] Skeleton screens — shimmer gradient, 3–5 bubbles of varying width
- [x] Pinned bar accent left border — `border-left: 3px solid var(--accent)`
- [x] Date separator sticky pill — `position: sticky; top: 8px`, border-radius pill
- [x] Online dot pulse animation — `online-pulse` keyframes, header/profile only
- [x] Sidebar unread bold — title 600, preview 500 when `unread_count > 0`
- [x] Poll option stagger — `transition-delay: idx * 60ms`
- [x] Reaction picker pop — `picker-pop` keyframes
- [x] Haptic feedback — `navigator.vibrate(10)` on send
- [x] Audio playback speed — 1×/1.5×/2×/0.5× toggle, persisted in localStorage
- [x] Highlight search terms — `highlightText()` wraps matches in `<mark>`
- [x] Emoji picker search — input above categories, filter by name
- [x] Paste image in composer — `paste` event → `processFile(item.getAsFile())`
- [x] Sidebar chat filter — search input filters `sidebarChats` by title
- [x] Unread divider — `unreadDividerBeforeId` + accent-styled `unread-divider`
- [x] Composer mic→send animation — opacity + scale 0.15s transition
- [x] Character limit hint — counter fade-in above 3500 chars (limit 4000)
- [x] Message ticks animation — `tick-appear` keyframe on `.message-ticks.read`
- [x] Last seen humanized — "today at HH:MM" / "yesterday at HH:MM" / "N days ago"
- [x] Image lightbox swipe navigation — `touchstart/touchend`, deltaX > 60px at zoom 1
- [x] Empty state SVG illustration — chat icon, "Your messages", "New chat" CTA
- [x] Message forwarding — `forwardingMsg` ref + chat picker modal
- [x] Copy message text — `navigator.clipboard` from both context menus
- [x] Draft messages — `localStorage draft:${chatId}`, "Draft:" preview in sidebar
- [x] Muted chats — `localStorage mutedChats`, bell-with-slash icon
- [x] Inline video playback — `<video controls>` for `video` attachments
- [x] Composer auto-resize (#1) — textarea grows to 120px max as content grows
- [x] Toast notifications (#2) — `toastMsg` + `showToast()`
- [x] SSE status indicator (#3) — `sseStatus` ref + animated bar on reconnect/error
- [x] Upload progress bar (#4) — XHR `onprogress` → `.upload-progress-bar`
- [x] Voice waveform (#6) — `AnalyserNode` + 16 `.waveform-bar` spans during recording
- [x] Text formatting / linkify (#7) — `renderContent()` + `v-html`
- [x] Sound notifications (#9) — Web Audio API, localStorage toggle
- [x] Keyboard shortcuts (#10) — Ctrl+K, Alt+↑↓, Ctrl+F, Ctrl+Shift+M, ?
- [x] @mention autocomplete (#8) — popup, ↑↓ nav, Enter/Tab select, clickable highlight
- [x] Light theme (#11) — `:root[data-theme="light"]` + ProfileView toggle
- [x] In-chat search navigation (#15) — ↑↓ buttons + "N / total" counter + arrow keys
- [x] Sidebar pinned chats (#5) — `ChatMember.isPinned`, sections, right-click menu
- [x] Bulk message selection (#13) — Shift+Click, checkboxes, floating bar
- [x] Read receipt details (#14) — group ticks open "Read by" modal
- [x] Link preview cards (#12) — OG meta fetch, SSRF guard, 1h cache, `LinkPreview.vue`
- [x] Message time inside bubble (#16) — `.message-meta` floats inside bubble, Telegram-style
- [x] Sidebar avatar shape variety (#17) — groups `border-radius: 30%`, DMs circle
- [x] Message bubble hover actions (#18) — `@media (pointer: fine)` hover reveal
- [x] SVG app logo — replaced 💬 emoji in sidebar / Login / Register
- [x] Password show/hide toggle — eye button on password fields
- [x] Typed toast notifications — success/error/warning/info variants
- [x] Auth page background — subtle dot-grid pattern
- [x] Header overflow menu — Sound / Shortcuts / Delete moved to `⋯` dropdown
- [x] Fix `var(--bg-2)` bug — system-notification token corrected to `var(--surface-2)`

## Reference

### Already good (do not touch without strong reason)

- Tokens and color system — clean, consistent
- Sidebar `cubic-bezier` slide + backdrop
- Blur backdrop on chat header (glass-morphism)
- Bubble tail (4px corner)
- `.btn:active { transform: scale(0.97) }`
- Poll progress bar `transition: width 0.35s ease`
- Hover scale on reactions and emoji
- Highlight flash on quote click
- `ScheduledMessageDispatcher::dispatchDue()` — корректно обёрнут в try/catch на уровне сервиса, цикл диспатча не падает при ошибке одного сообщения
- `word-break: break-word; overflow-wrap: anywhere` — применено к message bubble (style.css:882-883)
- Text overflow ellipsis — покрыт во всех ключевых местах (style.css:413, 426, 471, 571...)
- `sendMessage` timer/debounce cleanup — корректно очищается в `onBeforeUnmount` (ChatView:3514)
- `uploadFile` error handling — корректно обрабатывает HTTP ошибки через XHR status check
