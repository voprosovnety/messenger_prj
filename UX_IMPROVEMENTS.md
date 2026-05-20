# Messenger UX/UI Audit & Improvement Roadmap

## Context

Это аудит существующего мессенджера — Vue 3 + Symfony 7, тёмная тема, дизайн-система на токенах, без внешних UI-библиотек. Продукт функционально почти готов (чаты, голосовые, опросы, реакции, поиск, пины, расписание). Цель — найти где ощущение "сырое" и дать конкретные, реалистичные улучшения уровня Telegram/iMessage.

---

## Что уже хорошо (не трогать)

- Токены и цветовая система — чистые, консистентные
- Сайдбар: `cubic-bezier` слайд + backdrop — работает premium
- Blur-backdrop на шапке чата — уже glass-morphism
- Bubble tail (4px угол) — правильная деталь
- Кнопка `.btn:active { transform: scale(0.97) }` — тактильно
- Poll progress bar с `transition: width 0.35s ease` — анимация есть
- Hover scale на реакциях и эмодзи — приятная мелочь
- Highlight flash при клике на цитату — Telegram-like

## Что уже реализовано ✓

- **msg-in animation** — `style.css:709`, `.message-row.msg-new`, `ChatView.vue` ставит класс
- **Typing dots animation** — `style.css:856`, три точки с stagger bounce
- **Mobile swipe-to-reply** — `ChatView.vue:2331`, 60px порог, translateX bubble, иконка reply
- **Modal enter/exit transition** — `style.css:80` + modal-in keyframes применены везде
- **Mobile long-press context menu** — `ChatView.vue:2331`, 500ms timeout, bottom sheet
- **Desktop right-click context menu** — Telegram-style, commit `04e4707`
- **Scroll-to-bottom FAB** ✓ — floating button 36px, badge с новыми сообщениями, commit `19b9164`
- **Reaction toggle animation** ✓ — `reaction-pop` keyframes, 0.25s cubic-bezier(0.34,1.5,0.64,1), commit `19b9164`
- **Skeleton screens** ✓ — shimmer gradient, 3–5 пузыри разной ширины, commit `19b9164`
- **Pinned bar accent left border** ✓ — `border-left: 3px solid var(--accent)`, commit `ee44f16`
- **Date separator: sticky pill** ✓ — `position: sticky; top: 8px`, border-radius pill, commits `ee44f16`, `1e278fe`, `0a76836`
- **Online dot pulse animation** ✓ — `online-pulse` keyframes, только в header/profile, commit `ee44f16`
- **Sidebar unread bold** ✓ — `font-weight: 600` для title, `500` для preview при `unread_count > 0`, commit `ee44f16`
- **Poll stagger animation** ✓ — `transition-delay: idx * 60ms` на option bars, commit `ee44f16`
- **Reaction picker pop animation** ✓ — `picker-pop` keyframes `scale(0.7)→scale(1.05)→scale(1)`, commit `ee44f16`
- **Haptic feedback** ✓ — `navigator.vibrate(10)` при отправке, commit `ee44f16`

---

## Не реализовано — HIGH priority

### 4. Аудиоплеер: playback speed
**Проблема:** Голосовые сообщения нельзя ускорить. В Telegram — базовая фича.

**Решение:** В `AudioPlayer.vue` добавить кнопку `1×` → `1.5×` → `2×` → `0.5×` с `audio.playbackRate = speed`. Сохранять preference в localStorage.

```css
.audio-speed-btn {
  font-size: 11px; font-weight: 600;
  color: var(--text-2); padding: 2px 5px;
  border-radius: 4px; background: var(--surface-3);
}
```

---

### 5. Highlight search terms в результатах
**Проблема:** Search находит сообщения, но не выделяет совпадающий текст. Сложно понять релевантность.

**Решение:** Утилита `highlightText(text, query)` → оборачивает совпадения в `<mark>` со стилем `background: var(--accent-dim); color: var(--accent)`. Применить в `.msg-search-item-text` и `.global-search-item-text`.

---

### 6. Emoji picker — поиск
**Проблема:** 8 категорий + скролл — неэффективно. Нет поиска по названию emoji.

**Решение:** Input поверх категорий (placeholder "Search emoji...") + фильтрация по `emoji.name`. Уже есть данные в компоненте.

---

## Не реализовано — MEDIUM priority

### 8. Unread divider в ленте сообщений
**Проблема:** При открытии чата с непрочитанными сообщениями — нет разделителя «Непрочитанные сообщения».

**Решение:** При первой загрузке найти индекс первого непрочитанного → вставить `<div class="unread-divider">N new messages</div>` с accent-цветом.

