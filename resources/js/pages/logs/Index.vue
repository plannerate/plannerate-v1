<template>
  <AppLayout title="Visualizador de Logs">
    <!-- Header com estatísticas -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <FileText class="h-6 w-6 text-blue-600" />
            Visualizador de Logs
          </h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1">
            Monitore logs do sistema em tempo real
          </p>
        </div>
        <div class="flex gap-2">
          <Button variant="outline" size="sm" class="gap-2" @click="downloadLogs">
            <ActionIconBox variant="outline">
              <Download />
            </ActionIconBox>
            Download
          </Button>
          <Button variant="destructive" size="sm" class="gap-2" @click="showClearModal = true">
            <ActionIconBox variant="destructive">
              <Trash2 />
            </ActionIconBox>
            Limpar Logs
          </Button>
        </div>
      </div>

      <!-- Cards de estatísticas -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
              <FileText class="h-5 w-5 text-blue-600" />
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Entradas</p>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ logStats.total_entries }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-green-100 dark:bg-green-900/20 rounded-lg">
              <HardDrive class="h-5 w-5 text-green-600" />
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tamanho do Arquivo</p>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ logStats.file_size }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
              <Clock class="h-5 w-5 text-purple-600" />
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Última Modificação</p>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ logStats.last_modified || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <div class="flex items-center">
            <div class="p-2 bg-red-100 dark:bg-red-900/20 rounded-lg">
              <AlertTriangle class="h-5 w-5 text-red-600" />
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Erros</p>
              <p class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ (logStats.levels_count?.error || 0) + (logStats.levels_count?.critical || 0) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Busca -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Buscar
          </label>
          <div class="relative">
            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <input
              v-model="localFilters.search"
              @input="debouncedSearch"
              type="text"
              placeholder="Buscar nos logs..."
              class="pl-10 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Nível -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Nível
          </label>
          <select
            v-model="localFilters.level"
            @change="applyFilters"
            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
          >
            <option v-for="level in logLevels" :key="level.value" :value="level.value">
              {{ level.label }}
            </option>
          </select>
        </div>

        <!-- Data de início -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Data de Início
          </label>
          <input
            v-model="localFilters.date_from"
            @change="applyFilters"
            type="date"
            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
        </div>

        <!-- Data de fim -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Data de Fim
          </label>
          <input
            v-model="localFilters.date_to"
            @change="applyFilters"
            type="date"
            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
          />
        </div>
      </div>

      <!-- Botões de ação dos filtros -->
      <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">
          Mostrando {{ logs.from || 0 }} até {{ logs.to || 0 }} de {{ logs.total || 0 }} entradas
        </div>
        <div class="flex gap-2">
          <Button variant="ghost" size="sm" class="gap-2" @click="clearFilters">
            <ActionIconBox variant="ghost">
              <X />
            </ActionIconBox>
            Limpar Filtros
          </Button>
          <Button variant="outline" size="sm" class="gap-2" :disabled="loading" @click="refreshLogs">
            <ActionIconBox variant="outline">
              <RefreshCw :class="{ 'animate-spin': loading }" />
            </ActionIconBox>
            Atualizar
          </Button>
        </div>
      </div>
    </div>

    <!-- Tabela de logs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Data/Hora
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Nível
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Mensagem
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Ações
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="4" class="px-6 py-8 text-center">
                <div class="flex items-center justify-center">
                  <RefreshCw class="h-5 w-5 animate-spin text-blue-600 mr-2" />
                  <span class="text-gray-600 dark:text-gray-400">Carregando logs...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="!logs.data || logs.data.length === 0">
              <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                <FileText class="h-12 w-12 mx-auto mb-2 text-gray-300" />
                <p>Nenhum log encontrado</p>
              </td>
            </tr>
            <tr v-else v-for="log in logs.data" :key="log.datetime" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ log.formatted_date }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="getLevelBadgeClass(log.level_color)"
                  class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                >
                  {{ log.level }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                <div class="max-w-7xl">
                  <p class="truncate">{{ log.message }}</p>
                  <button
                    v-if="log.context && log.context.trim()"
                    @click="toggleLogDetails(log)"
                    class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 text-xs mt-1"
                  >
                    {{ expandedLogs.has(log.datetime) ? 'Ocultar' : 'Ver' }} detalhes
                  </button>
                </div>
                <div
                  v-if="expandedLogs.has(log.datetime) && log.context"
                  class="mt-2 p-3 bg-gray-100 dark:bg-gray-700 rounded text-xs font-mono whitespace-pre-wrap"
                >
                  {{ log.context.trim() }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button
                  @click="copyLogToClipboard(log)"
                  class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                  title="Copiar log"
                >
                  <Copy class="h-4 w-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginação -->
      <div v-if="logs.data && logs.data.length > 0" class="bg-white dark:bg-gray-800 px-4 py-3 border-t border-gray-200 dark:border-gray-700 sm:px-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <span class="text-sm text-gray-700 dark:text-gray-300">
              Mostrando {{ logs.from }} até {{ logs.to }} de {{ logs.total }} resultados
            </span>
          </div>
          <div class="flex items-center space-x-2">
            <Link
              v-if="logs.current_page > 1"
              :href="index.url({ query: { ...localFilters, page: logs.current_page - 1 } })"
              class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Anterior
            </Link>
            <span class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300">
              Página {{ logs.current_page }} de {{ logs.last_page }}
            </span>
            <Link
              v-if="logs.current_page < logs.last_page"
              :href="index.url({ query: { ...localFilters, page: logs.current_page + 1 } })"
              class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
            >
              Próxima
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de confirmação para limpar logs -->
    <div
      v-if="showClearModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="showClearModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800"
        @click.stop
      >
        <div class="mt-3 text-center">
          <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/20">
            <AlertTriangle class="h-6 w-6 text-red-600" />
          </div>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mt-2">
            Limpar Logs
          </h3>
          <div class="mt-2 px-7 py-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">
              Tem certeza que deseja limpar todos os logs? Esta ação não pode ser desfeita.
            </p>
          </div>
          <div class="items-center px-4 py-3 space-y-3">
            <Button
              variant="destructive"
              size="default"
              class="w-full"
              :disabled="clearingLogs"
              @click="clearLogs"
            >
              <span v-if="clearingLogs">Limpando...</span>
              <span v-else>Sim, Limpar Logs</span>
            </Button>
            <Button
              variant="outline"
              size="default"
              class="w-full"
              @click="showClearModal = false"
            >
              Cancelar
            </Button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '~/components/ui/button'
import ActionIconBox from '~/components/ui/ActionIconBox.vue'
import { index, clear, download } from '@/actions/App/Http/Controllers/LogViewerController'
import {
  FileText,
  Search,
  Download,
  Trash2,
  RefreshCw,
  X,
  Copy,
  AlertTriangle,
  Clock,
  HardDrive
} from 'lucide-vue-next'

// Props
const props = defineProps({
  logs: Object,
  filters: Object,
  logLevels: Array,
  logStats: Object,
  perPage: Number
})

// Estado reativo
const loading = ref(false)
const showClearModal = ref(false)
const clearingLogs = ref(false)
const expandedLogs = ref(new Set())

// Filtros locais
const localFilters = reactive({
  search: props.filters.search || '',
  level: props.filters.level || 'all',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || ''
})

// Debounce para busca
let searchTimeout = null
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500)
}

