<script setup lang="ts">
import { Check, Pencil } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { usePlanogramEditor } from '@/composables/plannerate/core/usePlanogramEditor';
import { useT } from '@/composables/useT';

const open = defineModel<boolean>('open', { default: false });

const editor = usePlanogramEditor();
const { t } = useT();

/** Gôndola atualmente selecionada no editor */
const currentGondola = computed(() => editor.currentGondola.value);

/** Nome em edição (cópia local, só persistido ao salvar) */
const name = ref('');

// Sempre que o modal abrir, carrega o nome atual da gôndola
watch(open, (isOpen) => {
    if (isOpen) {
        name.value = currentGondola.value?.name ?? '';
    }
});

/** Desabilita salvar quando vazio ou sem alteração */
const canSave = computed(() => {
    const trimmed = name.value.trim();

    return trimmed.length > 0 && trimmed !== (currentGondola.value?.name ?? '');
});

/**
 * Persiste o novo nome via editor (commit otimista + auto-save) e fecha o modal.
 */
function handleSave(): void {
    if (!canSave.value) {
        return;
    }

    const updated = editor.updateGondola({
        name: name.value.trim(),
        updated_at: new Date().toISOString(),
    });

    if (updated) {
        toast.success(t('plannerate.toolbar.rename_modal.success'));
        open.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Pencil class="size-4" />
                    {{ t('plannerate.toolbar.rename_modal.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('plannerate.toolbar.rename_modal.description') }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label for="gondola-rename-input">{{ t('plannerate.toolbar.rename_modal.label') }}</Label>
                <Input
                    id="gondola-rename-input"
                    v-model="name"
                    :placeholder="t('plannerate.toolbar.rename_modal.placeholder')"
                    autofocus
                    @keydown.enter.prevent="handleSave"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">
                    {{ t('plannerate.common.cancel') }}
                </Button>
                <Button :disabled="!canSave" @click="handleSave">
                    <Check class="mr-2 size-4" />
                    {{ t('plannerate.common.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
