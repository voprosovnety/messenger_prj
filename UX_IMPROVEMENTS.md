# UX Improvements Backlog

Living backlog of UX/UI/polish/hardening work for the messenger. Maintained by the agent pipeline (see CLAUDE.md → "UX backlog maintenance"). **Do not delete the three section headers** (`## Planned / In progress`, `## Completed`, `## Reference`) — agents key off them.

## How this file is used

- **Planned / In progress** — open work, grouped by area and tagged by priority (P0 critical → P3 nice-to-have) and perceived-quality impact (high / med / low). New ideas are appended to the matching group.
- **Completed** — shipped to `dev`, with the `/VERSION` it shipped in.
- **Reference** — design intent, intentional tradeoffs, and "already good — do not regress" notes. Not actionable; not pruned.

Priority legend: **P0** breaks core UX · **P1** clearly hurts quality/security · **P2** polish & consistency · **P3** nice-to-have. Impact = how much it moves *perceived product quality*.

---

## Planned / In progress

<!-- Polish audit 2026-05-28 (v1.4.8): full frontend + backend + infra pass. Prior audit 2026-05-22 (v1.1.1) items are archived in Completed. -->

### Frontend — P1 (high perceived-quality impact)

<!-- All previously listed P1 items shipped in v1.5.0 — see Completed. -->


### Frontend — P1 follow-up from backend hardening

- [ ] **Live avatar sync — handle `chat.updated.avatar_url` and `user.updated` SSE events** · _impact: med_
  _Problem:_ The v1.5.0 backend hardening added Mercure publishes for group-avatar changes (`chat.updated` with `avatar_url`) and own-avatar changes (`user.updated`). The frontend ChatView SSE handler currently only patches `title` from `chat.updated` and has no `user.updated` case — both events are published but silently dropped, so OTHER users still need a reload to see avatar changes.
  _Fix:_ extend the `chat.updated` handler (~ChatView.vue:2122) to also patch `avatar_url` on the matching sidebar chat and on `chat.value`; add a `user.updated` case that patches the participant's avatar in `participants.value` and on `peerUser`, and propagates to any UserAvatar instance keyed on that user id.
  _Files:_ `frontend/src/views/ChatView.vue`

### Frontend — P2 (polish & consistency)

- [ ] **Focus trap applied to only 4 of ~10 modals** · _impact: med_
  _Problem:_ `useFocusTrap` is wired for create/forward/readBy/shortcuts modals only. Not applied to UserProfileModal, GroupProfileModal, PollForm, PollResultsModal, ScheduledMessagesModal, SchedulePickerModal, MediaGallery, ImageLightbox. Tab escapes behind them; focus isn't restored to the trigger on close.
  _Fix:_ apply the composable (or a shared modal wrapper) to all overlays; restore focus on close; lock body scroll for the lightbox.
  _Files:_ all modal components

- [ ] **Touch targets — remaining controls** · _impact: low_
  _Problem:_ v1.5.0 added `@media (hover: none)` min 44px on `.btn-icon`, but two inline-styled controls still shrink below 44px on touch: in-chat search-nav prev/next (inline `padding:3px` ≈ 20px) and the per-attachment cancel-file badge (inline `width:18px; height:18px`).
  _Fix:_ either add `min-width/min-height: 44px` rules targeting those specific selectors under `@media (hover: none)`, or extract the inline styles into classes and add them to the existing block in `style.css`.
  _Files:_ `ChatView.vue:215-219,521-527`, `style.css`

- [ ] **Forward modal has no search/filter** · _impact: low_
  _Problem:_ the forward-to-chat modal lists all chats unfiltered, unlike the new-chat modal which has a search input.
  _Fix:_ add the same filter input.
  _Files:_ `ChatView.vue`

- [ ] **`loadSidebarChats` fails silently** · _impact: med_
  _Problem:_ `catch {}` — if the chat list fails to load, the sidebar shows only the AI entry with no error/retry, unlike the well-done chat-load-error retry UI.
  _Fix:_ show a retry affordance for sidebar load failure.
  _Files:_ `ChatView.vue:2005-2010`

### Frontend — P3 (nice-to-have)

