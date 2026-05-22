<template>
  <div v-if="report" class="space-y-2">
    <!-- Descasamento de módulos: gôndola tem mais módulos que o subtemplate ativo -->
    <div v-if="report.modules_mismatch" class="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950">
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-orange-500 dark:text-orange-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <p class="font-medium text-orange-800 dark:text-orange-100">Módulos da gôndola não cobertos pelo template</p>
          <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
            A gôndola tem <strong>{{ report.gondola_modules }}</strong> módulo(s), mas o subtemplate ativo cobre apenas
            <strong>{{ report.template_modules }}</strong>. Os módulos
            {{ (report.template_modules ?? 0) + 1 }}–{{ report.gondola_modules }} ficarão vazios.
          </p>
          <p class="mt-2 text-sm text-orange-700 dark:text-orange-300">
            Clone o subtemplate para <strong>{{ report.gondola_modules }} módulos</strong> e configure os slots extras no wizard.
          </p>
          <form
            v-if="report.subtemplate_id && report.template_id"
            :action="cloneUrl"
            method="POST"
            class="mt-3"
            @submit.prevent="submitClone"
          >
            <input type="hidden" name="target_modules" :value="report.gondola_modules" />
            <button
              type="submit"
              :disabled="cloning"
              class="inline-flex items-center gap-2 rounded-md bg-orange-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-60 dark:bg-orange-700 dark:hover:bg-orange-600"
            >
              <svg v-if="cloning" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
              </svg>
              Clonar subtemplate para {{ report.gondola_modules }} módulos
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Score neutro: sem dados de venda no modo template -->
    <div v-if="report.score_type === 'neutral'" class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-500 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <p class="font-medium text-blue-900 dark:text-blue-100">Sem dados de venda no período selecionado</p>
          <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
            O planograma foi gerado normalmente com o template selecionado.
            A ordenação interna dos produtos dentro de cada slot está neutra — sem priorização por giro.
            Para ordenação por vendas, selecione um período com dados disponíveis.
          </p>
        </div>
      </div>
    </div>

    <!-- Mix maior que a gôndola -->
    <div v-if="report.mix_excede_gondola" class="rounded-lg border border-amber-200 bg-amber-50 p-4">
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        </svg>
        <div class="flex-1">
          <p class="font-medium text-amber-800">Mix maior que a gôndola</p>
          <p class="mt-1 text-sm text-amber-700">
            Esta gôndola comporta <strong>{{ report.posicionados }}</strong>
            dos <strong>{{ report.total_produtos }}</strong> produtos selecionados.
            <strong>{{ report.rejeitados_espaco }}</strong> produto(s) ficaram de fora por falta de espaço —
            os de menor score foram priorizados para exclusão.
          </p>
          <p class="mt-2 text-sm text-amber-700">
            Para incluir todos os produtos, considere
            <strong>ampliar a gôndola</strong> ou
            <strong>reduzir o mix selecionado</strong>.
          </p>

          <details v-if="report.produtos_rejeitados_espaco?.length" class="mt-3">
            <summary class="cursor-pointer text-sm font-medium text-amber-800 hover:text-amber-900">
              Ver produtos que não couberam ({{ report.rejeitados_espaco }})
            </summary>
            <ul class="mt-2 space-y-1">
              <li
                v-for="produto in report.produtos_rejeitados_espaco"
                :key="produto.id"
                class="text-sm text-amber-700"
              >
                {{ produto.name }}
                <span v-if="produto.category" class="text-amber-500">({{ produto.category }})</span>
              </li>
            </ul>
          </details>
        </div>
      </div>
    </div>

    <!-- Produtos sem dimensões cadastradas -->
    <div v-if="report.rejeitados_sem_dimensao && report.rejeitados_sem_dimensao > 0" class="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-950">
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-purple-500 dark:text-purple-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm text-purple-700 dark:text-purple-300">
          <strong>{{ report.rejeitados_sem_dimensao }} produto(s)</strong> foram excluídos porque não possuem
          <strong>width</strong> ou <strong>height</strong> cadastrados. Cadastre as dimensões destes produtos
          para que sejam incluídos na geração.
        </p>
      </div>
    </div>

    <!-- Produtos com altura maior que o clearance da prateleira -->
    <div v-if="report.rejeitados_altura > 0" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm text-blue-700">
          <strong>{{ report.rejeitados_altura }} produto(s)</strong> não foram posicionados porque a altura
          cadastrada excede o clearance das prateleiras desta gôndola. Verifique o cadastro de dimensões
          destes produtos.
        </p>
      </div>
    </div>

    <!-- Estoque alvo não atendido (apenas modo template) -->
    <div
      v-if="targetStockNotMetAlert"
      class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950"
    >
      <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-500 dark:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
        </svg>
        <p class="text-sm text-indigo-700 dark:text-indigo-300">
          <strong>{{ targetStockNotMetAlert.count }} produto(s)</strong> com estoque alvo definido não
          tiveram frentes expandidas — o espaço disponível não foi suficiente para atingir o alvo.
          Considere ampliar a gôndola ou aumentar o limite de frentes do slot.
        </p>
      </div>
    </div>

    <!-- Resumo ABC da alocação (apenas modo template) -->
    <div
      v-if="report.explanation_report && allocationSummary.total > 0"
      class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900"
    >
      <details>
        <summary class="cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900">
          Resumo da alocação — {{ allocationSummary.total }} produto(s) posicionados
        </summary>
        <div class="mt-3 flex flex-wrap gap-4">
          <div v-if="allocationSummary.mandatory > 0" class="flex items-center gap-1.5 text-sm">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-500" />
            <span class="text-slate-600 dark:text-slate-400">{{ allocationSummary.mandatory }} obrigatório(s)</span>
          </div>
          <div v-if="allocationSummary.a > 0" class="flex items-center gap-1.5 text-sm">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500" />
            <span class="text-slate-600 dark:text-slate-400">{{ allocationSummary.a }} curva A</span>
          </div>
          <div v-if="allocationSummary.b > 0" class="flex items-center gap-1.5 text-sm">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-yellow-500" />
            <span class="text-slate-600 dark:text-slate-400">{{ allocationSummary.b }} curva B</span>
          </div>
          <div v-if="allocationSummary.c > 0" class="flex items-center gap-1.5 text-sm">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-orange-400" />
            <span class="text-slate-600 dark:text-slate-400">{{ allocationSummary.c }} curva C</span>
          </div>
          <div v-if="allocationSummary.neutral > 0" class="flex items-center gap-1.5 text-sm">
            <span class="inline-block h-2.5 w-2.5 rounded-full bg-slate-400" />
            <span class="text-slate-600 dark:text-slate-400">{{ allocationSummary.neutral }} sem dados de venda</span>
          </div>
          <div v-if="allocationSummary.expanded > 0" class="flex items-center gap-1.5 text-sm">
            <span class="text-slate-500 dark:text-slate-400">·</span>
            <span class="text-slate-600 dark:text-slate-400">{{ allocationSummary.expanded }} com frentes expandidas</span>
          </div>
        </div>
      </details>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { cloneSubtemplate } from '@/actions/App/Http/Controllers/Tenant/TemplateSlotController'
