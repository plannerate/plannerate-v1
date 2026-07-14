<script setup lang="ts">
/**
 * Cadência de reotimização da gôndola: liga/desliga, ritmo e disparo sob demanda.
 *
 * Só existe para gôndolas em modo template. No modo automático o motor sintetiza o template no
 * banco durante a geração — a "simulação" deixaria rastro, e a promessa da proposta (nada muda
 * até você aprovar) seria falsa. Aqui isso vira um estado desabilitado com explicação, não um
 * botão que falha depois.
 */
import { router } from '@inertiajs/vue3';
import { CalendarClock, Play } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FieldHelpTooltip from '@/components/form/FieldHelpTooltip.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useT } from '@/composables/useT';
import type { Gondola } from '../../../types/planogram';

const props = defineProps<{ gondola: Gondola }>();

const { t } = useT();

type Frequency = 'weekly' | 'biweekly' | 'monthly';

const FREQUENCIES: Frequency[] = ['weekly', 'biweekly', 'monthly'];

const enabled = ref<boolean>(props.gondola.reoptimization_enabled ?? false);
const frequency = ref<Frequency>((props.gondola.reoptimization_frequency ?? 'monthly') as Frequency);
const saving = ref(false);
const analyzing = ref(false);

/** Sem template não há como simular — o motor precisaria sintetizar um, e isso grava no banco. */
const canReoptimize = computed(() => Boolean(props.gondola.template_id));

const nextRunLabel = computed(() => {
    if (!enabled.value || !props.gondola.reoptimization_next_run_at) {
        return null;
    }

    return t('plannerate.reoptimization.cadence.next_run', {
        date: new Date(props.gondola.reoptimization_next_run_at).toLocaleDateString('pt-BR'),
    });
});

const lastRunLabel = computed(() => {
    if (!props.gondola.reoptimization_last_run_at) {
        return t('plannerate.reoptimization.cadence.never_run');
    }

    return t('plannerate.reoptimization.cadence.last_run', {
        date: new Date(props.gondola.reoptimization_last_run_at).toLocaleDateString('pt-BR'),
    });
});

function save(): void {
    saving.value = true;

    router.put(
        `/api/gondolas/${props.gondola.id}/reoptimization`,
        {
            reoptimization_enabled: enabled.value,
            reoptimization_frequency: enabled.value ? frequency.value : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => toast.success(t('plannerate.reoptimization.messages.cadence_saved')),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? ''),
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function analyzeNow(): void {
    analyzing.value = true;

    router.post(
        `/api/gondolas/${props.gondola.id}/reoptimization/run-now`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success(t('plannerate.reoptimization.messages.queued')),
            onError: (errors) => toast.error(Object.values(errors)[0] ?? ''),
            onFinish: () => {
                analyzing.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="rounded-lg border border-border bg-muted/20 p-3">
        <div class="mb-2 flex items-center gap-2">
            <CalendarClock class="size-4 text-muted-foreground" />
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {{ t('plannerate.reoptimization.cadence.title') }}
            </p>
            <FieldHelpTooltip :text="t('plannerate.reoptimization.cadence.help')" side="right" />
        </div>

        <p class="mb-3 text-xs text-muted-foreground">
            {{ t('plannerate.reoptimization.cadence.description') }}
        </p>

        <!-- Sem template: explica em vez de oferecer um botão que vai falhar -->
        <p
            v-if="!canReoptimize"
            class="rounded border border-dashed border-border px-2 py-3 text-center text-xs text-muted-foreground"
        >
            {{ t('plannerate.reoptimization.cadence.requires_template_hint') }}
        </p>

        <template v-else>
            <div class="flex items-center justify-between gap-2">
                <Label for="reoptimization-enabled" class="text-xs font-normal">
                    {{ t('plannerate.reoptimization.cadence.enabled') }}
                </Label>
                <Switch id="reoptimization-enabled" v-model="enabled" :disabled="saving" @update:model-value="save" />
            </div>

            <div v-if="enabled" class="mt-3 space-y-1.5">
                <Label for="reoptimization-frequency" class="text-xs font-normal text-muted-foreground">
                    {{ t('plannerate.reoptimization.cadence.frequency') }}
                </Label>
                <Select id="reoptimization-frequency" v-model="frequency" :disabled="saving" @update:model-value="save">
                    <SelectTrigger class="h-8 text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="option in FREQUENCIES" :key="option" :value="option" class="text-xs">
                            {{ t(`plannerate.reoptimization.frequency.${option}`) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <p v-if="nextRunLabel" class="mt-2 text-[11px] text-muted-foreground">{{ nextRunLabel }}</p>
            <p class="mt-1 text-[11px] text-muted-foreground">{{ lastRunLabel }}</p>

            <Button
                variant="outline"
                size="sm"
                class="mt-3 w-full"
                type="button"
                :disabled="analyzing"
                @click="analyzeNow"
            >
                <span
                    v-if="analyzing"
                    class="mr-2 size-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
                />
                <Play v-else class="mr-2 size-3.5" />
                {{ t('plannerate.reoptimization.cadence.run_now') }}
            </Button>
        </template>
    </div>
</template>
