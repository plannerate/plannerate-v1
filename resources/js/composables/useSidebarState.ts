import { computed, ref, watchEffect } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import {
    SIDEBAR_COOKIE_NAME,
    SIDEBAR_WIDTH,
    SIDEBAR_WIDTH_ICON,
} from '@/components/ui/sidebar/utils'

/**
 * Standalone composable to read sidebar state without requiring Sidebar context.
 * Useful for components that need to know sidebar dimensions but are outside the Sidebar hierarchy.
 */
export function useSidebarState() {
    const isMobile = useMediaQuery('(max-width: 768px)')
    const isOpen = ref(true)

    // Read initial state from cookie
    const readCookieState = () => {
        const cookies = document.cookie.split(';')
        const sidebarCookie = cookies.find((c) =>
            c.trim().startsWith(`${SIDEBAR_COOKIE_NAME}=`)
        )
        if (sidebarCookie) {
            const value = sidebarCookie.split('=')[1]?.trim()
            return value === 'true'
        }
        return true // default to open
    }

    // Initialize from cookie
    if (typeof document !== 'undefined') {
        isOpen.value = readCookieState()
    }

    // Watch for cookie changes (when sidebar is toggled)
    watchEffect(() => {
        if (typeof document !== 'undefined') {
            const checkCookie = () => {
                isOpen.value = readCookieState()
            }

            // Check periodically for cookie changes
            const interval = setInterval(checkCookie, 500)

            return () => clearInterval(interval)
        }
    })

    const state = computed(() => (isOpen.value ? 'expanded' : 'collapsed'))

    const sidebarWidth = computed(() => {
        if (isMobile.value) return '0px'
        return state.value === 'collapsed' ? SIDEBAR_WIDTH_ICON : SIDEBAR_WIDTH
    })

    return {
        state,
        isOpen,
        isMobile,
        sidebarWidth,
    }
}
