
# Tema e Mobile — Raptor

## Tema Claro / Escuro

### Estratégia: class-based dark mode

O Tailwind usa `darkMode: 'class'` — adicionar a classe `dark` no `<html>` ativa o tema escuro.
Nunca usar `prefers-color-scheme` diretamente no CSS — deixar o `useTheme` composable gerenciar.

### Inicialização (evitar flash de tema errado)

Adicionar no `<head>` do blade ANTES do CSS principal:

```html
<!-- resources/views/app.blade.php -->
<head>
  <!-- Script inline para aplicar tema ANTES do render (evita flash) -->
  <script>
    (function() {
      const theme = localStorage.getItem('raptor-theme') || 'system'
      const isDark = theme === 'dark' ||
        (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
      if (isDark) document.documentElement.classList.add('dark')
    })()
  </script>
  @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
```

### Componente ThemeToggle

```vue
<!-- components/ui/ThemeToggle.vue -->
<script setup lang="ts">
import { useTheme } from '@/composables/useTheme'
import { SunIcon, MoonIcon, MonitorIcon } from 'lucide-vue-next'

const { theme, setTheme } = useTheme()

const options = [
  { value: 'light',  icon: SunIcon,     label: 'Tema claro' },
  { value: 'dark',   icon: MoonIcon,    label: 'Tema escuro' },
  { value: 'system', icon: MonitorIcon, label: 'Padrão do sistema' },
] as const
</script>

<template>
  <div class="relative" v-dropdown>
    <button class="p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700
                   transition-colors focus:outline-none focus:ring-2 focus:ring-amber-500">
      <SunIcon v-if="theme === 'light'" class="h-5 w-5" />
      <MoonIcon v-else-if="theme === 'dark'" class="h-5 w-5" />
      <MonitorIcon v-else class="h-5 w-5" />
    </button>

    <div class="absolute right-0 mt-2 w-44 bg-white dark:bg-slate-800 rounded-xl
                shadow-xl border border-surface-border py-1 z-50">
      <button
        v-for="opt in options"
        :key="opt.value"
        class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-left
               hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
        :class="theme === opt.value ? 'text-amber-500 font-semibold' : 'text-slate-700 dark:text-slate-200'"
        @click="setTheme(opt.value)"
      >
        <component :is="opt.icon" class="h-4 w-4" />
        {{ opt.label }}
        <CheckIcon v-if="theme === opt.value" class="h-4 w-4 ml-auto" />
      </button>
    </div>
  </div>
</template>
```

---

## Mobile — Padrões de Responsividade

### Breakpoints (Tailwind padrão)
```
sm:  640px  — tablets pequenos
md:  768px  — tablets
lg:  1024px — desktop (sidebar aparece aqui)
xl:  1280px — desktop largo
2xl: 1536px — telas grandes
```

### Sidebar em mobile

```
Mobile (< lg):   sidebar OCULTA, abre como drawer com overlay
Desktop (≥ lg):  sidebar FIXA na lateral, colapsável para 64px
```

```vue
<!-- AppSidebar.vue — comportamento mobile -->
<template>
  <!-- Desktop: sidebar fixa -->
  <aside
    class="hidden lg:flex flex-col fixed inset-y-0 left-0 z-30 bg-sidebar-bg
           shadow-sidebar transition-all duration-300"
    :class="collapsed ? 'w-16' : 'w-60'"
  >
    <slot />
  </aside>

  <!-- Mobile: drawer com Transition -->
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="-translate-x-full"
    enter-to-class="translate-x-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="translate-x-0"
    leave-to-class="-translate-x-full"
  >
    <aside
      v-if="mobileOpen"
      class="lg:hidden fixed inset-y-0 left-0 z-30 w-72 bg-sidebar-bg shadow-xl flex flex-col"
    >
      <!-- Botão fechar no mobile -->
      <button
        class="absolute top-4 right-4 p-1 rounded-lg text-slate-400 hover:text-white"
        @click="$emit('close-mobile')"
      >
        <XIcon class="h-5 w-5" />
      </button>
      <slot />
    </aside>
  </Transition>
</template>
```

### Header em mobile

