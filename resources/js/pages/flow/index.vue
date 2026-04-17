<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader } from '~/components/ui/card';
import { GitBranch } from 'lucide-vue-next';

interface Flow {
  id: string;
  name: string;
  slug: string;
  status: string;
}

interface Props {
  flows: Flow[];
}

defineProps<Props>();

function statusClass(status: string): string {
  const s = status?.toLowerCase() ?? '';
  if (s === 'published' || s === 'active' || s === 'ativo') {
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
  }
  if (s === 'draft' || s === 'rascunho') {
    return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
  }
  return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}
</script>

<template>
  <AppLayout>
    <Head title="Fluxos" />

    <div class="p-6 space-y-6">
      <div class="flex items-center gap-3">
        <GitBranch class="size-6 text-primary" />
        <h1 class="text-2xl font-bold text-foreground">Fluxos</h1>
      </div>

      <div v-if="flows.length === 0" class="flex flex-col items-center justify-center py-16 text-muted-foreground gap-2">
        <GitBranch class="size-10 opacity-30" />
        <p class="text-sm">Nenhum fluxo cadastrado.</p>
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Link
          v-for="flow in flows"
          :key="flow.id"
          :href="`/flow/flows/${flow.slug}`"
        >
          <Card class="transition-shadow hover:shadow-md cursor-pointer h-full">
            <CardHeader class="pb-2">
              <div class="flex items-start justify-between gap-2">
                <h2 class="text-base font-semibold text-card-foreground leading-tight">
                  {{ flow.name }}
                </h2>
                <span
                  class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                  :class="statusClass(flow.status)"
                >
                  {{ flow.status }}
                </span>
              </div>
            </CardHeader>
            <CardContent>
              <p class="text-xs text-muted-foreground font-mono">{{ flow.slug }}</p>
            </CardContent>
          </Card>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
