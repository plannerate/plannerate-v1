<script setup lang="ts">
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';

withDefaults(
    defineProps<{
        editHref: string;
        deleteHref: string;
        deleteLabel?: string;
        requireConfirmWord?: boolean;
    }>(),
    {
        requireConfirmWord: false,
    },
);
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <slot />
        <EditButton :href="editHref" />
        <DeleteButton :href="deleteHref" :label="deleteLabel" :require-confirm-word="requireConfirmWord" />
    </div>
</template>
