<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import type { ButtonProps } from '@/components/ui/button'

interface Props extends /* @vue-ignore */ ButtonProps {
  tooltip?: string
  tooltipSide?: 'top' | 'right' | 'bottom' | 'left'
  tooltipAlign?: 'start' | 'center' | 'end'
}

const props = withDefaults(defineProps<Props>(), {
  tooltipSide: 'bottom',
  tooltipAlign: 'center',
})
</script>

<template>
  <TooltipProvider v-if="tooltip">
    <Tooltip>
      <TooltipTrigger as-child>
        <Button v-bind="$attrs">
          <slot />
        </Button>
      </TooltipTrigger>
      <TooltipContent :side="tooltipSide" :align="tooltipAlign">
        <p>{{ tooltip }}</p>
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
  
  <Button v-else v-bind="$attrs" data-properties-panel>
    <slot />
  </Button>
</template>