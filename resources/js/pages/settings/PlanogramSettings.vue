<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ScoringWeightsController from '@/actions/App/Http/Controllers/Settings/ScoringWeightsController';
import ShelfLevelPreferencesController from '@/actions/App/Http/Controllers/Settings/ShelfLevelPreferencesController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useT } from '@/composables/useT';

type WeightsProps = {
    w_giro: number;
    w_margem: number;
    w_estrategico: number;
    w_doh: number;
    sales_window_months: number;
    block_hierarchy_level: number;
    adjacency_hierarchy_level: number;
};

type HierarchyLevel = {
    value: number;
    label: string;
    note: string;
};

type ShelfLevelOption = {
    value: string;
    label: string;
    color: string;
};

type Preference = {
    id: string;
    category_id: string | null;
    category_label: string | null;
    preferred_level: string;
    preferred_level_label: string;
    preferred_level_color: string;
};

type Props = {
    weights: WeightsProps;
    hierarchy_levels: HierarchyLevel[];
    shelf_levels: ShelfLevelOption[];
    preferences: Preference[];
};

const props = defineProps<Props>();
const { t } = useT();

const planogramEditUrl = computed(() =>
    ScoringWeightsController.edit.url().replace('/settings/scoring-weights', '/settings/planogram'),
);

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.planogram_settings'),
            href: planogramEditUrl.value,
        },
    ],
});

const defaults: WeightsProps = {
    w_giro: 0.40,
    w_margem: 0.30,
    w_estrategico: 0.20,
    w_doh: 0.10,
    sales_window_months: 4,
    block_hierarchy_level: 5,
    adjacency_hierarchy_level: 4,
};

const form = useForm({ ...props.weights });

const weightSum = computed(() =>
    +(form.w_giro + form.w_margem + form.w_estrategico + form.w_doh).toFixed(2),
);

const weightSumWarning = computed(() => Math.abs(weightSum.value - 1.0) > 0.05);

const hierarchyWarning = computed(() => form.block_hierarchy_level > 5);

const hierarchyLevelLabel = computed(() => {
    const level = props.hierarchy_levels.find((l) => l.value === form.block_hierarchy_level);

    return level?.label ?? String(form.block_hierarchy_level);
});

const adjacencyLevelLabel = computed(() => {
    const level = props.hierarchy_levels.find((l) => l.value === form.adjacency_hierarchy_level);

    return level?.label ?? String(form.adjacency_hierarchy_level);
});

function submit() {
    form.put(planogramEditUrl.value);
}

function resetToDefaults() {
    form.w_giro = defaults.w_giro;
    form.w_margem = defaults.w_margem;
    form.w_estrategico = defaults.w_estrategico;
    form.w_doh = defaults.w_doh;
    form.sales_window_months = defaults.sales_window_months;
    form.block_hierarchy_level = defaults.block_hierarchy_level;
    form.adjacency_hierarchy_level = defaults.adjacency_hierarchy_level;
}

// ── Shelf preferences dialog ──────────────────────────────────────────────────

const isDialogOpen = ref(false);
const editingPreferenceId = ref<string | null>(null);

const prefForm = useForm({
    category_id: null as string | null,
    preferred_level: props.shelf_levels[0]?.value ?? 'hand',
});

const tenantDefault = computed(() => props.preferences.find((p) => p.category_id === null));
const categoryPreferences = computed(() => props.preferences.filter((p) => p.category_id !== null));

function openCreateDialog() {
    editingPreferenceId.value = null;
    prefForm.reset();
    prefForm.clearErrors();
    prefForm.category_id = null;
    prefForm.preferred_level = props.shelf_levels[0]?.value ?? 'hand';
    isDialogOpen.value = true;
}

function openEditDialog(pref: Preference) {
    editingPreferenceId.value = pref.id;
    prefForm.category_id = pref.category_id;
    prefForm.preferred_level = pref.preferred_level;
    prefForm.clearErrors();
    isDialogOpen.value = true;
}

function submitPref() {
    const options = { onSuccess: () => {
 isDialogOpen.value = false; 
} };

    if (editingPreferenceId.value) {
        prefForm.put(
            ShelfLevelPreferencesController.update.url({ preference: editingPreferenceId.value }),
            options,
        );

        return;
    }

    prefForm.post(ShelfLevelPreferencesController.store.url(), options);
}

function destroyPref(pref: Preference) {
    router.delete(ShelfLevelPreferencesController.destroy.url({ preference: pref.id }));
}

function badgeVariant(color: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (color === 'warning') {
return 'outline';
}

    if (color === 'secondary') {
return 'secondary';
}

    return 'default';
}
</script>

