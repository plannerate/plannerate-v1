<script setup lang="ts">
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import RestoreButton from '@/components/RestoreButton.vue';

withDefaults(
    defineProps<{
        editHref: string;
        deleteHref: string;
        deleteLabel?: string;
        requireConfirmWord?: boolean;
        isTrashed?: boolean;
        restoreHref?: string;
    }>(),
    {
        requireConfirmWord: false,
        isTrashed: false,
        restoreHref: undefined,
    },
);
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <slot />
        <RestoreButton v-if="isTrashed && restoreHref" :href="restoreHref" />
        <EditButton v-if="!isTrashed" :href="editHref" />
        <DeleteButton :href="deleteHref" :label="deleteLabel" :require-confirm-word="requireConfirmWord" />
    </div>
</template>
