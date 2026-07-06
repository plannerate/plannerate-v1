<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Edit, LogIn, Mail, RotateCcw, Trash2, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import WayfinderLink from '@/components/WayfinderLink.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { useT } from '@/composables/useT';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

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
    is_admin: boolean;
};

const props = defineProps<{
    user: UserAccessRow;
    tenantId: string;
    roles: RoleOption[];
    adminLimitReached: boolean;
    canImpersonate: boolean;
}>();

const emit = defineEmits<{
    edit: [userId: string];
}>();

const { t } = useT();

const localRoleNames = ref([...props.user.role_names]);
watch(() => props.user.role_names, (val) => {
 localRoleNames.value = [...val]; 
});

const flushRoles = useDebounceFn(() => {
    router.patch(tenantWayfinderPath(TenantUserAccessController.syncRoles.url({ tenant: props.tenantId, userId: props.user.id })), {
        role_names: localRoleNames.value,
    }, { preserveScroll: true });
}, 1000);

function onRoleChange(roleName: string, checked: boolean): void {
    localRoleNames.value = checked
        ? [...localRoleNames.value, roleName]
        : localRoleNames.value.filter((r) => r !== roleName);
    flushRoles();
}

function onActiveChange(tenantId: string, userId: string, currentIsActive: boolean): void {
    router.patch(tenantWayfinderPath(TenantUserAccessController.toggleActive.url({ tenant: tenantId, userId })), {
        is_active: currentIsActive ? 0 : 1,
    }, { preserveScroll: true });
}

const isForceDeleteOpen = ref(false);
const isImpersonateOpen = ref(false);

/**
 * Emite o código de impersonation e navega o navegador para o host do tenant para consumi-lo.
 */
function onImpersonate(): void {
    router.post(
        TenantUserAccessController.impersonate.url({ tenant: props.tenantId, userId: props.user.id }),
        {},
        {
            onFinish: () => {
                isImpersonateOpen.value = false;
            },
        },
    );
}

/**
 * Exclui o usuário definitivamente (hard delete) após confirmação no diálogo.
 */
function onForceDelete(): void {
    router.delete(
        tenantWayfinderPath(TenantUserAccessController.forceDelete.url({ tenant: props.tenantId, userId: props.user.id })),
        {
            preserveScroll: true,
            onFinish: () => {
                isForceDeleteOpen.value = false;
            },
        },
    );
}

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
                        class="flex cursor-pointer items-center gap-1.5 rounded-full border border-input px-3 py-1 text-sm transition-colors has-checked:border-primary/60 has-checked:bg-primary/5 has-checked:text-primary"
                        :class="[
                            user.deleted_at || (role.is_admin && adminLimitReached && !localRoleNames.includes(role.name))
                                ? 'pointer-events-none opacity-60'
                                : 'hover:bg-accent',
                        ]"
                    >
                        <input
                            type="checkbox"
                            :value="role.name"
                            :checked="localRoleNames.includes(role.name)"
                            :disabled="!!user.deleted_at || (role.is_admin && adminLimitReached && !localRoleNames.includes(role.name))"
                            class="accent-primary"
                            @change="onRoleChange(role.name, ($event.target as HTMLInputElement).checked)"
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
            <!-- Edit + Logar como -->
            <div class="flex items-center gap-1">
                <button
                    v-if="!user.deleted_at"
                    class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                    :title="t('app.landlord.common.edit')"
                    @click="emit('edit', user.id)"
                >
                    <Edit class="size-4" />
                </button>

                <AlertDialog v-if="!user.deleted_at && user.is_active && canImpersonate" v-model:open="isImpersonateOpen">
                    <AlertDialogTrigger as-child>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                            :title="t('app.landlord.tenant_access.impersonate.title')"
                        >
                            <LogIn class="size-4" />
                        </button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('app.landlord.tenant_access.impersonate.title') }}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ t('app.landlord.tenant_access.impersonate.description', { name: user.name }) }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{{ t('app.actions.cancel') }}</AlertDialogCancel>
                            <AlertDialogAction @click="onImpersonate">
                                {{ t('app.landlord.tenant_access.impersonate.confirm') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>

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

            <div v-if="user.deleted_at" class="flex items-center gap-1">
                <!-- Restaurar usuário -->
                <WayfinderLink
                    :href="TenantUserAccessController.restore.url({ tenant: tenantId, userId: user.id })"
                    method="patch"
                    as="button"
                    class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                    :title="t('app.actions.restore')"
                >
                    <RotateCcw class="size-4" />
                </WayfinderLink>

                <!-- Excluir definitivamente (com confirmação) -->
                <AlertDialog v-model:open="isForceDeleteOpen">
                    <AlertDialogTrigger as-child>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-destructive/10 hover:text-destructive"
                            :title="t('app.actions.force_delete')"
                        >
                            <XCircle class="size-4" />
                        </button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('app.landlord.tenant_access.force_delete.title') }}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {{ t('app.landlord.tenant_access.force_delete.description', { name: user.name }) }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{{ t('app.actions.cancel') }}</AlertDialogCancel>
                            <AlertDialogAction
                                class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                @click="onForceDelete"
                            >
                                {{ t('app.landlord.tenant_access.force_delete.confirm') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </div>
    </div>
</template>
