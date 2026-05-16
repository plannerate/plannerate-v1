<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ShelfLevelPreferencesController from '@/actions/App/Http/Controllers/Settings/ShelfLevelPreferencesController';
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

type Preference = {
    id: string;
    category_id: string | null;
    category_label: string | null;
    preferred_level: string;
    preferred_level_label: string;
    preferred_level_color: string;
};

type ShelfLevelOption = {
    value: string;
    label: string;
    color: string;
};

type Props = {
    subdomain: string;
    preferences: Preference[];
    shelfLevels: ShelfLevelOption[];
};

const props = defineProps<Props>();
const { t } = useT();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('app.shelf_level_preferences_settings'),
            href: ShelfLevelPreferencesController.edit.url(props.subdomain),
        },
    ],
});

const isDialogOpen = ref(false);
const editingPreferenceId = ref<string | null>(null);

const form = useForm({
    category_id: null as string | null,
    preferred_level: props.shelfLevels[0]?.value ?? 'hand',
});

const tenantDefault = computed(() => props.preferences.find((p) => p.category_id === null));
const categoryPreferences = computed(() => props.preferences.filter((p) => p.category_id !== null));

function openCreateDialog() {
    editingPreferenceId.value = null;
    form.reset();
    form.clearErrors();
    form.category_id = null;
    form.preferred_level = props.shelfLevels[0]?.value ?? 'hand';
    isDialogOpen.value = true;
}

function openEditDialog(preference: Preference) {
    editingPreferenceId.value = preference.id;
    form.category_id = preference.category_id;
    form.preferred_level = preference.preferred_level;
    form.clearErrors();
    isDialogOpen.value = true;
}

function submit() {
    const options = {
        onSuccess: () => {
            isDialogOpen.value = false;
        },
    };

    if (editingPreferenceId.value) {
        form.put(ShelfLevelPreferencesController.update.url({
            subdomain: props.subdomain,
            preference: editingPreferenceId.value,
        }), options);
        return;
    }

    form.post(ShelfLevelPreferencesController.store.url(props.subdomain), options);
}

function destroy(preference: Preference) {
    router.delete(ShelfLevelPreferencesController.destroy.url({
        subdomain: props.subdomain,
        preference: preference.id,
    }));
}

function badgeVariant(color: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (color === 'warning') return 'outline';
    if (color === 'secondary') return 'secondary';
    return 'default';
}
</script>

<template>
    <Head :title="t('app.shelf_level_preferences_settings')" />

    <h1 class="sr-only">{{ t('app.shelf_level_preferences_settings') }}</h1>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <Heading
                variant="small"
                :title="t('app.shelf_level_preferences_settings')"
                :description="t('app.shelf_level_preferences_description')"
            />

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
                    <Button type="button" variant="destructive" size="sm" @click="destroy(tenantDefault)">
                        {{ t('app.actions.delete') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Preferências por categoria -->
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
                                <Button type="button" variant="destructive" size="sm" @click="destroy(pref)">
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
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ editingPreferenceId ? t('app.actions.edit') : t('app.actions.new') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('app.shelf_level_preferences_modal_description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="space-y-2">
                    <Label>{{ t('app.labels.category') }}</Label>
                    <CategoryCascadeSelect
                        v-model="form.category_id"
                        :cascade-levels="4"
                        :cols="2"
                        :error="form.errors.category_id"
                        input-name="category_id"
                    />
                    <p class="text-muted-foreground text-xs">{{ t('app.labels.tenant_default_hint') }}</p>
                </div>

                <div class="space-y-2">
                    <Label>{{ t('app.labels.preferred_level') }}</Label>
                    <Select :model-value="form.preferred_level" @update:model-value="(v) => (form.preferred_level = String(v ?? ''))">
                        <SelectTrigger>
                            <SelectValue :placeholder="t('app.labels.select_level')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="level in props.shelfLevels" :key="level.value" :value="level.value">
                                {{ level.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.preferred_level" />
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
