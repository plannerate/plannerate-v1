<script setup lang="ts">
import DeleteButton from '@/components/DeleteButton.vue';
import EditButton from '@/components/EditButton.vue';
import RestoreButton from '@/components/RestoreButton.vue';

withDefaults(
    defineProps<{
        editHref: string;
        deleteHref: string;
        deleteLabel?: string;
        /** Rótulo/verbo do botão de exclusão (ex.: "Excluir definitivamente"). */
        deleteText?: string;
        requireConfirmWord?: boolean;
        isTrashed?: boolean;
        restoreHref?: string;
        /** Quando false, oculta o botão de exclusão (ex.: registros protegidos). */
        canDelete?: boolean;
    }>(),
    {
        deleteLabel: undefined,
        deleteText: undefined,
        requireConfirmWord: true,
        isTrashed: false,
        restoreHref: undefined,
        canDelete: true,
    },
);
</script>

<template>
    <div class="inline-flex items-center gap-2">
        <slot v-if="!isTrashed" />
        <RestoreButton v-if="isTrashed && restoreHref" :href="restoreHref" />
        <EditButton v-if="!isTrashed" :href="editHref" />
        <DeleteButton
            v-if="canDelete"
            :href="deleteHref"
            :label="deleteLabel"
            :text="deleteText"
            :permanent="isTrashed"
            :require-confirm-word="requireConfirmWord"
        />
    </div>
</template>
