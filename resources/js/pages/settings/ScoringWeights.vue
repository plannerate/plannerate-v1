<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import ScoringWeightsController from '@/actions/App/Http/Controllers/Settings/ScoringWeightsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type WeightsProps = {
    w_giro: number;
    w_margem: number;
    w_estrategico: number;
    w_doh: number;
    w_crescimento: number;
    sales_window_months: number;
    block_hierarchy_level: number;
    adjacency_hierarchy_level: number;
};

type Props = {
    weights: WeightsProps;
};

const props = defineProps<Props>();
const { t } = useT();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.scoring_weights_settings'),
            href: ScoringWeightsController.edit.url(),
        },
    ],
});

const form = useForm({
    w_giro: props.weights.w_giro,
    w_margem: props.weights.w_margem,
    w_estrategico: props.weights.w_estrategico,
    w_doh: props.weights.w_doh,
    w_crescimento: props.weights.w_crescimento,
    sales_window_months: props.weights.sales_window_months,
    block_hierarchy_level: props.weights.block_hierarchy_level,
    adjacency_hierarchy_level: props.weights.adjacency_hierarchy_level,
});

const weightSum = computed(() =>
    +(form.w_giro + form.w_margem + form.w_estrategico + form.w_doh + form.w_crescimento).toFixed(2),
);

function submit() {
    form.put(ScoringWeightsController.update.url());
}

function resetToDefault() {
    form.w_giro = 0.40;
    form.w_margem = 0.30;
    form.w_estrategico = 0.20;
    form.w_doh = 0.10;
    form.w_crescimento = 0.00;
    form.sales_window_months = 4;
    form.block_hierarchy_level = 5;
    form.adjacency_hierarchy_level = 4;
}

const sliders: { key: keyof WeightsProps; label: string; hint?: string }[] = [
    { key: 'w_giro', label: t('app.labels.w_giro') },
    { key: 'w_margem', label: t('app.labels.w_margem') },
    { key: 'w_estrategico', label: t('app.labels.w_estrategico') },
    { key: 'w_doh', label: t('app.labels.w_doh') },
    {
        key: 'w_crescimento',
        label: t('app.labels.w_crescimento'),
        hint: 'Padrao 0 (inativo). Ative para que produtos BCG Estrela e Interrogacao subam no score vs. Abacaxi.',
    },
];
</script>

<template>
    <Head :title="t('app.scoring_weights_settings')" />

    <h1 class="sr-only">{{ t('app.scoring_weights_settings') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('app.scoring_weights_settings')"
            :description="t('app.scoring_weights_description')"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div
                v-for="slider in sliders"
                :key="slider.key"
                class="grid gap-2"
            >
                <div class="flex items-center justify-between">
                    <Label :for="slider.key">{{ slider.label }}</Label>
                    <span class="text-muted-foreground text-sm font-medium">
                        {{ form[slider.key] }}
                    </span>
                </div>
                <input
                    :id="slider.key"
                    v-model.number="form[slider.key]"
                    class="accent-primary w-full cursor-pointer"
                    type="range"
                    min="0"
                    max="1"
                    step="0.05"
                />
                <p v-if="slider.hint" class="text-muted-foreground text-xs">{{ slider.hint }}</p>
                <InputError :message="form.errors[slider.key]" />
            </div>

            <div class="grid gap-2">
                <Label for="sales_window_months">
                    {{ t('app.labels.sales_window_months') }}
                </Label>
                <Input
                    id="sales_window_months"
                    v-model.number="form.sales_window_months"
                    class="w-32"
                    type="number"
                    min="1"
                    max="24"
                />
                <InputError :message="form.errors.sales_window_months" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="block_hierarchy_level">
                        {{ t('app.labels.block_hierarchy_level') }}
                    </Label>
                    <Input
                        id="block_hierarchy_level"
                        v-model.number="form.block_hierarchy_level"
                        class="w-32"
                        type="number"
                        min="1"
                        max="7"
                    />
                    <InputError :message="form.errors.block_hierarchy_level" />
                </div>

                <div class="grid gap-2">
                    <Label for="adjacency_hierarchy_level">
                        {{ t('app.labels.adjacency_hierarchy_level') }}
                    </Label>
                    <Input
                        id="adjacency_hierarchy_level"
                        v-model.number="form.adjacency_hierarchy_level"
                        class="w-32"
                        type="number"
                        min="1"
                        max="7"
                    />
                    <InputError :message="form.errors.adjacency_hierarchy_level" />
                </div>
            </div>

            <p class="text-muted-foreground text-sm">
                {{ t('app.labels.weight_sum') }}:
                <span
                    :class="{ 'text-destructive font-semibold': weightSum > 1 }"
                >{{ weightSum }}</span>
            </p>

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ t('app.actions.save') }}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="form.processing"
                    @click="resetToDefault"
                >
                    {{ t('app.actions.restore_defaults') }}
                </Button>
            </div>
        </form>
    </div>
</template>