- [ ] **Message list has no enter transition** — new SSE messages pop in instantly; wrap the list in `<TransitionGroup>` with a short fade/translate. _impact: med_
- [ ] **Modals/lightbox/MediaGallery lack exit animations** — enter-only; wrap in `<Transition>` with matching leave. _impact: low_
- [ ] **No empty state for a zero-message chat** — renders blank; add a centered "Say hi 👋" illustration. _impact: low_
- [ ] **Unify mobile vs desktop context-menu items** — order and labels differ ("Copy" vs "Copy text", Pin/Forward swapped, mobile lacks explicit "Add reaction"). _impact: low_
- [ ] **Profile "Save changes" is a no-op** — only avatar is savable and it's already persisted on upload; remove the form or make it meaningful. _impact: low_
- [ ] **Toast is single-slot** — rapid actions drop messages; consider a small queue. _impact: low_
- [ ] **Extract duplicated helpers** — `formatTimeShort`, `formatRelative`, `escapeHtml`/`highlightText`, `fmt` are copy-pasted across 3-4 components; move to `utils/format.js`. _impact: low (maintainability)_
- [ ] **Dead code / inconsistency** — `openUrl` and `isEditing` appear unused; the `error` ref is write-only; a Russian-language comment in an otherwise-English file (`ChatView.vue:3205`). _impact: low_

### Frontend — architecture / performance (P2, longer-term)

- [ ] **ChatView.vue is 3834 lines** — owns sidebar, SSE, 12+ event types, composer, recording, polls, reactions, drag-drop, swipe, shortcuts, mention autocomplete, 6 modals, toast, drafts. High regression risk. Extract composables (`useChatSse`, `useVoiceRecording`, `useMessageSwipe`, `useDrafts`, `useKeyboardShortcuts`) and split the sidebar into its own component. _impact: med (maintainability)_
- [ ] **No list virtualization** — `grouped` renders every loaded message and re-runs on every `messages` mutation (including reaction-only patches). Long chats render hundreds of nodes. Window long histories; at minimum avoid full regroup on reaction-only updates. _impact: med_

### Backend & Infra — P2 / P3

