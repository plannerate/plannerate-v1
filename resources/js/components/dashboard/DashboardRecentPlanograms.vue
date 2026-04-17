<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { getPlanogramStatusLabel } from '@/lib/status';
import type { RecentPlanogram } from '@/types/dashboard';

interface Props {
    recentPlanograms: RecentPlanogram[];
}

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-base">
                Planogramas recentes
            </CardTitle>
            <CardDescription>
                Últimos 5 planogramas criados
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div
                    v-for="planogram in recentPlanograms"
                    :key="planogram.id"
                    class="flex items-center justify-between rounded-lg border bg-card p-3 transition-colors hover:bg-muted/40"
                >
                    <div class="min-w-0 space-y-0.5">
                        <p class="truncate text-sm font-medium">
                            {{ planogram.name }}
                        </p>
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                            <span v-if="planogram.client_name">{{ planogram.client_name }}</span>
                            <span v-if="planogram.client_name && planogram.store_name">·</span>
                            <span v-if="planogram.store_name">{{ planogram.store_name }}</span>
                            <span class="shrink-0">{{ planogram.created_at }}</span>
                        </div>
                    </div>
                    <Badge
                        :variant="planogram.status === 'published' ? 'default' : 'secondary'"
                        class="ml-3 shrink-0"
                    >
                        {{ getPlanogramStatusLabel(planogram.status) }}
                    </Badge>
                </div>
                <div
                    v-if="recentPlanograms.length === 0"
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Nenhum planograma criado ainda
                </div>
            </div>
        </CardContent>
    </Card>
</template>
