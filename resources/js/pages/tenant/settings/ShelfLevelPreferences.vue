<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-2xl font-bold text-gray-900">Preferências de Nível de Prateleira</h2>
      <p class="mt-1 text-sm text-gray-500">Defina as preferências de posicionamento dos produtos por categoria</p>
    </div>

    <div class="bg-white shadow rounded-lg divide-y">
      <!-- Default Preference -->
      <div class="px-6 py-5">
        <h3 class="text-lg font-medium text-gray-900">Preferência Padrão do Tenant</h3>
        <div class="mt-4 flex items-center space-x-4">
          <select
            v-model="defaultLevel"
            @change="updateDefaultPreference"
            class="block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          >
            <option v-for="level in Object.values(ShelfLevelOptions)" :key="level" :value="level">
              {{ getShelfLevelLabel(level) }}
            </option>
          </select>
          <span :class="`px-3 py-1 rounded-full text-sm font-medium ${getShelfLevelColorClass(defaultLevel)}`">
            {{ getShelfLevelLabel(defaultLevel) }}
          </span>
        </div>
      </div>

      <!-- Category Preferences -->
      <div class="px-6 py-5">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Preferências por Categoria</h3>
          <button
            @click="showAddForm = true"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700"
          >
            Adicionar
          </button>
        </div>

        <!-- Add/Edit Form -->
        <div v-if="showAddForm" class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-300">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Categoria</label>
              <input
                v-model.lazy="formData.searchCategory"
                type="text"
                placeholder="Buscar categoria..."
                @input="searchCategories"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
              <div v-if="filteredCategories.length > 0" class="mt-2 max-h-40 overflow-y-auto border border-gray-300 rounded">
                <div
                  v-for="cat in filteredCategories"
                  :key="cat.id"
                  @click="formData.categoryId = cat.id; formData.searchCategory = cat.name"
                  class="px-3 py-2 cursor-pointer hover:bg-gray-100"
                >
                  {{ cat.name }}
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Nível Preferido</label>
              <select
                v-model="formData.preferredLevel"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option v-for="level in Object.values(ShelfLevelOptions)" :key="level" :value="level">
                  {{ getShelfLevelLabel(level) }}
                </option>
              </select>
            </div>

            <div class="flex space-x-3">
              <button
                @click="savePreference"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                :disabled="!formData.categoryId || isLoading"
              >
                {{ isLoading ? 'Salvando...' : 'Salvar' }}
              </button>
              <button
                @click="cancelForm"
                class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
              >
                Cancelar
              </button>
            </div>
          </div>
        </div>

        <!-- Preferences Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Categoria</th>
                <th scope="col" class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Nível Preferido</th>
                <th scope="col" class="px-6 py-3  text-sm font-semibold text-gray-900">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="pref in preferences" :key="pref.id">
                <td class="px-6 py-4 text-sm text-gray-900">{{ pref.category?.name || 'N/A' }}</td>
                <td class="px-6 py-4 text-sm">
                  <span :class="`px-3 py-1 rounded-full text-xs font-medium ${getShelfLevelColorClass(pref.preferred_level)}`">
                    {{ getShelfLevelLabel(pref.preferred_level) }}
                  </span>
                </td>
                <td class="px-6 py-4  text-sm space-x-2">
                  <button
                    @click="editPreference(pref)"
                    class="text-indigo-600 hover:text-indigo-900"
                  >
                    Editar
                  </button>
                  <button
                    @click="deletePreference(pref.id)"
                    class="text-red-600 hover:text-red-900"
                  >
                    Remover
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="preferences.length === 0" class="text-center py-6">
          <p class="text-gray-500">Nenhuma preferência configurada. Adicione uma para começar.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

type ShelfLevel = 'eye' | 'hand' | 'low' | 'high'

const ShelfLevelOptions = {
  EYE: 'eye' as ShelfLevel,
  HAND: 'hand' as ShelfLevel,
  LOW: 'low' as ShelfLevel,
  HIGH: 'high' as ShelfLevel,
}

const preferences = ref<any[]>([])
const categories = ref<any[]>([])
const filteredCategories = ref<any[]>([])
const showAddForm = ref(false)
const isLoading = ref(false)
const defaultLevel = ref<ShelfLevel>(ShelfLevelOptions.HAND)

const formData = ref({
  categoryId: null as string | null,
  searchCategory: '',
  preferredLevel: ShelfLevelOptions.EYE as ShelfLevel,
})

const getShelfLevelLabel = (level: ShelfLevel): string => {
  const labels: Record<ShelfLevel, string> = {
    eye: 'Nível dos olhos',
    hand: 'Nível das mãos',
    low: 'Nível do chão',
    high: 'Nível alto',
  }
  return labels[level] || level
}

const getShelfLevelColorClass = (level: ShelfLevel): string => {
  const colors: Record<ShelfLevel, string> = {
    eye: 'bg-green-100 text-green-800',
    hand: 'bg-blue-100 text-blue-800',
    low: 'bg-yellow-100 text-yellow-800',
    high: 'bg-gray-100 text-gray-800',
  }
  return colors[level] || 'bg-gray-100 text-gray-800'
}

const searchCategories = () => {
  const search = formData.value.searchCategory.toLowerCase()
  filteredCategories.value = categories.value.filter(
    cat => cat.name.toLowerCase().includes(search) && cat.id !== formData.value.categoryId
  )
}

const savePreference = async () => {
  if (!formData.value.categoryId) return

  isLoading.value = true
  try {
    // TODO: Call API endpoint to save preference
    console.log('Saving preference:', formData.value)
    showAddForm.value = false
    resetForm()
  } catch (error) {
    console.error('Failed to save preference:', error)
  } finally {
    isLoading.value = false
  }
}

const editPreference = (pref: any) => {
  formData.value = {
    categoryId: pref.category_id,
    searchCategory: pref.category?.name || '',
    preferredLevel: pref.preferred_level,
  }
  showAddForm.value = true
}

const deletePreference = async (id: string) => {
  if (!confirm('Tem certeza que deseja remover esta preferência?')) return

  try {
    // TODO: Call API endpoint to delete preference
    console.log('Deleting preference:', id)
  } catch (error) {
    console.error('Failed to delete preference:', error)
  }
}

const updateDefaultPreference = async () => {
  try {
    // TODO: Call API endpoint to update default preference
    console.log('Updating default level to:', defaultLevel.value)
  } catch (error) {
    console.error('Failed to update default preference:', error)
  }
}

const cancelForm = () => {
  showAddForm.value = false
  resetForm()
}

const resetForm = () => {
  formData.value = {
    categoryId: null,
    searchCategory: '',
    preferredLevel: ShelfLevelOptions.EYE,
  }
  filteredCategories.value = []
}

const loadPreferences = async () => {
  try {
    // TODO: Load preferences from API
    console.log('Loading preferences...')
  } catch (error) {
    console.error('Failed to load preferences:', error)
  }
}

const loadCategories = async () => {
  try {
    // TODO: Load categories from API
    console.log('Loading categories...')
  } catch (error) {
    console.error('Failed to load categories:', error)
  }
}

onMounted(() => {
  loadPreferences()
  loadCategories()
})
</script>