```css
.unread-divider {
  text-align: center;
  font-size: 12px; font-weight: 600;
  color: var(--accent);
  padding: 4px 0;
  position: relative;
}
.unread-divider::before, .unread-divider::after {
  content: ''; position: absolute;
  top: 50%; height: 1px;
  background: var(--accent); opacity: 0.3;
  width: 30%;
}
.unread-divider::before { left: 0; }
.unread-divider::after  { right: 0; }
```

---

### 10. Composer: плавная анимация mic→send кнопки
**Проблема:** Mic и Send кнопки переключаются мгновенно при вводе.

**Решение:** Обе кнопки в одном контейнере, `transition: opacity 0.15s, transform 0.15s`. Mic уходит `scale(0.8) opacity(0)`, Send появляется `scale(1) opacity(1)`.

---

### 12. Empty states с иллюстрацией
**Проблема:** `.chat-area-empty` — emoji 48px + текст серый. Не приглашает.

**Решение:** SVG-иллюстрация (inline, stroke-based, --text-3 цвет), крупнее (80px), заголовок + подзаголовок, кнопка «New chat» как CTA.

---

### 13. Paste image в composer
**Проблема:** Ctrl+V с изображением в буфере — ничего не происходит.

**Решение:** `window.addEventListener('paste', e => { const item = e.clipboardData.items[...find image...]; if(item) processFile(item.getAsFile()); })` Уже есть `processFile()` и `onFileSelect()` — просто добавить paste listener.

---

### 14. Sidebar: filter/search по имени чата
**Проблема:** Много чатов — нет возможности быстро найти нужный по имени.

**Решение:** Input вверху sidebar (появляется по клику на лупу — кнопка уже есть `.chats-section-search-btn`). `v-model` фильтрует `sidebarChats` по `title`.

---

### 16. Voice recording: live waveform bars
**Проблема:** При записи — пульсирующий кружок-индикатор. Нет визуализации громкости.

**Решение:** `AnalyserNode` из Web Audio API → 16 баров, обновляемых в `requestAnimationFrame`. Минимальная реализация — 8–16 `<span>` с `height` = уровень сигнала.

---

### 18. Image lightbox: swipe navigation на mobile
**Проблема:** Стрелки на lightbox неудобны на мобильном. Нет swipe.

**Решение:** В `ImageLightbox.vue` добавить touch handlers (уже есть drag-to-pan — расширить логику): если `|deltaX| > 60` и zoom === 1 → prev/next image.

---

### 19. Message ticks: animated delivery
**Проблема:** `.message-ticks` — просто текст "✓✓". Нет анимации при смене статуса.

**Решение:**
```css
@keyframes tick-appear { from { opacity:0; transform: scale(0.5); } to { opacity:1; transform: scale(1); } }
.message-ticks.read { animation: tick-appear 0.2s; color: var(--accent); }
```

---

### 20. Composer: character limit hint
Когда сообщение длинное (>500 символов), показывать счётчик оставшихся. Fade-in только когда > 400 символов.

---

## Не реализовано — LOW priority

### 21. Toast: progress bar + slide animation
`.toast` появляется без анимации. Добавить slide-from-top + `@keyframes toast-progress` полоска убывает за время показа.

### 22. «Last seen» более humanized
Вместо raw timestamp — "Today at 14:23", "Yesterday at 09:11", "3 days ago". Утилита `formatLastSeen(date)` в `api.js` или util-файле.

### 23. Group profile: member count в заголовке чата
`.chat-header-sub` → "42 members" вместо просто типа группы.

### 25. Attach menu: entrance animation
```css
@keyframes menu-in {
  from { opacity: 0; transform: translateY(6px) scale(0.96); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.attach-menu { animation: menu-in 0.15s cubic-bezier(0.34,1.1,0.64,1); }
```

### 26. Sidebar: pinned chats at top
Чаты, закреплённые пользователем, всегда показываются первыми с иконкой pin. Требует backend + UI.

---

## Файлы, которые нужно изменить

| Файл | Изменения |
|---|---|
| `frontend/src/style.css` | Анимации: tick-appear, menu-in, unread-divider styles |
| `frontend/src/views/ChatView.vue` | unread divider, paste listener, smooth mic↔send toggle, char limit hint |
| `frontend/src/components/AudioPlayer.vue` | Playback speed button (1×/1.5×/2×/0.5×) |
| `frontend/src/components/ImageLightbox.vue` | Swipe navigation на mobile |
| `frontend/src/components/EmojiPicker.vue` | Search input |
| `DESIGN.md` | Документировать новые animation keyframes |

---

## Порядок реализации (by impact)

1. **Audio playback speed** — 1h, Telegram-like базовая фича
2. **Search highlighting** — 1h, резко улучшает поиск
3. **Emoji search** — 1.5h
4. **Paste image** — 30 min, quick win
5. **Sidebar chat search** — 1h
6. **Unread divider** — 1h
7. **Mic→send animation** — 30 min, polish
8. Остальные — по одному
