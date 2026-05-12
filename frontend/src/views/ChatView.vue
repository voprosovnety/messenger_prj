<template>
  <div class="app-shell">
    <!-- Sidebar with chat list -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">💬</div>
        <span class="sidebar-logo-text">RealtimeChat</span>
        <button class="btn-icon" title="New chat" @click="showCreate = true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </div>

      <div class="sidebar-chats">
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
          <UserAvatar :username="c.display_name || c.id" size="md" />
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
    <div class="chat-area">
      <!-- Header -->
      <div class="chat-header">
        <UserAvatar :username="chatTitle" size="md" />
        <div class="chat-header-info">
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
          <button v-if="isGroup" class="btn-icon" :title="showMembersPanel ? 'Hide members' : 'Show members'" @click="showMembersPanel = !showMembersPanel">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </button>
          <button v-if="isGroup && !isOwner" class="btn-icon" title="Leave chat" style="color:var(--danger)" @click="leaveChat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
          <button v-if="canDeleteChat" class="btn-icon" title="Delete chat" style="color:var(--danger)" @click="deleteChat">
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
                  class="message-row"
                  :class="{
                    own: isMine(m),
                    'same-sender': idx > 0 && g.items[idx-1].sender === m.sender && !g.items[idx-1].deleted_at
                  }"
                >
                  <!-- Avatar slot (others only) -->
                  <div class="message-avatar-slot">
                    <UserAvatar
                      v-if="!isMine(m) && (idx === g.items.length-1 || g.items[idx+1]?.sender !== m.sender)"
                      :username="m.sender"
                      :avatarUrl="m.sender_avatar_url"
                      size="sm"
                    />
                  </div>

                  <div class="message-bubble-wrap">
                    <!-- Sender name (group chats, others only) -->
                    <div v-if="isGroup && !isMine(m) && (idx === 0 || g.items[idx-1].sender !== m.sender)" class="message-sender-name">
                      {{ m.sender }}
                    </div>

                    <!-- Editing mode -->
                    <template v-if="isEditing(m)">
                      <textarea
                        v-model="editingText"
                        class="input"
                        style="width:100%;min-width:280px"
                        rows="2"
                        :disabled="busy"
                        @keydown.esc.prevent="cancelEdit"
                        @keydown.enter.exact.prevent="saveEdit(m)"
                      />
                      <div style="display:flex;gap:6px;margin-top:4px;justify-content:flex-end">
                        <button class="btn btn-ghost" style="font-size:13px;padding:5px 10px" :disabled="busy" @click="cancelEdit">Cancel</button>
                        <button class="btn btn-primary" style="font-size:13px;padding:5px 10px" :disabled="busy || !editingText.trim()" @click="saveEdit(m)">Save</button>
                      </div>
                    </template>

                    <!-- Normal bubble -->
                    <template v-else>
                      <div class="message-bubble-outer">
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
                        </div>
                        <div class="message-bubble" :class="{ deleted: !!m.deleted_at }">
                          <div v-if="m.reply_to" class="reply-quote">
                            <span class="reply-quote-sender">{{ m.reply_to.sender }}</span>
                            <span class="reply-quote-content">{{ m.reply_to.deleted ? 'Message deleted' : m.reply_to.content }}</span>
                          </div>
                          <span v-if="m.deleted_at" style="font-style:italic">Message deleted</span>
                          <template v-else>
                            <span v-if="m.content" style="white-space:pre-wrap;word-break:break-word">{{ m.content }}</span>
                            <div v-if="m.attachment_url" class="attachment">
                              <img v-if="m.attachment_type === 'image'" :src="m.attachment_url" class="attachment-img" @click="openUrl(m.attachment_url)" />
                              <video v-else-if="m.attachment_type === 'video'" :src="m.attachment_url" controls class="attachment-video"></video>
                              <AudioPlayer v-else-if="m.attachment_type === 'audio'" :src="m.attachment_url" />
                              <a v-else :href="m.attachment_url" target="_blank" download class="attachment-file">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                {{ m.attachment_name || 'Download file' }}
                              </a>
                            </div>
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
                    </template>
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

          <!-- Pending file preview -->
          <div v-if="pendingFile" class="reply-bar">
            <img v-if="pendingFile.previewUrl" :src="pendingFile.previewUrl" style="height:40px;width:40px;object-fit:cover;border-radius:4px;flex-shrink:0" />
            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            <span class="reply-bar-text" style="font-size:13px">{{ pendingFile.name }}</span>
            <button class="btn-icon" style="padding:4px" @click="cancelFile">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Composer -->
          <div class="composer">
            <!-- Normal mode -->
            <template v-if="!recording">
              <input ref="fileInputEl" type="file" style="display:none" @change="onFileSelect" />
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
              <button v-if="!isAiChat" class="btn-icon composer-mic" title="Record voice message" :disabled="uploading" @click="startRecording">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="8" y1="22" x2="16" y2="22"/></svg>
              </button>
              <button class="composer-send" :disabled="isAiChat ? (!input.trim() || aiLoading) : (!input.trim() && !pendingFile)" @click="send">
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
        </div>

        <!-- Members sidebar (group chats) -->
        <div v-if="isGroup && showMembersPanel" class="members-panel">
          <div class="members-panel-header">Members</div>

          <div v-if="isOwner" style="padding:10px 12px;border-bottom:1px solid var(--border)">
            <div v-if="!showRename">
              <button class="btn btn-ghost" style="width:100%;font-size:13px" @click="startRename">✏️ Rename group</button>
            </div>
            <div v-else style="display:flex;flex-direction:column;gap:8px">
              <input
                v-model="renameInput"
                class="input"
                placeholder="Group name"
                style="font-size:13px"
                :disabled="renaming"
                @keydown.enter.prevent="saveRename"
                @keydown.esc.prevent="showRename = false"
              />
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost" style="flex:1;font-size:13px" @click="showRename = false">Cancel</button>
                <button class="btn btn-primary" style="flex:1;font-size:13px" :disabled="renaming || !renameInput.trim()" @click="saveRename">Save</button>
              </div>
            </div>
          </div>

          <div v-if="isOwner" style="padding:10px 12px">
            <div v-if="!showAddMember">
              <button class="btn btn-ghost" style="width:100%;font-size:13px" @click="showAddMember = true">+ Add member</button>
            </div>
            <div v-else style="display:flex;flex-direction:column;gap:8px">
              <input
                v-model="participantInput"
                class="input"
                placeholder="username or email"
                style="font-size:13px"
                :disabled="busy"
                @keydown.enter.prevent="addParticipant"
              />
              <div style="display:flex;gap:6px">
                <button class="btn btn-ghost" style="flex:1;font-size:13px" @click="showAddMember = false">Cancel</button>
                <button class="btn btn-primary" style="flex:1;font-size:13px" :disabled="busy || !participantInput.trim()" @click="addParticipant">Add</button>
              </div>
            </div>
          </div>

          <div v-for="p in participants" :key="p.id" class="member-item">
            <UserAvatar :username="p.username" :avatarUrl="p.avatar_url" :isOnline="isUserOnline(p)" size="sm" />
            <div class="member-item-info">
              <div class="member-item-name">{{ p.username }}<span v-if="p.is_me" style="color:var(--text-3);font-weight:400;font-size:12px"> (you)</span></div>
              <div class="member-item-role">{{ p.role.toLowerCase() }}</div>
            </div>
            <div class="member-item-actions">
              <button
                v-if="isOwner && !p.is_me && p.role !== 'OWNER'"
                class="btn-icon"
                style="color:var(--danger)"
                title="Remove"
                :disabled="busy"
                @click="removeParticipant(p.id)"
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </div>
          </div>

          <div v-if="error" style="padding:8px 12px;font-size:12px;color:var(--danger)">{{ error }}</div>
        </div>
      </div>
    </div>

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
const busy = ref(false)
const error = ref('')