- [ ] **N+1 queries** — polls loaded one-per-message in `ListMessagesController`; participant lookup per-member in `CreateChatController`; membership chat lazy-load in `GlobalSearchMessagesController`. Batch with `IN (...)`. _P2_
- [ ] **`$em->clear()` in VotePoll** detaches all managed entities to force a re-read — fragile. Use `$em->refresh($poll)`. _Files:_ `VotePollController.php:105-110` _P2_
- [ ] **Edited messages don't refresh link preview** — `EditMessageController` never re-runs preview extraction; adding/removing a URL on edit yields a stale/absent preview. _P2_
- [ ] **Logout doesn't bind refresh token to caller; no token purge/index** — `AuthLogoutController` revokes any token by value; `refresh_token` table grows unbounded with no purge job or index on `(owner)`/`(expires_at)`. _P2_
- [ ] **No nginx security headers / gzip** — no `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP, HSTS; assets/JSON uncompressed. _P2_
- [ ] **Migrations run twice / can race** — both the entrypoint and the deploy workflow run `doctrine:migrations:migrate`; `php` and `scheduler` share the entrypoint and migrate simultaneously on start. Run migrations once (deploy step only); give the scheduler a migration-free entrypoint; back up DB before prod migrate. _P2_
- [ ] **De-duplicate system-message + publish boilerplate** — the "create system Message → persist → build payload → publish `message.created`" block is copy-pasted across Add/Remove/Rename/Leave controllers; extract a `ChatEventPublisher`/`SystemMessageService`. _P2_
- [ ] **Raw `json_decode` without validation** across controllers — a non-array JSON body can TypeError on array access. Centralize a "decode + must be array else 400" helper. _P2_
- [ ] **Author-only message delete** — a group OWNER can't moderate other members' messages. Product/security decision. _P2_
- [ ] **ScheduledMessageDispatcher swallows all errors** — a permanently-invalid row (chat/sender gone) is retried every 30s forever with no logging. Log + drop after N failures. _P3_
- [ ] **Missing indexes** — `last_seen_at` (online scan), and a partial `(chat_id, created_at) WHERE deleted_at IS NULL` for unread/media scans. _P3_
- [ ] **`message.created` payload hardcodes `type: 'text'`** even for media-only messages. _P3_

### Feature ideas (Telegram-inspired, unprioritized)

- [ ] **Spoiler text** — `||text||` markup, blurred block, tap to reveal. Parser in `renderContent()` + `.spoiler` CSS.
- [ ] **Named typing in groups** — show "Alice is typing…" / "Alice and Bob are typing…" (`user.typing` payload already has `userId`).
- [ ] **Delete for everyone vs delete for me** — offer a choice when deleting own messages.
- [ ] **Disappearing messages** — per-chat self-destruct timer; new `Message.deletedAfterSeconds` + scheduler job.
- [ ] **Saved Messages** — a self-chat (`type: saved`) for notes/bookmarks; "Save" on forward.
- [ ] **Message translation** — "Translate" in the context menu via `/api/ai/translate`, inline result.
- [ ] **Slow mode for groups** — OWNER-set min interval; backend 429 on violation.
- [ ] **Group invite links** — `POST /api/chats/{id}/invite-link` token; `GET /api/invite/{token}` joins; OWNER can revoke.
- [ ] **Chat export** — export history as JSON/plain text (no media).
- [ ] **Send without sound** — long-press send → silent send (`silent: true`, 🔕 indicator).
- [ ] **Read receipts in DM** — "read at 14:32" under the last read message (`chat.read` timestamp already exists).
- [ ] **Speed-persist per message** — remember voice playback speed separately from regular audio.
- [ ] **Compact message list** — settings toggle for denser bubbles/font.

---

## Completed

### Polish audit 2026-05-28 (v1.5.0) — premium polish + P1 backend hardening

Frontend / design system:
- [x] Undefined CSS token refs fixed (`--bg-1`/`--bg-2`/`--bg-3`/`--text-1` mapped to real palette) — new-chat user-search dropdown no longer renders transparent — v1.5.0
- [x] EmojiPicker search works for ~60 common keywords (was character-only, returned "No results" for any text) — v1.5.0
- [x] Global `:focus-visible` ring on all interactive elements (a11y) — v1.5.0
- [x] Global `@media (prefers-reduced-motion: reduce)` block neutralizes animations/transitions — v1.5.0
- [x] aria-labels on icon-only buttons; `role="dialog" aria-modal="true"` on previously-unlabelled modals (ChatView, ImageLightbox, GroupProfileModal, UserProfileModal, ScheduledMessagesModal) — v1.5.0
- [x] Silent failures eliminated — all six write-only `error.value =` sites route through `showToast(..., 'error')`; dead `error` ref removed — v1.5.0
- [x] Native `confirm()`/`alert()` replaced with themed inline two-step UI everywhere (single-message delete, group leave/delete, scheduled-message delete) — v1.5.0
- [x] Off-palette greens consolidated to `var(--online)`; `--online-dim` token added; all four `font-weight: 700` → `600`; ProfileView success banner extracted to `.profile-success-banner` class — v1.5.0
- [x] Light theme committed to (DESIGN.md updated from "Dark only" to "Dual-theme"; UserAvatar deterministic palette documented) — v1.5.0
- [x] `@media (hover: none)` enforces 44px min hit area on `.btn-icon`; search-nav and cancel-file badge remain (tracked) — v1.5.0

Backend & infra (P1 hardening):
- [x] Upload XSS hardening — exact-match MIME allowlist in `UploadController` (rejects svg/html/js with 415); nginx `X-Content-Type-Options: nosniff` server-level + on `/uploads/`; `X-Frame-Options SAMEORIGIN`; `Referrer-Policy strict-origin-when-cross-origin` — v1.5.0
- [x] Production build hardening — Dockerfile `composer install --no-dev --classmap-authoritative`; entrypoint `cache:clear --env=${APP_ENV:-prod}`; `_wdt|_profiler` stripped from nginx PHP regex — v1.5.0
- [x] Global `ExceptionListener` — uniform `{error}` JSON for `/api` requests; HttpException pass-through; generic message + no stack trace in prod — v1.5.0
- [x] AI endpoint hardened — `HttpClientInterface` replaces blocking `@file_get_contents`; per-user `RateLimiterFactory` (20/min sliding, 429 + delta-seconds `Retry-After`); upstream error logged server-side, client gets generic message — v1.5.0
- [x] Transactions on multi-step writes — `DeleteChatController` and `CreateChatController` wrap their DQL+ORM ops in `$em->wrapInTransaction(...)` (Mercure publish outside) — v1.5.0
- [x] `ChatMediaController` query bounded with `setMaxResults(500)` at QB level — v1.5.0
- [x] Avatar change publishes Mercure events — `chat.updated` (group avatar, RenameChatController) and `user.updated` (own avatar, MeController). Frontend handler is a tracked follow-up — v1.5.0
- [x] User-enumeration hardening — `SearchUsersController` is username-only with `addcslashes($q, '%_\\')` wildcard escape; "user not found: $ident" → generic message in CreateChatController (both sites) and AddChatMemberController — v1.5.0

### Polish audit 2026-05-22 (v1.1.1 / v1.1.2) — shipped

- [x] Send/forward/voice/reaction/pin errors no longer swallowed — now throw + `showToast` — v1.1.1
- [x] `console.error('togglePin')` replaced with toast — v1.1.1
- [x] Registration validates email format, password ≥8, username 2-32 — v1.1.1
- [x] `getLinkPreview` returns parsed JSON/null (consistent with api.js) — v1.1.1
- [x] `sidebarTypingTimers` cleared on unmount — v1.1.1
- [x] Dead code removed: `ChatsView.vue`, Vite scaffold files, empty `App.vue` script — v1.1.1
- [x] Router guard redirects authenticated users away from /login, /register — v1.1.1
- [x] Poll with 0 votes shows "No votes yet" — v1.1.1
- [x] Register password hint + `minlength=8` — v1.1.1
- [x] Rate limiting on `/api/auth/login` (nginx 5r/m, burst 5, 429) — v1.1.2
- [x] Mercure `cors_origins` → `${CORS_ORIGINS:-*}`, documented — v1.1.2
- [x] `POSTGRES_PASSWORD` → `${POSTGRES_PASSWORD:-messenger}`, documented in README — v1.1.2
- [x] Focus trap (`useFocusTrap`) added to the core modals — v1.1.2
- [x] Bulk-delete inline two-step confirm — v1.1.2
- [x] Link preview loading skeleton — v1.1.2
- [x] README API table completed — v1.1.2
- [x] Vue error boundary (`onErrorCaptured` + `app.config.errorHandler`) — v1.1.2

### Polls (v1.3.0)

- [x] Poll vote retraction (`allow_retraction` + RetractPollVoteController + "Return vote") — v1.3.0
- [x] Poll UI redesign (Telegram-like progress fill, rounded cards, accent selected) — v1.3.0
- [x] Poll results modal (per-option counts/percentages, voter names for non-anonymous) — v1.3.0
- [x] Hide poll results before voting; voter avatars — v1.3.0
- [x] Context-menu Edit hidden for media-only and poll messages — v1.3.0

### Voice / audio (v1.2.0)

- [x] Waveform visualization on playback (Web Audio, 60 bars, click-to-seek) — v1.2.0
- [x] Auto-play next voice message (Telegram-style) — v1.2.0
- [x] Global voice player bar
- [x] Playback speed persisted globally; speed dropdown
- [x] OfflineAudioContext waveform decode; iOS AudioContext gesture/probe fixes

### Earlier UX micro-interactions & features

- [x] Media gallery from chat info panels (v1.1.0); msg-in animation; typing dots; mobile swipe-to-reply; modal enter/exit transition; mobile long-press menu; desktop right-click menu; scroll-to-bottom FAB; reaction-pop; skeleton screens; pinned bar accent; sticky date pill; online dot pulse; sidebar unread bold; poll option stagger; reaction picker pop; haptic feedback; highlight search terms; emoji picker search input (UI — see open bug); paste image; sidebar chat filter; unread divider; composer mic→send animation; char-limit hint; message ticks animation; humanized last-seen; lightbox swipe nav; empty-state SVG; message forwarding; copy text; drafts; muted chats; inline video; composer auto-resize; toasts; SSE status indicator; upload progress; voice waveform-on-record; linkify; sound notifications; keyboard shortcuts; @mention autocomplete; light theme toggle (see open decision); in-chat search nav; sidebar pinned chats; bulk selection; read-receipt details; link preview cards; in-bubble time; avatar shape variety; bubble hover actions; SVG logo; password show/hide; auth dot-grid background; header overflow menu

---

## Reference

### Intentional tradeoffs (don't "fix" without product sign-off)

- **`user-select: none` on the messages area (≤640px)** — deliberate, to enable swipe gestures; users copy via the long-press menu.
- **`maximum-scale=1, user-scalable=no` while in chat** — intentional; the JS lightbox replaces pinch-zoom and the meta tag is restored on unmount. Disables pinch-zoom on message text (a11y tradeoff).
- **`ListChatsController` raw DBAL LATERAL query** — single round-trip for peer/last-message/unread. Do NOT convert to DQL/ORM.
- **Stateless JWT, 1h TTL, no access-token revocation** — standard tradeoff; only refresh tokens are revocable.
- **Author-only message delete** — current model; OWNER moderation is an open product question (above).

### Already good — do not regress

- Token/color system; sidebar `cubic-bezier` slide + backdrop; glass-morphism header blur; bubble tail; `.btn:active { transform: scale(0.97) }`; poll progress `transition: width 0.35s`; hover scale on reactions/emoji; quote-click highlight flash.
- Skeleton loading; chat-load-error retry UI; SSE reconnect with backoff + generation-counter guard; optimistic reaction/poll updates with revert; `@media (hover:none)` guards; safe-area + `--vvh` keyboard handling; drag-counter overlay; thorough `onBeforeUnmount` cleanup (intervals, timers, SSE, listeners, viewport, title); AudioPlayer duration-probe + waveform robustness.
- `LinkPreviewService` SSRF guard (scheme allowlist, DNS + `NO_PRIV_RANGE|NO_RES_RANGE`, `max_redirects:0`, DNS-pinning `resolve`); login rate limiting at nginx; correct Mercure subscription scoping; `MarkChatReadController` anti-rollback; correct unique constraints + FK `onDelete: CASCADE`; keyset pagination cursor; postgres port not host-exposed; secrets gitignored; CI runs full phpunit before deploy.
- `ScheduledMessageDispatcher::dispatchDue()` per-message try/catch (loop survives one bad row); `word-break`/`overflow-wrap` on bubbles; text-overflow ellipsis coverage.
