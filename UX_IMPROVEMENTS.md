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

---

## Критические слабые места (приоритет HIGH)

### 1. Нет кнопки «Scroll to bottom»
**Проблема:** При загрузке истории пользователь теряет текущую позицию. Нет способа быстро вернуться вниз.

**Решение:** Floating button (36px circle, `--accent`) появляется когда `scrollTop < scrollHeight - clientHeight - 200`. При клике — smooth scroll to bottom. Опционально — badge с количеством новых сообщений.

```css
.scroll-to-bottom {
  position: absolute;
  bottom: 80px;
  right: 20px;
  width: 36px; height: 36px;
  background: var(--accent);
  border-radius: 50%;
  box-shadow: var(--shadow-sm);
  transition: opacity 0.2s, transform 0.2s;
}
.scroll-to-bottom.hidden { opacity: 0; transform: scale(0.8); pointer-events: none; }
```

---

### 2. Реакции: нет toggle-анимации
**Проблема:** Клик на реакцию — мгновенное изменение. Ощущение плоское.

**Решение:**
```css
@keyframes reaction-pop {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.35); }
  100% { transform: scale(1); }
}
.reaction-pill.toggling { animation: reaction-pop 0.25s cubic-bezier(0.34,1.5,0.64,1); }
```
Ставить класс `toggling` на 300ms при клике.

---

### 3. Skeleton screens вместо "Loading..."
**Проблема:** При загрузке чата — просто пустота или текст. Хочется perceived performance.

**Решение:**
```css
@keyframes shimmer {
  from { background-position: -400px 0; }
  to   { background-position:  400px 0; }
}
.skeleton {
  background: linear-gradient(90deg,
    var(--surface-2) 25%, var(--surface-3) 50%, var(--surface-2) 75%);
  background-size: 800px 100%;
  animation: shimmer 1.4s infinite;
  border-radius: var(--radius-sm);
}
```
3–5 skeleton-пузырей (разной ширины) пока грузятся реальные сообщения.

---

## Средний приоритет (HIGH impact, less urgent)

### 4. Emoji picker — нет search
**Проблема:** 8 категорий + скролл — неэффективно. Нет поиска по названию emoji.

**Решение:** Input поверх категорий (placeholder "Search emoji...") + фильтрация по `emoji.name`. Уже есть данные в компоненте.

---

### 5. Highlight search terms в результатах
**Проблема:** Search находит сообщения, но не выделяет совпадающий текст. Сложно понять релевантность.

**Решение:** Утилита `highlightText(text, query)` → оборачивает совпадения в `<mark>` со стилем `background: var(--accent-dim); color: var(--accent)`. Применить в `.msg-search-item-text` и `.global-search-item-text`.

---

### 6. Аудиоплеер: playback speed
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

### 7. Pinned bar: accent left border + transition между пинами
**Проблема:** Pinned bar выглядит как обычная строка, нет визуального акцента слева.

**Решение:**
```css
.pinned-bar { border-left: 3px solid var(--accent); }
.pinned-bar-content { transition: opacity 0.15s; }
```
При смене пина — fade через `opacity: 0` → контент меняется → `opacity: 1`.

---

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

### 9. Date separator: sticky + pill
**Проблема:** Date separator с линиями — стандартно, но не фиксируется при скролле.

**Решение:**
```css
.date-separator-text {
  background: var(--surface-3);
  padding: 3px 10px;
  border-radius: 20px;
  border: 1px solid var(--border);
}
.date-separator { position: sticky; top: 8px; z-index: 2; }
```

---

### 10. Composer: плавная анимация mic→send кнопки
**Проблема:** Mic и Send кнопки переключаются мгновенно при вводе.

**Решение:** Обе кнопки в одном контейнере, `transition: opacity 0.15s, transform 0.15s`. Mic уходит `scale(0.8) opacity(0)`, Send появляется `scale(1) opacity(1)`.

---

### 11. Online dot: pulse animation
**Проблема:** `.online-indicator-dot` — статичная точка. У Telegram точка пульсирует.

**Решение:**
```css
@keyframes online-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0.4); }
  50%       { box-shadow: 0 0 0 4px rgba(34,197,94,0); }
}
.online-indicator-dot { animation: online-pulse 2s infinite; }
/* Только в header и profile — не везде, иначе шумно */
.chat-header-online-dot { animation: online-pulse 2s infinite; }
```

---

## Средний приоритет (UX polish)

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

### 15. Sidebar chat items: unread bold + sender preview
**Проблема:** Непрочитанные чаты визуально не отличаются от прочитанных (нет bold текста превью).

**Решение:**
```css
.chat-item.unread .chat-item-text { color: var(--text); font-weight: 500; }
.chat-item.unread .chat-item-title { font-weight: 600; }
```
В template уже есть `unread_count` — просто добавить класс.

---

### 16. Voice recording: live waveform bars
**Проблема:** При записи — пульсирующий кружок-индикатор. Нет визуализации громкости.

**Решение:** `AnalyserNode` из Web Audio API → 16 баров, обновляемых в `requestAnimationFrame`. Минимальная реализация — 8–16 `<span>` с `height` = уровень сигнала.

---

### 17. Poll: stagger анимации при загрузке
**Проблема:** Ширина bar анимируется (уже!), но не stagger-ится при загрузке.

**Решение:** Добавить `transition-delay` для каждой опции: `:style="{ transitionDelay: \`${idx * 60}ms\`" }` на `.poll-option-bar`.

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

## Низкий приоритет (nice-to-have)

### 21. Toast: progress bar + slide animation
`.toast` появляется без анимации. Добавить slide-from-top + `@keyframes toast-progress` полоска убывает за время показа.

### 22. «Last seen» более humanized
Вместо raw timestamp — "Today at 14:23", "Yesterday at 09:11", "3 days ago". Утилита `formatLastSeen(date)` в `api.js` или util-файле.

### 23. Group profile: member count в заголовке чата
`.chat-header-sub` → "42 members" вместо просто типа группы.

### 24. Reaction picker: появление с animation
`.reaction-quick-pick` появляется мгновенно. `@keyframes picker-pop: scale(0.7) → scale(1.05) → scale(1)`.

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

### 27. Haptic feedback на мобильном
`navigator.vibrate(10)` при отправке, `navigator.vibrate([5,5,5])` при получении реакции. Только если `'vibrate' in navigator`.

---

## Файлы, которые нужно изменить

| Файл | Изменения |
|---|---|
| `frontend/src/style.css` | Анимации: shimmer, reaction-pop, online-pulse, tick-appear, menu-in, picker-pop |
| `frontend/src/views/ChatView.vue` | scroll-to-bottom FAB, skeleton loader, unread divider, paste listener, smooth mic↔send toggle |
| `frontend/src/components/AudioPlayer.vue` | Playback speed button (1×/1.5×/2×/0.5×) |
| `frontend/src/components/PollMessage.vue` | transition-delay stagger на option bars |
| `frontend/src/components/ImageLightbox.vue` | Swipe navigation на mobile |
| `frontend/src/components/EmojiPicker.vue` | Search input |
| `DESIGN.md` | Документировать новые animation keyframes как часть design system |

---

## Порядок реализации (by impact)

1. **Scroll-to-bottom FAB** — 1h, critical UX
2. **Reaction toggle animation** — 20 min, quick win
3. **Skeleton screens** — 1h, perceived performance
4. **Audio playback speed** — 1h
5. **Search highlighting** — 1h
6. **Emoji search** — 1.5h
7. Остальные — по одному
