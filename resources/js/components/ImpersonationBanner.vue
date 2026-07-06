<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { LogOut } from 'lucide-vue-next';
import { computed } from 'vue';
import { useT } from '@/composables/useT';

type ImpersonationPayload = {
    target_user_name: string | null;
    initiator_name: string | null;
    tenant_name: string | null;
    started_at: string | null;
} | null;

const { t } = useT();
const page = usePage();

const impersonation = computed(() => (page.props.impersonation ?? null) as ImpersonationPayload);

/**
 * Encerra a sessão de impersonation. O backend responde com Inertia::location(), então
 * o navegador é redirecionado automaticamente de volta ao host do painel landlord.
 */
function onLeave(): void {
    router.post('/impersonation/leave');
}
</script>

<template>
    <div
        v-if="impersonation"
        class="fixed inset-x-0 top-0 z-50 flex items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950 shadow-md"
    >
        <span>
            {{
                t('app.impersonation.banner.message', {
                    target: impersonation.target_user_name ?? '',
                    tenant: impersonation.tenant_name ?? '',
                    initiator: impersonation.initiator_name ?? '',
                })
            }}
        </span>
        <button
            type="button"
            class="flex items-center gap-1.5 rounded-md bg-amber-950/10 px-3 py-1 font-semibold transition-colors hover:bg-amber-950/20"
            @click="onLeave"
        >
            <LogOut class="size-3.5" />
            {{ t('app.impersonation.banner.leave_button') }}
        </button>
    </div>
</template>
