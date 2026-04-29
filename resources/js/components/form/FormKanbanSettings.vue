<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import WorkflowPlanogramStepController from '@/actions/App/Http/Controllers/Tenant/WorkflowPlanogramStepController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import Popover from '@/components/ui/popover/Popover.vue';

type PlanogramPayload = {
    id: string;
};

type UserOption = {
    id: string;
    name: string;
};

type WorkflowStepSetting = {
    id: string;
    workflow_template_id: string;
    name: string;
    description: string | null;
    estimated_duration_days: number | null;
    role_id: string | null;
    is_required: boolean;
    is_skipped: boolean;
    status: string;
    suggested_order: number;
    selected_user_ids: string[];
};

type WorkflowSettingsResponse = {
    steps: WorkflowStepSetting[];
    users: UserOption[];
};

type WorkflowSettingsSaveStepPayload = {
    step_id: string;
    is_required: boolean;
    is_skipped: boolean;
    estimated_duration_days: number | null;
    user_ids: string[];
};

const props = defineProps<{
    subdomain: string;
    planogram: PlanogramPayload;
}>();

const loading = ref(false);
const saving = ref(false);
const loadingDefaults = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const users = ref<UserOption[]>([]);
const steps = ref<WorkflowStepSetting[]>([]);
const userSearchByStep = ref<Record<string, string>>({});
const http = useHttp();
const saveHttp = useHttp<{ steps: WorkflowSettingsSaveStepPayload[] }>({
    steps: [],
});

const hasSteps = computed(() => steps.value.length > 0);

function filteredUsers(stepId: string): UserOption[] {
    const search = (userSearchByStep.value[stepId] ?? '').trim().toLowerCase();

    if (search === '') {
        return users.value;
    }

    return users.value.filter((user) => user.name.toLowerCase().includes(search));
}

function isUserSelected(step: WorkflowStepSetting, userId: string): boolean {
    return step.selected_user_ids.includes(userId);
}

function toggleStepUser(step: WorkflowStepSetting, userId: string): void {
    if (step.selected_user_ids.includes(userId)) {
        step.selected_user_ids = step.selected_user_ids.filter((id) => id !== userId);

        return;
    }

    step.selected_user_ids = [...step.selected_user_ids, userId];
}

function selectedUsersLabel(step: WorkflowStepSetting): string {
    if (step.selected_user_ids.length === 0) {
        return 'Nenhum usuário selecionado';
    }

    if (step.selected_user_ids.length === 1) {
        const selectedUser = users.value.find((user) => user.id === step.selected_user_ids[0]);

        return selectedUser?.name ?? '1 usuário selecionado';
    }

    return `${step.selected_user_ids.length} usuários selecionados`;
}

function updateEstimatedDurationDays(step: WorkflowStepSetting, rawValue: string): void {
    if (rawValue.trim() === '') {
        step.estimated_duration_days = null;

        return;
    }

    const parsedValue = Number.parseInt(rawValue, 10);
    step.estimated_duration_days = Number.isNaN(parsedValue) ? null : Math.max(0, parsedValue);
}

async function loadSettings(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        const payload = await http.submit(
            WorkflowPlanogramStepController.index({
                subdomain: props.subdomain,
                planogram: props.planogram.id,
            }),
        ) as WorkflowSettingsResponse;

        users.value = payload.users ?? [];
        steps.value = payload.steps ?? [];
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Não foi possível carregar as configurações do Kanban.';
    } finally {
        loading.value = false;
    }
}

async function saveSettings(): Promise<void> {
    saving.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        saveHttp.steps = steps.value.map((step) => ({
            step_id: step.id,
            is_required: step.is_required,
            is_skipped: step.is_skipped,
            estimated_duration_days: step.estimated_duration_days,
            user_ids: step.selected_user_ids,
        }));

        const payload = await saveHttp.submit(
            WorkflowPlanogramStepController.update({
                subdomain: props.subdomain,
                planogram: props.planogram.id,
            }),
        ) as WorkflowSettingsResponse;

        users.value = payload.users ?? users.value;
        steps.value = payload.steps ?? steps.value;
        successMessage.value = 'Configurações salvas com sucesso.';
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Não foi possível salvar as configurações do Kanban.';
    } finally {
        saving.value = false;
    }
}

async function loadDefaultSettings(): Promise<void> {
    const hadStepsBeforeLoading = hasSteps.value;
    loadingDefaults.value = true;
    errorMessage.value = null;
    successMessage.value = null;

    try {
        const payload = await http.submit(
            WorkflowPlanogramStepController.loadDefaults({
                subdomain: props.subdomain,
                planogram: props.planogram.id,
            }),
        ) as WorkflowSettingsResponse;

        users.value = payload.users ?? users.value;
        steps.value = payload.steps ?? steps.value;
        successMessage.value = hadStepsBeforeLoading
            ? 'Configuração padrão carregada com sucesso.'
            : 'Configuração padrão criada com sucesso.';
    } catch (error) {
        console.error(error);
        errorMessage.value = 'Não foi possível carregar a configuração padrão.';
    } finally {
        loadingDefaults.value = false;
    }
}

