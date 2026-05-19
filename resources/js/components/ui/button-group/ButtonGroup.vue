<script setup lang="ts">
import type { PrimitiveProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { Primitive } from 'reka-ui';
import { cn } from '@/lib/utils';

interface Props extends PrimitiveProps {
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    as: 'div',
});
</script>

<template>
    <Primitive
        data-slot="button-group"
        :as="as"
        :as-child="asChild"
        :class="
            cn(
                'inline-flex items-center overflow-hidden rounded-md border border-input bg-background [&>*:first-child]:rounded-l-md [&>*:last-child]:rounded-r-md',
                props.class,
            )
        "
    >
        <slot />
    </Primitive>
</template>
