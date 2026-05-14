<script setup lang="ts">
import { Bell } from 'lucide-vue-next';

defineProps<{
    unreadCount: number;
}>();

defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <button
        type="button"
        class="relative flex size-8 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
        :aria-label="`Notificações${unreadCount > 0 ? ` (${unreadCount} não lidas)` : ''}`"
        @click="$emit('toggle')"
    >
        <Bell class="size-4" />
        <span
            v-if="unreadCount > 0"
            class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] leading-none font-bold text-primary-foreground"
        >
            {{ unreadCount > 99 ? '99+' : unreadCount }}
        </span>
    </button>
</template>
