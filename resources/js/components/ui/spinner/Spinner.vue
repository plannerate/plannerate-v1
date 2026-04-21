<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { Loader2Icon } from "lucide-vue-next"
import { useT } from "@/composables/useT"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()

const { t } = useT()
</script>

<template>
  <Loader2Icon
    role="status"
    :aria-label="t('app.loading')"
    :class="cn('size-4 animate-spin', props.class)"
  />
</template>
