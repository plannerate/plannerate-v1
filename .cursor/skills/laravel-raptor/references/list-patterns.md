# Padrões de Listagem — Plannerate

## Como escolher o padrão certo

| Padrão | Quando usar | Exemplos no sistema |
|--------|-------------|---------------------|
| Card Rico | Entidade visual com imagem, progresso, muitas ações | Planogramas |
| Linha Compacta | Lista densa, muitos itens, dados técnicos secundários | Produtos |
| Grid de Dados | Campos estruturados com labels visíveis, poucas ações | Categorias, Dimensões |

---

## Padrão 1 — Card Rico (Planogramas)

### Estrutura visual

```
┌─────────────────────────────────────────────────────────────┐
│ [DRAFT] • SUPERMERCADO                                      │
│                                                             │
│  ┌───────┐   Bruda — Planograma Importados                  │
│  │ img   │   📋 SUPERMERCADO                                │
│  │gondola│                                                  │
│  └───────┘   Progresso ████████░░ 65%                       │
│              DATA DE INÍCIO        PRAZO DE TÉRMINO         │
│              2025-01-02            2025-10-10               │
│                                                             │
│  [● Editar] [🗑 Excluir] [⚙ Gerenciar Gôndolas] [+ Gôndola]│
└─────────────────────────────────────────────────────────────┘
```

### Implementação Vue

```vue
<!-- components/list/RichCard.vue -->
<script setup lang="ts">
interface Props {
  image?: string
  statusLabel?: string
  statusVariant?: 'draft' | 'published' | 'archived' | 'inactive'
  context?: string        // "SUPERMERCADO"
  title: string
  subtitle?: string
  progress?: number       // 0-100
  meta?: Array<{ label: string; value: string }>
}

defineProps<Props>()
defineEmits<{ action: [key: string] }>()

const statusClasses = {
  draft:    'bg-gray-100 text-gray-700 border-gray-200',
  published:'bg-green-50 text-green-700 border-green-200',
  archived: 'bg-amber-50 text-amber-700 border-amber-200',
  inactive: 'bg-red-50 text-red-700 border-red-200',
}
</script>

<template>
  <div class="bg-white dark:bg-slate-800 rounded-xl border border-surface-border
              shadow-card hover:shadow-card-hover transition-shadow p-5">
    <!-- Status row -->
    <div class="flex items-center gap-2 mb-3">
      <span
        v-if="statusLabel"
        class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold
               uppercase tracking-wider border"
        :class="statusClasses[statusVariant ?? 'draft']"
      >
        {{ statusLabel }}
      </span>
      <span v-if="context" class="text-xs text-slate-400 font-medium">
        • {{ context }}
      </span>
    </div>

    <!-- Body -->
    <div class="flex gap-4">
      <!-- Imagem/thumbnail -->
      <div v-if="image || $slots.image"
           class="shrink-0 w-20 h-20 rounded-lg border border-surface-border
                  bg-slate-50 flex items-center justify-center overflow-hidden">
        <slot name="image">
          <img :src="image" :alt="title" class="w-full h-full object-cover" />
        </slot>
      </div>

      <!-- Conteúdo -->
      <div class="flex-1 min-w-0">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white truncate">
          {{ title }}
        </h3>
        <p v-if="subtitle" class="text-sm text-slate-500 flex items-center gap-1 mt-0.5">
          <slot name="subtitle-icon" />
          {{ subtitle }}
        </p>

        <!-- Progresso -->
        <div v-if="progress !== undefined" class="mt-3">
          <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              Progresso
            </span>
            <span class="text-xs font-semibold text-slate-600">{{ progress }}%</span>
          </div>
          <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
            <div
              class="h-full bg-green-500 rounded-full transition-all duration-500"
              :style="{ width: `${progress}%` }"
            />
          </div>
        </div>

        <!-- Meta dados (DATA DE INÍCIO, PRAZO DE TÉRMINO, etc.) -->
        <div v-if="meta?.length" class="flex flex-wrap gap-x-6 gap-y-1 mt-3">
          <div v-for="item in meta" :key="item.label" class="flex flex-col">
            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">
              {{ item.label }}
            </span>
            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
              {{ item.value }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Ações — sempre no rodapé, pill buttons escuros + amber -->
    <div class="flex flex-wrap items-center gap-2 mt-4 pt-4 border-t border-surface-border">
      <slot name="actions" />
    </div>
  </div>
</template>
```

