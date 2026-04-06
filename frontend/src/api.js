async function request(path, options = {}) {
    const access = localStorage.getItem('access_token')
    const headers = { ...(options.headers || {}) }

    if (!headers['Content-Type'] && options.body) {
        headers['Content-Type'] = 'application/json'
    }
    if (access) headers['Authorization'] = `Bearer ${access}`

    const res = await fetch(path, { ...options, headers })

    // auto refresh если access истёк
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
    login: async (email, password) => {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password }),
        })
        const json = await res.json()
        if (!res.ok) throw new Error(json.error || 'login failed')
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
            })
        }
        localStorage.removeItem('access_token')
        localStorage.removeItem('refresh_token')
    },

    listChats: async () => {
        const res = await request('/api/chats')
        return res.json()
    },

    listMessages: async (chatId) => {
        const res = await request(`/api/chats/${chatId}/messages`)
        return res.json()
    },

    sendMessage: async (chatId, content) => {
        const res = await request(`/api/chats/${chatId}/messages`, {
            method: 'POST',
            body: JSON.stringify({ content }),
        })
        return res.json()
    },

    getMercureToken: async (chatId) => {
        const res = await request(`/api/chats/${chatId}/mercure-token`)
        return res.json()
    },

    getMercureCookie: async (chatId) => {
        const res = await request(`/api/chats/${chatId}/mercure-subscribe`, { method: 'POST' })
        return res.json()
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