// Métodos
const applyFilters = () => {
  loading.value = true
  
  const params = { ...localFilters }
  
  // Remove parâmetros vazios
  Object.keys(params).forEach(key => {
    if (!params[key] || params[key] === 'all') {
      delete params[key]
    }
  })
  
  router.get(index.url({ query: params }), params, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false
    }
  })
}

const clearFilters = () => {
  Object.keys(localFilters).forEach(key => {
    if (key === 'level') {
      localFilters[key] = 'all'
    } else {
      localFilters[key] = ''
    }
  })
  applyFilters()
}

const refreshLogs = () => {
  applyFilters()
}

const getLevelBadgeClass = (color) => {
  const classes = {
    red: 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
    yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
    blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
    gray: 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
  }
  return classes[color] || classes.gray
}

const toggleLogDetails = (log) => {
  if (expandedLogs.value.has(log.datetime)) {
    expandedLogs.value.delete(log.datetime)
  } else {
    expandedLogs.value.add(log.datetime)
  }
}

const copyLogToClipboard = async (log) => {
  const logText = `[${log.datetime}] ${log.level}: ${log.message}${log.context ? '\n' + log.context : ''}`
  
  try {
    await navigator.clipboard.writeText(logText)
    // Aqui você pode adicionar uma notificação de sucesso
  } catch (err) {
    console.error('Erro ao copiar log:', err)
  }
}

const downloadLogs = () => {
  window.open(download.url())
}

const clearLogs = async () => {
  clearingLogs.value = true
  
  try {
    const response = await fetch(clear.url(), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })
    
    const data = await response.json()
    
    if (data.success) {
      showClearModal.value = false
      refreshLogs()
      // Aqui você pode adicionar uma notificação de sucesso
    } else {
      console.error('Erro ao limpar logs:', data.message)
      // Aqui você pode adicionar uma notificação de erro
    }
  } catch (error) {
    console.error('Erro ao limpar logs:', error)
    // Aqui você pode adicionar uma notificação de erro
  } finally {
    clearingLogs.value = false
  }
}
</script> 