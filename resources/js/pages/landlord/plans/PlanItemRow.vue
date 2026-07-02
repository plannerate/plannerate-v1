<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type PlanItem = {
    id: string | null;
    key: string;
    label: string;
    value: string;
    type: 'integer' | 'boolean' | 'string';
    sort_order: number;
    is_active: boolean;
    limit_message: string | null;
    upgrade_url: string | null;
};

const item = defineModel<PlanItem>({ required: true });

const props = defineProps<{
    index: number;
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    remove: [];
}>();
</script>

<template>
    <div class="space-y-2.5 p-4">
        <!-- Hidden fields -->
        <input v-if="item.id" type="hidden" :name="`items[${index}][id]`" :value="item.id" />
        <input type="hidden" :name="`items[${index}][sort_order]`" :value="item.sort_order" />
        <input type="hidden" :name="`items[${index}][is_active]`" value="0" />
        <input type="checkbox" class="sr-only" :name="`items[${index}][is_active]`" value="1" :checked="item.is_active" />

        <!-- Primary row: Label / Key / Value / Type / Remove -->
        <div class="grid grid-cols-[1fr_1fr_1fr_auto_auto] items-end gap-3">
            <div class="grid gap-1">
                <label :for="`item_label_${index}`" class="text-xs font-medium text-muted-foreground">Label</label>
                <Input
                    :id="`item_label_${index}`"
                    v-model="item.label"
                    :name="`items[${index}][label]`"
                    placeholder="Ex: Máximo de lojas"
                    required
                />
            </div>

            <div class="grid gap-1">
                <label :for="`item_key_${index}`" class="text-xs font-medium text-muted-foreground">Chave</label>
                <Input
                    :id="`item_key_${index}`"
                    v-model="item.key"
                    :name="`items[${index}][key]`"
                    placeholder="Ex: store_limit"
                    required
                />
            </div>

            <div class="grid gap-1">
                <label :for="`item_value_${index}`" class="text-xs font-medium text-muted-foreground">Valor</label>
                <Input
                    :id="`item_value_${index}`"
                    v-model="item.value"
                    :name="`items[${index}][value]`"
                    placeholder="Em branco = ilimitado"
                />
            </div>

            <div class="grid gap-1">
                <label :for="`item_type_${index}`" class="text-xs font-medium text-muted-foreground">Tipo</label>
                <select
                    :id="`item_type_${index}`"
                    v-model="item.type"
                    :name="`items[${index}][type]`"
                    class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
                >
                    <option value="string">Texto</option>
                    <option value="integer">Inteiro</option>
                    <option value="boolean">Booleano</option>
                </select>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                @click="emit('remove')"
            >
                <Trash2 class="size-4" />
            </Button>
        </div>

        <!-- Secondary row: Limit message / Upgrade URL -->
        <div class="grid grid-cols-2 gap-3 rounded-md bg-muted/30 px-3 py-2.5">
            <div class="grid gap-1">
                <label :for="`item_limit_message_${index}`" class="text-xs font-medium text-muted-foreground">
                    Mensagem de limite
                </label>
                <Input
                    :id="`item_limit_message_${index}`"
                    v-model="item.limit_message"
                    :name="`items[${index}][limit_message]`"
                    placeholder="Ex: Você atingiu o limite de lojas do seu plano."
                />
                <p v-if="errors[`items.${index}.limit_message`]" class="text-xs text-destructive">
                    {{ errors[`items.${index}.limit_message`] }}
                </p>
            </div>

            <div class="grid gap-1">
                <label :for="`item_upgrade_url_${index}`" class="text-xs font-medium text-muted-foreground">
                    URL de upgrade <span class="font-normal opacity-60">(opcional)</span>
                </label>
                <Input
                    :id="`item_upgrade_url_${index}`"
                    v-model="item.upgrade_url"
                    :name="`items[${index}][upgrade_url]`"
                    type="url"
                    placeholder="https://..."
                />
                <p v-if="errors[`items.${index}.upgrade_url`]" class="text-xs text-destructive">
                    {{ errors[`items.${index}.upgrade_url`] }}
                </p>
            </div>
        </div>
    </div>
</template>
