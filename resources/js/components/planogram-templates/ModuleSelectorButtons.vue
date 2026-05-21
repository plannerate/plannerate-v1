<script setup lang="ts">
import { Check, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    currentModule: number;
    subtemplates: Array<{ num_modules: number }>;
    readonly?: boolean;
}>();

const emit = defineEmits<{
    select: [moduleCount: number];
    add: [moduleCount: number];
}>();

const sorted = () =>
    [...props.subtemplates].sort((a, b) => a.num_modules - b.num_modules);

const addingNew = ref(false);
const newCount = ref<number | null>(null);

function openAdd(): void {
    newCount.value = null;
    addingNew.value = true;
}

function confirmAdd(): void {
    if (!newCount.value || newCount.value < 1) return;
    emit('add', newCount.value);
    addingNew.value = false;
    newCount.value = null;
}

function cancelAdd(): void {
    addingNew.value = false;
    newCount.value = null;
}
</script>

<template>
    <button
        v-for="s in sorted()"
        :key="s.num_modules"
        type="button"
        class="rounded-md border px-3 py-1.5 text-sm font-medium transition"
        :class="
            props.currentModule === s.num_modules
                ? 'border-primary bg-primary text-primary-foreground'
                : 'border-border bg-background text-foreground hover:border-primary/60 hover:bg-muted/30'
        "
        @click="emit('select', s.num_modules)"
    >
        {{ s.num_modules }} módulo{{ s.num_modules > 1 ? 's' : '' }}
    </button>

    <!-- Inline add form -->
    <template v-if="!props.readonly">
        <template v-if="addingNew">
            <input
                v-model.number="newCount"
                type="number"
                min="1"
                max="20"
                placeholder="N"
                class="w-16 rounded-md border border-border bg-background px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                autofocus
                @keyup.enter="confirmAdd"
                @keyup.escape="cancelAdd"
            />
            <button
                type="button"
                class="rounded-md border border-primary bg-primary px-2 py-1.5 text-primary-foreground transition hover:bg-primary/90"
                :disabled="!newCount || newCount < 1"
                @click="confirmAdd"
            >
                <Check class="size-3.5" />
            </button>
        </template>

        <button
            v-else
            type="button"
            title="Adicionar configuração para novo número de módulos"
            class="rounded-md border border-dashed border-border px-2 py-1.5 text-muted-foreground transition hover:border-primary hover:text-primary"
            @click="openAdd"
        >
            <Plus class="size-3.5" />
        </button>
    </template>
</template>