---

## Padrão 2 — Linha Compacta (Produtos)

### Estrutura visual

```
┌──┬──┬──┬─────────────────────────┬────────────────────────────────────┐
│th│sw│✓ │ NOME DO PRODUTO          │ HIERARQUIA > CATEGORIA > SUBCATEG  │
│  │  │  │ SKU: 000 · Código ERP: 0 │ Estoque · Altura · Largura · Peso  │
└──┴──┴──┴─────────────────────────┴────────────────────────────────────┘
```

### Implementação Vue

```vue
<!-- components/list/CompactRow.vue -->
<script setup lang="ts">
interface Props {
  thumbnail?: string
  active?: boolean
  verified?: boolean
  title: string
  meta?: string         // "SKU: 000 · Código ERP: 000"
  hierarchy?: string    // "SUPERMERCADO > HIGIENE > ..."
  details?: string      // "Estoque: 14.50 · Altura: 13.00 · ..."
  selected?: boolean
}

defineProps<Props>()
defineEmits<{
  'update:active': [value: boolean]
  'update:selected': [value: boolean]
}>()
</script>

<template>
  <div
    class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-slate-800
           border-b border-surface-border hover:bg-slate-50 dark:hover:bg-slate-750
           transition-colors group"
    :class="{ 'bg-brand-50 dark:bg-brand-900/10': selected }"
  >
    <!-- Checkbox seleção -->
    <input
      type="checkbox"
      :checked="selected"
      class="rounded border-gray-300 text-amber-500 focus:ring-amber-500"
      @change="$emit('update:selected', ($event.target as HTMLInputElement).checked)"
    />

    <!-- Thumbnail -->
    <div class="shrink-0 w-10 h-10 rounded-lg border border-surface-border
                bg-slate-50 overflow-hidden flex items-center justify-center">
      <img v-if="thumbnail" :src="thumbnail" :alt="title" class="w-full h-full object-cover" />
      <div v-else class="w-full h-full bg-slate-100 flex items-center justify-center">
        <PackageIcon class="h-5 w-5 text-slate-300" />
      </div>
    </div>

    <!-- Toggle ativo + verificado -->
    <div class="flex flex-col items-center gap-1 shrink-0">
      <!-- Toggle switch (ativo/inativo) -->
      <button
        type="button"
        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2
               border-transparent transition-colors duration-200 focus:outline-none
               focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
        :class="active ? 'bg-amber-500' : 'bg-gray-200'"
        @click="$emit('update:active', !active)"
      >
        <span
          class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white
                 shadow ring-0 transition duration-200"
          :class="active ? 'translate-x-4' : 'translate-x-0'"
        />
      </button>
      <!-- Ícone verificado -->
      <CheckCircle2Icon
        class="h-4 w-4 transition-colors"
        :class="verified ? 'text-amber-500' : 'text-slate-200'"
      />
    </div>

    <!-- Info principal -->
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
        {{ title }}
      </p>
      <p v-if="meta" class="text-xs text-slate-400 mt-0.5 truncate">{{ meta }}</p>
    </div>

    <!-- Hierarquia e detalhes — hidden em mobile -->
    <div class="hidden lg:flex flex-col items-end min-w-0 max-w-xs xl:max-w-sm">
      <p v-if="hierarchy"
         class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-0.5">
        Hierarquia
      </p>
      <p v-if="hierarchy" class="text-xs text-slate-600 dark:text-slate-300 truncate text-right">
        {{ hierarchy }}
      </p>
      <p v-if="details" class="text-[11px] text-slate-400 mt-0.5 truncate text-right">
        {{ details }}
      </p>
    </div>

    <!-- Ações — aparecem no hover em desktop, sempre visíveis em mobile -->
    <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100
                transition-opacity lg:opacity-100">
      <slot name="actions" />
    </div>
  </div>
</template>
```

---

## Padrão 3 — Grid de Dados (Categorias)

### Estrutura visual

