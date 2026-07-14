<script setup lang="ts">
import { CircleHelp } from 'lucide-vue-next';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

withDefaults(
    defineProps<{
        text: string;
        side?: 'top' | 'right' | 'bottom' | 'left';
    }>(),
    {
        side: 'top',
    },
);
</script>

<template>
    <TooltipProvider :delay-duration="150">
        <Tooltip>
            <TooltipTrigger
                class="inline-flex items-center rounded-sm text-muted-foreground align-text-bottom transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            >
                <CircleHelp class="size-3.5" />
                <span class="sr-only">Ajuda</span>
            </TooltipTrigger>
            <TooltipContent :side="side" class="max-w-72 text-xs">
                {{ text }}
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
