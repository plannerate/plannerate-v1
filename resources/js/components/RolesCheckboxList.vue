<script setup lang="ts">
import InputError from '@/components/InputError.vue';

type RoleItem = {
    value: string;
    label: string;
    isAdmin: boolean;
};

const props = defineProps<{
    nameAttr: string;
    roles: RoleItem[];
    selectedValues?: string[];
    adminLimitReached: boolean;
    error?: string;
}>();

function isDisabled(role: RoleItem): boolean {
    return role.isAdmin && props.adminLimitReached && !(props.selectedValues?.includes(role.value) ?? false);
}
</script>

<template>
    <div class="grid gap-2 md:grid-cols-2">
        <label
            v-for="role in roles"
            :key="role.value"
            :class="[
                'flex items-center gap-2.5 rounded-lg border border-border px-3 py-2.5 text-sm transition-colors',
                isDisabled(role)
                    ? 'cursor-not-allowed opacity-50'
                    : 'cursor-pointer hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/5',
            ]"
        >
            <input
                type="checkbox"
                :name="nameAttr"
                :value="role.value"
                :checked="selectedValues?.includes(role.value) ?? false"
                :disabled="isDisabled(role)"
                class="accent-primary"
            />
            <span>{{ role.label }}</span>
        </label>
    </div>
    <InputError v-if="error" :message="error" />
</template>
