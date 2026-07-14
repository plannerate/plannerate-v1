<script setup lang="ts">
/**
 * Aviso, no editor, de que existe uma proposta de reotimização esperando decisão.
 *
 * Sem isto, a proposta ficaria só na notificação — que o usuário pode ter perdido — e um layout
 * potencialmente melhor apodreceria na fila sem ninguém saber que existe.
 */
import { Link } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { useReoptimizationProposal } from '@/composables/plannerate/reoptimization/useReoptimizationProposal';

const props = defineProps<{ gondolaId: string }>();

const { t } = useT();
const { proposal } = useReoptimizationProposal(props.gondolaId);
</script>

<template>
    <div
        v-if="proposal"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 dark:border-violet-800 dark:bg-violet-950/40"
    >
        <div class="flex items-start gap-3">
            <Sparkles class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-400" />
            <div>
                <p class="text-sm font-medium text-violet-900 dark:text-violet-100">
                    {{ t('plannerate.reoptimization.banner.title') }}
                </p>
                <p class="text-xs text-violet-700 dark:text-violet-300">
                    {{ t('plannerate.reoptimization.banner.message', { count: String(proposal.changes_count) }) }}
                </p>
            </div>
        </div>

        <Button as-child size="sm" class="bg-violet-600 text-white hover:bg-violet-700">
            <Link :href="proposal.url">{{ t('plannerate.reoptimization.banner.action') }}</Link>
        </Button>
    </div>
</template>
