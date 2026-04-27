<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { useAppearance } from '@/composables/useAppearance';

withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        :class="[
            'inline-flex gap-1 rounded-lg',
            compact ? 'p-0' : 'bg-neutral-100 p-1 dark:bg-neutral-800',
        ]"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            :aria-label="label"
            :title="compact ? label : undefined"
            @click="updateAppearance(value)"
            :class="[
                'flex items-center rounded-md transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                compact ? 'h-8 w-8 justify-center p-0' : 'px-3.5 py-1.5',
                compact && appearance === value
                    ? 'bg-muted text-foreground'
                    : '',
                compact && appearance !== value
                    ? 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'
                    : '',
                !compact && appearance === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : '',
                !compact && appearance !== value
                    ? 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
                    : '',
            ]"
        >
            <component :is="Icon" :class="[compact ? 'h-4 w-4' : '-ml-1 h-4 w-4']" />
            <span :class="compact ? 'sr-only' : 'ml-1.5 text-sm'">{{ label }}</span>
        </button>
    </div>
</template>