const showMembersPanel = ref(false)
const showAddMember = ref(false)
const participantInput = ref('')
const showRename = ref(false)
const renameInput = ref('')
const renaming = ref(false)

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
const pendingFile = ref(null) // { url, type, name, previewUrl }
const uploading = ref(false)
const composerError = ref('')
const recording = ref(false)
const aiMessages = ref([])
const aiLoading = ref(false)
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

// ─── computed ────────────────────────────────────────────────────
const isAiChat = computed(() => chatId.value === 'ai')
const chatTitle = computed(() => isAiChat.value ? 'AI Assistant' : (chat.value?.display_name || 'Chat'))
const isGroup = computed(() => !!chat.value?.is_group)
const isOwner = computed(() => chat.value?.my_role === 'OWNER')
const canDeleteChat = computed(() => !isGroup.value || isOwner.value)
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

// ─── actions ──────────────────────────────────────────────────────
async function send() {
  if (isAiChat.value) {
    const text = input.value.trim()
    if (!text || aiLoading.value) return
    input.value = ''
    await sendToAi(text)
    return
  }
  const text = input.value.trim()
  const att = pendingFile.value
  if (!text && !att) return
  const replyId = replyingTo.value?.id ?? null
  input.value = ''
  replyingTo.value = null
  pendingFile.value = null
  await api.sendMessage(chatId.value, text, replyId, att).catch(() => {})
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

async function onFileSelect(e) {
  const file = e.target.files[0]
  e.target.value = ''
  if (!file) return
  uploading.value = true
  try {
    const result = await api.uploadFile(file)
    pendingFile.value = {
      url: result.url,
      type: result.type,
      name: result.name || file.name,
      previewUrl: result.type === 'image' ? result.url : null,
    }
  } catch (err) {
    error.value = err.message
  } finally {
    uploading.value = false
  }
}

function cancelFile() {
  pendingFile.value = null
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
  if (e.key === 'Escape') cancelReply()
}

function startReply(m) {
  replyingTo.value = { id: m.id, sender: m.sender, content: m.content, deleted: !!m.deleted_at }
  composerEl.value?.focus()
}

function cancelReply() {
  replyingTo.value = null
}

function startEdit(m) {
  if (m.deleted_at) return
  editingId.value = m.id
  editingText.value = m.content || ''
}

function cancelEdit() {
  editingId.value = null
  editingText.value = ''
}

async function saveEdit(m) {
  const text = editingText.value.trim()
  if (!text) return
  busy.value = true
  try {
    const updated = await api.editMessage(chatId.value, m.id, text)
    const i = messages.value.findIndex(x => x.id === m.id)
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

function startRename() {
  renameInput.value = chat.value?.title || ''
  showRename.value = true
}

async function saveRename() {
  const title = renameInput.value.trim()
  if (!title) return
  renaming.value = true
  try {
    const res = await api.renameChat(chatId.value, title)
    chat.value = { ...chat.value, title: res.title, display_name: res.title }
    const idx = sidebarChats.value.findIndex(c => c.id === chatId.value)
    if (idx !== -1) sidebarChats.value = sidebarChats.value.map((c, i) => i === idx ? { ...c, display_name: res.title, title: res.title } : c)
    showRename.value = false
  } catch (e) { error.value = e.message }
  finally { renaming.value = false }
}

async function addParticipant() {
  const ident = participantInput.value.trim()
  if (!ident) return
  busy.value = true
  try {
    await api.addChatMember(chatId.value, ident)
    participantInput.value = ''
    showAddMember.value = false
    const data = await api.getChat(chatId.value)
    chat.value = data
    participants.value = data.participants || []
  } catch (e) { error.value = e.message }
  finally { busy.value = false }
}

async function removeParticipant(userId) {
  busy.value = true
  try {
    await api.removeChatMember(chatId.value, userId)
    const data = await api.getChat(chatId.value)
    chat.value = data
    participants.value = data.participants || []
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

async function leaveChat() {
  if (!confirm('Leave this chat?')) return
  try {
    await api.leaveChat(chatId.value)
    router.push('/')
  } catch (e) { error.value = e.message }
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

// ─── watcher: reloads chat data when chatId changes (same component reuse) ───
watch(chatId, async (newId, oldId) => {
  if (!newId || newId === oldId) return
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
  error.value = ''
  composerError.value = ''
  showMembersPanel.value = false
  showRename.value = false
  showAddMember.value = false
  await load()
  if (!isAiChat.value) {
    clearCurrentChatUnread()
    await connectSse()
    await markReadIfPossible()
  }
}, { immediate: false })

// ─── lifecycle ────────────────────────────────────────────────────
onMounted(async () => {
  [me.value] = await Promise.all([api.me()])
  await Promise.all([load(), loadSidebarChats()])
  if (!isAiChat.value) {
    clearCurrentChatUnread()
    await connectSse()
    await markReadIfPossible()
  }
  document.addEventListener('visibilitychange', markReadIfPossible)
  api.ping().catch(() => {})
  pingInterval = setInterval(() => api.ping().catch(() => {}), 30000)
})

onBeforeUnmount(() => {
  stopChatSse()
  if (pingInterval) clearInterval(pingInterval)
  clearTimeout(typingTimeout)
  clearTimeout(typingDebounce)
  document.removeEventListener('visibilitychange', markReadIfPossible)
  cancelRecording()
})
</script>
