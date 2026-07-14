<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CategoryCascadeSelect from '@/components/tenant/CategoryCascadeSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useT } from '@/composables/useT';

type TemplatePayload = {
    id: string;
    code: string;
    name: string;
    department: string;
    category_id: string | null;
    category_name?: string | null;
    description: string | null;
    is_active: boolean;
};

const props = defineProps<{
    template?: TemplatePayload | null;
    errors: Record<string, string | undefined>;
    translationScope: string;
}>();

const { t } = useT();

const categoryId = ref<string | null>(props.template?.category_id ?? null);
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="code">{{ t(`${props.translationScope}.fields.code`) }}</Label>
            <Input id="code" name="code" :default-value="props.template?.code ?? ''" required />
            <InputError :message="props.errors.code" />
        </div>

        <div class="grid gap-2">
            <Label for="name">{{ t(`${props.translationScope}.fields.name`) }}</Label>
            <Input id="name" name="name" :default-value="props.template?.name ?? ''" required />
            <InputError :message="props.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="department">{{ t(`${props.translationScope}.fields.department`) }}</Label>
            <Input id="department" name="department" :default-value="props.template?.department ?? ''" required />
            <InputError :message="props.errors.department" />
        </div>

        <!-- Categoria da gôndola — obrigatória antes de configurar slots -->
        <div class="grid gap-2">
            <Label>{{ t(`${props.translationScope}.fields.gondola_category`) }} <span class="text-destructive">*</span></Label>
            <p class="text-xs text-muted-foreground">
                {{ t(`${props.translationScope}.fields.gondola_category_hint`) }}
            </p>
            <CategoryCascadeSelect
                v-model="categoryId"
                input-name="category_id"
                :cascade-levels="5"
                :cols="3"
            />
            <InputError :message="props.errors.category_id" />
        </div>

        <div class="grid gap-2">
            <Label for="description">{{ t(`${props.translationScope}.fields.description`) }}</Label>
            <textarea
                id="description"
                name="description"
                rows="3"
                :value="props.template?.description ?? ''"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            />
            <InputError :message="props.errors.description" />
        </div>

        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3 transition-colors hover:bg-muted/50 has-checked:border-primary/50 has-checked:bg-primary/5">
            <input type="hidden" name="is_active" value="0" />
            <input
                id="is_active"
                name="is_active"
                type="checkbox"
                value="1"
                :checked="props.template?.is_active ?? true"
                class="accent-primary"
            />
            <div>
                <span class="text-sm font-medium">{{ t(`${props.translationScope}.fields.status`) }}</span>
                <p class="text-xs text-muted-foreground">
                    {{ t(`${props.translationScope}.status.active`) }} / {{ t(`${props.translationScope}.status.inactive`) }}
                </p>
            </div>
            <InputError :message="props.errors.is_active" />
        </label>
    </div>
</template>
