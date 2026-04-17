<template>
    <div>
        <Button
            v-if="!isActionStyle"
            type="button"
            :variant="computedVariant"
            :size="computedSize"
            :class="cn('gap-1.5', className)"
            @click="handleOpen"
        >
            <ActionIconBox v-if="iconComponent" :variant="iconBoxVariant">
                <component :is="iconComponent" />
            </ActionIconBox>
            <span class="text-xs">{{ action.label }}</span>
        </Button>
        <button
            v-else
            type="button"
            :class="cn(actionStyle.buttonClasses, className)"
            @click="handleOpen"
        >
            <div v-if="iconComponent" :class="actionStyle.iconWrapperClasses">
                <component :is="iconComponent" :class="actionStyle.iconClasses" />
            </div>
            <span :class="actionStyle.labelClasses">{{ action.label }}</span>
        </button>

        <GondolaCreateStepper
            v-if="planogramIdForCreate"
            v-model:open="isCreateModalOpen"
            :planogram-id="planogramIdForCreate"
            @success="handleGondolaCreated"
        />
    </div>
</template>
<script setup lang="ts">
import GondolaCreateStepper from '@/components/plannerate/v3/form/GondolaCreateStepper.vue';
import { computed, ref } from 'vue';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { cn } from '~/lib/utils';
import { useActionUI } from '~/composables/useActionUI';
import { TableAction } from '~/types/table';

interface Props {
    action: TableAction;
    record?: any;
    variant?:
        | 'default'
        | 'create'
        | 'outline'
        | 'ghost'
        | 'destructive'
        | 'secondary'
        | 'link'
        | 'success'
        | 'warning';
    size?: 'default' | 'sm' | 'lg' | 'icon';
    asChild?: boolean;
    className?: string;
    column?: Record<string, any>;
    [key: string]: any;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
    asChild: false,
});

const { iconComponent, variant: computedVariant, size: computedSize, isActionStyle, actionStyle, iconBoxVariant } = useActionUI({
    action: props.action,
    defaultSize: 'sm',
    defaultVariant: props.variant,
});

const emit = defineEmits<{
    (e: 'click', event: MouseEvent): void;
}>();

const isCreateModalOpen = ref(false);

// Planograma para criação: filtro ativo ou primeiro da lista
const planogramIdForCreate = computed(() => {
    return props.record?.id ?? null;
});

const handleGondolaCreated = () => {
    isCreateModalOpen.value = false;
};

const handleOpen = (event: MouseEvent) => {
    emit('click', event);
    isCreateModalOpen.value = true;
};
</script>
