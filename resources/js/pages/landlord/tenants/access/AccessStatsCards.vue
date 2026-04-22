<script setup lang="ts">
import { computed } from 'vue';
import { TrendingUp } from 'lucide-vue-next';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
    plan_user_limit: number | null;
    users_count: number;
    can_create_users: boolean;
    limit_message: string | null;
};

const props = defineProps<{
    tenant: TenantPayload;
}>();

const usagePct = computed<number>(() => {
    if (!props.tenant.plan_user_limit) {
        return 0;
    }
    return Math.min(100, Math.round((props.tenant.users_count / props.tenant.plan_user_limit) * 100));
});

const usageText = computed<string>(() => {
    const limit = props.tenant.plan_user_limit === null ? '∞' : String(props.tenant.plan_user_limit);
    return `${props.tenant.users_count} de ${limit} usuários utilizados`;
});

const countFormatted = computed<string>(() => {
    return String(props.tenant.users_count).padStart(2, '0');
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <!-- Card: Contagem de usuários -->
        <div class="relative overflow-hidden rounded-xl border border-border bg-card p-6">
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <p class="mb-3 text-xs font-bold uppercase tracking-widest text-muted-foreground">
                        Usuários ({{ tenant.users_count }})
                    </p>
                    <p class="text-6xl font-bold leading-none tabular-nums text-primary">
                        {{ countFormatted }}
                    </p>
                </div>
                <div class="rounded-lg bg-primary/10 p-2.5">
                    <svg class="size-5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
            </div>
            <div class="relative z-10 mt-6 flex items-center gap-1.5 text-sm text-primary/70">
                <TrendingUp class="size-4" />
                <span>ativos no tenant</span>
            </div>
            <!-- Watermark icon -->
            <div class="pointer-events-none absolute -bottom-3 -right-3 opacity-[0.07]">
                <svg class="size-36 text-primary" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
            </div>
        </div>

        <!-- Card: Limite do plano -->
        <div class="relative col-span-1 overflow-hidden rounded-xl border border-border bg-card p-6 md:col-span-2">
            <div class="flex items-start justify-between gap-6">
                <div class="flex-1">
                    <p class="mb-3 text-xs font-bold uppercase tracking-widest text-muted-foreground">
                        Limite do Plano ({{ tenant.plan_user_limit ?? '∞' }})
                    </p>
                    <div class="flex items-end gap-4">
                        <p class="text-6xl font-bold leading-none tabular-nums text-foreground">
                            {{ tenant.plan_user_limit ? `${usagePct}%` : '—' }}
                        </p>
                        <p class="mb-1 text-sm text-muted-foreground">{{ usageText }}</p>
                    </div>
                    <div class="mt-6 h-2 w-full overflow-hidden rounded-full bg-border">
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-700"
                            :style="{ width: tenant.plan_user_limit ? `${usagePct}%` : '0%' }"
                        />
                    </div>
                </div>

                <!-- Circular progress -->
                <div v-if="tenant.plan_user_limit" class="hidden shrink-0 lg:block">
                    <div class="relative flex size-24 items-center justify-center">
                        <svg class="absolute inset-0 size-full -rotate-90" viewBox="0 0 96 96">
                            <circle
                                class="text-border"
                                cx="48" cy="48" r="40"
                                fill="transparent"
                                stroke="currentColor"
                                stroke-width="8"
                            />
                            <circle
                                class="text-primary transition-all duration-700"
                                cx="48" cy="48" r="40"
                                fill="transparent"
                                stroke="currentColor"
                                stroke-width="8"
                                stroke-linecap="round"
                                :stroke-dasharray="251"
                                :stroke-dashoffset="251 - (251 * usagePct) / 100"
                            />
                        </svg>
                        <span class="text-sm font-bold tabular-nums text-muted-foreground">{{ usagePct }}%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
