<script setup lang="ts">
import { SlidersHorizontal, X } from 'lucide-vue-next';
import { ref } from 'vue';
import Header from './Header.vue';
import Toolbar from './Toolbar.vue';

interface Props {
    title?: string;
    status?: string;
    planogramId?: string;
    tenant?: any;
    availableUsers?: Array<{ id: string; name: string }>;
    permissions: {
        can_create_gondola: boolean;
        can_update_gondola: boolean;
    };
    backRoute?: string;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    status: 'draft',
    planogramId: '',
    tenant: {},
    availableUsers: () => [],
    backRoute: '',
});

const emit = defineEmits<{
    updateGondolaImages: [];
}>();

const open = ref(false);
</script>

<template>
    <div>
        <!-- Botão de toggle quando fechado -->
        <button
            v-if="!open"
            class="group absolute left-0 top-2 z-20 flex items-center rounded-r border-b border-r border-t border-border bg-background p-1 shadow-sm transition-colors hover:bg-accent"
            type="button"
            @click="open = true"
        >
            <SlidersHorizontal class="size-4 shrink-0 text-foreground transition-all duration-300 group-hover:mr-2" />
            <span
                class="max-w-0 overflow-hidden whitespace-nowrap text-xs text-foreground transition-all duration-300 group-hover:ml-1 group-hover:max-w-xs"
            >
                Ferramentas
            </span>
        </button>

        <!-- Painel drawer -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-300 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <div
                v-if="open"
                class="absolute left-0 top-0 z-30 flex h-full w-72 flex-col border-r border-border bg-background shadow-lg sm:w-80"
            >
                <!-- Cabeçalho do drawer -->
                <div class="flex shrink-0 items-center justify-between border-b border-border px-3 py-2">
                    <span class="text-sm font-medium">Ferramentas</span>
                    <button
                        class="rounded p-1 transition-colors hover:bg-accent"
                        type="button"
                        @click="open = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Conteúdo scrollável: min-h-0 necessário para overflow-y funcionar em flex child -->
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <Header
                        v-bind="props"
                        :sidebar="true"
                        @update-gondola-images="emit('updateGondolaImages')"
                    />
                    <Toolbar />
                </div>
            </div>
        </Transition>
    </div>
</template>
