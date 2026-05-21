# UX Improvements Backlog

Living backlog of UX/UI ideas for the messenger. This file is maintained automatically by the agent pipeline (see CLAUDE.md → "UX backlog maintenance"). Do not delete the section headers — agents key off them.

## How this file is used

- **Planned / In progress** — ideas waiting to be picked up, or actively being worked on. New ideas (from the user, or surfaced by any agent during work) are appended here.
- **Completed** — items that have shipped to `dev`. Each line records the version (from `/VERSION`) in which it was delivered.
- **Reference** — design intent, things deliberately left alone, and the "what's already good" notes. Not actionable; not pruned automatically.

## Planned / In progress

<!-- Add new items here as `- [ ] short description`. When an item starts being implemented, leave it here; `devops-agent` will move it to Completed after the push that ships it. -->


## Completed

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
