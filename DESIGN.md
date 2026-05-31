# Design System

Single source of truth for all visual decisions. Keep this file in sync whenever `style.css` tokens change.

---

## Principles

- **Dual-theme: dark (default) + light.** Toggle in Profile settings. All colors MUST use CSS custom properties — no hardcoded hex values anywhere in components or scoped styles.
- **Neutral grays.** Backgrounds are pure near-blacks with no color tint. Color lives in the accent and status indicators only.
- **Single accent.** One blue (`--accent`). No secondary accent, no rainbow states.
- **Functional beauty.** Every pixel serves communication. No decorative gradients, no heavy drop shadows, no animations beyond micro-transitions.
- **Inter everywhere.** One typeface, three weights (400 / 500 / 600), no custom display fonts.

---

## Color tokens

All tokens live in the `:root` block at the top of `frontend/src/style.css`.

| Token | Value | Role |
|---|---|---|
| `--bg` | `#0f1117` | Page / main chat background |
| `--surface` | `#161b22` | Sidebar, cards, modals, composer |
| `--surface-2` / `--hover` | `#1c2128` | Hover states, input backgrounds |
| `--surface-3` / `--active` | `#22272e` | Active/selected backgrounds |
| `--border` | `#252b33` | All borders and dividers |
| `--text` | `#e6edf3` | Primary text |
| `--text-2` / `--text2` | `#7d8590` | Secondary text (timestamps, subtitles) |
| `--text-3` | `#484f58` | Placeholder, disabled, very muted |
| `--accent` | `#5b8dee` | Buttons, links, active indicators, focus rings |
| `--accent-hover` | `#4a7cdd` | Accent on hover |
| `--accent-dim` | `rgba(91,141,238,0.12)` | Accent tinted backgrounds (focus ring, selection) |
| `--sent-bg` / `--sent` | `#1a2d4a` | Own message bubbles |
| `--recv-bg` | `#161b22` | Received message bubbles (matches surface) |
| `--danger` | `#ff4757` | Destructive actions, error states |
| `--danger-dim` | `rgba(255,71,87,0.12)` | Danger tinted backgrounds |
| `--online` | `#22c55e` | Online status dot |
| `--online-dim` | `rgba(34,197,94,0.15)` | Green-tinted backgrounds (success banners, online indicators) |
| `--shadow-card` | `0 2px 12px rgba(0,0,0,.22)` | Card/bubble elevation |
| `--shadow-pop` | `0 8px 32px rgba(0,0,0,.40)` | Modals, context menus, heavy elevation |

Halo aliases (`--hover`, `--active`, `--sent`, `--text2`) are added as canonical Halo names pointing to the same values as their `--surface-*` / `--sent-bg` counterparts. All existing code using the original names continues to work unchanged.

**Rule:** Never introduce a color outside this palette. Need a new shade? Derive it from an existing token with opacity.

**Light theme:** A `[data-theme="light"]` override block at the bottom of `style.css` re-maps all tokens to light values. The toggle lives in ProfileView. Every component reads tokens — nothing hardcoded — so both themes work automatically.

---

## Typography

```
Font family:  'Inter', system-ui, sans-serif  (--font)
Base size:    14px
Line height:  1.5
Weights:      400 (body), 500 (labels, buttons), 600 (headings, names)
```

| Usage | Size | Weight |
|---|---|---|
| Timestamps, labels | 11px | 400 |
| Secondary text, role tags | 12px | 400 |
| Form labels, descriptions | 13px | 400 |
| Body text, buttons | 14px | 500 |
| Chat header names | 15px | 600 |
| Modal titles | 17px | 600 |
| Auth headings | 18–22px | 600 |

---

## Spacing & sizing

Base unit: **8px**. All spacing values are multiples or near-multiples of 8.

Common values: `4px · 8px · 10px · 12px · 14px · 16px · 20px · 24px · 28px · 32px`

