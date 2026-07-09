<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import RoleController from '@/actions/App/Http/Controllers/Landlord/RoleController';
import FormSlugField from '@/components/form/FormSlugField.vue';
import FormCard from '@/components/FormCard.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudPageMeta } from '@/composables/useCrudPageMeta';
import { useT } from '@/composables/useT';
import AppLayout from '@/layouts/AppLayout.vue';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

type RolePayload = {
    id: string;
    type: string;
    name: string;
    system_name: string | null;
    is_administrative: boolean;
    permissions: string[];
    is_protected: boolean;
};

type PermissionOption = {
    name: string;
    type: string;
    short_name: string | null;
    description: string | null;
};

type TypeOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    role: RolePayload | null;
    types: TypeOption[];
    permissions: PermissionOption[];
}>();

const { t } = useT();
const isEdit = computed(() => props.role !== null);
const isProtected = computed(() => props.role?.is_protected ?? false);
const rolesIndexPath = RoleController.index.url().replace(/^\/\/[^/]+/, '');
const selectedType = ref(
    props.role?.type ?? props.types[0]?.value ?? 'landlord',
);
const permissionSearch = ref('');
const name = ref(props.role?.name ?? '');

/**
 * Permissões selecionadas (modelo reativo). Mantido separado da lista filtrada
 * para que filtrar por texto NÃO perca seleções que ficaram fora do filtro.
 */
const selectedPermissions = ref<string[]>([...(props.role?.permissions ?? [])]);

/** Mapa nome-da-permissão -> tipo, usado ao trocar o tipo do perfil. */
const permissionTypeByName = computed(() => {
    const map: Record<string, string> = {};

    for (const permission of props.permissions) {
        map[permission.name] = permission.type;
    }

    return map;
});

const filteredPermissions = computed(() =>
    props.permissions.filter((p) => {
        if (p.type !== selectedType.value) {
            return false;
        }

        if (!permissionSearch.value) {
            return true;
        }

        const term = permissionSearch.value.toLowerCase();

        return (
            p.name.toLowerCase().includes(term) ||
            (p.short_name?.toLowerCase().includes(term) ?? false) ||
            (p.description?.toLowerCase().includes(term) ?? false)
        );
    }),
);

/** Verdadeiro quando todas as permissões visíveis (filtradas) estão marcadas. */
const allFilteredSelected = computed(
    () =>
        filteredPermissions.value.length > 0 &&
        filteredPermissions.value.every((p) =>
            selectedPermissions.value.includes(p.name),
        ),
);

/** Verdadeiro quando ao menos uma permissão visível está marcada. */
const someFilteredSelected = computed(() =>
    filteredPermissions.value.some((p) =>
        selectedPermissions.value.includes(p.name),
    ),
);

/** Estado indeterminado do "selecionar todas": algumas, mas não todas. */
const selectAllIndeterminate = computed(
    () => someFilteredSelected.value && !allFilteredSelected.value,
);

/**
 * Alterna a seleção de todas as permissões atualmente visíveis (filtradas),
 * preservando as que estão fora do filtro.
 */
function toggleAllFiltered(): void {
    const visibleNames = filteredPermissions.value.map((p) => p.name);

    if (allFilteredSelected.value) {
        selectedPermissions.value = selectedPermissions.value.filter(
            (name) => !visibleNames.includes(name),
        );

        return;
    }

    const merged = new Set(selectedPermissions.value);

    for (const name of visibleNames) {
        merged.add(name);
    }

    selectedPermissions.value = [...merged];
}

// Ao trocar o tipo do perfil, remove permissões que não pertencem ao novo tipo,
// evitando enviar permissões inválidas para o backend.
watch(selectedType, (type) => {
    selectedPermissions.value = selectedPermissions.value.filter(
        (name) => permissionTypeByName.value[name] === type,
    );
});

const pageMeta = useCrudPageMeta({
    headTitle: isEdit.value
        ? t('app.landlord.roles.actions.edit')
        : t('app.landlord.roles.actions.new'),
    title: isEdit.value
        ? t('app.landlord.roles.actions.edit')
        : t('app.landlord.roles.actions.new'),
    description: t('app.landlord.roles.description'),
    breadcrumbs: [
        {
            title: t('app.landlord.roles.navigation'),
            href: rolesIndexPath,
        },
        {
            title: isEdit.value
                ? t('app.landlord.common.edit')
                : t('app.landlord.common.create'),
            href: isEdit.value
                ? tenantWayfinderPath(RoleController.edit.url(props.role!.id))
                : tenantWayfinderPath(RoleController.create.url()),
        },
    ],
});
</script>