```
┌──────────────────────────────────────────────────────────────┐
│  STATUS              NOME              CATEGORIA PAI         │
│  [toggle][✓]         MOÍDO             SUPERMERCADO > ...    │
│                                                              │
│  NÍVEL               NÍVEL NOME        CRIADO EM             │
│  5                   subcategoria      04/03/2026 11:41      │
│                                                              │
│  [Editar] [Excluir]                                          │
└──────────────────────────────────────────────────────────────┘
```

### Implementação Vue

```vue
<!-- components/list/DataGrid.vue -->
<script setup lang="ts">
interface Field {
  label: string
  value: string | number | boolean | null
  type?: 'text' | 'status' | 'date' | 'badge' | 'boolean'
  span?: 1 | 2 | 3  // quantas colunas ocupa no grid
}

interface Props {
  fields: Field[]       // campos a exibir com label+valor
  columns?: 2 | 3      // colunas do grid (default 3)
  selected?: boolean
}

defineProps<Props>()
</script>

<template>
  <div
    class="bg-white dark:bg-slate-800 rounded-xl border border-surface-border
           shadow-card p-5 space-y-4"
    :class="{ 'ring-2 ring-amber-500': selected }"
  >
    <!-- Grid de campos -->
    <dl
      class="grid gap-x-6 gap-y-4"
      :class="{
        'grid-cols-2': columns === 2,
        'grid-cols-2 lg:grid-cols-3': !columns || columns === 3,
      }"
    >
      <div
        v-for="field in fields"
        :key="field.label"
        :class="{
          'col-span-2': field.span === 2,
          'col-span-full': field.span === 3,
        }"
      >
        <!-- Label (uppercase, small) -->
        <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">
          {{ field.label }}
        </dt>

        <!-- Valor — renderiza diferente por tipo -->
        <dd>
          <!-- Status toggle -->
          <div v-if="field.type === 'status'" class="flex items-center gap-2">
            <slot :name="`field-${field.label.toLowerCase().replace(/\s+/g, '-')}`">
              <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                {{ field.value }}
              </span>
            </slot>
          </div>

          <!-- Boolean -->
          <span
            v-else-if="field.type === 'boolean'"
            class="inline-flex items-center gap-1 text-sm font-medium"
            :class="field.value ? 'text-green-600' : 'text-slate-400'"
          >
            <CheckIcon v-if="field.value" class="h-4 w-4" />
            <XIcon v-else class="h-4 w-4" />
          </span>

          <!-- Texto padrão -->
          <span v-else class="text-sm font-medium text-slate-700 dark:text-slate-200">
            {{ field.value ?? '—' }}
          </span>
        </dd>
      </div>
    </dl>

    <!-- Ações no rodapé -->
    <div v-if="$slots.actions"
         class="flex flex-wrap items-center gap-2 pt-4 border-t border-surface-border">
      <slot name="actions" />
    </div>
  </div>
</template>
```

---

## ListPageHeader — Cabeçalho de Listagem

Padrão único para o topo de todas as páginas de listagem:

```vue
<!-- components/list/ListPageHeader.vue -->
<template>
  <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
    <!-- Busca + Filtros -->
    <div class="flex-1 flex items-center gap-2">
      <div class="relative flex-1 max-w-md">
        <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
        <input
          v-model="searchModel"
          type="search"
          placeholder="Buscar..."
          class="w-full pl-9 pr-4 py-2 text-sm border border-surface-border rounded-lg
                 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
      </div>
      <button class="flex items-center gap-1.5 px-3 py-2 text-sm text-slate-600
                     border border-surface-border rounded-lg hover:bg-slate-50
                     dark:border-slate-700 dark:hover:bg-slate-700 transition-colors">
        <SlidersHorizontalIcon class="h-4 w-4" />
        <span class="hidden sm:inline">Filtros</span>
        <span v-if="activeFilters > 0"
              class="inline-flex items-center justify-center w-4 h-4 text-[10px] font-bold
                     rounded-full bg-amber-500 text-white">
          {{ activeFilters }}
        </span>
      </button>
    </div>

    <!-- Ações (Criar Novo, Exportar, Importar) -->
    <div class="flex items-center gap-2 shrink-0">
      <slot name="actions" />
    </div>
  </div>
</template>
```