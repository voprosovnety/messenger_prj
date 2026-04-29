async function request(path, options = {}) {
    const access = localStorage.getItem('access_token')
    const headers = { ...(options.headers || {}) }

    if (!headers['Content-Type'] && options.body) {
        headers['Content-Type'] = 'application/json'
    }
    if (access) headers['Authorization'] = `Bearer ${access}`

    const res = await fetch(path, { ...options, headers })

    if (res.status === 401) {
        const refreshed = await tryRefresh()
        if (refreshed) {
            const access2 = localStorage.getItem('access_token')
            const headers2 = { ...headers, Authorization: `Bearer ${access2}` }
            return fetch(path, { ...options, headers: headers2 })
        }
    }

    return res
}

async function tryRefresh() {
    const refresh = localStorage.getItem('refresh_token')
    if (!refresh) return false

    const res = await fetch('/api/auth/refresh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: refresh }),
    })

    if (!res.ok) {
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
        return false
    }

    const json = await res.json()
    localStorage.setItem('access_token', json.access_token)
    return true
}

export const api = {
    login: async (identifier, password) => {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier, password }),
        })
        const json = await res.json()
        if (!res.ok) throw new Error(json.error || 'Login failed')
        localStorage.setItem('access_token', json.access_token)
        localStorage.setItem('refresh_token', json.refresh_token)
    },

    logout: async () => {
        const refresh = localStorage.getItem('refresh_token')
        if (refresh) {
            await fetch('/api/auth/logout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refresh_token: refresh }),
            }).catch(() => {})
        }
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
    },

    me: async () => {
        const res = await request('/api/me')
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to load profile')
        return json
    },

    updateProfile: async ({ username, avatarUrl }) => {
        const body = {}
        if (username !== undefined) body.username = username
        if (avatarUrl !== undefined) body.avatar_url = avatarUrl
        const res = await request('/api/me', {
            method: 'PATCH',
            body: JSON.stringify(body),
        })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to update profile')
        return json
    },

    ping: async () => {
        await request('/api/me/ping', { method: 'POST' })
    },

    listChats: async () => {
        const res = await request('/api/chats')
        const text = await res.text()
        try {
            const json = JSON.parse(text)
            if (!res.ok) throw new Error(json.error || json.message || 'Failed to load chats')
            return json
        } catch {
            throw new Error(text.slice(0, 120) || 'Failed to load chats')
        }
    },

    getChat: async (chatId) => {
        const res = await request(`/api/chats/${chatId}`)
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || json.message || 'Failed to load chat')
        return json
    },

    createChat: async ({ isGroup, title, description, participants }) => {
        const res = await request('/api/chats', {
            method: 'POST',
            body: JSON.stringify({
                is_group: !!isGroup,
                title: isGroup ? title : null,
                description: description || null,
                participants: participants || [],
            }),
        })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to create chat')
        return json
    },

    deleteChat: async (chatId) => {
        const res = await request(`/api/chats/${chatId}`, { method: 'DELETE' })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to delete chat')
        return json
    },

    addChatMember: async (chatId, identifier) => {
        const res = await request(`/api/chats/${chatId}/members`, {
            method: 'POST',
            body: JSON.stringify({ identifier }),
        })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to add member')
        return json
    },

    removeChatMember: async (chatId, userId) => {
        const res = await request(`/api/chats/${chatId}/members/${userId}`, { method: 'DELETE' })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to remove member')
        return json
    },

    listMessages: async (chatId, params = {}) => {
        const qs = new URLSearchParams(params).toString()
        const res = await request(`/api/chats/${chatId}/messages${qs ? '?' + qs : ''}`)
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to load messages')
        return json
    },

    sendMessage: async (chatId, content) => {
        const res = await request(`/api/chats/${chatId}/messages`, {
            method: 'POST',
            body: JSON.stringify({ content }),
        })
        return res.json()
    },

    editMessage: async (chatId, messageId, content) => {
        const res = await request(`/api/chats/${chatId}/messages/${messageId}`, {
            method: 'PATCH',
            body: JSON.stringify({ content }),
        })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to edit message')
        return json
    },

    deleteMessage: async (chatId, messageId) => {
        const res = await request(`/api/chats/${chatId}/messages/${messageId}`, {
            method: 'DELETE',
        })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to delete message')
        return json
    },

    sendTyping: async (chatId) => {
        await request(`/api/chats/${chatId}/typing`, { method: 'POST' })
    },

    getMercureCookie: async (chatId) => {
        const res = await request(`/api/chats/${chatId}/mercure-subscribe`, { method: 'POST' })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to subscribe')
        return json
    },

    subscribeAllChats: async () => {
        const res = await request('/api/chats/mercure-subscribe', { method: 'POST' })
        const json = await res.json().catch(() => ({}))
        if (!res.ok) throw new Error(json.error || 'Failed to subscribe')
        return json
    },

    markDelivered: async (chatId, msgId) => {
        await request(`/api/chats/${chatId}/delivered`, {
            method: 'POST',
            body: JSON.stringify({ last_delivered_message_id: msgId }),
        })
    },

    markRead: async (chatId, msgId) => {
        await request(`/api/chats/${chatId}/read`, {
            method: 'POST',
            body: JSON.stringify({ last_read_message_id: msgId }),
        })
    },
}
