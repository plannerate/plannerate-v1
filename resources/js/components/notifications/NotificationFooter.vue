<script setup lang="ts">
defineProps<{
    notificationCount: number;
    unreadCount: number;
}>();
</script>

<template>
    <div
        v-if="notificationCount > 0"
        class="border-t border-border/40 px-4 py-2 text-[11px] text-muted-foreground"
    >
        {{ notificationCount }} notificaç{{
            notificationCount === 1 ? 'ão' : 'ões'
        }}
        <template v-if="unreadCount > 0">
            ·
            <span class="font-semibold text-primary">
                {{ unreadCount }} não {{ unreadCount === 1 ? 'lida' : 'lidas' }}
            </span>
        </template>
    </div>
</template>