| Token | Value | Usage |
|---|---|---|
| `--radius-sm` | `8px` | Buttons, inputs, small chips |
| `--radius` | `12px` | Cards, panels |
| `--radius-lg` | `18px` | Message bubbles, modals, composer |
| `--shadow` | `0 4px 24px rgba(0,0,0,0.5)` | Modals, floating elements |
| `--shadow-sm` | `0 2px 8px rgba(0,0,0,0.3)` | Subtle elevation |
| `--transition` | `150ms ease` | Default interactive transitions |
| `--transition-fast` | `120ms ease` | Micro-interactions (button press, badge) |
| `--transition-slow` | `280ms cubic-bezier(0.4,0,0.2,1)` | Panel/modal entry animations |

---

## Component patterns

### Buttons

| Class | Background | Text |
|---|---|---|
| `.btn-primary` | `--accent` | white |
| `.btn-ghost` | transparent | `--text-2`, border `--border` |
| `.btn-danger` | `--danger-dim` | `--danger` |
| `.btn-icon` | transparent | `--text-2`, 6px padding |

Send button: 36px circle, `--accent` fill.

### Inputs

- Background: `--surface-2`
- Border: `--border`
- Border-radius: `--radius-sm` (8px)
- Focus: border becomes `--accent`, box-shadow `0 0 0 3px --accent-dim`

### Message bubbles

- Received: `--hover` bg, `1px solid --border`, `border-bottom-left-radius: 4px` (tail), box-shadow `--shadow-card`
- Sent: `--sent` bg, no border, `color: #eef3fb`, `border-bottom-right-radius: 4px` (tail), right-aligned
- Sent timestamp color: `#8ba3c9`; sent ticks color: `#7fa8f0`
- Padding: `9px 13px`
- Border-radius: `22px` (not `--radius-lg` — bubbles use 22px directly per Halo spec)
- Grouped same-sender: top corner nearest sender = 4px instead of the tail corner
- Max width: `min(64%, 440px)` — responsive so text stays readable at any viewport width
- `overflow-wrap: anywhere` — prevents long URLs/strings from overflowing the bubble
- Editing active state: `outline: 2px solid --accent; outline-offset: 2px` on the bubble being edited
- New message entry: `msgIn` keyframe, 340ms `cubic-bezier(.22,.61,.36,1)`

### Composer

- Background: `--hover`, border `--border`, `border-radius: 24px`
- Focused: border `color-mix(in oklab, --accent 60%, transparent)`, ring shadow
- Box-shadow: `--shadow-card`
- Padding: `5px 6px 5px 18px` (pill shape)
- Send button: 44px circle; empty = `--hover` bg + icon; ready = `--accent` bg + glow

### Sidebar

- Width: 336px (open), 0px (hidden) — CSS `width` transition `0.22s ease`
- Background: `--surface`
- Right border: `1px solid --border` (removed when hidden)
- Active chat: `--surface-3` background + left inset border `--accent`
- Toggle button: `.sidebar-toggle` in the chat header, `btn-icon` style
- Mobile (`≤640px`): sidebar overlays the chat (absolute positioned, `z-index: 40`, `box-shadow: --shadow`); hidden by default, revealed by toggle
- The chat area always remains visible and readable regardless of sidebar state

### Members panel

- Width: 240px
- Background: `--surface`
- Left border: `1px solid --border`

### Modals

- Background: `--surface`
- Border: `1px solid --border`, `border-radius: 22px`
- Max-width: 440px, padding: 28px
- Overlay: `rgba(8,10,14,0.6)` backdrop, `scrimIn` 200ms
- Entry animation: `modalIn` 260ms `cubic-bezier(.22,.61,.36,1)`, box-shadow `--shadow-pop`

### User profile modal (Halo redesign)

- Cover band (96px gradient at top), avatar overlapping by −48px with `4px solid --surface` border
- Modal has `padding: 0; border-radius: 22px; overflow: hidden`
- Username: 21px/600; Status: 13.5px
- Meta card below status: username row + status row, `border: 1px solid --border; border-radius: 14px`
- Avatar size: `avatar-2xl` (96×96px)

---

### Image grid (message attachments)

- `.attachment-grid` CSS grid inside a message bubble
- Count-specific classes: `.count-1` (single full-width), `.count-2` (2 cols), `.count-3/.count-4` (2×2 grid)
- Each cell `.attachment-grid-img`: `object-fit: cover`, fixed height, `cursor: pointer`
- Clicking opens `ImageLightbox`

### Image lightbox

