# Identidade Visual — Raptor / Plannerate

## Paleta de Cores

### Cores de Marca
```
Amber 500   #F59E0B   Principal — botões, ícones ativos, acento
Amber 400   #FBBF24   Hover de elementos amber
Amber 600   #D97706   Pressed/active de elementos amber
Amber 100   #FEF3C7   Background suave amber (badges, highlights)
```

### Sidebar
```
Navy 900    #1A2035   Background do sidebar
Navy 800    #242D45   Hover de item
Navy 700    #2D3954   Item ativo (background)
Navy 700b   #2A3450   Borda separadora
Slate 400   #94A3B8   Texto de item inativo
White       #FFFFFF   Texto de item ativo
```

### Superfícies (tema claro)
```
Gray 50     #F8F9FA   Background geral da página
White       #FFFFFF   Background de cards
Gray 200    #E5E7EB   Bordas de cards e inputs
Gray 100    #F1F5F9   Background de inputs
```

### Superfícies (tema escuro)
```
Slate 950   #0F172A   Background geral da página
Slate 800   #1E293B   Background de cards
Slate 700   #334155   Bordas
Slate 600   #475569   Background de inputs
```

### Status / Semântica
```
Sucesso     #22C55E   Verde  — status PUBLISHED, ativo, sucesso
Atenção     #F59E0B   Amber  — status DRAFT, pendente, atenção
Erro        #EF4444   Vermelho — erro, excluir, inativo
Info        #3B82F6   Azul   — informação, dica
Neutro      #6B7280   Cinza  — desabilitado, secundário
```

### Badges de Status (extraídos das telas)
```
DRAFT:      bg #F3F4F6  text #374151  border #D1D5DB   (cinza neutro)
PUBLISHED:  bg #DCFCE7  text #166534  border #BBF7D0   (verde suave)
ARCHIVED:   bg #FEF3C7  text #92400E  border #FDE68A   (amber suave)
INACTIVE:   bg #FEE2E2  text #991B1B  border #FECACA   (vermelho suave)
```

---

## Tipografia

```
Fonte principal: Inter (Google Fonts)
Fallback: ui-sans-serif, system-ui, sans-serif

Tamanhos:
  xs:   12px / 16px   — labels de campos, metadados
  sm:   14px / 20px   — texto de corpo, inputs, botões
  base: 16px / 24px   — conteúdo principal
  lg:   18px / 28px   — subtítulos de seção
  xl:   20px / 28px   — títulos de card
  2xl:  24px / 32px   — títulos de página (H1)
  3xl:  30px / 36px   — títulos grandes

Pesos:
  normal:   400  — texto de corpo, labels secundários
  medium:   500  — labels de campo, itens de menu
  semibold: 600  — títulos de card, botões
  bold:     700  — títulos de página (H1)
```

### Convenções tipográficas das telas
```
Título da página (H1):  text-2xl font-bold text-slate-900 dark:text-white
Subtítulo da página:    text-sm text-slate-500 dark:text-slate-400 mt-0.5
Label de campo (card):  text-[10px] font-semibold uppercase tracking-wider text-slate-400
Valor de campo (card):  text-sm font-medium text-slate-700 dark:text-slate-200
Breadcrumb:             text-sm text-slate-500, ativo text-slate-700
```

---

## Espaçamento e Grid

```
Sidebar width:      240px (expandido) | 64px (colapsado)
Header height:      64px
Content padding:    24px (desktop) | 16px (mobile)
Card gap:           16px
Card padding:       20px (desktop) | 16px (mobile)
Card border-radius: 12px
Input border-radius: 8px
Button border-radius: 8px (padrão) | 9999px (pill — estilo Plannerate)
```

### Botões — estilo Plannerate (extraído das telas)

Os botões no Plannerate têm estilo **pill** (border-radius muito alto) com ícone + texto:

```
Filled (dark):  bg #1E293B  text white  hover bg #0F172A  — Editar, Excluir
Filled (amber): bg #F59E0B  text white  hover bg #D97706  — Criar Novo, Gerenciar
Outline:        border #E5E7EB  text slate  hover bg gray-50
```

```vue
<!-- Botão padrão Plannerate -->
<button class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold
               bg-slate-800 text-white hover:bg-slate-900 transition-colors">
  <Icon name="Edit" class="h-4 w-4" />
  Editar
</button>

<!-- Botão amber (ação primária) -->
<button class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold
               bg-amber-500 text-white hover:bg-amber-600 transition-colors">
  <Icon name="Plus" class="h-4 w-4" />
  Criar Novo
</button>
```

---

## Ícones

Usar **Lucide Vue Next** como biblioteca padrão:

```ts
import {
  // Layout
  LayoutDashboard, PanelLeft, ChevronRight, ChevronDown,
  // Ações comuns
  Plus, Pencil, Trash2, Download, Upload, Search, Filter, SlidersHorizontal,
  // Status/feedback
  CheckCircle2, AlertCircle, Info, XCircle, Loader2,
  // Tema
  Sun, Moon, Monitor,
  // Usuário
  Bell, User, Settings, LogOut,
  // Conteúdo
  Package, Tag, Ruler, BarChart2, Map, Kanban, List,
} from 'lucide-vue-next'
```

**Tamanhos de ícone padrão:**
```
Em botão:       h-4 w-4 (16px)
Em sidebar:     h-5 w-5 (20px)
Em header:      h-5 w-5 (20px)
Standalone:     h-6 w-6 (24px)
Hero/destaque:  h-8 w-8 (32px)
```

---

## Sombras e Elevação

```
Nível 0 (flat):   sem sombra — backgrounds, inputs
Nível 1 (card):   shadow-sm  — cards, dropdowns fechados
Nível 2 (hover):  shadow-md  — cards no hover, popovers
Nível 3 (modal):  shadow-xl  — modais, drawers
Sidebar:          shadow personalizada — 4px 0 24px rgba(0,0,0,0.12)
```

---

## Logo Plannerate

O logo nas telas mostra **"PLANNERATE"** com um símbolo de grade à esquerda.

```vue
<!-- Logo component -->
<template>
  <div class="flex items-center gap-2.5">
    <!-- Símbolo: grade de gondola estilizada -->
    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500">
      <svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor">
        <rect x="2" y="3" width="20" height="18" rx="2" stroke-width="2"/>
        <line x1="2" y1="9" x2="22" y2="9" stroke-width="1.5"/>
        <line x1="2" y1="15" x2="22" y2="15" stroke-width="1.5"/>
        <line x1="8" y1="3" x2="8" y2="21" stroke-width="1.5"/>
        <line x1="16" y1="3" x2="16" y2="21" stroke-width="1.5"/>
      </svg>
    </div>
    <!-- Texto — oculto quando sidebar colapsada -->
    <span
      class="text-white font-bold text-sm tracking-widest uppercase transition-all duration-300"
      :class="{ 'opacity-0 w-0 overflow-hidden': collapsed }"
    >
      Plannerate
    </span>
  </div>
</template>
```