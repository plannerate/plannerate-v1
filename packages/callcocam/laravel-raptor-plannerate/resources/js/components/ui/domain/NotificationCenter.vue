<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

type ToastVariant = 'success' | 'error' | 'warning' | 'info';

interface Toast {
    id: number;
    message: string;
    variant: ToastVariant;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

function show(message: string, variant: ToastVariant = 'info') {
    const id = ++nextId;
    toasts.value.push({ id, message, variant });
    setTimeout(() => remove(id), 4000);
}

function remove(id: number) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

defineExpose({ show });

const page = usePage();

const flashSuccess = computed(() => (page.props as Record<string, unknown>).success as string | undefined);
const flashError = computed(() => (page.props as Record<string, unknown>).error as string | undefined);

// Processar flash messages do Laravel automaticamente
computed(() => {
    if (flashSuccess.value) {
        show(flashSuccess.value, 'success');
    }

    if (flashError.value) {
        show(flashError.value, 'error');
    }
});

const variantClasses: Record<ToastVariant, string> = {
    success: 'bg-primary text-on-primary border-primary/40',
    error: 'bg-error text-white border-error/40',
    warning: 'bg-yellow-500 text-white border-yellow-400',
    info: 'bg-surface-container-high text-on-surface border-outline-variant',
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 pointer-events-none">
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="flex items-center gap-3 rounded-xl border px-5 py-3 shadow-lg text-sm font-medium max-w-sm pointer-events-auto"
                    :class="variantClasses[toast.variant]"
                >
                    <span>{{ toast.message }}</span>
                    <button class="ml-auto opacity-70 hover:opacity-100 transition-opacity" @click="remove(toast.id)">×</button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>
