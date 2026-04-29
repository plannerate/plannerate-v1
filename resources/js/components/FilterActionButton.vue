<script setup lang="ts">
import { TrainFrontTunnel } from 'lucide-vue-next';
import type { ButtonHTMLAttributes, HTMLAttributes } from 'vue'; 
import { Button } from '@/components/ui/button';
 

const props = withDefaults(
    defineProps<{
        type?: ButtonHTMLAttributes['type'];
        disabled?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    {
        type: 'submit',
        disabled: false,
        class: undefined,
    },
);
</script>

<template>
    <Button
        :type="props.type"
        variant="gradient"
        size="pill-sm"
        :disabled="props.disabled"
        :class="[
            'w-full rounded-xl border border-sidebar-border/70 text-primary-foreground shadow-xs dark:border-sidebar-border',
            props.class,
        ]"
    >
        <TrainFrontTunnel class="size-4" />
        <slot />
    </Button>
</template>