onMounted(() => {
    void loadSettings();
});
</script>

<template>
    <div class="space-y-4 md:col-span-12">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4">
            <div>
                <h3 class="text-sm font-semibold text-foreground">
                    Configuração de etapas do Kanban
                </h3>
                <p class="text-xs text-muted-foreground">
                    As etapas do tenant são fixas. Aqui você define obrigatório, pular etapa e usuários permitidos por planograma.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" variant="outline" :disabled="loading || saving || loadingDefaults" @click="loadDefaultSettings">
                    {{
                        loadingDefaults
                            ? 'Carregando...'
                            : hasSteps
                              ? 'Carregar configuração padrão'
                              : 'Criar configuração padrão'
                    }}
                </Button>
                <Button type="button" variant="gradient" :disabled="loading || saving || loadingDefaults" @click="saveSettings">
                    {{ saving ? 'Salvando...' : 'Salvar configurações' }}
                </Button>
            </div>
        </div>

        <div v-if="errorMessage" class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {{ errorMessage }}
        </div>

        <div v-if="successMessage" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-700 dark:text-emerald-400">
            {{ successMessage }}
        </div>

        <div v-if="loading" class="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
            Carregando configurações...
        </div>

        <div v-else-if="!hasSteps" class="rounded-lg border border-dashed border-border p-4 text-sm text-muted-foreground">
            Nenhuma etapa publicada foi encontrada para este tenant.
        </div>

        <div v-else class="space-y-3">
            <div v-for="step in steps" :key="step.id" class="rounded-lg border border-border bg-background p-4">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-foreground">
                                {{ step.name }}
                            </p>
                            <Badge variant="outline">
                                #{{ step.suggested_order }}
                            </Badge>
                            <Badge v-if="step.is_skipped" variant="secondary">
                                Pulada
                            </Badge>
                        </div>
                        <p v-if="step.description" class="mt-1 text-xs text-muted-foreground">
                            {{ step.description }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge v-if="step.estimated_duration_days !== null" variant="outline">
                                {{ step.estimated_duration_days }} dia(s)
                            </Badge>
                            <Badge v-if="step.role_id" variant="outline">
                                Perfil: {{ step.role_id }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                    <div class="md:col-span-3">
                        <p class="mb-1 text-xs font-medium text-transparent select-none">
                            Ação
                        </p>
                        <label class="flex h-9 items-center gap-2 text-sm text-foreground">
                            <input v-model="step.is_required" type="checkbox" class="size-4 rounded border-input accent-primary" />
                            Obrigatória
                        </label>
                    </div>

                    <div class="md:col-span-3">
                        <p class="mb-1 text-xs font-medium text-transparent select-none">
                            Ação
                        </p>
                        <label class="flex h-9 items-center gap-2 text-sm text-foreground">
                            <input v-model="step.is_skipped" type="checkbox" class="size-4 rounded border-input accent-primary" />
                            Pular etapa
                        </label>
                    </div>

                    <div class="md:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">
                            Duração estimada (dias)
                        </label>
                        <input
                            type="number"
                            min="0"
                            placeholder="Ex.: 3"
                            :value="step.estimated_duration_days ?? ''"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                            @input="updateEstimatedDurationDays(step, ($event.target as HTMLInputElement).value)"
                        />
                    </div>

                    <div class="md:col-span-3">
                        <p class="mb-1 text-xs font-medium text-transparent select-none">
                            Usuários
                        </p>
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button type="button" variant="outline" class="w-full justify-start gap-2 text-left">
                                    <Users class="size-4" />
                                    <span class="truncate">{{ selectedUsersLabel(step) }}</span>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent align="start" class="w-80 space-y-3 p-3">
                                <input
                                    v-model="userSearchByStep[step.id]"
                                    type="text"
                                    placeholder="Buscar usuário..."
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                                />

                                <div class="max-h-56 space-y-1 overflow-y-auto">
                                    <label
                                        v-for="user in filteredUsers(step.id)"
                                        :key="user.id"
                                        class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm transition hover:bg-muted"
                                    >
                                        <input
                                            type="checkbox"
                                            class="size-4 rounded border-input accent-primary"
                                            :checked="isUserSelected(step, user.id)"
                                            @change="toggleStepUser(step, user.id)"
                                        />
                                        <span class="truncate">{{ user.name }}</span>
                                    </label>

                                    <p v-if="filteredUsers(step.id).length === 0" class="px-2 py-1 text-xs text-muted-foreground">
                                        Nenhum usuário encontrado.
                                    </p>
                                </div>
                            </PopoverContent>
                        </Popover>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
