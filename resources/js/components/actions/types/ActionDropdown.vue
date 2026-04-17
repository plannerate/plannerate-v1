<!--
 * ActionDropdown - Dropdown de ações (Raptor + shadcn-vue)
 *
 * Renderiza um DropdownMenu (shadcn-vue) cujos itens são actions do WithActions.
 * Cada item é renderizado via ActionRenderer (link, api, modal, etc).
 -->
<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button :variant="variant" :size="computedSize" class="gap-1.5 btn-gradient">
        <component v-if="iconComponent" :is="iconComponent" :class="iconClasses" />
        <span class="text-xs">{{ action.label }}</span>
        <ChevronDown class="h-3 w-3 shrink-0" />
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-56">
      <DropdownMenuItem v-for="item in items" :key="item.name"
        class="cursor-pointer gap-1.5 p-0 [&_button]:w-full [&_button]:justify-start [&_button]:rounded-none [&_a]:w-full [&_a]:justify-start [&_a]:rounded-none">
        <ActionRenderer :action="item" :record="record" :column="column" @click="handleItemClick(item)" />
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { ChevronDown } from 'lucide-vue-next'
import ActionRenderer from '~/components/actions/ActionRenderer.vue'
import { useActionUI } from '~/composables/useActionUI'
import type { TableAction } from '~/types/table'

interface Props {
  action: TableAction & { actions?: TableAction[] }
  record?: Record<string, unknown>
  column?: Record<string, unknown>
  size?: 'default' | 'sm' | 'lg' | 'icon'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'sm',
})

const emit = defineEmits<{
  (e: 'click', event: Event): void
}>()

const items = computed(() => {
  return props.action.actions ?? props.action.options ?? []
})

const { variant, size: computedSize, iconComponent, iconClasses } = useActionUI({
  action: props.action,
  defaultSize: props.size,
})

function handleItemClick(event: Event) {
  emit('click', event)
}
 
</script>
