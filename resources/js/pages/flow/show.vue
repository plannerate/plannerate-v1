<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader } from '~/components/ui/card';
import { ArrowLeft, Clock, GitBranch, Layers } from 'lucide-vue-next';

interface StepTemplate {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  category: string | null;
  suggested_order: number;
  estimated_duration_days: number | null;
  color: string | null;
  icon: string | null;
  is_required_by_default: boolean;
  is_active: boolean;
  tags: string[] | null;
}

interface Flow {
  id: string;
  name: string;
  slug: string;
  status: string;
  step_templates: StepTemplate[];
}

interface Props {
  flow: Flow;
}

defineProps<Props>();

const colorClass: Record<string, string> = {
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200 dark:border-blue-800',
  green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
  yellow: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200 dark:border-amber-800',
  red: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border-rose-200 dark:border-rose-800',
  purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 border-purple-200 dark:border-purple-800',
  pink: 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400 border-pink-200 dark:border-pink-800',
  indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
  gray: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700',
};

function stepColorClass(color: string | null): string {
  return colorClass[color ?? 'gray'] ?? colorClass.gray;
}

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
    <Head :title="flow.name" />

    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center gap-4">
        <Link href="/flow/flows" class="text-muted-foreground hover:text-foreground transition-colors">
          <ArrowLeft class="size-5" />
        </Link>
        <div class="flex items-center gap-3 flex-1">
          <GitBranch class="size-6 text-primary shrink-0" />
          <h1 class="text-2xl font-bold text-foreground">{{ flow.name }}</h1>
          <span
            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
            :class="statusClass(flow.status)"
          >
            {{ flow.status }}
          </span>
        </div>
      </div>

      <!-- Step count summary -->
      <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <Layers class="size-4" />
        <span>{{ flow.step_templates.length }} etapa{{ flow.step_templates.length !== 1 ? 's' : '' }}</span>
      </div>

      <!-- Empty state -->
      <div
        v-if="flow.step_templates.length === 0"
        class="flex flex-col items-center justify-center py-16 text-muted-foreground gap-2"
      >
        <Layers class="size-10 opacity-30" />
        <p class="text-sm">Nenhuma etapa cadastrada para este fluxo.</p>
      </div>

      <!-- Steps -->
      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <Card
          v-for="step in flow.step_templates"
          :key="step.id"
          class="border"
          :class="stepColorClass(step.color)"
        >
          <CardHeader class="pb-2">
            <div class="flex items-start justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="text-lg font-bold opacity-50">{{ step.suggested_order }}</span>
                <h3 class="text-sm font-semibold leading-tight">{{ step.name }}</h3>
              </div>
              <span
                v-if="step.is_required_by_default"
                class="shrink-0 rounded-full bg-black/10 dark:bg-white/10 px-2 py-0.5 text-[9px] font-bold uppercase"
              >
                Obrigatória
              </span>
            </div>
          </CardHeader>
          <CardContent class="space-y-3">
            <p v-if="step.description" class="text-xs opacity-80 leading-relaxed">
              {{ step.description }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
              <span v-if="step.estimated_duration_days" class="flex items-center gap-1 text-[10px] font-medium opacity-70">
                <Clock class="size-3" />
                {{ step.estimated_duration_days }}d
              </span>
              <span
                v-for="tag in step.tags"
                :key="tag"
                class="rounded-full bg-black/10 dark:bg-white/10 px-2 py-0.5 text-[9px] font-medium"
              >
                {{ tag }}
              </span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
