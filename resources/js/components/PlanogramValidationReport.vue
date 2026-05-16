<template>
  <div v-if="report" class="bg-white shadow rounded-lg">
    <div class="px-6 py-4 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900">Qualidade do Planograma</h3>
        <button
          @click="isExpanded = !isExpanded"
          class="text-gray-500 hover:text-gray-700"
        >
          <svg class="h-5 w-5" :class="{ 'transform rotate-180': isExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
        </button>
      </div>

      <!-- Counters -->
      <div class="mt-4 grid grid-cols-3 gap-4">
        <div class="bg-red-50 rounded-lg px-4 py-3">
          <div class="text-2xl font-bold text-red-600">{{ report.error_count }}</div>
          <div class="text-sm text-red-700">Erros</div>
        </div>
        <div class="bg-yellow-50 rounded-lg px-4 py-3">
          <div class="text-2xl font-bold text-yellow-600">{{ report.warning_count }}</div>
          <div class="text-sm text-yellow-700">Avisos</div>
        </div>
        <div class="bg-blue-50 rounded-lg px-4 py-3">
          <div class="text-2xl font-bold text-blue-600">{{ report.info_count }}</div>
          <div class="text-sm text-blue-700">Informações</div>
        </div>
      </div>
    </div>

    <!-- Results List -->
    <div v-if="isExpanded" class="divide-y divide-gray-200">
      <div v-if="report.results.length === 0" class="px-6 py-6 text-center text-gray-500">
        Nenhum aviso ou erro encontrado!
      </div>

      <div
        v-for="(result, index) in report.results"
        :key="index"
        :class="getResultClass(result.severity)"
        class="px-6 py-4"
      >
        <div class="flex items-start space-x-3">
          <div :class="`flex-shrink-0 h-5 w-5 ${getSeverityIconClass(result.severity)}`">
            <svg fill="currentColor" viewBox="0 0 20 20">
              <path
                v-if="result.severity === 'error'"
                fill-rule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clip-rule="evenodd"
              />
              <path
                v-else-if="result.severity === 'warning'"
                fill-rule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clip-rule="evenodd"
              />
              <path
                v-else
                fill-rule="evenodd"
                d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2zm-11-1a1 1 0 11-2 0 1 1 0 012 0zm3 0a1 1 0 11-2 0 1 1 0 012 0zm3 0a1 1 0 11-2 0 1 1 0 012 0z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
          <div class="flex-1">
            <h4 :class="getSeverityTextClass(result.severity)" class="font-medium">
              {{ result.rule_name }}
            </h4>
            <p class="mt-1 text-sm text-gray-700">{{ result.message }}</p>

            <!-- Affected Products Chips -->
            <div v-if="result.affected_product_ids.length > 0" class="mt-2 flex flex-wrap gap-2">
              <span
                v-for="productId in result.affected_product_ids.slice(0, 3)"
                :key="productId"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 cursor-pointer hover:bg-gray-200"
                @click="$emit('selectProduct', productId)"
              >
                {{ productId.slice(0, 8) }}...
              </span>
              <span
                v-if="result.affected_product_ids.length > 3"
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
              >
                +{{ result.affected_product_ids.length - 3 }} mais
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

interface ValidationResult {
  rule_name: string
  severity: 'error' | 'warning' | 'info'
  message: string
  affected_product_ids: string[]
  affected_shelf_id?: string
  affected_section_id?: string
}

interface ValidationReport {
  passed: boolean
  error_count: number
  warning_count: number
  info_count: number
  results: ValidationResult[]
}

defineProps<{
  report: ValidationReport | null
}>()

defineEmits<{
  selectProduct: [productId: string]
}>()

const isExpanded = ref(true)

const getResultClass = (severity: string) => {
  const classes: Record<string, string> = {
    error: 'bg-red-50 border-l-4 border-red-400',
    warning: 'bg-yellow-50 border-l-4 border-yellow-400',
    info: 'bg-blue-50 border-l-4 border-blue-400',
  }
  return classes[severity] || 'bg-gray-50'
}

const getSeverityIconClass = (severity: string) => {
  const classes: Record<string, string> = {
    error: 'text-red-600',
    warning: 'text-yellow-600',
    info: 'text-blue-600',
  }
  return classes[severity] || 'text-gray-600'
}

const getSeverityTextClass = (severity: string) => {
  const classes: Record<string, string> = {
    error: 'text-red-900',
    warning: 'text-yellow-900',
    info: 'text-blue-900',
  }
  return classes[severity] || 'text-gray-900'
}
</script>
