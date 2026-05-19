<template>
  <div
    class="app-shell"
    @touchstart.passive="onSwipeTouchStart"
    @touchend.passive="onSwipeTouchEnd"
    @touchcancel.passive="onSwipeTouchCancel"
  >
    <!-- Sidebar with chat list -->
    <aside class="sidebar" :class="{ 'sidebar-hidden': sidebarHidden }">
      <div class="sidebar-header">
        <div class="sidebar-logo">💬</div>
        <span class="sidebar-logo-text">RealtimeChat</span>
        <button class="online-indicator" :class="{ active: showOnlinePanel }" :title="showOnlinePanel ? 'Close' : 'Online users'" @click="showOnlinePanel = !showOnlinePanel">
          <span class="online-indicator-dot"></span>{{ onlineUsers.length }} online
        </button>
        <button class="btn-icon" title="New chat" @click="openCreate">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <OnlineUsersPanel
        v-if="showOnlinePanel"
        :users="onlineUsers"
        @open-profile="openUserProfile"
        @close="showOnlinePanel = false"
      />
      <GlobalSearchPanel
        v-else-if="globalSearchOpen"
        @close="globalSearchOpen = false"
        @select="onGlobalSearchSelect"
      />
      <div v-else class="sidebar-chats">
        <!-- AI Assistant entry -->
        <button
          class="chat-item"
          :class="{ active: chatId.value === 'ai' }"
          type="button"
          @click="router.push('/chats/ai')"
        >
          <div class="ai-chat-icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/><circle cx="9" cy="14" r="1" fill="currentColor"/><circle cx="15" cy="14" r="1" fill="currentColor"/></svg>
          </div>
          <div class="chat-item-info">
            <div class="chat-item-top">
              <span class="chat-item-name">AI Assistant</span>
            </div>
            <div class="chat-item-top" style="margin-top:1px">
              <span class="chat-item-preview">Ask me anything</span>
            </div>
          </div>
        </button>

        <div v-if="sidebarChats.length" class="chats-section-header">
          <span class="chats-section-label">Conversations</span>
          <button class="btn-icon chats-section-search-btn" title="Search all messages" @click="globalSearchOpen = true">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </button>
        </div>
        <button
          v-for="c in sidebarChats"
          :key="c.id"
          class="chat-item"
          :class="{ active: c.id === chatId.value, unread: (c.unread_count || 0) > 0 }"
          type="button"
          @click="router.push(`/chats/${c.id}`)"
        >
          <UserAvatar :username="c.display_name || c.id" :avatarUrl="c.avatar_url || null" size="md" />
          <div class="chat-item-info">
            <div class="chat-item-top">
              <span class="chat-item-name">{{ c.display_name || c.id }}</span>
              <svg v-if="isMuted(c.id)" class="muted-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <span class="chat-item-time">{{ formatTimeShort(c.last_message?.created_at) }}</span>
            </div>
            <div class="chat-item-top" style="margin-top:1px">
              <span class="chat-item-preview" :class="{ 'chat-item-preview--typing': sidebarTypingMap[c.id] }">
                <template v-if="sidebarTypingMap[c.id]">
                  <span v-if="c.is_group" class="sidebar-typing-name">{{ sidebarTypingMap[c.id] }}</span>
                  <span class="typing-dots typing-dots--sm"><span></span><span></span><span></span></span>
                </template>
                <template v-else>
                  <span v-if="draftMap[c.id]" class="draft-label">Draft: </span>{{ sidebarPreview(c) }}
                </template>
              </span>
              <span v-if="(c.unread_count || 0) > 0" class="unread-badge" :class="{ 'unread-badge--muted': isMuted(c.id) }">{{ c.unread_count }}</span>
            </div>
          </div>
        </button>
      </div>

      <div class="sidebar-footer">
        <UserAvatar :username="me?.username || '?'" :avatarUrl="me?.avatar_url" size="md" style="cursor:pointer" @click="router.push('/profile')" />
        <div class="sidebar-footer-user" style="cursor:pointer" @click="router.push('/profile')">
          <div class="sidebar-footer-name">{{ me?.username || '…' }}</div>
          <div class="sidebar-footer-status">{{ me?.email }}</div>
        </div>
        <button class="btn-icon" title="Logout" @click="logout">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </div>
    </aside>

    <!-- Mobile: tap-outside backdrop behind the open sidebar -->
    <div
      class="sidebar-backdrop"
      :class="{ 'sidebar-backdrop--active': !sidebarHidden }"
      @click="sidebarHidden = true"
    />

    <!-- Main chat area -->
    <div
      class="chat-area"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
      @click="showEmojiPicker = false; closeReactionPicker(); showAttachMenu = false; showSendMenu = false; closeMobileMenu()"
    >
      <!-- Drag-and-drop overlay -->
      <div v-if="dragging && !isAiChat" class="drop-overlay">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        Drop to attach
      </div>

      <!-- Header -->
      <div class="chat-header">
        <button class="sidebar-toggle" :title="sidebarHidden ? 'Show sidebar' : 'Hide sidebar'" @click="sidebarHidden = !sidebarHidden">
          <svg v-if="sidebarHidden" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="18" rx="1"/><line x1="14" y1="9" x2="21" y2="9"/><line x1="14" y1="15" x2="21" y2="15"/></svg>
        </button>
        <UserAvatar
          :username="chatTitle"
          :avatarUrl="isGroup ? chat?.avatar_url : (peerUser?.avatar_url ?? null)"
          size="md"
          style="cursor:pointer"
          @click="isGroup ? openGroupProfile() : peerUser ? openUserProfile(peerUser.username) : undefined"
        />
        <div class="chat-header-info" style="cursor:pointer" @click="isGroup ? openGroupProfile() : peerUser ? openUserProfile(peerUser.username) : undefined">
          <div class="chat-header-name">{{ chatTitle }}</div>
          <div class="chat-header-sub">
            <span v-if="!isGroup && peerUser">
              <span v-if="isPeerOnline" class="chat-header-online">● Online</span>
              <span v-else-if="peerUser.last_seen_at">Last seen {{ formatRelative(peerUser.last_seen_at) }}</span>
              <span v-else>Offline</span>
            </span>
            <span v-else-if="isGroup">{{ participants.length }} members<template v-if="onlineParticipantsCount > 0">, <span class="chat-header-online">{{ onlineParticipantsCount }} online</span></template></span>
          </div>
        </div>
        <div class="chat-header-actions">
          <button v-if="!isAiChat" class="btn-icon" :title="searchOpen ? 'Close search' : 'Search messages'" :style="searchOpen ? 'color:var(--accent)' : ''" @click="toggleSearch">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </button>
          <button v-if="!isAiChat" class="btn-icon" :title="isMuted(chatId) ? 'Unmute' : 'Mute'" :style="isMuted(chatId) ? 'color:var(--text-3)' : ''" @click="toggleMute(chatId)">
            <svg v-if="isMuted(chatId)" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          </button>
          <button v-if="!isGroup" class="btn-icon" title="Delete chat" style="color:var(--danger)" @click="deleteChat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>
      </div>

      <!-- Search bar -->
      <div v-if="searchOpen && !isAiChat" class="msg-search-bar">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          ref="searchInputEl"
          v-model="searchQuery"
          class="msg-search-input"
          placeholder="Search messages…"
          @input="onSearchInput"
          @keydown.escape="closeSearch"
        />
        <button v-if="searchQuery" class="btn-icon" style="padding:4px" title="Clear" @click="searchQuery = ''; searchResults = []; searchLoading = false">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Search results panel -->
      <div v-if="searchOpen && !isAiChat && (searchLoading || searchQuery.trim().length >= 2)" class="msg-search-results">
        <div v-if="searchLoading" class="msg-search-status">Searching…</div>
        <template v-else>
          <div v-if="!searchResults.length" class="msg-search-status">No messages found for "{{ searchQuery.trim() }}"</div>
          <button
            v-for="r in searchResults"
            :key="r.id"
            class="msg-search-item"
            @click="jumpToSearchResult(r.id)"
          >
            <UserAvatar :username="r.sender" :avatarUrl="r.sender_avatar_url" size="sm" />
            <div class="msg-search-item-info">
              <div class="msg-search-item-meta">
                <span class="msg-search-item-sender">{{ r.sender }}</span>
                <span class="msg-search-item-time">{{ formatTimeShort(r.created_at) }}</span>
              </div>
              <div class="msg-search-item-text">{{ r.content }}</div>
            </div>
          </button>
        </template>
      </div>

      <!-- Pinned message bar -->
      <div v-if="currentPinned && !isAiChat" class="pinned-bar" @click="clickPinnedBar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0"><path d="M12 2a2 2 0 0 0-2 2v8l-3 3v1h10v-1l-3-3V4a2 2 0 0 0-2-2z"/><line x1="12" y1="22" x2="12" y2="19"/></svg>
        <div class="pinned-bar-info">
          <span class="pinned-bar-label">Pinned{{ pinnedMessages.length > 1 ? ` · ${pinnedIndex + 1} / ${pinnedMessages.length}` : '' }}</span>
          <span class="pinned-bar-content">{{ pinnedPreview(currentPinned) }}</span>
        </div>
        <div v-if="pinnedMessages.length > 1" class="pinned-nav-row">
          <button class="btn-icon pinned-nav-btn" title="Newer pinned" @click.stop="navigatePin(1)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <button class="btn-icon pinned-nav-btn" title="Older pinned" @click.stop="navigatePin(-1)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
          </button>
        </div>
        <button v-if="canPin" class="btn-icon" style="padding:4px;flex-shrink:0" title="Unpin" @click.stop="doPin(currentPinned.id)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      <!-- Messages + Members panel wrapper -->
      <div style="display:flex;flex:1;min-height:0;overflow:hidden">
        <!-- Messages -->
        <div style="display:flex;flex-direction:column;flex:1;min-width:0;overflow:hidden;position:relative">
          <div ref="listEl" class="messages-area" @scroll="onScroll">
            <div v-if="loadingMore" class="load-more-spinner">Loading…</div>

            <template v-if="chatLoading && !messages.length">
              <div class="skeleton-row"><div class="skeleton-bubble" style="width:60%"></div></div>
              <div class="skeleton-row own"><div class="skeleton-bubble" style="width:40%"></div></div>
              <div class="skeleton-row"><div class="skeleton-bubble" style="width:75%"></div></div>
              <div class="skeleton-row own"><div class="skeleton-bubble" style="width:50%"></div></div>
              <div class="skeleton-row"><div class="skeleton-bubble" style="width:65%"></div></div>
            </template>

            <div class="message-group" v-else v-for="g in grouped" :key="g.key">
              <div class="date-separator">
                <span class="date-separator-text">{{ g.title }}</span>
              </div>

              <template v-for="(m, idx) in g.items" :key="m.id">
                <div v-if="m.type === 'system'" class="system-notification">{{ m.content }}</div>
                <div
                  v-else
                  :id="`msg-${m.id}`"
                  class="message-row"
                  :class="{
                    own: isMine(m),
                    'same-sender': idx > 0 && g.items[idx-1].sender === m.sender && !g.items[idx-1].deleted_at,
                    'msg-highlighted': highlightedId === m.id,
                    'msg-new': newMessageIds.has(m.id),
                  }"
                >
                  <!-- Avatar slot (others only) -->
                  <div class="message-avatar-slot">
                    <UserAvatar
                      v-if="!isMine(m) && (idx === g.items.length-1 || g.items[idx+1]?.sender !== m.sender)"
                      :username="m.sender"
                      :avatarUrl="m.sender_avatar_url"
                      size="sm"
                      style="cursor:pointer"
                      @click="openUserProfile(m.sender)"
                    />
                  </div>

                  <div class="message-bubble-wrap">
                    <!-- Sender name (group chats, others only) -->
                    <div v-if="isGroup && !isMine(m) && (idx === 0 || g.items[idx-1].sender !== m.sender)" class="message-sender-name" style="cursor:pointer" @click="openUserProfile(m.sender)">
                      {{ m.sender }}
                    </div>

                    <div
                      class="message-bubble-outer"
                      :class="{ 'editing-active': editingId === m.id }"
                      :style="swipeMsgId === m.id ? { transform: `translateX(${msgSwipeX}px)`, transition: msgSwipeDone ? 'transform 0.25s cubic-bezier(0.25,1,0.5,1)' : 'none' } : {}"
                      @touchstart.passive="onMsgTouchStart($event, m)"
                      @touchend.passive="onMsgTouchEnd($event, m)"
                      @touchcancel.passive="onMsgTouchCancel()"
                      @contextmenu.prevent
                    >
                      <!-- Swipe-to-reply icon (mobile only, revealed as bubble slides) -->
                      <div
                        v-if="!isAiChat && !m.deleted_at && m.type !== 'system'"
                        class="msg-swipe-icon"
                        :style="swipeMsgId === m.id && msgSwipeX > 0 ? { opacity: Math.min(msgSwipeX / 50, 1), transform: `translateY(-50%) scale(${0.4 + 0.6 * Math.min(msgSwipeX / 50, 1)})` } : { opacity: 0, transform: 'translateY(-50%) scale(0.4)' }"
                        aria-hidden="true"
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                      </div>
                        <!-- Actions (desktop hover only, hidden on mobile) -->
                        <div v-if="!m.deleted_at && !isAiChat" class="message-actions">
                          <button v-if="isMine(m)" class="btn-icon" style="padding:4px 6px;border-radius:4px" title="Edit" @click="startEdit(m)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          </button>
                          <button v-if="isMine(m)" class="btn-icon" style="padding:4px 6px;border-radius:4px;color:var(--danger)" title="Delete" @click="removeMessage(m)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                          </button>
                          <button class="btn-icon" style="padding:4px 6px;border-radius:4px" title="Reply" @click="startReply(m)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                          </button>
                          <button class="btn-icon" style="padding:4px 6px;border-radius:4px" title="Forward" @click="startForward(m)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg>
                          </button>
                          <button v-if="canPin" class="btn-icon" style="padding:4px 6px;border-radius:4px" :title="pinnedMessages.some(p => p.id === m.id) ? 'Unpin' : 'Pin'" @click="doPin(m.id)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 0-2 2v8l-3 3v1h10v-1l-3-3V4a2 2 0 0 0-2-2z"/><line x1="12" y1="22" x2="12" y2="19"/></svg>
                          </button>
                          <button class="btn-icon" style="padding:4px 6px;border-radius:4px" title="React" @click.stop="openReactionPicker(m.id, $event)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                          </button>
                        </div>
                        <div class="message-bubble" :class="{ deleted: !!m.deleted_at }">
                          <div v-if="m.forwarded_from" class="forwarded-badge">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg>
                            Forwarded from <strong>{{ m.forwarded_from }}</strong>
                          </div>
                          <div v-if="m.reply_to" class="reply-quote" @click.stop="jumpToMessage(m.reply_to.id)">
                            <span class="reply-quote-sender">{{ m.reply_to.sender }}</span>
                            <span class="reply-quote-content">{{ m.reply_to.deleted ? 'Message deleted' : m.reply_to.content }}</span>
                          </div>
                          <span v-if="m.deleted_at" style="font-style:italic">Message deleted</span>
                          <template v-else>
                            <!-- Poll message -->
                            <PollMessage
                              v-if="m.type === 'poll' && m.poll"
                              :poll="m.poll"
                              :my-username="me?.username"
                              @vote="doVotePoll(m.id, $event)"
                            />
                            <template v-else>
                            <span v-if="m.content" style="white-space:pre-wrap;word-break:break-word">{{ m.content }}</span>

                            <!-- Image grid -->
                            <template v-if="getAttachments(m).filter(a => a.type === 'image').length">
                              <div
                                class="attachment-grid"
                                :class="`count-${Math.min(getAttachments(m).filter(a => a.type === 'image').length, 4)}`"
                              >
                                <img
                                  v-for="(a, ai) in getAttachments(m).filter(a => a.type === 'image').slice(0, 4)"
                                  :key="ai"
                                  :src="a.url"
                                  class="attachment-grid-img"
                                  @click="openLightbox(a.url)"
                                />
                              </div>
                            </template>

                            <!-- Non-image attachments -->
                            <template v-for="(a, ai) in getAttachments(m).filter(a => a.type !== 'image')" :key="ai">
                              <div class="attachment">
                                <video v-if="a.type === 'video'" :src="a.url" controls class="attachment-video"></video>
                                <AudioPlayer v-else-if="a.type === 'audio'" :src="a.url" />
                                <a v-else :href="a.url" target="_blank" download class="attachment-file">
                                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                  {{ a.name || 'Download file' }}
                                </a>
                              </div>
                            </template>
                            </template><!-- end v-else (non-poll) -->
                          </template>
                        </div>
                    </div>

                    <div class="message-meta">
                      <span class="message-time">{{ formatTime(m.created_at) }}</span>
                      <span v-if="m.edited_at && !m.deleted_at" class="message-edited">edited</span>
                      <span v-if="isMine(m) && !m.deleted_at" class="message-ticks" :class="{ read: peerReadId && idLE(m.id, peerReadId) }">
                        <template v-if="peerReadId && idLE(m.id, peerReadId)">✓✓</template>
                        <template v-else-if="peerDeliveredId && idLE(m.id, peerDeliveredId)">✓</template>
                      </span>
                    </div>

                    <!-- Reactions row -->
                    <div v-if="m.reactions && m.reactions.length" class="message-reactions">
                      <button
                        v-for="r in m.reactions"
                        :key="r.emoji"
                        class="reaction-pill"
                        :class="{ 'by-me': isMyReaction(m, r.emoji) }"
                        :title="r.users.join(', ')"
                        @click.stop="handleReactionClick(m, r.emoji, $event)"
                      >{{ r.emoji }} {{ r.count }}</button>
                      <button v-if="!isAiChat && !m.deleted_at" class="reaction-add-btn" title="Add reaction" @click.stop="openReactionPicker(m.id, $event)">+</button>
                    </div>
                  </div>

                </div>
              </template>
            </div>
          </div>

          <button
            v-if="!isAiChat"
            class="scroll-to-bottom"
            :class="{ hidden: !showScrollBtn }"
            @click="scrollToBottomFab"
            aria-label="Scroll to bottom"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            <span v-if="unreadWhileScrolled > 0" class="scroll-to-bottom-badge">{{ unreadWhileScrolled }}</span>
          </button>

          <!-- Typing indicator / inline error -->
          <div class="typing-indicator">
            <span v-if="isAiChat && aiLoading" class="ai-thinking">AI Assistant is thinking…</span>
            <span v-else-if="composerError" style="color:var(--danger);cursor:pointer" @click="composerError=''">⚠ {{ composerError }}</span>
            <span v-else-if="typingUser" class="typing-indicator-content">{{ typingUser }}&nbsp;<span class="typing-dots"><span></span><span></span><span></span></span></span>
          </div>

          <!-- Editing bar -->
          <div v-if="editingId" class="reply-bar editing-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span class="reply-bar-text">
              <strong style="color:var(--accent)">Editing</strong>{{ editingText ? ': ' + editingText : '' }}
            </span>
            <button class="btn-icon" style="padding:4px" @click="cancelEdit">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Reply bar -->
          <div v-if="replyingTo" class="reply-bar">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
            <span class="reply-bar-text">
              <strong>{{ replyingTo.sender }}</strong>{{ replyingTo.deleted ? ' · Message deleted' : ': ' + replyingTo.content }}
            </span>
            <button class="btn-icon" style="padding:4px" @click="cancelReply">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Pending files preview -->
          <div v-if="pendingFiles.length" class="reply-bar" style="flex-wrap:wrap;gap:8px;align-items:flex-start">
            <div
              v-for="(f, fi) in pendingFiles"
              :key="fi"
              style="position:relative;flex-shrink:0"
            >
              <img v-if="f.previewUrl" :src="f.previewUrl" style="height:52px;width:52px;object-fit:cover;border-radius:6px;display:block" />
              <div v-else style="height:52px;display:flex;align-items:center;gap:4px;font-size:12px;color:var(--text-2);max-width:120px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                {{ f.name }}
              </div>
              <button
                class="btn-icon"
                style="position:absolute;top:-6px;right:-6px;background:var(--surface-2);border-radius:50%;width:18px;height:18px;padding:0;display:flex;align-items:center;justify-content:center"
                @click="cancelFile(fi)"
              >
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
            <button class="btn-icon" style="padding:4px;align-self:center" title="Clear all" @click="cancelFile()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Composer -->
          <div class="composer-wrap">
            <!-- Attach menu portal -->
            <Teleport to="body">
              <template v-if="showAttachMenu">
                <div class="floating-menu-backdrop" @click="showAttachMenu = false" @touchstart.prevent="showAttachMenu = false"></div>
                <div
                  class="attach-menu-portal"
                  :style="{ bottom: attachMenuPos.bottom + 'px', left: attachMenuPos.left + 'px' }"
                  @click.stop
                  @touchstart.stop
                >
                  <button class="attach-menu-item" @click="fileInputEl.click(); showAttachMenu = false">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    Attach file
                  </button>
                  <button class="attach-menu-item" @click="showPollForm = true; showAttachMenu = false">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="4" height="18" rx="1"/><rect x="10" y="8" width="4" height="13" rx="1"/><rect x="17" y="13" width="4" height="8" rx="1"/></svg>
                    Create poll
                  </button>
                </div>
              </template>
            </Teleport>
            <!-- Send menu portal -->
            <Teleport to="body">
              <template v-if="showSendMenu">
                <div class="floating-menu-backdrop" @click="showSendMenu = false" @touchstart.prevent="showSendMenu = false"></div>
                <div
                  class="send-menu-portal"
                  :style="{ bottom: sendMenuPos.bottom + 'px', right: sendMenuPos.right + 'px' }"
                  @click.stop
                  @touchstart.stop
                >
                  <button class="attach-menu-item" @click="showSendMenu = false; openSchedulePicker()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Schedule message
                  </button>
                </div>
              </template>
            </Teleport>
            <!-- Emoji picker for composer -->
            <Teleport to="body">
              <div
                v-if="showEmojiPicker"
                class="emoji-picker-portal"
                :style="{ left: emojiPickerPos.left + 'px', bottom: emojiPickerPos.bottom + 'px', transform: 'translateX(-50%)', top: 'auto' }"
                @click.stop
                @mousedown.stop
              >
                <EmojiPicker @select="onEmojiSelect" />
              </div>
            </Teleport>

            <!-- Reaction quick picker -->
            <Teleport to="body">
              <div
                v-if="reactionPickerMsgId"
                class="reaction-picker-portal"
                :style="{ left: reactionPickerPos.x + 'px', top: reactionPickerPos.y + 'px' }"
                @click.stop
                @mousedown.stop
              >
                <div v-if="!showFullReactionPicker" class="reaction-quick-pick">
                  <button
                    v-for="e in QUICK_REACTIONS"
                    :key="e"
                    class="reaction-quick-btn"
                    :class="{ active: isMyReaction(messages.find(m => m.id === reactionPickerMsgId), e) }"
                    @click.stop="doToggleReaction(reactionPickerMsgId, e); closeReactionPicker()"
                  >{{ e }}</button>
                  <button class="reaction-quick-btn reaction-more-btn" title="More emojis" @click.stop="showFullReactionPicker = true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                  </button>
                </div>
                <EmojiPicker v-else @select="onEmojiSelect" />
              </div>
            </Teleport>

            <!-- Mobile long-press context menu -->
            <Teleport to="body">
              <div
                v-if="mobileMenu"
                class="mobile-ctx-overlay"
                @click="closeMobileMenu()"
                @touchstart.prevent="closeMobileMenu()"
              >
                <div
                  ref="mobileMenuEl"
                  class="mobile-ctx-menu"
                  :style="{ left: mobileMenu.x + 'px', top: mobileMenu.y + 'px', visibility: mobileMenu.adjusted ? 'visible' : 'hidden' }"
                  @click.stop
                  @touchstart.stop
                >
                  <!-- Quick reactions — always visible, never scrolled away -->
                  <div class="mobile-ctx-reactions">
                    <button
                      v-for="e in QUICK_REACTIONS"
                      :key="e"
                      class="mobile-ctx-reaction-btn"
                      :class="{ active: isMyReaction(mobileMenu.msg, e) }"
                      @click="doToggleReaction(mobileMenu.msg.id, e); closeMobileMenu()"
                    >{{ e }}</button>
                  </div>
                  <!-- Action items in a scrollable block so they never overflow off-screen -->
                  <div class="mobile-ctx-items">
                    <button class="mobile-ctx-item" @click="startReply(mobileMenu.msg); closeMobileMenu()">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                      Reply
                    </button>
                    <button v-if="mobileMenu.msg.content" class="mobile-ctx-item" @click="copyMessageText(mobileMenu.msg)">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                      Copy
                    </button>
                    <button v-if="isMine(mobileMenu.msg)" class="mobile-ctx-item" @click="startEdit(mobileMenu.msg); closeMobileMenu()">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      Edit
                    </button>
                    <button v-if="canPin" class="mobile-ctx-item" @click="doPin(mobileMenu.msg.id); closeMobileMenu()">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 0-2 2v8l-3 3v1h10v-1l-3-3V4a2 2 0 0 0-2-2z"/><line x1="12" y1="22" x2="12" y2="19"/></svg>
                      {{ pinnedMessages.some(p => p.id === mobileMenu.msg.id) ? 'Unpin' : 'Pin' }}
                    </button>
                    <button class="mobile-ctx-item" @click="startForward(mobileMenu.msg); closeMobileMenu()">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 0 1 4-4h12"/></svg>
                      Forward
                    </button>
                    <button v-if="isMine(mobileMenu.msg)" class="mobile-ctx-item mobile-ctx-danger" @click="removeMessage(mobileMenu.msg); closeMobileMenu()">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            </Teleport>

          <div class="composer">
            <!-- Normal mode -->
            <template v-if="!recording">
              <input ref="fileInputEl" type="file" multiple style="display:none" @change="onFileSelect" />
              <!-- Attach menu -->
              <div v-if="!isAiChat" class="attach-menu-wrap">
                <button
                  ref="attachBtnEl"
                  class="btn-icon composer-attach"
                  :class="{ active: showAttachMenu }"
                  title="Attach"
                  :disabled="uploading"
                  @click.stop="toggleAttachMenu()"
                >
                  <svg v-if="!uploading" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                  <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                </button>
              </div>
              <textarea
                ref="composerEl"
                v-model="input"
                class="composer-input"
                placeholder="Type a message…"
                rows="1"
                @keydown="onKeydown"
                @input="onTyping"
              />
              <button
                ref="emojiButtonEl"
                class="btn-icon composer-emoji"
                :class="{ active: showEmojiPicker }"
                title="Emoji"
                @click.stop="toggleEmojiPicker"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"/>
                  <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                  <circle cx="9" cy="9.5" r="1.5" fill="currentColor" stroke="none"/>
                  <circle cx="15" cy="9.5" r="1.5" fill="currentColor" stroke="none"/>
                </svg>
              </button>
              <button
                v-if="!isAiChat && scheduledMessages.length > 0"
                class="btn-icon composer-clock"
                :title="`Scheduled (${scheduledMessages.length})`"
                @click="showScheduledList = true"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span class="composer-clock-badge">{{ scheduledMessages.length }}</span>
              </button>
              <button v-if="!isAiChat" class="btn-icon composer-mic" title="Record voice message" :disabled="uploading" @click="startRecording">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/></svg>
              </button>
              <div v-if="!isAiChat" class="send-menu-wrap">
                <button
                  ref="sendBtnEl"
                  class="composer-send"
                  :disabled="!input.trim() && !pendingFiles.length"
                  @click="send"
                  @contextmenu.prevent="openSendMenu()"
                  @touchstart="onSendTouchStart"
                  @touchend="onSendTouchEnd"
                  @touchmove="onSendTouchEnd"
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
              </div>
              <button v-else class="composer-send" :disabled="!input.trim() || aiLoading" @click="send">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </template>

            <!-- Recording mode -->
            <template v-else>
              <span class="recording-dot"></span>
              <span class="recording-time">{{ fmtRecTime(recordingTime) }}</span>
              <span style="flex:1" />
              <button class="btn-icon" style="color:var(--danger);padding:6px 8px" title="Cancel" @click="cancelRecording">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
              <button class="composer-send" title="Send voice message" @click="sendRecording">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              </button>
            </template>
          </div>
          </div><!-- /composer-wrap -->
        </div>

      </div>
    </div>

    <!-- Group profile modal -->
    <GroupProfileModal
      v-if="showGroupProfile && isGroup && chat"
      :chat="chat"
      :participants="participants"
      :me="me"
      :onlineUsers="onlineUsers"
      @close="showGroupProfile = false"
      @updated="onGroupUpdated($event)"
      @member-added="onGroupMembersChanged($event)"
      @member-removed="onGroupMembersChanged($event)"
      @left="sidebarChats = sidebarChats.filter(c => c.id !== chatId); router.push('/')"
      @deleted="sidebarChats = sidebarChats.filter(c => c.id !== chatId); router.push('/')"
      @open-user="openUserProfile($event)"
    />

    <!-- User profile modal -->
    <UserProfileModal
      v-if="profileUsername"
      :username="profileUsername"
      :sidebarChats="sidebarChats"
      @close="profileUsername = null"
      @open-chat="(id) => { profileUsername = null; router.push(`/chats/${id}`) }"
      @go-profile="router.push('/profile')"
    />

    <!-- Image lightbox -->
    <ImageLightbox
      v-if="lightboxOpen"
      :images="allImages"
      :index="lightboxIndex"
      @close="lightboxOpen = false"
      @navigate="lightboxIndex = $event"
    />

    <!-- Forward modal -->
    <div v-if="showForwardModal" class="modal-overlay" @click.self="showForwardModal = false">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">Forward to…</span>
          <button class="btn-icon" @click="showForwardModal = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body" style="padding:0;max-height:360px;overflow-y:auto">
          <button
            v-for="c in sidebarChats"
            :key="c.id"
            class="forward-chat-item"
            @click="doForward(c.id)"
          >
            <UserAvatar :username="c.display_name || c.id" :avatarUrl="c.avatar_url || null" size="sm" />
            <span class="forward-chat-name">{{ c.display_name || c.id }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- New chat modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="closeCreate">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">New conversation</span>
          <button class="btn-icon" @click="closeCreate">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="toggle-tabs">
            <div class="toggle-tab" :class="{ active: !createIsGroup }" @click="createIsGroup = false; selectedUsers = []">Direct</div>
            <div class="toggle-tab" :class="{ active: createIsGroup }" @click="createIsGroup = true; selectedUsers = []">Group</div>
          </div>
          <div v-if="createIsGroup">
            <label class="form-label">Title</label>
            <input v-model="createTitle" class="input" placeholder="Group name" />
          </div>
          <div>
            <label class="form-label">{{ createIsGroup ? 'Participants' : 'Username or email' }}</label>
            <div v-if="createIsGroup && selectedUsers.length" class="user-chips">
              <span v-for="u in selectedUsers" :key="u.username" class="user-chip">
                {{ u.username }}
                <button type="button" class="user-chip-remove" @click="removeUser(u.username)">×</button>
              </span>
            </div>
            <div style="position:relative">
              <input
                v-model="userSearchQuery"
                class="input"
                :placeholder="createIsGroup ? 'Search users…' : 'Search by username or email'"
                autocomplete="off"
                @input="onUserSearchInput"
                @focus="showSuggestions = userSuggestions.length > 0"
                @blur="onSearchBlur"
              />
              <div v-if="showSuggestions && userSuggestions.length" class="user-suggestions">
                <button
                  v-for="u in userSuggestions"
                  :key="u.username"
                  type="button"
                  class="user-suggestion-item"
                  @mousedown.prevent
                  @click="selectUser(u)"
                >
                  <span class="user-suggestion-avatar">{{ u.username[0].toUpperCase() }}</span>
                  <span class="user-suggestion-name">{{ u.username }}</span>
                </button>
              </div>
            </div>
          </div>
          <div v-if="createError" class="auth-error" style="margin:0">{{ createError }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="closeCreate">Cancel</button>
          <button
            class="btn btn-primary"
            :disabled="creating || (selectedUsers.length === 0 && !userSearchQuery.trim()) || (createIsGroup && !createTitle.trim())"
            @click="createChat"
          >
            {{ creating ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Poll form modal -->
    <PollForm
      v-if="showPollForm"
      @close="showPollForm = false"
      @submit="submitPoll"
    />

    <ScheduledMessagesModal
      v-if="showScheduledList"
      :items="scheduledMessages"
      @close="showScheduledList = false"
      @updated="onScheduledUpdated"
      @deleted="onScheduledDeleted"
    />

    <SchedulePickerModal
      v-if="showSchedulePicker"
      @close="showSchedulePicker = false"
      @submit="onSchedulePicked"
    />
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, nextTick, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api'
import UserAvatar from '../components/UserAvatar.vue'
import AudioPlayer from '../components/AudioPlayer.vue'
import ImageLightbox from '../components/ImageLightbox.vue'
import UserProfileModal from '../components/UserProfileModal.vue'
import EmojiPicker from '../components/EmojiPicker.vue'
import GroupProfileModal from '../components/GroupProfileModal.vue'
import OnlineUsersPanel from '../components/OnlineUsersPanel.vue'
import PollMessage from '../components/PollMessage.vue'
import PollForm from '../components/PollForm.vue'
import GlobalSearchPanel from '../components/GlobalSearchPanel.vue'
import ScheduledMessagesModal from '../components/ScheduledMessagesModal.vue'
import SchedulePickerModal from '../components/SchedulePickerModal.vue'

const route = useRoute()
const router = useRouter()
const chatId = computed(() => route.params.chatId)

const me = ref(null)
const chat = ref(null)
const participants = ref([])
const sidebarChats = ref([])

// ── Mute ─────────────────────────────────────────────────────────────────────
const mutedChats = ref(new Set(JSON.parse(localStorage.getItem('mutedChats') || '[]')))

function isMuted(id) { return mutedChats.value.has(id) }

function toggleMute(id) {
  const s = new Set(mutedChats.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  mutedChats.value = s
  localStorage.setItem('mutedChats', JSON.stringify([...s]))
}

// ── Tab title ─────────────────────────────────────────────────────────────────
const totalUnread = computed(() =>
  sidebarChats.value.reduce((sum, c) => {
    if (mutedChats.value.has(c.id)) return sum
    return sum + (c.unread_count || 0)
  }, 0)
)

watch(totalUnread, n => {
  document.title = n > 0 ? `(${n}) RealtimeChat` : 'RealtimeChat'
}, { immediate: true })

const peerDeliveredId = ref(null)
const peerReadId = ref(null)

const messages = ref([])
const nextCursor = ref(null)
const hasMore = ref(false)
const loadingMore = ref(false)
const chatLoading = ref(false)
const showScrollBtn = ref(false)
const unreadWhileScrolled = ref(0)
const input = ref('')
const editingId = ref(null)
const editingText = ref('')
const replyingTo = ref(null)
const highlightedId = ref(null)
const newMessageIds = ref(new Set())
const busy = ref(false)
const error = ref('')

const showCreate = ref(false)
const createIsGroup = ref(false)
const createTitle = ref('')
const createError = ref('')
const creating = ref(false)
const selectedUsers = ref([])
const userSearchQuery = ref('')
const userSuggestions = ref([])
const showSuggestions = ref(false)
let createSearchDebounce = null

const typingUser = ref('')
let typingTimeout = null
const sidebarTypingMap = ref({})
const sidebarTypingTimers = {}
let typingDebounce = null

const listEl = ref(null)
const composerEl = ref(null)
const fileInputEl = ref(null)
const emojiButtonEl = ref(null)
const emojiPickerPos = ref({ bottom: 70, left: '50%', transform: 'translateX(-50%)' })
const pendingFiles = ref([]) // [{ url, type, name, previewUrl }]
const uploading = ref(false)
const dragging = ref(false)
let dragCounter = 0

const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

const profileUsername = ref(null)
const sidebarHidden = ref(window.innerWidth < 640)
const composerError = ref('')
const recording = ref(false)
const aiMessages = ref([])
const aiLoading = ref(false)

const pinnedMessages = ref([])
const pinnedIndex = ref(0)

const showGroupProfile = ref(false)
const showOnlinePanel = ref(false)
const globalSearchOpen = ref(false)
const onlineUsers = ref([])
const draftMap = ref({})
const showEmojiPicker = ref(false)
const showAttachMenu = ref(false)
const attachBtnEl = ref(null)
const attachMenuPos = ref({ bottom: 0, left: 0 })
const showPollForm = ref(false)

const scheduledMessages = ref([])
const showScheduledList = ref(false)
const showSchedulePicker = ref(false)
const showSendMenu = ref(false)
const sendBtnEl = ref(null)
const sendMenuPos = ref({ bottom: 0, right: 0 })

const searchOpen = ref(false)
const searchQuery = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const searchInputEl = ref(null)
let searchDebounce = null
const reactionPickerMsgId = ref(null)
const reactionPickerPos = ref({ x: 0, y: 0 })
const showFullReactionPicker = ref(false)

const forwardingMsg = ref(null)
const showForwardModal = ref(false)

const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '😡', '🔥', '👎']

// ─── Mobile message interactions ──────────────────────────────────
const swipeMsgId = ref(null)    // message id whose bubble is being swiped
const msgSwipeX = ref(0)        // current X translate offset (px)
const msgSwipeDone = ref(false) // true during spring-back transition
const mobileMenu = ref(null)    // { msg, rawX, rawY, x, y, adjusted } or null
const mobileMenuEl = ref(null)  // ref to the rendered menu DOM element

let _msgSwipeId = null          // non-reactive; tracks swipe in touchmove handler
let _msgSwipeStartX = 0
let _msgSwipeStartY = 0
let _msgSwipeDecided = null     // null | 'h' | 'v'
let _msgLongPressTimer = null
let _msgLongPressTriggered = false
const recordingTime = ref(0)
let mediaRecorder = null
let recordingChunks = []
let recordingStream = null
let recordingTimer = null
let sendLongPressTimer = null
let sendLongPressTriggered = false
let es = null
let chatSseStopped = false
let chatSseDelay = 1000
let chatSseTimer = null
let chatSseGen = 0
let pingInterval = null
let onlineUsersInterval = null
let pinnedNavLock = false
let pinnedNavLockTimer = null

// ─── computed ────────────────────────────────────────────────────
const isAiChat = computed(() => chatId.value === 'ai')
const chatTitle = computed(() => isAiChat.value ? 'AI Assistant' : (chat.value?.display_name || 'Chat'))
const isGroup = computed(() => !!chat.value?.is_group)
const isOwner = computed(() => chat.value?.my_role === 'OWNER')
const displayMessages = computed(() => isAiChat.value ? aiMessages.value : messages.value)

const peerUser = computed(() => {
  if (isGroup.value) return null
  return participants.value.find(p => !p.is_me) || null
})

function isUserOnline(user) {
  if (!user?.last_seen_at) return false
  return (Date.now() - new Date(user.last_seen_at).getTime()) < 65000
}

const isPeerOnline = computed(() => peerUser.value ? isUserOnline(peerUser.value) : false)
const onlineParticipantsCount = computed(() => participants.value.filter(p => isUserOnline(p)).length)
const canPin = computed(() => !isAiChat.value && (isOwner.value || !isGroup.value))
const currentPinned = computed(() => pinnedMessages.value[pinnedIndex.value] || null)

const grouped = computed(() => {
  const groups = []
  for (const m of displayMessages.value) {
    const key = dayKey(m.created_at)
    const last = groups[groups.length - 1]
    if (!last || last.key !== key) {
      groups.push({ key, title: formatDateHeader(m.created_at), items: [m] })
    } else {
      last.items.push(m)
    }
  }
  return groups
})

function getAttachments(m) {
  if (m.attachments?.length) return m.attachments
  if (m.attachment_url) return [{ url: m.attachment_url, type: m.attachment_type, name: m.attachment_name }]
  return []
}

const allImages = computed(() => {
  const imgs = []
  for (const m of displayMessages.value) {
    if (m.deleted_at) continue
    for (const a of getAttachments(m)) {
      if (a.type === 'image') imgs.push(a.url)
    }
  }
  return imgs
})

function openLightbox(url) {
  const idx = allImages.value.indexOf(url)
  lightboxIndex.value = idx >= 0 ? idx : 0
  lightboxOpen.value = true
}

// ─── helpers ─────────────────────────────────────────────────────
function openUrl(url) { window.open(url, '_blank') }
function myId() { return me.value?.username || '' }
function isMine(m) { return m.sender === myId() }
function isEditing(m) { return editingId.value === m.id }

function idLE(a, b) {
  if (!a || !b) return false
  return String(a) <= String(b)
}

function dayKey(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return 'unknown'
  return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`
}

function formatTime(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
}

function formatTimeShort(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const now = new Date()
  if (d.toDateString() === now.toDateString()) {
    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')
  }
  return d.toLocaleDateString('en', { month: 'short', day: 'numeric' })
}

function formatDateHeader(iso) {
  const d = new Date(iso)
  if (isNaN(d)) return ''
  const now = new Date()
  const diffDays = Math.floor((now - d) / 86400000)
  if (diffDays === 0) return 'Today'
  if (diffDays === 1) return 'Yesterday'
  return d.toLocaleDateString('en', { year: 'numeric', month: 'long', day: 'numeric' })
}

function formatRelative(iso) {
  if (!iso) return 'a while ago'
  const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (diff < 60) return 'just now'
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

function saveDraft(id, text) {
  if (!id || id === 'ai') return
  if (text) {
    localStorage.setItem(`draft:${id}`, text)
    draftMap.value = { ...draftMap.value, [id]: text }
  } else {
    localStorage.removeItem(`draft:${id}`)
    const m = { ...draftMap.value }
    delete m[id]
    draftMap.value = m
  }
}

function loadDraft(id) {
  if (!id || id === 'ai') return ''
  return localStorage.getItem(`draft:${id}`) || ''
}

function sidebarPreview(c) {
  const draft = draftMap.value[c.id]
  if (draft) return draft
  const lm = c.last_message
  if (!lm) return 'No messages'
  const isMe = lm.sender_username === me.value?.username
  const prefix = c.is_group
    ? (isMe ? 'You: ' : (lm.sender_username ? lm.sender_username + ': ' : ''))
    : (isMe ? 'You: ' : '')
  if (lm.type === 'poll') return prefix + '📊 Poll'
  if (lm.attachments?.length) {
    const imgs = lm.attachments.filter(a => /\.(jpe?g|png|gif|webp)(\?|$)/i.test(a.url || ''))
    if (imgs.length > 1) return prefix + imgs.length + ' photos'
    if (imgs.length === 1) return prefix + 'Photo'
    const vids = lm.attachments.filter(a => /\.(mp4|webm|mov|avi)(\?|$)/i.test(a.url || ''))
    if (vids.length) return prefix + 'Video'
    return prefix + 'File'
  }
  if (lm.content) return prefix + lm.content
  if (lm.attachment_type === 'audio') return prefix + 'Voice message'
  if (lm.attachment_type === 'image') return prefix + 'Photo'
  if (lm.attachment_type === 'video') return prefix + 'Video'
  if (lm.attachment_url) return prefix + 'File'
  return prefix || 'No messages'
}

// ─── scrolling ────────────────────────────────────────────────────
function isNearBottom(thresholdPx = 100) {
  const el = listEl.value
  if (!el) return true
  return el.scrollHeight - el.scrollTop - el.clientHeight < thresholdPx
}

async function scrollToBottom() {
  await nextTick()
  if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight
}

function scrollToBottomFab() {
  scrollToBottom()
  unreadWhileScrolled.value = 0
}

// ─── online users ─────────────────────────────────────────────────
async function loadOnlineUsers() {
  try {
    onlineUsers.value = await api.listOnlineUsers()
  } catch {}
}

// ─── data loading ─────────────────────────────────────────────────
async function load() {
  if (isAiChat.value) {
    chat.value = { display_name: 'AI Assistant', is_group: false, my_role: 'MEMBER' }
    participants.value = []
    await scrollToBottom()
    return
  }
  chatLoading.value = true
  try {
    const [chatData, msgData] = await Promise.all([
      api.getChat(chatId.value),
      api.listMessages(chatId.value),
    ])
    chat.value = chatData
    participants.value = chatData.participants || []
    pinnedMessages.value = chatData.pinned_messages || []
    pinnedIndex.value = Math.max(0, (chatData.pinned_messages || []).length - 1)
    messages.value = msgData.items || []
    nextCursor.value = msgData.next_cursor || null
    hasMore.value = !!msgData.next_cursor
    peerDeliveredId.value = msgData.peer_delivered_message_id || null
    peerReadId.value = msgData.peer_read_message_id || null
  } catch {
    chatLoading.value = false
    router.push('/')
    return
  }
  chatLoading.value = false
  const last = messages.value[messages.value.length - 1]
  if (last) await api.markDelivered(chatId.value, last.id).catch(() => {})
  await scrollToBottom()
  loadScheduled()
}

async function loadScheduled() {
  if (isAiChat.value) { scheduledMessages.value = []; return }
  try {
    const data = await api.listScheduledMessages(chatId.value)
    scheduledMessages.value = data.items || []
  } catch { scheduledMessages.value = [] }
}

async function openSchedulePicker() {
  if (isAiChat.value) return
  const text = input.value.trim()
  const atts = pendingFiles.value.slice()
  if (!text && !atts.length) {
    composerError.value = 'Type a message first to schedule it'
    return
  }
  composerError.value = ''
  showSchedulePicker.value = true
}

function onSendTouchStart() {
  sendLongPressTriggered = false
  sendLongPressTimer = setTimeout(() => {
    sendLongPressTriggered = true
    if (sendBtnEl.value) {
      const rect = sendBtnEl.value.getBoundingClientRect()
      sendMenuPos.value = { bottom: window.innerHeight - rect.top + 8, right: window.innerWidth - rect.right }
    }
    showSendMenu.value = true
  }, 500)
}

function onSendTouchEnd(e) {
  clearTimeout(sendLongPressTimer)
  if (sendLongPressTriggered) {
    e.preventDefault()
    sendLongPressTriggered = false
  }
}

async function onSchedulePicked(isoTime) {
  showSchedulePicker.value = false
  const text = input.value.trim()
  const atts = pendingFiles.value.slice()
  const replyId = replyingTo.value?.id ?? null
  try {
    await api.createScheduledMessage(chatId.value, {
      content: text,
      scheduledAt: isoTime,
      replyToId: replyId,
      attachments: atts,
    })
    input.value = ''
    replyingTo.value = null
    pendingFiles.value = []
    await loadScheduled()
  } catch (e) {
    composerError.value = e.message
  }
}

function onScheduledUpdated(updated) {
  const i = scheduledMessages.value.findIndex(s => s.id === updated.id)
  if (i !== -1) {
    scheduledMessages.value = scheduledMessages.value
      .map((s, idx) => idx === i ? updated : s)
      .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))
  }
}

function onScheduledDeleted(id) {
  scheduledMessages.value = scheduledMessages.value.filter(s => s.id !== id)
  if (!scheduledMessages.value.length) showScheduledList.value = false
}

async function loadMore() {
  if (loadingMore.value || !hasMore.value || !nextCursor.value) return
  loadingMore.value = true
  const el = listEl.value
  const prevScrollHeight = el ? el.scrollHeight : 0
  try {
    const data = await api.listMessages(chatId.value, { before: nextCursor.value, limit: 50 })
    const older = data.items || []
    if (older.length) {
      messages.value = [...older, ...messages.value]
      nextCursor.value = data.next_cursor || null
      hasMore.value = !!data.next_cursor
      await nextTick()
      if (el) el.scrollTop = el.scrollHeight - prevScrollHeight
    }
  } catch {}
  finally { loadingMore.value = false }
}

function onScroll() {
  if (listEl.value && listEl.value.scrollTop < 120 && hasMore.value && !loadingMore.value) {
    loadMore()
  }
  updatePinnedIndexFromScroll()
  if (listEl.value) {
    const distFromBottom = listEl.value.scrollHeight - listEl.value.scrollTop - listEl.value.clientHeight
    showScrollBtn.value = distFromBottom > 200
    if (distFromBottom <= 200) unreadWhileScrolled.value = 0
  }
}

async function loadSidebarChats() {
  try {
    const data = await api.listChats()
    sidebarChats.value = data.items || []
  } catch {}
}

function clearCurrentChatUnread() {
  const idx = sidebarChats.value.findIndex(c => c.id === chatId.value)
  if (idx === -1) return
  sidebarChats.value = sidebarChats.value.map((c, i) => i === idx ? { ...c, unread_count: 0 } : c)
}

// ─── receipts ─────────────────────────────────────────────────────
async function markReadIfPossible() {
  if (document.visibilityState !== 'visible') return
  const last = messages.value[messages.value.length - 1]
  if (!last) return
  await api.markRead(chatId.value, last.id).catch(() => {})
}

// ─── typing ───────────────────────────────────────────────────────
function onTyping() {
  clearTimeout(typingDebounce)
  typingDebounce = setTimeout(() => {
    api.sendTyping(chatId.value).catch(() => {})
  }, 400)
}

// ─── SSE ─────────────────────────────────────────────────────────
function stopChatSse() {
  chatSseStopped = true
  chatSseGen++
  clearTimeout(chatSseTimer)
  if (es) { es.close(); es = null }
}

async function connectSse() {
  chatSseStopped = false
  chatSseDelay = 1000
  const gen = ++chatSseGen

  const attempt = async () => {
    if (chatSseStopped || chatSseGen !== gen) return
    try {
      const sub = await api.subscribeAllChats()
      if (chatSseStopped || chatSseGen !== gen) return
      const params = new URLSearchParams()
      for (const t of sub.topics || []) params.append('topic', t)
      const source = new EventSource(`/.well-known/mercure?${params.toString()}`, { withCredentials: true })
      es = source

      source.onopen = () => { chatSseDelay = 1000 }
      source.onmessage = async (evt) => {
        const payload = JSON.parse(evt.data)
        const d = payload.data

        if (payload.type === 'chat.created') {
          stopChatSse()
          await loadSidebarChats()
          await connectSse()
          return
        }

        if (payload.type === 'chat.deleted') {
          const deletedId = d?.chat_id
          if (deletedId) {
            sidebarChats.value = sidebarChats.value.filter(c => c.id !== deletedId)
            if (chatId.value === deletedId) router.push('/')
          }
          return
        }

        if (payload.type === 'chat.updated') {
          const updatedId = d?.chat_id
          const newTitle = d?.title
          if (updatedId && newTitle) {
            const idx = sidebarChats.value.findIndex(c => c.id === updatedId)
            if (idx !== -1) sidebarChats.value = sidebarChats.value.map((c, i) => i === idx ? { ...c, display_name: newTitle, title: newTitle } : c)
            if (chatId.value === updatedId) chat.value = { ...chat.value, title: newTitle, display_name: newTitle }
          }
          return
        }

        if (payload.type === 'chat.member_removed') {
          if (d?.chat_id === chatId.value && d?.user_id) {
            participants.value = participants.value.filter(p => p.id !== d.user_id)
          }
          return
        }

        // Typing indicator — handled before per-chat filter so all chats get it
        if (payload.type === 'user.typing') {
          const tChatId = d.chatId ?? d.chat_id
          if (tChatId && d.username !== myId()) {
            sidebarTypingMap.value = { ...sidebarTypingMap.value, [tChatId]: d.username }
            clearTimeout(sidebarTypingTimers[tChatId])
            sidebarTypingTimers[tChatId] = setTimeout(() => {
              const m = { ...sidebarTypingMap.value }
              delete m[tChatId]
              sidebarTypingMap.value = m
            }, 3000)
            if (tChatId === chatId.value) {
              typingUser.value = d.username
              clearTimeout(typingTimeout)
              typingTimeout = setTimeout(() => { typingUser.value = '' }, 3000)
            }
          }
          return
        }

        // Sidebar update for every message.created regardless of chat
        if (payload.type === 'message.created') {
          // Clear typing for this chat since message was sent
          if (sidebarTypingMap.value[d.chat_id]) {
            clearTimeout(sidebarTypingTimers[d.chat_id])
            const m = { ...sidebarTypingMap.value }
            delete m[d.chat_id]
            sidebarTypingMap.value = m
          }
          if (d.chat_id === chatId.value) { typingUser.value = ''; clearTimeout(typingTimeout) }
          const fromMe = d.sender === myId()
          const idx = sidebarChats.value.findIndex(c => c.id === d.chat_id)
          if (idx !== -1) {
            const cur = sidebarChats.value[idx]
            const arr = sidebarChats.value.map((c, i) => i === idx ? {
              ...cur,
              last_message: {
                content: d.content,
                created_at: d.created_at,
                sender_username: d.sender,
                type: d.type ?? 'text',
                attachment_url: d.attachment_url ?? null,
                attachment_type: d.attachment_type ?? null,
                attachments: d.attachments ?? null,
              },
              unread_count: (d.chat_id === chatId.value || fromMe) ? cur.unread_count : (cur.unread_count || 0) + 1,
            } : c)
            arr.sort((a, b) => {
              const ta = a.last_message?.created_at ? Date.parse(a.last_message.created_at) : Date.parse(a.created_at || 0)
              const tb = b.last_message?.created_at ? Date.parse(b.last_message.created_at) : Date.parse(b.created_at || 0)
              return tb - ta
            })
            sidebarChats.value = arr
          }
        }

        // All other logic only applies to the currently open chat
        const eventChatId = d?.chat_id ?? d?.chatId
        if (eventChatId && eventChatId !== chatId.value) return

        const shouldStick = isNearBottom()

        if (payload.type === 'message.created') {
          if (!messages.value.find(m => m.id === d.id)) {
            messages.value.push(d)
            newMessageIds.value = new Set([...newMessageIds.value, d.id])
            setTimeout(() => {
              newMessageIds.value = new Set([...newMessageIds.value].filter(x => x !== d.id))
            }, 300)
          }
          if (showScrollBtn.value && d.sender !== myId()) {
            unreadWhileScrolled.value++
          }
          await api.markDelivered(chatId.value, d.id).catch(() => {})
          await markReadIfPossible()
          if (shouldStick) await scrollToBottom()
          if (d.sender === myId() && scheduledMessages.value.length) loadScheduled()
          return
        }
        if (payload.type === 'message.edited') {
          const i = messages.value.findIndex(m => m.id === d.id)
          if (i !== -1) Object.assign(messages.value[i], d)
          return
        }
        if (payload.type === 'message.deleted') {
          const i = messages.value.findIndex(m => m.id === d.id)
          if (i !== -1) messages.value[i].deleted_at = d.deleted_at
          return
        }
        if (payload.type === 'chat.delivered') {
          if (d?.user && d.user !== myId()) {
            const id = d.last_delivered_message_id
            if (id && (!peerDeliveredId.value || String(id) > String(peerDeliveredId.value))) peerDeliveredId.value = id
          }
          return
        }
        if (payload.type === 'chat.read') {
          if (d?.user && d.user !== myId()) {
            const id = d.last_read_message_id
            if (id && (!peerReadId.value || String(id) > String(peerReadId.value))) peerReadId.value = id
          }
          return
        }
        if (payload.type === 'message.reaction') {
          const i = messages.value.findIndex(m => m.id === d.message_id)
          if (i !== -1) messages.value[i].reactions = d.reactions
          return
        }
        if (payload.type === 'message.pinned') {
          const currentId = currentPinned.value?.id
          pinnedMessages.value = d.pinned_messages || []
          pinnedIndex.value = stablePinnedIndex(pinnedMessages.value, currentId)
          return
        }
        if (payload.type === 'poll.voted') {
          const i = messages.value.findIndex(m => m.id === d.message_id)
          if (i !== -1 && d.poll) {
            const myV = messages.value[i].poll?.my_votes
            messages.value[i] = { ...messages.value[i], poll: { ...d.poll, my_votes: myV ?? d.poll.my_votes } }
          }
          return
        }
      }
      source.onerror = () => {
        source.close()
        if (chatSseStopped || chatSseGen !== gen) return
        chatSseTimer = setTimeout(attempt, chatSseDelay)
        chatSseDelay = Math.min(chatSseDelay * 2, 30000)
      }
    } catch {
      if (chatSseStopped || chatSseGen !== gen) return
      chatSseTimer = setTimeout(attempt, chatSseDelay)
      chatSseDelay = Math.min(chatSseDelay * 2, 30000)
    }
  }

  await attempt()
}

// ─── attach menu / send menu ──────────────────────────────────────
function toggleAttachMenu() {
  if (!showAttachMenu.value && attachBtnEl.value) {
    const rect = attachBtnEl.value.getBoundingClientRect()
    attachMenuPos.value = { bottom: window.innerHeight - rect.top + 8, left: rect.left }
  }
  showAttachMenu.value = !showAttachMenu.value
}

function openSendMenu() {
  if (sendBtnEl.value) {
    const rect = sendBtnEl.value.getBoundingClientRect()
    sendMenuPos.value = {
      bottom: window.innerHeight - rect.top + 8,
      right: window.innerWidth - rect.right,
    }
  }
  showSendMenu.value = !showSendMenu.value
}

// ─── emoji / reactions ────────────────────────────────────────────
function toggleEmojiPicker() {
  closeReactionPicker()
  if (showEmojiPicker.value) {
    showEmojiPicker.value = false
    return
  }
  if (emojiButtonEl.value) {
    const rect = emojiButtonEl.value.getBoundingClientRect()
    const PICKER_W = 310
    const center = rect.left + rect.width / 2
    const clampedLeft = Math.max(PICKER_W / 2 + 8, Math.min(center, window.innerWidth - PICKER_W / 2 - 8))
    emojiPickerPos.value = {
      left: clampedLeft,
      bottom: window.innerHeight - rect.top + 8,
    }
  }
  showEmojiPicker.value = true
}

function onEmojiSelect(emoji) {
  if (showFullReactionPicker.value && reactionPickerMsgId.value) {
    doToggleReaction(reactionPickerMsgId.value, emoji)
    closeReactionPicker()
    return
  }
  input.value += emoji
  composerEl.value?.focus()
}

function openReactionPicker(msgId, event) {
  if (reactionPickerMsgId.value === msgId) {
    closeReactionPicker()
    return
  }
  const rect = event.currentTarget.getBoundingClientRect()
  // Clamp x so the picker (which uses translate(-50%)) never overflows the
  // viewport on narrow mobile screens. Half the quick-picker width ≈ 144px.
  const halfW = 144
  const clampedX = Math.max(halfW + 8, Math.min(rect.left, window.innerWidth - halfW - 8))
  reactionPickerPos.value = { x: clampedX, y: rect.top }
  reactionPickerMsgId.value = msgId
  showFullReactionPicker.value = false
}

function closeReactionPicker() {
  reactionPickerMsgId.value = null
  showFullReactionPicker.value = false
}

async function doToggleReaction(msgId, emoji) {
  const msg = messages.value.find(m => m.id === msgId)
  if (!msg) return
  navigator.vibrate?.(10)

  const myUser = me.value?.username
  const existing = (msg.reactions || []).find(r => r.emoji === emoji)
  const isMineReaction = existing?.users?.includes(myUser)

  if (!msg.reactions) msg.reactions = []
  if (isMineReaction) {
    const idx = msg.reactions.findIndex(r => r.emoji === emoji)
    if (idx !== -1) {
      const newUsers = msg.reactions[idx].users.filter(u => u !== myUser)
      if (newUsers.length === 0) msg.reactions.splice(idx, 1)
      else msg.reactions[idx] = { ...msg.reactions[idx], count: newUsers.length, users: newUsers }
    }
  } else {
    const idx = msg.reactions.findIndex(r => r.emoji === emoji)
    if (idx !== -1) {
      msg.reactions[idx] = { ...msg.reactions[idx], count: msg.reactions[idx].count + 1, users: [...msg.reactions[idx].users, myUser] }
    } else {
      msg.reactions.push({ emoji, count: 1, users: [myUser] })
    }
  }

  await api.toggleReaction(chatId.value, msgId, emoji).catch(() => {})
}

function handleReactionClick(m, emoji, event) {
  const el = event.currentTarget
  el.classList.remove('toggling')
  void el.offsetWidth  // force reflow to restart animation on rapid clicks
  el.classList.add('toggling')
  setTimeout(() => el.classList.remove('toggling'), 300)
  doToggleReaction(m.id, emoji)
}

function isMyReaction(msg, emoji) {
  if (!msg) return false
  return (msg.reactions || []).find(r => r.emoji === emoji)?.users?.includes(me.value?.username) ?? false
}

// ─── search ───────────────────────────────────────────────────────
function toggleSearch() {
  if (searchOpen.value) {
    closeSearch()
  } else {
    searchOpen.value = true
    nextTick(() => searchInputEl.value?.focus())
  }
}

function closeSearch() {
  searchOpen.value = false
  searchQuery.value = ''
  searchResults.value = []
  searchLoading.value = false
  clearTimeout(createSearchDebounce)
}

function onSearchInput() {
  clearTimeout(createSearchDebounce)
  const q = searchQuery.value.trim()
  if (q.length < 2) {
    searchResults.value = []
    searchLoading.value = false
    return
  }
  searchLoading.value = true
  searchDebounce = setTimeout(() => doSearch(q), 300)
}

async function doSearch(q) {
  try {
    const data = await api.searchMessages(chatId.value, q)
    if (searchQuery.value.trim() !== q) return
    searchResults.value = data.items || []
  } catch {
    searchResults.value = []
  } finally {
    searchLoading.value = false
  }
}

async function jumpToSearchResult(id) {
  closeSearch()
  await jumpToMessage(id)
}

// ─── polls ───────────────────────────────────────────────────────
async function submitPoll(pollData) {
  showPollForm.value = false
  try {
    await api.sendPoll(chatId.value, pollData)
  } catch (e) { composerError.value = e.message }
}

async function doVotePoll(messageId, optionId) {
  const msg = messages.value.find(m => m.id === messageId)
  if (!msg?.poll) return
  const poll = msg.poll
  const myVotes = poll.my_votes || []

  // Optimistic update
  let newMyVotes
  if (poll.multiple_answers) {
    newMyVotes = myVotes.includes(optionId)
      ? myVotes.filter(v => v !== optionId)
      : [...myVotes, optionId]
  } else {
    newMyVotes = myVotes.includes(optionId) ? [] : [optionId]
  }

  const updatedOptions = poll.options.map(o => {
    const wasVoted = myVotes.includes(o.id)
    const willBeVoted = newMyVotes.includes(o.id)
    if (wasVoted === willBeVoted) return o
    const delta = willBeVoted ? 1 : -1
    return { ...o, votes: Math.max(0, o.votes + delta) }
  })
  const totalDelta = newMyVotes.length - myVotes.length
  const i = messages.value.findIndex(m => m.id === messageId)
  if (i !== -1) {
    messages.value[i] = {
      ...messages.value[i],
      poll: { ...poll, options: updatedOptions, my_votes: newMyVotes, total_votes: poll.total_votes + totalDelta },
    }
  }

  try {
    const res = await api.votePoll(chatId.value, messageId, [optionId])
    const j = messages.value.findIndex(m => m.id === messageId)
    if (j !== -1 && res.poll) {
      messages.value[j] = { ...messages.value[j], poll: res.poll }
    }
  } catch (e) {
    // revert
    const j = messages.value.findIndex(m => m.id === messageId)
    if (j !== -1) messages.value[j] = { ...messages.value[j], poll }
    composerError.value = e.message
  }
}

// ─── actions ──────────────────────────────────────────────────────
async function send() {
  if (isAiChat.value) {
    const text = input.value.trim()
    if (!text || aiLoading.value) return
    input.value = ''
    composerEl.value?.focus()
    await sendToAi(text)
    return
  }
  if (editingId.value) {
    await saveEdit()
    return
  }
  const text = input.value.trim()
  const atts = pendingFiles.value.slice()
  if (!text && !atts.length) return
  const replyId = replyingTo.value?.id ?? null
  input.value = ''
  replyingTo.value = null
  pendingFiles.value = []
  composerEl.value?.focus()
  navigator.vibrate?.(10)
  await api.sendMessage(chatId.value, text, replyId, atts).catch(() => {})
}

async function sendToAi(text) {
  aiMessages.value.push({
    id: 'u-' + Date.now(),
    sender: me.value?.username || 'You',
    sender_avatar_url: me.value?.avatar_url || null,
    content: text,
    created_at: new Date().toISOString(),
  })
  await scrollToBottom()
  aiLoading.value = true

  const msgs = aiMessages.value.map(m => ({
    role: m.sender === 'AI Assistant' ? 'assistant' : 'user',
    content: m.content,
  }))

  try {
    const result = await api.aiChat(msgs)
    aiMessages.value.push({
      id: 'ai-' + Date.now(),
      sender: 'AI Assistant',
      sender_avatar_url: null,
      content: result.reply,
      created_at: new Date().toISOString(),
    })
  } catch (err) {
    aiMessages.value.push({
      id: 'ai-err-' + Date.now(),
      sender: 'AI Assistant',
      sender_avatar_url: null,
      content: '⚠ ' + (err.message || 'Something went wrong'),
      created_at: new Date().toISOString(),
    })
  } finally {
    aiLoading.value = false
    await scrollToBottom()
  }
}

async function processFile(file) {
  if (!file) return
  uploading.value = true
  try {
    const result = await api.uploadFile(file)
    pendingFiles.value.push({
      url: result.url,
      type: result.type,
      name: result.name || file.name,
      previewUrl: result.type === 'image' ? result.url : null,
    })
  } catch (err) {
    error.value = err.message
  } finally {
    uploading.value = false
  }
}

async function onFileSelect(e) {
  const files = Array.from(e.target.files)
  e.target.value = ''
  for (const f of files) await processFile(f)
}

function cancelFile(idx) {
  if (idx === undefined) pendingFiles.value = []
  else pendingFiles.value.splice(idx, 1)
}

function onDragEnter(e) {
  if (isAiChat.value) return
  if (!e.dataTransfer?.types.includes('Files')) return
  dragCounter++
  dragging.value = true
}

function onDragLeave() {
  dragCounter--
  if (dragCounter <= 0) {
    dragCounter = 0
    dragging.value = false
  }
}

async function onDrop(e) {
  dragCounter = 0
  dragging.value = false
  if (isAiChat.value) return
  const files = Array.from(e.dataTransfer?.files || [])
  for (const f of files) await processFile(f)
}

function fmtRecTime(s) {
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}

function releaseStream() {
  recordingStream?.getTracks().forEach(t => t.stop())
  recordingStream = null
}

async function startRecording() {
  composerError.value = ''
  if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
    composerError.value = 'Voice recording requires HTTPS — works on localhost or via a secure URL'
    return
  }
  let stream
  try {
    stream = await navigator.mediaDevices.getUserMedia({ audio: true })
  } catch (e) {
    composerError.value = e.name === 'NotAllowedError'
      ? 'Microphone permission denied'
      : `Microphone error: ${e.message}`
    return
  }
  recordingStream = stream

  const mimeType = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', '']
    .find(t => !t || MediaRecorder.isTypeSupported(t))
  try {
    mediaRecorder = new MediaRecorder(recordingStream, mimeType ? { mimeType } : {})
  } catch (e) {
    composerError.value = `Recording not supported: ${e.message}`
    releaseStream()
    return
  }
  recordingChunks = []
  mediaRecorder.ondataavailable = e => { if (e.data.size > 0) recordingChunks.push(e.data) }
  mediaRecorder.start()
  recording.value = true
  recordingTime.value = 0
  recordingTimer = setInterval(() => recordingTime.value++, 1000)
}

function cancelRecording() {
  clearInterval(recordingTimer)
  recording.value = false
  recordingTime.value = 0
  if (mediaRecorder) {
    mediaRecorder.onstop = null
    if (mediaRecorder.state !== 'inactive') mediaRecorder.stop()
  }
  recordingChunks = []
  releaseStream()
}

async function sendRecording() {
  clearInterval(recordingTimer)
  recording.value = false

  if (!mediaRecorder) return

  const blob = await new Promise(resolve => {
    mediaRecorder.onstop = () => {
      const type = recordingChunks[0]?.type || 'audio/webm'
      resolve(new Blob(recordingChunks, { type }))
    }
    if (mediaRecorder.state !== 'inactive') mediaRecorder.stop()
    else {
      const type = recordingChunks[0]?.type || 'audio/webm'
      resolve(new Blob(recordingChunks, { type }))
    }
  })

  releaseStream()
  recordingChunks = []
  recordingTime.value = 0

  if (blob.size === 0) return

  uploading.value = true
  try {
    const type = blob.type || 'audio/webm'
    const ext = type.includes('ogg') ? 'ogg' : 'webm'
    const file = new File([blob], `voice-${Date.now()}.${ext}`, { type })
    const result = await api.uploadFile(file)
    await api.sendMessage(chatId.value, '', null, [{ url: result.url, type: 'audio', name: 'Voice message' }]).catch(() => {})
  } catch (err) {
    error.value = err.message
  } finally {
    uploading.value = false
  }
}

function onKeydown(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    send()
  }
  if (e.key === 'Escape') {
    if (editingId.value) cancelEdit()
    else cancelReply()
  }
}

function openGroupProfile() {
  if (!isGroup.value) return
  showGroupProfile.value = true
}

function onGroupUpdated(patch) {
  chat.value = { ...chat.value, ...patch }
  const idx = sidebarChats.value.findIndex(c => c.id === chatId.value)
  if (idx !== -1) sidebarChats.value = sidebarChats.value.map((c, i) => i === idx ? { ...c, ...patch } : c)
}

function onGroupMembersChanged(newParticipants) {
  participants.value = newParticipants
}

function openUserProfile(username) {
  if (!username || username === me.value?.username) return
  profileUsername.value = username
}

function onGlobalSearchSelect({ chatId: targetChatId, messageId }) {
  globalSearchOpen.value = false
  if (targetChatId === chatId.value) {
    jumpToMessage(messageId)
    if (route.query.highlight) {
      router.replace({ query: { ...route.query, highlight: undefined } })
    }
    return
  }
  router.push({ path: `/chats/${targetChatId}`, query: { highlight: messageId } })
}

function startReply(m) {
  replyingTo.value = { id: m.id, sender: m.sender, content: m.content, deleted: !!m.deleted_at }
  composerEl.value?.focus()
}

async function jumpToMessage(id) {
  // Load older pages until the message appears in the DOM
  let attempts = 0
  while (!document.getElementById(`msg-${id}`) && hasMore.value && attempts < 8) {
    await loadMore()
    attempts++
  }
  await nextTick()
  const el = document.getElementById(`msg-${id}`)
  if (!el) return
  el.scrollIntoView({ behavior: 'smooth', block: 'center' })
  highlightedId.value = id
  setTimeout(() => { highlightedId.value = null }, 1800)
}

async function maybeJumpFromQuery() {
  const id = route.query.highlight
  if (!id || isAiChat.value) return
  await jumpToMessage(id)
  const { highlight, ...rest } = route.query
  router.replace({ query: rest })
}

function cancelReply() {
  replyingTo.value = null
}

function startForward(m) {
  forwardingMsg.value = m
  showForwardModal.value = true
}

async function doForward(targetChatId) {
  const m = forwardingMsg.value
  if (!m) return
  showForwardModal.value = false
  forwardingMsg.value = null
  try {
    await api.sendForwardedMessage(targetChatId, m.id)
    if (targetChatId !== chatId.value) {
      router.push(`/chats/${targetChatId}`)
    }
  } catch (e) {
    error.value = e.message
  }
}

function startEdit(m) {
  if (m.deleted_at) return
  editingId.value = m.id
  editingText.value = m.content || ''
  input.value = m.content || ''
  nextTick(() => {
    composerEl.value?.focus()
    const el = composerEl.value
    if (el) el.setSelectionRange(el.value.length, el.value.length)
  })
}

function cancelEdit() {
  editingId.value = null
  editingText.value = ''
  input.value = loadDraft(chatId.value)
}

async function saveEdit() {
  const text = input.value.trim()
  if (!text) return
  const id = editingId.value
  busy.value = true
  try {
    const updated = await api.editMessage(chatId.value, id, text)
    const i = messages.value.findIndex(x => x.id === id)
    if (i !== -1) Object.assign(messages.value[i], updated)
    cancelEdit()
  } catch (e) {
    error.value = e.message
  } finally { busy.value = false }
}

async function removeMessage(m) {
  if (!confirm('Delete this message?')) return
  busy.value = true
  try {
    await api.deleteMessage(chatId.value, m.id)
    const i = messages.value.findIndex(x => x.id === m.id)
    if (i !== -1) messages.value[i].deleted_at = new Date().toISOString()
    if (editingId.value === m.id) cancelEdit()
  } catch (e) { error.value = e.message }
  finally { busy.value = false }
}

async function doPin(messageId) {
  try {
    const currentId = currentPinned.value?.id
    const res = await api.pinMessage(chatId.value, messageId)
    pinnedMessages.value = res.pinned_messages || []
    pinnedIndex.value = stablePinnedIndex(pinnedMessages.value, currentId)
  } catch (e) { error.value = e.message }
}

function pinnedPreview(pm) {
  if (pm.content) return pm.content
  const atts = pm.attachments || []
  if (atts.length > 0) {
    const images = atts.filter(a => a.type === 'image')
    if (images.length) return images.length === 1 ? 'Image' : `${images.length} images`
    const audios = atts.filter(a => a.type === 'audio')
    if (audios.length) return 'Voice message'
    const videos = atts.filter(a => a.type === 'video')
    if (videos.length) return 'Video message'
    return atts[0]?.name || 'File'
  }
  if (pm.attachment_type === 'image') return 'Image'
  if (pm.attachment_type === 'audio') return 'Voice message'
  if (pm.attachment_type === 'video') return 'Video message'
  if (pm.attachment_url) return pm.attachment_name || 'File'
  return ''
}

function lockPinnedNav() {
  pinnedNavLock = true
  clearTimeout(pinnedNavLockTimer)
  pinnedNavLockTimer = setTimeout(() => { pinnedNavLock = false }, 1500)
}

function stablePinnedIndex(newList, currentId) {
  if (!newList.length) return 0
  if (currentId) {
    const idx = newList.findIndex(p => p.id === currentId)
    if (idx >= 0) return idx
  }
  return Math.min(pinnedIndex.value, newList.length - 1)
}

async function clickPinnedBar() {
  const pm = currentPinned.value
  if (!pm) return
  const len = pinnedMessages.value.length
  const nextIdx = len > 1 ? (pinnedIndex.value - 1 + len) % len : pinnedIndex.value
  lockPinnedNav()
  await jumpToMessage(pm.id)
  pinnedIndex.value = nextIdx
}

async function navigatePin(delta) {
  const len = pinnedMessages.value.length
  if (!len) return
  const nextIdx = (pinnedIndex.value + delta + len) % len
  pinnedIndex.value = nextIdx
  lockPinnedNav()
  const pm = pinnedMessages.value[nextIdx]
  if (pm) await jumpToMessage(pm.id)
}

function updatePinnedIndexFromScroll() {
  if (pinnedNavLock || !pinnedMessages.value.length || !listEl.value) return
  const container = listEl.value
  const bottom = container.getBoundingClientRect().bottom
  // Find the newest (highest index) pin whose element hasn't scrolled past the bottom fold.
  // When scrolling UP, a pin is "scrolled past" once its element exits below the container.
  let targetIdx = 0
  for (let i = pinnedMessages.value.length - 1; i >= 0; i--) {
    const el = document.getElementById(`msg-${pinnedMessages.value[i].id}`)
    if (!el) continue
    if (el.getBoundingClientRect().top <= bottom) {
      targetIdx = i
      break
    }
  }
  if (pinnedIndex.value !== targetIdx) pinnedIndex.value = targetIdx
}

async function deleteChat() {
  if (!confirm('Delete this chat permanently?')) return
  const id = chatId.value
  await api.deleteChat(id)
  sidebarChats.value = sidebarChats.value.filter(c => c.id !== id)
  router.push('/')
}

async function logout() {
  await api.logout()
  router.push('/login')
}

function openCreate() {
  selectedUsers.value = []
  userSearchQuery.value = ''
  userSuggestions.value = []
  showSuggestions.value = false
  createTitle.value = ''
  createError.value = ''
  createIsGroup.value = false
  clearTimeout(createSearchDebounce)
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
  selectedUsers.value = []
  userSearchQuery.value = ''
  userSuggestions.value = []
  showSuggestions.value = false
  clearTimeout(createSearchDebounce)
}

function onUserSearchInput() {
  clearTimeout(createSearchDebounce)
  const q = userSearchQuery.value.trim()
  if (!q) {
    userSuggestions.value = []
    showSuggestions.value = false
    return
  }
  createSearchDebounce = setTimeout(async () => {
    const results = await api.searchUsers(q)
    const selectedNames = new Set(selectedUsers.value.map(u => u.username))
    userSuggestions.value = results.filter(u => !selectedNames.has(u.username))
    showSuggestions.value = userSuggestions.value.length > 0
  }, 300)
}

function onSearchBlur() {
  setTimeout(() => { showSuggestions.value = false }, 150)
}

async function selectUser(user) {
  if (createIsGroup.value) {
    selectedUsers.value.push(user)
    userSearchQuery.value = ''
    userSuggestions.value = []
    showSuggestions.value = false
  } else {
    selectedUsers.value = [user]
    userSearchQuery.value = ''
    userSuggestions.value = []
    showSuggestions.value = false
    await createChat()
  }
}

function removeUser(username) {
  selectedUsers.value = selectedUsers.value.filter(u => u.username !== username)
}

async function createChat() {
  createError.value = ''
  creating.value = true
  const isGroup = createIsGroup.value
  try {
    const typedIdentifier = userSearchQuery.value.trim()
    const participants = selectedUsers.value.length > 0
      ? selectedUsers.value.map(u => u.username)
      : typedIdentifier ? [typedIdentifier] : []
    const newChat = await api.createChat({
      isGroup,
      title: createTitle.value.trim(),
      participants,
    })
    closeCreate()
    // Optimistic insert — появляется мгновенно без перезагрузки
    if (!sidebarChats.value.find(c => c.id === newChat.id)) {
      sidebarChats.value.unshift({
        id: newChat.id,
        is_group: newChat.is_group,
        title: newChat.title,
        display_name: newChat.title || newChat.peer_username || (isGroup ? 'Group chat' : 'New chat'),
        last_message: null,
        unread_count: 0,
        created_at: new Date().toISOString(),
      })
    }
    router.push(`/chats/${newChat.id}`)
  } catch (e) { createError.value = e.message }
  finally { creating.value = false }
}

watch(showOnlinePanel, (val) => { if (val) loadOnlineUsers() })

// ─── Smart repositioning of mobile long-press menu ────────────────
// Runs after Vue updates the DOM so we can measure actual menu dimensions.
watch(mobileMenu, (newVal) => {
  if (!newVal || newVal.adjusted) return
  if (!mobileMenuEl.value) return
  const el = mobileMenuEl.value
  const menuW = el.offsetWidth
  const menuH = el.offsetHeight
  const vw = window.innerWidth
  const vh = window.innerHeight
  const EDGE = 8
  const SAFE_BOTTOM = 20   // covers env(safe-area-inset-bottom) on most devices
  const tx = newVal.rawX
  const ty = newVal.rawY
  // Prefer opening below touch, fall back to above when not enough room
  let y = ty + 16
  if (y + menuH > vh - EDGE - SAFE_BOTTOM) y = ty - menuH - 16
  y = Math.max(EDGE, Math.min(y, vh - menuH - EDGE - SAFE_BOTTOM))
  // Center horizontally on touch point, clamp to edges
  let x = tx - menuW / 2
  x = Math.max(EDGE, Math.min(x, vw - menuW - EDGE))
  mobileMenu.value = { ...newVal, x, y, adjusted: true }
}, { flush: 'post' })

watch(input, (val) => {
  if (!editingId.value && !isAiChat.value) saveDraft(chatId.value, val)
})

// ─── watcher: reloads chat data when chatId changes (same component reuse) ───
watch(chatId, async (newId, oldId) => {
  if (!newId || newId === oldId) return
  if (window.innerWidth < 640) sidebarHidden.value = true
  stopChatSse()
  clearTimeout(typingTimeout)
  clearTimeout(typingDebounce)
  messages.value = []
  nextCursor.value = null
  hasMore.value = false
  loadingMore.value = false
  chatLoading.value = false
  showScrollBtn.value = false
  unreadWhileScrolled.value = 0
  chat.value = null
  participants.value = []
  pinnedMessages.value = []
  pinnedIndex.value = 0
  peerDeliveredId.value = null
  peerReadId.value = null
  typingUser.value = ''
  cancelEdit()
  cancelReply()
  cancelFile()
  cancelRecording()
  dragCounter = 0
  dragging.value = false
  lightboxOpen.value = false
  error.value = ''
  composerError.value = ''
  showEmojiPicker.value = false
  closeReactionPicker()
  showGroupProfile.value = false
  scheduledMessages.value = []
  showScheduledList.value = false
  showSchedulePicker.value = false
  showSendMenu.value = false
  closeSearch()
  globalSearchOpen.value = false
  showForwardModal.value = false
  forwardingMsg.value = null
  mobileMenu.value = null
  swipeMsgId.value = null
  msgSwipeX.value = 0
  msgSwipeDone.value = false
  clearTimeout(_msgLongPressTimer)
  await load()
  await connectSse()
  if (!isAiChat.value) {
    clearCurrentChatUnread()
    await markReadIfPossible()
  }
  await maybeJumpFromQuery()
}, { immediate: false })

// ─── Mobile message swipe-to-reply & long-press menu ─────────────
function onMsgTouchStart(e, m) {
  if (window.innerWidth > 640 || isAiChat.value) return
  if (m.type === 'system' || m.deleted_at) return
  if (mobileMenu.value) return

  _msgSwipeId = m.id
  _msgSwipeStartX = e.touches[0].clientX
  _msgSwipeStartY = e.touches[0].clientY
  _msgSwipeDecided = null
  _msgLongPressTriggered = false
  swipeMsgId.value = m.id
  msgSwipeX.value = 0
  msgSwipeDone.value = false

  _msgLongPressTimer = setTimeout(() => {
    _msgLongPressTriggered = true
    navigator.vibrate?.(30)
    // Cancel any in-progress swipe
    _msgSwipeId = null
    swipeMsgId.value = null
    msgSwipeX.value = 0
    // Store raw touch position; watcher will clamp to viewport after render
    const tx = e.touches[0].clientX
    const ty = e.touches[0].clientY
    mobileMenu.value = { msg: m, rawX: tx, rawY: ty, x: -9999, y: -9999, adjusted: false }
  }, 500)
}

function onMsgTouchEnd(e, m) {
  clearTimeout(_msgLongPressTimer)
  if (_msgLongPressTriggered) return

  const swipeXValue = msgSwipeX.value
  const hadSwipe = _msgSwipeId !== null

  _msgSwipeId = null
  _msgSwipeDecided = null
  msgSwipeDone.value = true
  msgSwipeX.value = 0

  setTimeout(() => {
    if (swipeMsgId.value === m.id) {
      swipeMsgId.value = null
      msgSwipeDone.value = false
    }
  }, 280)

  if (hadSwipe && swipeXValue >= 60) {
    startReply(m)
  }
}

function onMsgTouchCancel() {
  clearTimeout(_msgLongPressTimer)
  _msgLongPressTriggered = false
  _msgSwipeId = null
  _msgSwipeDecided = null
  msgSwipeDone.value = true
  msgSwipeX.value = 0
  setTimeout(() => {
    swipeMsgId.value = null
    msgSwipeDone.value = false
  }, 280)
}

// Must be registered with { passive: false } to call preventDefault.
function _msgAreaTouchMove(e) {
  if (window.innerWidth > 640 || !_msgSwipeId || _msgLongPressTriggered) return

  const touch = e.touches[0]
  const dx = touch.clientX - _msgSwipeStartX
  const dy = touch.clientY - _msgSwipeStartY

  if (_msgSwipeDecided === null) {
    if (Math.abs(dx) < 6 && Math.abs(dy) < 6) return
    _msgSwipeDecided = Math.abs(dx) > Math.abs(dy) ? 'h' : 'v'
  }

  if (_msgSwipeDecided === 'v') {
    // Vertical scroll: cancel swipe and long press, let scroll proceed naturally
    clearTimeout(_msgLongPressTimer)
    _msgSwipeId = null
    swipeMsgId.value = null
    return
  }

  // Confirmed horizontal swipe: cancel long press, prevent page scroll
  clearTimeout(_msgLongPressTimer)
  e.preventDefault()
  e.stopPropagation() // prevents sidebar swipe from also triggering

  // Rightward only, rubber-band resistance after 60 px
  const raw = Math.max(0, dx)
  const x = raw <= 60 ? raw : 60 + (raw - 60) * 0.25
  msgSwipeX.value = Math.min(x, 80)
}

function closeMobileMenu() {
  mobileMenu.value = null
}

function copyMessageText(m) {
  if (m.content) navigator.clipboard?.writeText(m.content).catch(() => {})
  closeMobileMenu()
}

// ─── Mobile swipe gesture to open / close sidebar ────────────────
// Strategy: use a non-passive touchmove listener (registered imperatively
// below) to call preventDefault() the moment a horizontal swipe is confirmed.
// This stops iOS from firing touchcancel (which it does when a scrollable
// container intercepts the touch) and guarantees touchend fires.
const SWIPE_THRESHOLD = 50  // px to commit to sidebar toggle

let swipeStartX  = 0
let swipeStartY  = 0
let swipeDecided = null  // null | 'h' | 'v' — direction locked after 5 px
let swipeInScrollZone = false  // true when touch started inside a real scroll container

// Walk up the DOM to check if el is inside a vertically scrollable container.
// Called once per touch sequence (at touchstart) so getComputedStyle cost is fine.
function _isInScrollZone(el) {
  while (el && el !== document.documentElement) {
    const oy = window.getComputedStyle(el).overflowY
    if ((oy === 'auto' || oy === 'scroll') && el.scrollHeight > el.clientHeight) return true
    el = el.parentElement
  }
  return false
}

function onSwipeTouchStart(e) {
  const touch = e.touches[0]
  swipeStartX  = touch.clientX
  swipeStartY  = touch.clientY
  swipeDecided = null
  swipeInScrollZone = _isInScrollZone(e.target)
  // No left-edge guard needed: _msgAreaTouchMove calls e.stopPropagation()
  // on confirmed message swipe-to-reply, so those touches never reach
  // _swipeTouchMove and cannot accidentally open the sidebar.
}

// Must be registered with { passive: false } so preventDefault() is allowed.
// Horizontal moves always get preventDefault so the browser never native-scrolls
// the page left/right (there is no horizontal scroll target — only our JS sidebar).
// Vertical moves get preventDefault outside scroll zones to stop iOS page bounce.
function _swipeTouchMove(e) {
  if (window.innerWidth > 640) return
  if (swipeDecided !== null) {
    if (swipeDecided === 'h') e.preventDefault()
    else if (!swipeInScrollZone) e.preventDefault()
    return
  }
  const dx = Math.abs(e.touches[0].clientX - swipeStartX)
  const dy = Math.abs(e.touches[0].clientY - swipeStartY)
  // Before direction is locked: if there is any horizontal component that clearly
  // dominates the vertical, prevent immediately so iOS can't start a native scroll.
  if (dx < 5 && dy < 5) {
    if (dx > 0 && dx >= dy) e.preventDefault()
    return
  }
  swipeDecided = dx > dy ? 'h' : 'v'
  if (swipeDecided === 'h') e.preventDefault()
  else if (!swipeInScrollZone) e.preventDefault()
}

function onSwipeTouchEnd(e) {
  const wasH = swipeDecided === 'h'
  swipeDecided = null
  if (window.innerWidth > 640 || !wasH) return
  const dx = e.changedTouches[0].clientX - swipeStartX
  if (sidebarHidden.value) {
    if (dx > SWIPE_THRESHOLD) sidebarHidden.value = false
  } else {
    if (dx < -SWIPE_THRESHOLD) sidebarHidden.value = true
  }
}

function onSwipeTouchCancel() { swipeDecided = null }

// ─── lifecycle ────────────────────────────────────────────────────
function onWindowResize() {
  if (window.innerWidth < 640 && !sidebarHidden.value) sidebarHidden.value = true
}

// Tracks the visible viewport height (shrinks when iOS keyboard opens).
// Sets --vvh on :root; .app-shell uses height:var(--vvh) so the shell
// shrinks with the keyboard, keeping the composer above it.
// Two-frame approach: first rAF sets --vvh, second rAF scrolls the
// messages list to the bottom (after layout recalculates with new height)
// so the last message stays visible above the composer.
let _vvhRafId = null
function updateVVH() {
  // Capture scroll state now, before the rAF changes clientHeight.
  const wasAtBottom = isNearBottom(200)
  if (_vvhRafId) cancelAnimationFrame(_vvhRafId)
  _vvhRafId = requestAnimationFrame(() => {
    _vvhRafId = null
    const vv = window.visualViewport
    const h = vv ? vv.height : window.innerHeight
    document.documentElement.style.setProperty('--vvh', `${h}px`)
    if ((window.scrollY !== 0 || window.scrollX !== 0) && window.scrollTo) {
      window.scrollTo(0, 0)
    }
    // After --vvh is set the layout reflows; a second rAF runs after that
    // reflow so scrollHeight reflects the new (shorter) messages-area height.
    if (wasAtBottom) {
      requestAnimationFrame(() => {
        if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight
      })
    }
  })
}

onMounted(async () => {
  [me.value] = await Promise.all([api.me()])
  await Promise.all([load(), loadSidebarChats()])
  const map = {}
  for (let i = 0; i < localStorage.length; i++) {
    const key = localStorage.key(i)
    if (key?.startsWith('draft:')) {
      const val = localStorage.getItem(key)
      if (val) map[key.slice(6)] = val
    }
  }
  draftMap.value = map
  input.value = loadDraft(chatId.value)
  await connectSse()
  if (!isAiChat.value) {
    clearCurrentChatUnread()
    await markReadIfPossible()
  }
  await maybeJumpFromQuery()
  // Disable browser pinch-zoom while in chat: ImageLightbox uses JS zoom instead.
  // Scoped to ChatView so /login, /register, /profile retain normal zoom.
  const _metaVP = document.querySelector('meta[name="viewport"]')
  if (_metaVP) _metaVP.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover'
  document.addEventListener('visibilitychange', markReadIfPossible)
  window.addEventListener('resize', onWindowResize)
  window.visualViewport?.addEventListener('resize', updateVVH)
  window.visualViewport?.addEventListener('scroll', updateVVH)
  document.addEventListener('touchmove', _swipeTouchMove, { passive: false })
  if (listEl.value) listEl.value.addEventListener('touchmove', _msgAreaTouchMove, { passive: false })
  updateVVH()
  api.ping().catch(() => {})
  loadOnlineUsers()
  pingInterval = setInterval(() => api.ping().catch(() => {}), 30000)
  onlineUsersInterval = setInterval(loadOnlineUsers, 15000)
})

onBeforeUnmount(() => {
  stopChatSse()
  if (pingInterval) clearInterval(pingInterval)
  if (onlineUsersInterval) clearInterval(onlineUsersInterval)
  clearTimeout(typingTimeout)
  clearTimeout(typingDebounce)
  document.removeEventListener('visibilitychange', markReadIfPossible)
  window.removeEventListener('resize', onWindowResize)
  window.visualViewport?.removeEventListener('resize', updateVVH)
  window.visualViewport?.removeEventListener('scroll', updateVVH)
  document.removeEventListener('touchmove', _swipeTouchMove)
  if (listEl.value) listEl.value.removeEventListener('touchmove', _msgAreaTouchMove)
  clearTimeout(_msgLongPressTimer)
  if (_vvhRafId) cancelAnimationFrame(_vvhRafId)
  // Restore default viewport so other routes can zoom normally.
  const _metaVP = document.querySelector('meta[name="viewport"]')
  if (_metaVP) _metaVP.content = 'width=device-width, initial-scale=1.0, viewport-fit=cover'
  cancelRecording()
  document.title = 'RealtimeChat'
})
</script>

<style scoped>
.user-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 8px;
}
.user-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px 2px 10px;
  background: var(--accent);
  color: #fff;
  border-radius: 12px;
  font-size: 13px;
}
.user-chip-remove {
  background: none;
  border: none;
  color: rgba(255,255,255,0.8);
  cursor: pointer;
  font-size: 15px;
  line-height: 1;
  padding: 0;
  display: flex;
  align-items: center;
}
.user-chip-remove:hover { color: #fff; }
.user-suggestions {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  right: 0;
  background: var(--bg-2);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
  z-index: 50;
  overflow: hidden;
  max-height: 220px;
  overflow-y: auto;
}
.user-suggestion-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 8px 12px;
  background: none;
  border: none;
  cursor: pointer;
  text-align: left;
  color: var(--text-1);
  font-size: 14px;
  transition: background 0.12s;
}
.user-suggestion-item:hover { background: var(--bg-3); }
.user-suggestion-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--accent);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}
.user-suggestion-name { font-weight: 500; }

.muted-icon {
  color: var(--text-3);
  flex-shrink: 0;
  display: block;
}
</style>