<template>
    <Head :title="t('app.planogram_settings')" />

    <h1 class="sr-only">{{ t('app.planogram_settings') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('app.planogram_settings')"
            :description="t('app.planogram_settings_description')"
        />

        <form class="space-y-10" @submit.prevent="submit">

            <!-- ── Seção 1: Hierarquia ────────────────────────────────── -->
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold">Hierarquia Mercadológica</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Define em qual nível da árvore de categorias o sistema agrupa produtos e calcula adjacências.
                        Recomendamos Subcategoria (5) para agrupamento e Categoria (4) para adjacência — padrão GS1/ABRAS.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <!-- Nível de agrupamento -->
                    <div class="space-y-2">
                        <Label for="block_hierarchy_level">
                            Nível de agrupamento
                        </Label>
                        <p class="text-muted-foreground text-xs">Produtos do mesmo nível ficam juntos na gôndola</p>
                        <Select
                            :model-value="String(form.block_hierarchy_level)"
                            @update:model-value="(v) => (form.block_hierarchy_level = Number(v))"
                        >
                            <SelectTrigger>
                                <SelectValue :placeholder="hierarchyLevelLabel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="level in hierarchy_levels"
                                    :key="level.value"
                                    :value="String(level.value)"
                                >
                                    {{ level.value }} — {{ level.label }}
                                    <span class="text-muted-foreground ml-1 text-xs">{{ level.note }}</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div class="flex items-center gap-2">
                            <Badge v-if="form.block_hierarchy_level === 5" variant="default" class="text-xs">
                                Recomendado
                            </Badge>
                            <p v-if="hierarchyWarning" class="text-amber-600 text-xs">
                                Atenção: níveis 6 e 7 podem ter poucos dados preenchidos neste tenant
                            </p>
                        </div>
                        <InputError :message="form.errors.block_hierarchy_level" />
                    </div>

                    <!-- Nível de adjacência -->
                    <div class="space-y-2">
                        <Label for="adjacency_hierarchy_level">
                            Nível de adjacência
                        </Label>
                        <p class="text-muted-foreground text-xs">Nível usado para as regras de proximidade entre categorias</p>
                        <Select
                            :model-value="String(form.adjacency_hierarchy_level)"
                            @update:model-value="(v) => (form.adjacency_hierarchy_level = Number(v))"
                        >
                            <SelectTrigger>
                                <SelectValue :placeholder="adjacencyLevelLabel" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="level in hierarchy_levels"
                                    :key="level.value"
                                    :value="String(level.value)"
                                >
                                    {{ level.value }} — {{ level.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Badge v-if="form.adjacency_hierarchy_level === 4" variant="default" class="text-xs">
                            Recomendado
                        </Badge>
                        <InputError :message="form.errors.adjacency_hierarchy_level" />
                    </div>
                </div>
            </div>

            <!-- ── Seção 2: Scoring ───────────────────────────────────── -->
            <div class="space-y-4">
                <div>
                    <h2 class="text-base font-semibold">Critério de Score</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Define o peso de cada fator no ranking dos produtos.
                        A soma dos pesos não precisa ser 1 — o sistema normaliza automaticamente.
                    </p>
                </div>

                <!-- Janela de vendas -->
                <div class="space-y-2">
                    <Label for="sales_window_months">Janela de vendas</Label>
                    <p class="text-muted-foreground text-xs">Período usado para calcular giro e margem</p>
                    <Select
                        :model-value="String(form.sales_window_months)"
                        @update:model-value="(v) => (form.sales_window_months = Number(v))"
                    >
                        <SelectTrigger class="w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">Último mês</SelectItem>
                            <SelectItem value="3">Últimos 3 meses</SelectItem>
                            <SelectItem value="4">Últimos 4 meses (padrão)</SelectItem>
                            <SelectItem value="6">Últimos 6 meses</SelectItem>
                            <SelectItem value="12">Último ano</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.sales_window_months" />
                </div>

                <!-- Pesos -->
                <div class="space-y-4">
                    <div v-for="(entry, key) in ({
                        w_giro: 'Giro (quantidade vendida)',
                        w_margem: 'Margem de contribuição',
                        w_estrategico: 'Produto estratégico',
                        w_doh: 'Cobertura de estoque (DOH)',
                    } as const)" :key="key" class="space-y-1">
                        <div class="flex items-center justify-between">
                            <Label :for="key">{{ entry }}</Label>
                            <span class="text-muted-foreground text-sm font-medium">{{ form[key] }}</span>
                        </div>
                        <input
                            :id="key"
                            v-model.number="form[key]"
                            class="accent-primary w-full cursor-pointer"
                            type="range"
                            min="0"
                            max="1"
                            step="0.05"
                        />
                        <InputError :message="form.errors[key]" />
                    </div>
                </div>

                <!-- Soma dos pesos -->
                <p class="text-muted-foreground text-sm">
                    Soma dos pesos:
                    <span :class="{ 'text-destructive font-semibold': weightSumWarning }">{{ weightSum }}</span>
                    <span v-if="weightSumWarning" class="text-muted-foreground ml-2 text-xs">
                        (pesos serão normalizados automaticamente)
                    </span>
                </p>
            </div>

            <!-- ── Footer ─────────────────────────────────────────────── -->
            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing">
                    {{ t('app.actions.save') }}
                </Button>
                <Button type="button" variant="outline" :disabled="form.processing" @click="resetToDefaults">
                    {{ t('app.actions.restore_defaults') }}
                </Button>
            </div>
        </form>

        <!-- ── Seção 3: Preferências de nível por categoria ───────────── -->
        <div class="space-y-4 border-t pt-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Nível de Prateleira por Categoria</h2>
                    <p class="text-muted-foreground mt-1 text-sm">
                        Define em qual nível de prateleira cada categoria deve ser posicionada.
                        Sem configuração explícita, o sistema decide pelo score do produto.
                    </p>
                </div>
                <Button type="button" @click="openCreateDialog">
                    {{ t('app.actions.new') }}
                </Button>
            </div>

            <!-- Padrão do tenant -->
            <div v-if="tenantDefault" class="bg-muted/50 rounded-lg border p-4">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-sm font-medium">{{ t('app.labels.tenant_default') }}</p>
                        <Badge :variant="badgeVariant(tenantDefault.preferred_level_color)">
                            {{ tenantDefault.preferred_level_label }}
                        </Badge>
                    </div>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" size="sm" @click="openEditDialog(tenantDefault)">
                            {{ t('app.actions.edit') }}
                        </Button>
                        <Button type="button" variant="destructive" size="sm" @click="destroyPref(tenantDefault)">
                            {{ t('app.actions.delete') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Tabela de preferências por categoria -->
            <div class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ t('app.labels.category') }}</TableHead>
                            <TableHead>{{ t('app.labels.preferred_level') }}</TableHead>
                            <TableHead>{{ t('app.labels.actions') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="categoryPreferences.length === 0">
                            <TableCell colspan="3" class="text-muted-foreground text-center">
                                {{ t('app.messages.no_shelf_level_preferences') }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="pref in categoryPreferences" :key="pref.id">
                            <TableCell class="align-top">{{ pref.category_label }}</TableCell>
                            <TableCell class="align-top">
                                <Badge :variant="badgeVariant(pref.preferred_level_color)">
                                    {{ pref.preferred_level_label }}
                                </Badge>
                            </TableCell>
                            <TableCell class="align-top">
                                <div class="flex gap-2">
                                    <Button type="button" variant="outline" size="sm" @click="openEditDialog(pref)">
                                        {{ t('app.actions.edit') }}
                                    </Button>
                                    <Button type="button" variant="destructive" size="sm" @click="destroyPref(pref)">
                                        {{ t('app.actions.delete') }}
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </div>

    <!-- ── Dialog: Adicionar / Editar preferência ─────────────────────── -->
    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ editingPreferenceId ? t('app.actions.edit') : t('app.actions.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('app.shelf_level_preferences_modal_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitPref">
                <div class="space-y-2">
                    <Label>{{ t('app.labels.category') }}</Label>
                    <CategoryCascadeSelect
                        v-model="prefForm.category_id"
                        :cascade-levels="4"
                        :cols="2"
                        :error="prefForm.errors.category_id"
                        input-name="category_id"
                    />
                    <p class="text-muted-foreground text-xs">{{ t('app.labels.tenant_default_hint') }}</p>
                </div>

                <div class="space-y-2">
                    <Label>{{ t('app.labels.preferred_level') }}</Label>
                    <Select
                        :model-value="prefForm.preferred_level"
                        @update:model-value="(v) => (prefForm.preferred_level = String(v ?? ''))"
                    >
                        <SelectTrigger>
                            <SelectValue :placeholder="t('app.labels.select_level')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="level in shelf_levels" :key="level.value" :value="level.value">
                                {{ level.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="prefForm.errors.preferred_level" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isDialogOpen = false">
                        {{ t('app.actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="prefForm.processing">
                        {{ t('app.actions.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
