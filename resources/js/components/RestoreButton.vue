<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';
import { tenantWayfinderPath } from '@/support/tenantWayfinderPath';

const props = defineProps<{
    href: string;
}>();

const { t } = useT();

const isRestoring = ref(false);

function handleClick(): void {
    isRestoring.value = true;
    router.post(
        tenantWayfinderPath(props.href),
        {},
        {
            onFinish: () => {
                isRestoring.value = false;
            },
        },
    );
}
</script>

<template>
    <Button variant="outline" size="sm" class="inline-flex items-center gap-1.5" :disabled="isRestoring" @click="handleClick">
        <RotateCcw class="size-3.5" />
        <span class="hidden sm:inline">{{ isRestoring ? t('app.common.actions.restoring') : t('app.common.actions.restore') }}</span>
    </Button>
</template>
