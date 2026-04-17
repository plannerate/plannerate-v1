<script setup lang="ts">
import { useSyncNotifications } from '@/composables/useSyncNotifications'
import { cn } from '@/lib/utils'
import { CheckCircle2, XCircle, Loader2 } from 'lucide-vue-next'
import { computed } from 'vue'

const { activeSync, getProgress, formatMessage } = useSyncNotifications()

const icon = computed(() => {
    if (!activeSync.value) return null
    
    switch (activeSync.value.type) {
        case 'started':
        case 'progress':
            return Loader2
        case 'completed':
            return CheckCircle2
        case 'failed':
            return XCircle
        default:
            return Loader2
    }
})

const iconColorClass = computed(() => {
    if (!activeSync.value) return ''
    
    switch (activeSync.value.type) {
        case 'completed':
            return 'text-green-600 dark:text-green-500'
        case 'failed':
            return 'text-destructive'
        default:
            return 'text-muted-foreground'
    }
})

const progress = computed(() => {
    if (!activeSync.value) return 0
    return getProgress(activeSync.value)
})

const message = computed(() => {
    if (!activeSync.value) return ''
    return formatMessage(activeSync.value)
})

const isLoading = computed(() => {
    return activeSync.value?.type === 'started' || activeSync.value?.type === 'progress'
})
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="translate-x-full opacity-0"
        enter-to-class="translate-x-0 opacity-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="translate-x-0 opacity-100"
        leave-to-class="translate-x-full opacity-0"
    >
        <div
            v-if="activeSync"
            class="fixed bottom-4 right-4 z-50 w-80"
        >
            <div class="rounded-lg border bg-card text-card-foreground shadow-sm">
                <div class="flex items-start gap-3 p-3">
                    <component
                        :is="icon"
                        :class="cn(
                            'h-4 w-4 flex-shrink-0 mt-0.5',
                            iconColorClass,
                            isLoading && 'animate-spin'
                        )"
                    />
                    
                    <div class="flex-1 space-y-1.5 min-w-0">
                        <p class="text-sm font-medium leading-tight">
                            {{ message }}
                        </p>
                        
                        <div
                            v-if="isLoading && progress > 0"
                            class="space-y-1"
                        >
                            <div class="h-1 w-full overflow-hidden rounded-full bg-secondary">
                                <div
                                    class="h-full bg-primary transition-all duration-300"
                                    :style="{ width: `${progress}%` }"
                                />
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ progress }}%
                            </p>
                        </div>

                        <p
                            v-if="activeSync.message"
                            class="text-xs text-muted-foreground truncate"
                        >
                            {{ activeSync.message }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
