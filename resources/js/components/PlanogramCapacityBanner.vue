<template>
  <div v-if="report" class="space-y-2">
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
  </div>
</template>

<script setup lang="ts">
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
}

defineProps<{
  report: CapacityReport | null
}>()
</script>
