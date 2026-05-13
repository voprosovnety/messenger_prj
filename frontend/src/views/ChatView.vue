<template>
  <div class="app-shell">
    <!-- Sidebar with chat list -->
    <aside class="sidebar" :class="{ 'sidebar-hidden': sidebarHidden }">
      <div class="sidebar-header">
        <div class="sidebar-logo">💬</div>
        <span class="sidebar-logo-text">RealtimeChat</span>
        <button class="online-indicator" :class="{ active: showOnlinePanel }" :title="showOnlinePanel ? 'Close' : 'Online users'" @click="showOnlinePanel = !showOnlinePanel">
          <span class="online-indicator-dot"></span>{{ onlineUsers.length }} online
        </button>
        <button class="btn-icon" title="New chat" @click="showCreate = true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <OnlineUsersPanel
        v-if="showOnlinePanel"
        :users="onlineUsers"
        @open-profile="openUserProfile"
        @close="showOnlinePanel = false"
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

        <p v-if="sidebarChats.length" class="chats-section-label">Conversations</p>
        <button
          v-for="c in sidebarChats"
          :key="c.id"
          class="chat-item"
          :class="{ active: c.id === chatId.value }"
          type="button"
          @click="router.push(`/chats/${c.id}`)"
        >
          <UserAvatar :username="c.display_name || c.id" :avatarUrl="c.avatar_url || null" size="md" />
          <div class="chat-item-info">
            <div class="chat-item-top">
              <span class="chat-item-name">{{ c.display_name || c.id }}</span>
              <span class="chat-item-time">{{ formatTimeShort(c.last_message?.created_at) }}</span>
            </div>
            <div class="chat-item-top" style="margin-top:1px">
              <span class="chat-item-preview">{{ sidebarPreview(c) }}</span>
              <span v-if="(c.unread_count || 0) > 0" class="unread-badge">{{ c.unread_count }}</span>
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

    <!-- Main chat area -->
    <div
      class="chat-area"
      @dragenter.prevent="onDragEnter"
      @dragover.prevent
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
      @click="showEmojiPicker = false; closeReactionPicker()"
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
            <span v-else-if="isGroup">{{ participants.length }} members</span>
          </div>
        </div>
        <div class="chat-header-actions">
          <button v-if="!isGroup" class="btn-icon" title="Delete chat" style="color:var(--danger)" @click="deleteChat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
          </button>
        </div>
      </div>

      <!-- Messages + Members panel wrapper -->
      <div style="display:flex;flex:1;min-height:0;overflow:hidden">
        <!-- Messages -->
        <div style="display:flex;flex-direction:column;flex:1;min-width:0;overflow:hidden">
          <div ref="listEl" class="messages-area" @scroll="onScroll">
            <div v-if="loadingMore" class="load-more-spinner">Loading…</div>
            <template v-for="g in grouped" :key="g.key">
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

                    <div class="message-bubble-outer" :class="{ 'editing-active': editingId === m.id }">
                        <!-- Actions -->
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
                          <button class="btn-icon" style="padding:4px 6px;border-radius:4px" title="React" @click.stop="openReactionPicker(m.id, $event)">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                          </button>
                        </div>
                        <div class="message-bubble" :class="{ deleted: !!m.deleted_at }">
                          <div v-if="m.reply_to" class="reply-quote" @click.stop="jumpToMessage(m.reply_to.id)">
                            <span class="reply-quote-sender">{{ m.reply_to.sender }}</span>
                            <span class="reply-quote-content">{{ m.reply_to.deleted ? 'Message deleted' : m.reply_to.content }}</span>
                          </div>
                          <span v-if="m.deleted_at" style="font-style:italic">Message deleted</span>
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
                        @click.stop="doToggleReaction(m.id, r.emoji)"
                      >{{ r.emoji }} {{ r.count }}</button>
                      <button v-if="!isAiChat && !m.deleted_at" class="reaction-add-btn" title="Add reaction" @click.stop="openReactionPicker(m.id, $event)">+</button>
                    </div>
                  </div>

                </div>
              </template>
            </template>
          </div>

          <!-- Typing indicator / inline error -->
          <div class="typing-indicator">
            <span v-if="isAiChat && aiLoading" class="ai-thinking">AI Assistant is thinking…</span>
            <span v-else-if="composerError" style="color:var(--danger);cursor:pointer" @click="composerError=''">⚠ {{ composerError }}</span>
            <span v-else-if="typingUser">{{ typingUser }} is typing…</span>
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

          <div class="composer">
            <!-- Normal mode -->
            <template v-if="!recording">
              <input ref="fileInputEl" type="file" multiple style="display:none" @change="onFileSelect" />
              <button v-if="!isAiChat" class="btn-icon composer-attach" title="Attach file" :disabled="uploading" @click="fileInputEl.click()">
                <svg v-if="!uploading" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
              </button>
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
              <button v-if="!isAiChat" class="btn-icon composer-mic" title="Record voice message" :disabled="uploading" @click="startRecording">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/></svg>
              </button>
              <button class="composer-send" :disabled="isAiChat ? (!input.trim() || aiLoading) : (!input.trim() && !pendingFiles.length)" @click="send">
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
              <button class="composer-send" title="Stop and send" @click="stopRecording">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><rect x="4" y="4" width="16" height="16" rx="2"/></svg>
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

    <!-- New chat modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
      <div class="modal">
        <div class="modal-header">
          <span class="modal-title">New conversation</span>
          <button class="btn-icon" @click="showCreate = false">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="modal-body">
          <div class="toggle-tabs">
            <div class="toggle-tab" :class="{ active: !createIsGroup }" @click="createIsGroup = false">Direct</div>
            <div class="toggle-tab" :class="{ active: createIsGroup }" @click="createIsGroup = true">Group</div>
          </div>
          <div v-if="createIsGroup">
            <label class="form-label">Title</label>
            <input v-model="createTitle" class="input" placeholder="Group name" />
          </div>
          <div>
            <label class="form-label">{{ createIsGroup ? 'Participants' : 'Username or email' }}</label>
            <input v-if="!createIsGroup" v-model="createParticipants" class="input" placeholder="friend@example.com" />
            <textarea v-else v-model="createParticipants" class="input" rows="3" placeholder="user1, user2" />
          </div>
          <div v-if="createError" class="auth-error" style="margin:0">{{ createError }}</div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-ghost" @click="showCreate = false">Cancel</button>
          <button class="btn btn-primary" :disabled="creating" @click="createChat">
            {{ creating ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
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

const route = useRoute()
const router = useRouter()
const chatId = computed(() => route.params.chatId)

const me = ref(null)
const chat = ref(null)
const participants = ref([])
const sidebarChats = ref([])

const peerDeliveredId = ref(null)
const peerReadId = ref(null)

const messages = ref([])
const nextCursor = ref(null)
const hasMore = ref(false)
const loadingMore = ref(false)
const input = ref('')
const editingId = ref(null)
const editingText = ref('')
const replyingTo = ref(null)
const highlightedId = ref(null)
const busy = ref(false)
const error = ref('')

const showCreate = ref(false)
const createIsGroup = ref(false)
const createTitle = ref('')
const createParticipants = ref('')
const createError = ref('')
const creating = ref(false)

const typingUser = ref('')
let typingTimeout = null
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

const showGroupProfile = ref(false)
const showOnlinePanel = ref(false)
const onlineUsers = ref([])
const showEmojiPicker = ref(false)
const reactionPickerMsgId = ref(null)
const reactionPickerPos = ref({ x: 0, y: 0 })
const showFullReactionPicker = ref(false)

const QUICK_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '😡', '🔥', '👎']
const recordingTime = ref(0)
let mediaRecorder = null
let recordingChunks = []
let recordingStream = null
let recordingTimer = null
let es = null
let chatSseStopped = false
let chatSseDelay = 1000
let chatSseTimer = null
let chatSseGen = 0
let pingInterval = null
let onlineUsersInterval = null

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

function sidebarPreview(c) {
  const lm = c.last_message
  if (!lm) return 'No messages'
  const prefix = c.is_group && lm.sender_username ? lm.sender_username + ': ' : (lm.sender_username === me.value?.username ? 'You: ' : '')
  if (lm.content) return prefix + lm.content
  if (lm.attachment_type === 'image') return prefix + '🖼 Photo'
  if (lm.attachment_type === 'video') return prefix + '🎬 Video'
  if (lm.attachment_type === 'audio') return prefix + '🎵 Audio'
  if (lm.attachment_url) return prefix + '📎 File'
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
  try {
    const [chatData, msgData] = await Promise.all([
      api.getChat(chatId.value),
      api.listMessages(chatId.value),
    ])
    chat.value = chatData
    participants.value = chatData.participants || []
    messages.value = msgData.items || []
    nextCursor.value = msgData.next_cursor || null
    hasMore.value = !!msgData.next_cursor
    peerDeliveredId.value = msgData.peer_delivered_message_id || null
    peerReadId.value = msgData.peer_read_message_id || null
  } catch {
    router.push('/')
    return
  }
  const last = messages.value[messages.value.length - 1]
  if (last) await api.markDelivered(chatId.value, last.id).catch(() => {})
  await scrollToBottom()
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

        // Sidebar update for every message.created regardless of chat
        if (payload.type === 'message.created') {
          const fromMe = d.sender === myId()
          const idx = sidebarChats.value.findIndex(c => c.id === d.chat_id)
          if (idx !== -1) {
            const cur = sidebarChats.value[idx]
            const arr = sidebarChats.value.map((c, i) => i === idx ? {
              ...cur,
              last_message: { content: d.content, created_at: d.created_at, sender_username: d.sender },
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
          if (!messages.value.find(m => m.id === d.id)) messages.value.push(d)
          await api.markDelivered(chatId.value, d.id).catch(() => {})
          await markReadIfPossible()
          if (shouldStick) await scrollToBottom()
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
        if (payload.type === 'user.typing') {
          if (d.username !== myId()) {
            typingUser.value = d.username
            clearTimeout(typingTimeout)
            typingTimeout = setTimeout(() => { typingUser.value = '' }, 3000)
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

// ─── emoji / reactions ────────────────────────────────────────────
function toggleEmojiPicker() {
  closeReactionPicker()
  if (showEmojiPicker.value) {
    showEmojiPicker.value = false
    return
  }
  if (emojiButtonEl.value) {
    const rect = emojiButtonEl.value.getBoundingClientRect()
    emojiPickerPos.value = {
      left: rect.left + rect.width / 2,
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
  reactionPickerPos.value = { x: rect.left, y: rect.top }
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

function isMyReaction(msg, emoji) {
  if (!msg) return false
  return (msg.reactions || []).find(r => r.emoji === emoji)?.users?.includes(me.value?.username) ?? false
}

// ─── actions ──────────────────────────────────────────────────────
async function send() {
  if (isAiChat.value) {
    const text = input.value.trim()
    if (!text || aiLoading.value) return
    input.value = ''
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
  mediaRecorder.onstop = handleRecordingStop
  mediaRecorder.start()
  recording.value = true
  recordingTime.value = 0
  recordingTimer = setInterval(() => recordingTime.value++, 1000)
}

async function stopRecording() {
  clearInterval(recordingTimer)
  recording.value = false
  mediaRecorder?.stop()
  releaseStream()
}

function cancelRecording() {
  clearInterval(recordingTimer)
  recording.value = false
  recordingTime.value = 0
  if (mediaRecorder) {
    mediaRecorder.onstop = null
    mediaRecorder.stop()
  }
  recordingChunks = []
  releaseStream()
}

async function handleRecordingStop() {
  const type = recordingChunks[0]?.type || 'audio/webm'
  const blob = new Blob(recordingChunks, { type })
  recordingChunks = []
  uploading.value = true
  try {
    const ext = type.includes('ogg') ? 'ogg' : 'webm'
    const file = new File([blob], `voice-${Date.now()}.${ext}`, { type })
    const result = await api.uploadFile(file)
    pendingFile.value = { url: result.url, type: 'audio', name: 'Voice message', previewUrl: null }
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

function cancelReply() {
  replyingTo.value = null
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
  input.value = ''
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

async function createChat() {
  createError.value = ''
  creating.value = true
  try {
    const parts = createParticipants.value.split(/[\s,\n]+/).map(s => s.trim()).filter(Boolean)
    const newChat = await api.createChat({
      isGroup: createIsGroup.value,
      title: createTitle.value.trim(),
      participants: parts,
    })
    showCreate.value = false
    createTitle.value = ''
    createParticipants.value = ''
    // Optimistic insert — появляется мгновенно без перезагрузки
    if (!sidebarChats.value.find(c => c.id === newChat.id)) {
      sidebarChats.value.unshift({
        id: newChat.id,
        is_group: newChat.is_group,
        title: newChat.title,
        display_name: newChat.title || newChat.peer_username || (createIsGroup.value ? 'Group chat' : 'New chat'),
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
  chat.value = null
  participants.value = []
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
  await load()
  await connectSse()
  if (!isAiChat.value) {
    clearCurrentChatUnread()
    await markReadIfPossible()
  }
}, { immediate: false })

// ─── lifecycle ────────────────────────────────────────────────────
function onWindowResize() {
  if (window.innerWidth < 640 && !sidebarHidden.value) sidebarHidden.value = true
}

onMounted(async () => {
  [me.value] = await Promise.all([api.me()])
  await Promise.all([load(), loadSidebarChats()])
  await connectSse()
  if (!isAiChat.value) {
    clearCurrentChatUnread()
    await markReadIfPossible()
  }
  document.addEventListener('visibilitychange', markReadIfPossible)
  window.addEventListener('resize', onWindowResize)
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
  cancelRecording()
})
</script>
