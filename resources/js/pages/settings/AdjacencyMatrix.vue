<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdjacencyMatrixController from '@/actions/App/Http/Controllers/Settings/AdjacencyMatrixController';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
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
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/composables/useT';

type Rule = {
    id: string;
    source_category_id: string;
    target_category_id: string;
    source_label: string;
    target_label: string;
    rule_type: string;
    rule_type_label: string;
    rule_type_color: string;
    weight: number;
    reason: string | null;
};

type RuleTypeOption = {
    value: string;
    label: string;
    color: string;
    default_weight: number;
};

type Props = {
    adjacencyHierarchyLevel: number;
    rules: Rule[];
    ruleTypes: RuleTypeOption[];
};

const props = defineProps<Props>();
const { t } = useT();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.adjacency_matrix_settings'),
            href: AdjacencyMatrixController.edit.url(),
        },
    ],
});

const isDialogOpen = ref(false);
const editingRuleId = ref<string | null>(null);

const form = useForm({
    source_category_id: '',
    target_category_id: '',
    rule_type: props.ruleTypes[0]?.value ?? 'prefer_near',
    weight: props.ruleTypes[0]?.default_weight ?? 10,
    reason: '',
});

function openCreateDialog() {
    editingRuleId.value = null;
    form.reset();
    form.clearErrors();
    form.rule_type = props.ruleTypes[0]?.value ?? 'prefer_near';
    form.weight = props.ruleTypes[0]?.default_weight ?? 10;
    form.reason = '';
    form.source_category_id = '';
    form.target_category_id = '';
    isDialogOpen.value = true;
}

function openEditDialog(rule: Rule) {
    editingRuleId.value = rule.id;
    form.source_category_id = rule.source_category_id;
    form.target_category_id = rule.target_category_id;
    form.rule_type = rule.rule_type;
    form.weight = rule.weight;
    form.reason = rule.reason ?? '';
    form.clearErrors();
    isDialogOpen.value = true;
}

function handleRuleTypeChange(value: string) {
    if (value === '') {
        return;
    }

    form.rule_type = String(value);

    const selectedType = props.ruleTypes.find((type) => type.value === form.rule_type);
    if (selectedType && !editingRuleId.value) {
        form.weight = selectedType.default_weight;
    }
}

function submit() {
    if (form.source_category_id === form.target_category_id) {
        form.setError('target_category_id', t('app.messages.adjacency_source_target_must_differ'));
        return;
    }

    const options = {
        onSuccess: () => {
            isDialogOpen.value = false;
        },
    };

    if (editingRuleId.value) {
        form.put(AdjacencyMatrixController.update.url({
            adjacencyRule: editingRuleId.value,
        }), options);
        return;
    }

    form.post(AdjacencyMatrixController.store.url(), options);
}

function destroy(rule: Rule) {
    router.delete(AdjacencyMatrixController.destroy.url({
        adjacencyRule: rule.id,
    }));
}

function badgeVariant(color: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (color === 'danger') {
        return 'destructive';
    }

    if (color === 'info') {
        return 'secondary';
    }

    return 'default';
}
</script>

<template>
    <Head :title="t('app.adjacency_matrix_settings')" />

    <h1 class="sr-only">{{ t('app.adjacency_matrix_settings') }}</h1>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <Heading
                variant="small"
                :title="t('app.adjacency_matrix_settings')"
                :description="t('app.adjacency_matrix_description', { level: String(props.adjacencyHierarchyLevel) })"
            />

            <Button type="button" @click="openCreateDialog">
                {{ t('app.actions.new') }}
            </Button>
        </div>

        <div class="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('app.labels.source_category') }}</TableHead>
                        <TableHead>{{ t('app.labels.target_category') }}</TableHead>
                        <TableHead>{{ t('app.labels.rule_type') }}</TableHead>
                        <TableHead>{{ t('app.labels.weight') }}</TableHead>
                        <TableHead>{{ t('app.labels.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="props.rules.length === 0">
                        <TableCell colspan="5" class="text-muted-foreground text-center">
                            {{ t('app.messages.no_adjacency_rules') }}
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="rule in props.rules" :key="rule.id">
                        <TableCell class="align-top">{{ rule.source_label }}</TableCell>
                        <TableCell class="align-top">{{ rule.target_label }}</TableCell>
                        <TableCell class="align-top">
                            <Badge :variant="badgeVariant(rule.rule_type_color)">
                                {{ rule.rule_type_label }}
                            </Badge>
                        </TableCell>
                        <TableCell class="align-top">{{ rule.weight }}</TableCell>
                        <TableCell class="align-top">
                            <div class="flex gap-2">
                                <Button type="button" variant="outline" size="sm" @click="openEditDialog(rule)">
                                    {{ t('app.actions.edit') }}
                                </Button>
                                <Button type="button" variant="destructive" size="sm" @click="destroy(rule)">
                                    {{ t('app.actions.delete') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>

    <Dialog :open="isDialogOpen" @update:open="isDialogOpen = $event">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ editingRuleId ? t('app.actions.edit') : t('app.actions.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('app.adjacency_matrix_modal_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label>{{ t('app.labels.source_category') }}</Label>
                    <CategoryCascadeSelect
                        :model-value="form.source_category_id || null"
                        :cascade-levels="props.adjacencyHierarchyLevel"
                        :cols="2"
                        :error="form.errors.source_category_id"
                        input-name="source_category_id"
                        @update:model-value="(v) => (form.source_category_id = v ?? '')"
                    />
                </div>

                <div class="space-y-2">
                    <Label>{{ t('app.labels.target_category') }}</Label>
                    <CategoryCascadeSelect
                        :model-value="form.target_category_id || null"
                        :cascade-levels="props.adjacencyHierarchyLevel"
                        :cols="2"
                        :error="form.errors.target_category_id"
                        input-name="target_category_id"
                        @update:model-value="(v) => (form.target_category_id = v ?? '')"
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>{{ t('app.labels.rule_type') }}</Label>
                        <Select :model-value="form.rule_type" @update:model-value="(value) => handleRuleTypeChange(String(value ?? ''))">
                            <SelectTrigger>
                                <SelectValue :placeholder="t('app.labels.rule_type')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in props.ruleTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.rule_type" />
                    </div>

                    <div class="space-y-2">
                        <Label for="weight">{{ t('app.labels.weight') }}</Label>
                        <Input id="weight" v-model.number="form.weight" type="number" min="-100" max="100" step="0.01" />
                        <InputError :message="form.errors.weight" />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="reason">{{ t('app.labels.reason') }}</Label>
                    <Textarea id="reason" v-model="form.reason" rows="3" />
                    <InputError :message="form.errors.reason" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isDialogOpen = false">
                        {{ t('app.actions.cancel') }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t('app.actions.save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