```vue
<!-- AppHeader.vue — adaptação mobile -->
<template>
  <header class="h-16 bg-white dark:bg-slate-900 border-b border-surface-border
                 flex items-center px-4 lg:px-6 gap-3 sticky top-0 z-20">

    <!-- Hambúrguer — só mobile -->
    <button
      class="lg:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"
      @click="$emit('toggle-mobile-sidebar')"
    >
      <MenuIcon class="h-5 w-5 text-slate-600" />
    </button>

    <!-- Breadcrumb — simplificado em mobile -->
    <nav class="flex-1 min-w-0">
      <ol class="flex items-center gap-1 text-sm">
        <!-- Em mobile: só mostra o último item -->
        <li class="hidden sm:flex items-center gap-1 text-slate-400">
          <a :href="route('dashboard')" class="hover:text-slate-600">
            <HomeIcon class="h-4 w-4" />
          </a>
        </li>
        <template v-for="(crumb, i) in breadcrumbs" :key="crumb.label">
          <li class="hidden sm:block text-slate-300">/</li>
          <li :class="i === breadcrumbs.length - 1 ? 'text-slate-700 dark:text-slate-200 font-medium' : 'text-slate-400 hidden sm:block'">
            <a v-if="crumb.href" :href="crumb.href" class="hover:text-slate-600">{{ crumb.label }}</a>
            <span v-else>{{ crumb.label }}</span>
          </li>
        </template>
      </ol>
    </nav>

    <!-- Busca global — ícone em mobile, campo em desktop -->
    <div class="flex items-center gap-2">
      <!-- Mobile: só ícone -->
      <button class="md:hidden p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
        <SearchIcon class="h-5 w-5 text-slate-500" />
      </button>
      <!-- Desktop: campo completo -->
      <div class="hidden md:flex relative">
        <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
        <input
          type="search"
          placeholder="Buscar..."
          class="pl-9 pr-4 py-2 text-sm w-56 lg:w-72 border border-surface-border rounded-lg
                 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500
                 focus:bg-white dark:focus:bg-slate-700 transition-all"
          @input="onSearch"
        />
        <kbd class="absolute right-3 top-1/2 -translate-y-1/2 hidden lg:block
                    text-[10px] text-slate-400 border border-slate-200 rounded px-1.5 py-0.5">
          ⌘K
        </kbd>
      </div>
    </div>

    <!-- Ações do header -->
    <div class="flex items-center gap-1">
      <ThemeToggle />
      <NotificationBell />
      <UserMenu />
    </div>
  </header>
</template>
```

### Listagens em mobile

```
Card Rico:    stack em coluna única, ações em 2 colunas
Linha Compacta: ocultar colunas de hierarquia/detalhes, manter thumbnail + nome + toggle
Grid de Dados:  de 3 colunas → 2 colunas → 1 coluna
Ações (botões): agrupar em dropdown "⋯" quando > 2 botões em mobile
```

```vue
<!-- Ações responsivas — colapsa para dropdown em mobile -->
<template>
  <!-- Desktop: botões visíveis -->
  <div class="hidden sm:flex items-center gap-2">
    <slot name="actions" />
  </div>

  <!-- Mobile: dropdown -->
  <div class="sm:hidden">
    <DropdownMenu>
      <DropdownMenuTrigger class="p-2 rounded-lg hover:bg-slate-100">
        <MoreHorizontalIcon class="h-5 w-5" />
      </DropdownMenuTrigger>
      <DropdownMenuContent>
        <slot name="actions-mobile" />
      </DropdownMenuContent>
    </DropdownMenu>
  </div>
</template>
```

### Tabelas em mobile

```vue
<!-- Wrapper para tabelas responsivas -->
<div class="overflow-x-auto -mx-4 sm:mx-0 rounded-xl">
  <table class="min-w-full">
    <!-- Colunas essenciais: sem min-width restritivo -->
    <!-- Colunas opcionais: hidden em mobile com classe sm:table-cell -->
    <th class="hidden sm:table-cell">Código ERP</th>
    <td class="hidden sm:table-cell">{{ item.erp_code }}</td>
  </table>
</div>
```