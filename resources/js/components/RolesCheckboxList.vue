<script setup lang="ts">
import InputError from '@/components/InputError.vue';

type RoleItem = {
    value: string;
    label: string;
    isAdmin: boolean;
    /** Limite de usuários do perfil no plano (null = ilimitado). */
    limit?: number | null;
    /** Quantidade atual de usuários com o perfil. */
    count?: number;
    /** Limite do perfil atingido (bloqueia novas atribuições). */
    limitReached?: boolean;
};

const props = defineProps<{
    nameAttr: string;
    roles: RoleItem[];
    selectedValues?: string[];
    error?: string;
}>();

function isSelected(role: RoleItem): boolean {
    return props.selectedValues?.includes(role.value) ?? false;
}

/**
 * Desabilita o perfil administrativo cujo limite foi atingido — exceto para
 * usuários que já o possuem (que não reconsomem vaga).
 */
function isDisabled(role: RoleItem): boolean {
    return role.isAdmin && (role.limitReached ?? false) && !isSelected(role);
}

/** Exibe o contador "usados/limite" para perfis administrativos com limite. */
function hasCounter(role: RoleItem): boolean {
    return role.isAdmin && role.limit !== null && role.limit !== undefined;
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
                :checked="isSelected(role)"
                :disabled="isDisabled(role)"
                class="accent-primary"
            />
            <span class="flex-1">{{ role.label }}</span>
            <span
                v-if="hasCounter(role)"
                class="shrink-0 rounded-full border border-border px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
            >
                {{ role.count ?? 0 }}/{{ role.limit }}
            </span>
        </label>
    </div>
    <InputError v-if="error" :message="error" />
</template>