import type { ExplanationReport, ExplanationAlert } from '@/components/planogram-templates/types'

interface RejectedProduct {
  id: string
  name: string
  category?: string
}

interface CapacityReport {
  total_produtos: number
  posicionados: number
  rejeitados_espaco: number
  rejeitados_altura: number
  mix_excede_gondola: boolean
  taxa_cobertura: number
  score_type?: string
  has_sales_data?: boolean
  produtos_rejeitados_espaco: RejectedProduct[]
  rejeitados_sem_dimensao?: number
  modules_mismatch?: boolean
  template_modules?: number
  gondola_modules?: number
  subtemplate_id?: string
  template_id?: string
  explanation_report?: ExplanationReport | null
}

const props = defineProps<{
  report: CapacityReport | null
}>()

const cloning = ref(false)

/** Alerta de estoque alvo não atendido, derivado do relatório de explicação */
const targetStockNotMetAlert = computed((): ExplanationAlert | null => {
  const alerts = props.report?.explanation_report?.alerts ?? []
  return alerts.find((a) => a.type === 'target_stock_not_met') ?? null
})

/** Resumo da curva ABC dos produtos alocados */
const allocationSummary = computed(() => {
  const allocated = props.report?.explanation_report?.allocated ?? []
  return {
    total: allocated.length,
    a: allocated.filter((e) => e.abc_class === 'A').length,
    b: allocated.filter((e) => e.abc_class === 'B').length,
    c: allocated.filter((e) => e.abc_class === 'C').length,
    neutral: allocated.filter((e) => e.abc_class === null).length,
    mandatory: allocated.filter((e) => e.is_mandatory).length,
    expanded: allocated.filter((e) => e.facings_expanded).length,
  }
})

const cloneUrl = computed(() => {
  const r = props.report
  if (!r?.subtemplate_id || !r.template_id) return ''
  return cloneSubtemplate.url({
    planogramTemplate: r.template_id,
    planogramSubtemplate: r.subtemplate_id,
  })
})

function submitClone(): void {
  const r = props.report
  if (!r?.subtemplate_id || !r.template_id || !r.gondola_modules) return
  cloning.value = true
  router.post(
    cloneUrl.value,
    { target_modules: r.gondola_modules },
    { onFinish: () => { cloning.value = false } },
  )
}
</script>