<template>
    <Head :title="pageMeta.headTitle" />
    <AppLayout :breadcrumbs="pageMeta.breadcrumbs" :page-header="pageMeta">
        <div class="p-4">
            <Form
                v-bind="
                    isEdit
                        ? {
                              ...RoleController.update.form(props.role!.id),
                              action: tenantWayfinderPath(
                                  RoleController.update.form(props.role!.id)
                                      .action,
                              ),
                          }
                        : {
                              ...RoleController.store.form(),
                              action: tenantWayfinderPath(
                                  RoleController.store.form().action,
                              ),
                          }
                "
                v-slot="{ errors, processing }"
            >
                <FormCard
                    :processing="processing"
                    :cancel-href="rolesIndexPath"
                    :title="pageMeta.title"
                    :description="pageMeta.description"
                >
                    <template #icon>
                        <ShieldCheck class="size-5" />
                    </template>

                    <template v-if="isProtected" #header-extra>
                        <Badge variant="secondary" class="gap-1.5 text-xs">
                            <ShieldCheck class="size-3" />
                            {{ t('app.landlord.common.protected') }}
                        </Badge>
                    </template>

                    <template v-if="isProtected" #before>
                        <div
                            class="flex items-start gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-600 dark:text-amber-400"
                        >
                            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
                            <span>{{ t('app.landlord.roles.protected') }}</span>
                        </div>
                    </template>

                    <!-- Type -->
                    <div class="grid gap-2">
                        <Label for="type">{{
                            t('app.landlord.roles.fields.type')
                        }}</Label>
                        <select
                            id="type"
                            name="type"
                            v-model="selectedType"
                            :disabled="isProtected"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground transition outline-none focus:border-primary/60 focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"
                            required
                        >
                            <option
                                v-for="type in props.types"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option>
                        </select>
                        <!-- Perfis protegidos têm o select desabilitado; envia o tipo atual
                             por um campo oculto para que a validação (type obrigatório) passe. -->
                        <input
                            v-if="isProtected"
                            type="hidden"
                            name="type"
                            :value="selectedType"
                        />
                        <InputError :message="errors.type" />
                    </div>

                    <!-- Name -->
                    <div class="grid gap-2">
                        <Label for="name">{{
                            t('app.landlord.roles.fields.name')
                        }}</Label>
                        <Input
                            id="name"
                            v-model="name"
                            name="name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- System Name: gerado a partir do nome na criação e imutável na edição (slug). -->
                    <FormSlugField
                        id="system_name"
                        name="system_name"
                        :label="t('app.landlord.roles.fields.system_name')"
                        :source="name"
                        :default-value="props.role?.system_name ?? ''"
                        :disabled="isEdit"
                        :error="errors.system_name"
                    />

                    <!-- Perfil administrativo: conta no limite de usuários do plano.
                         Só faz sentido para perfis de tenant. -->
                    <label
                        v-if="selectedType === 'tenant'"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5"
                    >
                        <input type="hidden" name="is_administrative" value="0" />
                        <input
                            id="is_administrative"
                            name="is_administrative"
                            type="checkbox"
                            value="1"
                            :checked="props.role?.is_administrative ?? false"
                            class="accent-primary"
                        />
                        <div>
                            <span class="text-sm font-medium">{{ t('app.landlord.roles.fields.is_administrative') }}</span>
                            <p class="text-xs text-muted-foreground">{{ t('app.landlord.roles.fields.is_administrative_hint') }}</p>
                        </div>
                        <InputError :message="errors.is_administrative" />
                    </label>

                    <!-- Permissions -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <Label>{{
                                t('app.landlord.roles.fields.permissions')
                            }}</Label>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'app.landlord.roles.permissions_ui.selected_count',
                                        {
                                            count: String(
                                                selectedPermissions.length,
                                            ),
                                        },
                                    )
                                }}
                            </span>
                        </div>
                        <Input
                            v-model="permissionSearch"
                            type="search"
                            :placeholder="
                                t(
                                    'app.landlord.roles.permissions_ui.filter_placeholder',
                                )
                            "
                            class="h-9"
                        />

                        <!-- Selecionar/desmarcar todas as permissões visíveis (filtradas) -->
                        <label
                            v-if="filteredPermissions.length > 0"
                            class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-dashed border-border px-3 py-2 text-sm font-medium transition-colors hover:bg-muted/40"
                        >
                            <input
                                type="checkbox"
                                :checked="allFilteredSelected"
                                :indeterminate="selectAllIndeterminate"
                                :disabled="isProtected"
                                class="accent-primary"
                                @change="toggleAllFiltered"
                            />
                            {{
                                allFilteredSelected
                                    ? t(
                                          'app.landlord.roles.permissions_ui.deselect_all',
                                      )
                                    : t(
                                          'app.landlord.roles.permissions_ui.select_all',
                                      )
                            }}
                        </label>

                        <div
                            class="grid max-h-96 gap-2 overflow-y-auto rounded-lg border border-border/60 p-2 md:grid-cols-2"
                        >
                            <label
                                v-for="permission in filteredPermissions"
                                :key="permission.name"
                                class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2.5 text-sm transition-colors hover:bg-muted/40 has-checked:border-primary/50 has-checked:bg-primary/5"
                                :title="
                                    permission.description ?? permission.name
                                "
                            >
                                <input
                                    v-model="selectedPermissions"
                                    type="checkbox"
                                    :value="permission.name"
                                    :disabled="isProtected"
                                    class="mt-0.5 accent-primary"
                                />
                                <span class="min-w-0 flex-1 space-y-0.5">
                                    <span
                                        class="block font-medium text-foreground"
                                    >
                                        {{
                                            permission.short_name ||
                                            permission.name
                                        }}
                                    </span>
                                    <span
                                        v-if="permission.description"
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ permission.description }}
                                    </span>
                                    <span
                                        class="block font-mono text-[10px] text-muted-foreground/70"
                                    >
                                        {{ permission.name }}
                                    </span>
                                </span>
                            </label>
                        </div>
                        <p
                            v-if="filteredPermissions.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('app.landlord.roles.permissions_ui.empty') }}
                        </p>

                        <!-- Envia TODAS as permissões selecionadas, inclusive as
                             que estão fora do filtro atual (não renderizadas acima). -->
                        <input
                            v-for="permissionName in selectedPermissions"
                            :key="`selected-${permissionName}`"
                            type="hidden"
                            name="permissions[]"
                            :value="permissionName"
                        />

                        <InputError :message="errors.permissions" />
                    </div>
                </FormCard>
            </Form>
        </div>
    </AppLayout>
</template>
