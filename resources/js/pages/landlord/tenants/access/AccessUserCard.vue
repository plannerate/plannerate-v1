<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Edit, Ban, Trash2, RotateCcw, Mail } from 'lucide-vue-next';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import { useT } from '@/composables/useT';

type UserAccessRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    deleted_at: string | null;
    role_names: string[];
};

const props = defineProps<{
    user: UserAccessRow;
    tenantId: string;
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

            <!-- Roles grid -->
            <div class="border-t border-border pt-5">
                <div>
                    <p class="mb-1 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">Perfis</p>
                    <p v-if="user.role_names.length === 0" class="text-sm text-muted-foreground">
                        Nenhum perfil
                    </p>
                    <div v-else class="flex flex-wrap gap-1">
                        <span
                            v-for="roleName in user.role_names"
                            :key="roleName"
                            class="text-sm text-foreground"
                        >{{ roleName }}<span v-if="user.role_names.indexOf(roleName) < user.role_names.length - 1">, </span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer action strip -->
        <div class="flex items-center justify-between border-t border-border bg-muted/30 px-6 py-3">
            <div class="flex items-center gap-1">
                <!-- Edit -->
                <button
                    v-if="!user.deleted_at"
                    class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                    :title="t('app.landlord.common.edit')"
                    @click="emit('edit', user.id)"
                >
                    <Edit class="size-4" />
                </button>

                <!-- Toggle active -->
                <Link
                    v-if="!user.deleted_at"
                    :href="TenantUserAccessController.toggleActive.url({ tenant: tenantId, userId: user.id })"
                    method="patch"
                    as="button"
                    :data="{ is_active: user.is_active ? 0 : 1 }"
                    class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-amber-400/10 hover:text-amber-400"
                    :title="user.is_active ? t('app.landlord.common.inactive') : t('app.landlord.common.active')"
                >
                    <Ban class="size-4" />
                </Link>
            </div>

            <!-- Delete / Restore -->
            <Link
                v-if="!user.deleted_at"
                :href="TenantUserAccessController.destroy.url({ tenant: tenantId, userId: user.id })"
                method="delete"
                as="button"
                class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-destructive/10 hover:text-destructive"
                :title="t('app.landlord.common.delete')"
            >
                <Trash2 class="size-4" />
            </Link>

            <Link
                v-if="user.deleted_at"
                :href="TenantUserAccessController.restore.url({ tenant: tenantId, userId: user.id })"
                method="patch"
                as="button"
                class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                :title="t('app.actions.restore')"
            >
                <RotateCcw class="size-4" />
            </Link>
        </div>
    </div>
</template>
