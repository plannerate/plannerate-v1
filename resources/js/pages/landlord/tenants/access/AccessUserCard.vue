<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Edit, Mail, RotateCcw, Trash2 } from 'lucide-vue-next';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import WayfinderLink from '@/components/WayfinderLink.vue';
import { useT } from '@/composables/useT';

type UserAccessRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    deleted_at: string | null;
    role_names: string[];
};

type RoleOption = {
    id: string;
    name: string;
};

defineProps<{
    user: UserAccessRow;
    tenantId: string;
    roles: RoleOption[];
}>();

const emit = defineEmits<{
    edit: [userId: string];
}>();

const { t } = useT();

function getUserInitials(name: string): string {
    const tokens = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    if (tokens.length === 0) {
        return 'US';
    }

    return tokens.map((part) => part.charAt(0).toUpperCase()).join('');
}

function onRoleChange(roleName: string, currentRoles: string[], tenantId: string, userId: string, checked: boolean): void {
    const newRoles = checked
        ? [...currentRoles, roleName]
        : currentRoles.filter((r) => r !== roleName);

    router.patch(TenantUserAccessController.syncRoles.url({ tenant: tenantId, userId }), {
        role_names: newRoles,
    });
}

function onActiveChange(tenantId: string, userId: string, currentIsActive: boolean): void {
    router.patch(TenantUserAccessController.toggleActive.url({ tenant: tenantId, userId }), {
        is_active: currentIsActive ? 0 : 1,
    });
}
</script>

<template>
    <div class="group overflow-hidden rounded-xl border border-border bg-card transition-all duration-300 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5">
        <!-- Card body -->
        <div class="p-6">
            <!-- Top: avatar + status badge -->
            <div class="mb-5 flex items-start justify-between">
                <div class="relative">
                    <div
                        class="flex size-20 items-center justify-center rounded-full bg-primary/10 text-xl font-bold text-primary ring-4 ring-primary/10 transition-all duration-500 group-hover:ring-primary/20"
                    >
                        {{ getUserInitials(user.name) }}
                    </div>
                    <!-- Status dot -->
                    <div
                        v-if="!user.deleted_at"
                        class="absolute bottom-0.5 right-0.5 size-4 rounded-full border-2 border-card"
                        :class="user.is_active ? 'bg-primary' : 'bg-muted-foreground'"
                    />
                </div>

                <!-- Status pill badge -->
                <span
                    v-if="user.deleted_at"
                    class="rounded-full border border-destructive/30 bg-destructive/10 px-3 py-1 text-xs font-bold uppercase tracking-widest text-destructive"
                >
                    Excluído
                </span>
                <span
                    v-else-if="user.is_active"
                    class="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-widest text-primary"
                >
                    Ativo
                </span>
                <span
                    v-else
                    class="rounded-full border border-muted-foreground/30 bg-muted px-3 py-1 text-xs font-bold uppercase tracking-widest text-muted-foreground"
                >
                    Inativo
                </span>
            </div>

            <!-- Name + email -->
            <div class="mb-5">
                <h3 class="mb-1 text-xl font-semibold leading-tight">{{ user.name }}</h3>
                <p class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Mail class="size-3.5 shrink-0" />
                    <span class="truncate">{{ user.email }}</span>
                </p>
            </div>

            <!-- Roles inline checkboxes -->
            <div class="border-t border-border pt-5">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">Perfis</p>
                <p v-if="roles.length === 0" class="text-sm text-muted-foreground">Nenhum perfil disponível</p>
                <div v-else class="flex flex-wrap gap-1.5">
                    <label
                        v-for="role in roles"
                        :key="role.id"
                        class="flex cursor-pointer items-center gap-1.5 rounded-full border border-input px-3 py-1 text-sm transition-colors hover:bg-accent has-checked:border-primary/60 has-checked:bg-primary/5 has-checked:text-primary"
                        :class="user.deleted_at ? 'pointer-events-none opacity-60' : ''"
                    >
                        <input
                            type="checkbox"
                            :value="role.name"
                            :checked="user.role_names.includes(role.name)"
                            :disabled="!!user.deleted_at"
                            class="accent-primary"
                            @change="onRoleChange(role.name, user.role_names, tenantId, user.id, ($event.target as HTMLInputElement).checked)"
                        />
                        <span class="font-medium">{{ role.name }}</span>
                    </label>
                </div>
            </div>

            <!-- Active toggle -->
            <div v-if="!user.deleted_at" class="mt-4 border-t border-border pt-4">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">Status</p>
                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-input px-3 py-2 text-sm transition-colors hover:bg-accent has-checked:border-primary/60 has-checked:bg-primary/5">
                    <input
                        type="checkbox"
                        :checked="user.is_active"
                        class="accent-primary"
                        @change="onActiveChange(tenantId, user.id, user.is_active)"
                    />
                    <span class="font-medium">Usuário ativo</span>
                </label>
            </div>
        </div>

        <!-- Footer action strip -->
        <div class="flex items-center justify-between border-t border-border bg-muted/30 px-6 py-3">
            <!-- Edit -->
            <button
                v-if="!user.deleted_at"
                class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                :title="t('app.landlord.common.edit')"
                @click="emit('edit', user.id)"
            >
                <Edit class="size-4" />
            </button>
            <div v-else />

            <!-- Delete / Restore -->
            <WayfinderLink
                v-if="!user.deleted_at"
                :href="TenantUserAccessController.destroy.url({ tenant: tenantId, userId: user.id })"
                method="delete"
                as="button"
                class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-destructive/10 hover:text-destructive"
                :title="t('app.landlord.common.delete')"
            >
                <Trash2 class="size-4" />
            </WayfinderLink>

            <WayfinderLink
                v-if="user.deleted_at"
                :href="TenantUserAccessController.restore.url({ tenant: tenantId, userId: user.id })"
                method="patch"
                as="button"
                class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                :title="t('app.actions.restore')"
            >
                <RotateCcw class="size-4" />
            </WayfinderLink>
        </div>
    </div>
</template>