- Full-viewport overlay: `rgba(0,0,0,0.88)` backdrop, `z-index: 1000`
- Navigation arrows (left/right) `--surface` background, fade on edge
- Counter pill (current / total) top-center
- Zoom controls bar (bottom-center): `−` button, percentage label, `+` button — `--surface` background, `--radius-sm` border-radius
- Image transform: CSS `translate + scale` updated via JS refs (`panX`, `panY`, `zoom`)
- `cursor: grab` when zoom > 1, `cursor: grabbing` while dragging

### Editing bar

- Appears above the reply bar / composer when a message is being edited
- Left accent border: `inset 3px 0 0 var(--accent)` box-shadow
- Pencil icon + truncated message preview + cancel (×) button
- Same background as reply bar (`--surface-2`)

### Reply quote (inside bubble)

- `cursor: pointer`, `transition: opacity 150ms`
- Hover: `opacity: 0.75`
- On click: smooth-scroll to original + flash highlight animation (`@keyframes msg-highlight-flash`)

### Avatar upload button

- `.avatar-upload-btn`: absolute circle overlay on the bottom-right of an avatar
- Background: `--accent`, white camera icon SVG
- Contains hidden `<input type="file">` — clicking the label triggers the picker
- Spinner SVG replaces icon while uploading (`animation: spin 1s linear infinite`)

### User profile modal

- Standard `.modal` (max-width 440px, `--surface`, `--radius-lg`)
- Large avatar (XL size) centered at top
- Username (600 weight, 17px) + online/offline status text
- Action button full-width: "Send message" (btn-primary) or "Edit profile" (btn-ghost) for own user
- Close button (×) absolute top-right, Esc also closes

## Icons

All icons are **inline SVG**, stroke-based, `currentColor`. No external icon library. Stroke weight: 1.5–2px. Size: typically 16–20px.

---

## AI Assistant

The only place decorative gradients are allowed:

```css
background: linear-gradient(135deg, var(--accent) 0%, #a78bfa 100%);
```

Used on the AI avatar/icon only.

**Exception — skeleton shimmer:** `.skeleton-bubble` uses a functional shimmer gradient built exclusively from design tokens (`--surface-2`, `--surface-3`). This is a loading-state pattern, not a decorative gradient, and is the only other permitted use:

```css
background: linear-gradient(90deg, var(--surface-2) 25%, var(--surface-3) 50%, var(--surface-2) 75%);
```

---

### Poll card (Telegram-style)

`.poll-card` lives inside a message bubble. Each option row has a background fill bar (`.poll-option-bar`) that is absolutely positioned and uses `opacity` to blend with the container — no `color-mix()`, no hardcoded hex.

| Element | Token | Role |
|---|---|---|
| `.poll-option` background | `var(--surface-2)` | Resting state |
| `.poll-option-bar` fill | `var(--accent)` at opacity 0.15 | Progress fill behind text |
| `.poll-option-voted .poll-option-bar` | opacity 0.28 | Voted option emphasis |
| `.poll-option-winner .poll-option-bar` | opacity 0.38 | Winning option extra emphasis |
| `.poll-retract-btn` / vote count link | `var(--text-3)` → `var(--text-2)` on hover | Text-link style, no background |

**PollResultsModal** (`.poll-results-modal`) uses `.modal` base with `max-width: 420px`. Progress bars use `.prd-bar-track` / `.prd-bar-fill` with `var(--accent)` fill, 4px height. Voter pills use `var(--surface-2)` background.

---

## What NOT to do

- No new colors outside the palette above
- No hardcoded hex, rgb(), or hsl() in component or scoped styles — only `var(--token)` references
- No gradients except the AI icon (and the skeleton shimmer pattern)
- No `font-weight: 700` or `bold` — maximum weight is 600
- No shadows heavier than `--shadow`
- No border-radius values outside `--radius-sm / --radius / --radius-lg / 50%`
- No external CSS frameworks, Tailwind, or component libraries

---

### UserAvatar color palette

`UserAvatar.vue` generates a deterministic background color from the first character of the username. The palette is a fixed array of 8 hue-rotated colors defined directly in the component. This is intentional and acceptable — the values are self-contained presentation logic, not design tokens, and they adapt visually across both themes because they are saturated mid-tones.
