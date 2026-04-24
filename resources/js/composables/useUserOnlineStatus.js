/**
 * useUserOnlineStatus - Vue composable for tracking user online presence
 *
 * Uses Pusher Presence Channel (private-user.presence)
 * - .here()  : called when subscribing, returns all current online members
 * - .joining(): called when a new member joins the channel
 * - .leaving(): called when a member leaves the channel
 *
 * Usage:
 *   import { useUserOnlineStatus } from '@/composables/useUserOnlineStatus.js'
 *   const { onlineUsers, isReady } = useUserOnlineStatus()
 *
 *   // onlineUsers is a reactive Map<userId, { fullName, avatarUrl, lastSeen }>
 *   // isReady is true once the presence channel has loaded initial members
 */

import { ref, onMounted, onUnmounted } from 'vue'

export function useUserOnlineStatus() {
    const onlineUsers = ref(new Map())
    const isReady = ref(false)
    const error = ref(null)
    let presenceChannel = null

    const getCurrentUserId = () => {
        try {
            const userStr = localStorage.getItem('user')
            if (userStr) {
                const user = JSON.parse(userStr)
                return user.id
            }
        } catch (e) {
            console.warn('[useUserOnlineStatus] Cannot parse user from localStorage', e)
        }
        return null
    }

    const initPresence = () => {
        if (!window.Echo) {
            error.value = 'Laravel Echo chưa được khởi tạo'
            console.warn('[useUserOnlineStatus] window.Echo not available')
            return
        }

        presenceChannel = window.Echo.private('user.presence')

        // Initial list of online members (called once when channel subscription succeeds)
        presenceChannel.here((members) => {
            isReady.value = true
            members.forEach((member) => {
                onlineUsers.value.set(member.id, {
                    fullName: member.full_name,
                    avatarUrl: member.avatar_url,
                    lastSeen: new Date(),
                })
            })
        })

        // New member joined
        presenceChannel.joining((member) => {
            onlineUsers.value.set(member.id, {
                fullName: member.full_name,
                avatarUrl: member.avatar_url,
                lastSeen: new Date(),
            })
        })

        // Member left (disconnected or explicitly left)
        presenceChannel.leaving((member) => {
            onlineUsers.value.delete(member.id)
        })

        // Channel subscription failed
        presenceChannel.bind('pusher:subscription_error', (err) => {
            error.value = 'Không thể kết nối kênh online: ' + (err.message || 'Lỗi không xác định')
            console.error('[useUserOnlineStatus] Subscription error:', err)
        })

        // Bind to custom user.presence.changed event (for non-presence-channel triggers)
        presenceChannel.listen('.user.presence.changed', (data) => {
            if (data.is_online) {
                onlineUsers.value.set(data.user_id, {
                    fullName: data.full_name,
                    avatarUrl: data.avatar_url,
                    lastSeen: new Date(data.timestamp),
                })
            } else {
                onlineUsers.value.delete(data.user_id)
            }
        })
    }

    onMounted(() => {
        initPresence()
    })

    onUnmounted(() => {
        if (presenceChannel) {
            presenceChannel.stopListening('.user.presence.changed')
            presenceChannel.leave()
            presenceChannel = null
        }
        onlineUsers.value.clear()
        isReady.value = false
    })

    return {
        onlineUsers,   // Reactive Map<userId, { fullName, avatarUrl, lastSeen }>
        isReady,       // true when initial member list has been loaded
        error,         // error message if any
    }
}
