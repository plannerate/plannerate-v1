---
name: laravel-raptor-ui
description: >
  Analisa, refatora e cria a identidade visual completa do Laravel Raptor e Plannerate:
  converte componentes shadcn-vue existentes para o padrão Raptor, cria/atualiza o layout
  principal (sidebar, header, content), padroniza os três tipos de listagem (card-rico,
  linha-compacta, grid-de-dados), configura tema claro/escuro, responsividade mobile,
  Vue Head dinâmico, traduções PT-BR com integração ao sistema de traduções do Laravel,
  e estabelece a identidade visual da marca Raptor/Plannerate.
  Use esta skill SEMPRE que o usuário pedir para: "criar a identidade do raptor", "refatorar
  o tema", "atualizar o layout principal", "criar o sidebar", "criar o header", "configurar
  tema escuro", "adaptar para mobile", "configurar traduções pt-br", "corrigir gramática
  das páginas", "converter shadcn para raptor", "padronizar as páginas de listagem",
  "configurar Vue Head", ou qualquer pedido relacionado à aparência geral, layout ou
  identidade do sistema Raptor/Plannerate.
  Stack: Vue 3 + Inertia.js + TailwindCSS + TypeScript. Sem shadcn obrigatório — cria
  componentes próprios seguindo a identidade Raptor extraída das telas do sistema.
---

# Laravel Raptor UI — Identidade Visual e Layout

## ⚠️ PRIMEIRA AÇÃO — Obrigatória

**NÃO gere nenhum código** até concluir este levantamento completo.

### Passo 1 — Receber o caminho do pacote

Esta skill opera sobre um **pacote Laravel local** (não diretamente sobre um projeto app).
A primeira coisa a pedir ao usuário:

```
Qual é o caminho do pacote no seu projeto local?
Ex: /home/claudio/projects/callcocam/laravel-raptor
    ./packages/callcocam/laravel-raptor
    vendor/callcocam/laravel-raptor  (se estiver em development mode)
```

Com o caminho em mãos, mapear a estrutura automaticamente:

```bash
# Substituir {PACKAGE_PATH} pelo caminho informado

# Ver estrutura completa do pacote
find {PACKAGE_PATH} -type f \( -name "*.vue" -o -name "*.ts" -o -name "*.css" -o -name "tailwind*" \) \
  | grep -v node_modules | grep -v dist | sort

# Ler layout principal
find {PACKAGE_PATH} -name "*.vue" | xargs grep -l "AppLayout\|MainLayout\|DefaultLayout" 2>/dev/null
find {PACKAGE_PATH}/resources/js -name "*Layout*" 2>/dev/null

# Ler CSS/Tailwind atuais
find {PACKAGE_PATH} -name "tailwind.config*" | head -3
find {PACKAGE_PATH} -name "app.css" -o -name "*.css" | grep -v node_modules | head -5

# Ler componentes existentes
find {PACKAGE_PATH}/resources/js -name "*.vue" | head -30

# Ler traduções existentes
find {PACKAGE_PATH} -path "*/lang/*" -name "*.php" | head -20
```

### Passo 2 — Definir identidade de cores

**Antes de qualquer mudança visual**, perguntar sobre o sistema de cores:

```
Você tem um sistema de cores definido para usar?
Pode ser:

a) Uma paleta existente (cole as cores hex ou o nome — ex: "azul #1E3A8A + amarelo #F59E0B")
b) Uma referência (print, link de Figma, site que gosta do visual)
c) Deixar eu propor — crio uma identidade moderna baseada nas telas atuais do sistema

Se escolher (c), vou apresentar 2-3 opções de paleta para você escolher antes de aplicar.
```

Se o usuário pedir para propor → apresentar opções de paleta conforme `references/color-proposals.md`
antes de gerar qualquer código. **Aguardar aprovação da paleta escolhida.**

### Passo 3 — Diagnóstico antes de tocar em qualquer arquivo

Após ter o caminho e a paleta aprovada, rodar o diagnóstico:

```bash
# Para cada arquivo encontrado: LER antes de modificar
# Listar o que existe e o que falta

echo "=== LAYOUTS ==="
find {PACKAGE_PATH}/resources/js -name "*Layout*" -o -name "*layout*" 2>/dev/null

echo "=== COMPONENTES DE LAYOUT (Sidebar, Header, Nav) ==="
find {PACKAGE_PATH}/resources/js -name "*Sidebar*" -o -name "*Header*" -o -name "*Nav*" 2>/dev/null

echo "=== COMPONENTES UI ==="
find {PACKAGE_PATH}/resources/js/components -name "*.vue" 2>/dev/null | sort

echo "=== CSS GLOBAL ==="
find {PACKAGE_PATH}/resources -name "*.css" | grep -v node_modules

echo "=== TAILWIND CONFIG ==="
find {PACKAGE_PATH} -name "tailwind.config*" | grep -v node_modules

echo "=== TRADUÇÕES ==="
find {PACKAGE_PATH} -path "*/lang/*" -name "*.php" 2>/dev/null
```

**Regra absoluta: NUNCA recriar do zero o que já existe.**
- Se arquivo existe → ler → identificar o que muda → usar `str_replace` cirurgicamente
- Se arquivo não existe → criar do zero
- Se estrutura do pacote for diferente do esperado → adaptar ao que existe, não ao que a skill assume

---

## Identidade Visual Raptor/Plannerate

Leia `references/identity.md` para a paleta completa, tipografia e tokens de design.

### Resumo da identidade (extraído das telas do sistema)

```
Marca primária:    Amber/Dourado  #F59E0B  (botões, acento ativo, ícones)
Sidebar bg:        Navy escuro    #1A2035  (fundo lateral)
Sidebar texto:     Branco         #FFFFFF  (texto no sidebar)
Sidebar ativo:     Amber suave    rgba(245,158,11,0.15) com texto amber
Content bg:        Cinza claro    #F8F9FA  (fundo do conteúdo)
Card bg:           Branco         #FFFFFF  com border #E5E7EB
Texto principal:   Slate escuro   #1E293B
Texto secundário:  Slate médio    #64748B
Destrutivo:        Vermelho       #EF4444
Sucesso:           Verde          #22C55E
```



## Fase 1 — Leitura e diagnóstico

### 1a. Ler arquivos existentes

```bash
# Layout principal
cat resources/js/Layouts/AppLayout.vue

# Componentes de sidebar/header
find resources/js -name "*Sidebar*" -o -name "*Header*" -o -name "*Nav*" | head -20

# CSS global
cat resources/css/app.css

# Tailwind config
cat tailwind.config.js 2>/dev/null || cat tailwind.config.ts

# Traduções PT-BR
find lang/pt_BR -name "*.php" 2>/dev/null | head -10
find lang/pt-BR -name "*.php" 2>/dev/null | head -10
ls lang/ 2>/dev/null

# Verificar componentes shadcn existentes
find resources/js/components/ui -name "*.vue" 2>/dev/null | head -20
```

### 1b. Diagnóstico — o que produzir

Para cada item, verificar se existe e se precisa de criação ou atualização:

| Item | Verificar | Ação |
|------|-----------|------|
| `tailwind.config` | tokens de cor Raptor presentes? | atualizar ou criar |
| `app.css` | CSS vars de tema claro/escuro? | atualizar ou criar |
| `AppLayout.vue` | sidebar + header integrados? | atualizar ou criar |
| `AppSidebar.vue` | navy, colapsável, mobile? | atualizar ou criar |
| `AppHeader.vue` | breadcrumb, busca global, tema toggle? | atualizar ou criar |
| `Head` do Vue/Inertia | título dinâmico por página? | adicionar |
| `lang/pt_BR/` | arquivos de tradução completos? | criar ou completar |
| Componentes shadcn | convertidos para padrão Raptor? | converter |

---

## Fase 2 — Tailwind Config e CSS Vars

Leia `references/theme.md` para configuração completa.

### tailwind.config.ts — tokens Raptor

```ts
import type { Config } from 'tailwindcss'

export default {
  darkMode: 'class',  // dark mode via classe no <html>
  content: [
    './resources/js/**/*.{vue,ts,tsx}',
    './resources/views/**/*.blade.php',
  ],
  theme: {
    extend: {
      colors: {
        // Marca
        brand: {
          DEFAULT: '#F59E0B',
          50:  '#FFFBEB',
          100: '#FEF3C7',
          200: '#FDE68A',
          300: '#FCD34D',
          400: '#FBBF24',
          500: '#F59E0B',  // principal
          600: '#D97706',
          700: '#B45309',
          800: '#92400E',
          900: '#78350F',
        },
        // Sidebar
        sidebar: {
          bg:      '#1A2035',
          hover:   '#242D45',
          active:  '#2D3954',
          border:  '#2A3450',
          text:    '#94A3B8',
          'text-active': '#FFFFFF',
        },
        // Superfícies
        surface: {
          bg:      '#F8F9FA',
          card:    '#FFFFFF',
          border:  '#E5E7EB',
          input:   '#F1F5F9',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        'card': '0 1px 3px 0 rgba(0,0,0,0.08), 0 1px 2px -1px rgba(0,0,0,0.04)',
        'card-hover': '0 4px 12px 0 rgba(0,0,0,0.10)',
        'sidebar': '4px 0 24px 0 rgba(0,0,0,0.12)',
      },
    },
  },
  plugins: [],
} satisfies Config
```

### app.css — CSS vars e reset

```css
@import 'tailwindcss/base';
@import 'tailwindcss/components';
@import 'tailwindcss/utilities';

/* Fonte */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

:root {
  /* Tema claro */
  --color-brand:       245 158 11;   /* amber-500 em RGB para Tailwind */
  --color-bg:          248 249 250;
  --color-card:        255 255 255;
  --color-border:      229 231 235;
  --color-text:        30 41 59;
  --color-text-muted:  100 116 139;
  --color-sidebar-bg:  26 32 53;
  --sidebar-width:     240px;
  --sidebar-collapsed: 64px;
  --header-height:     64px;
}

.dark {
  /* Tema escuro */
  --color-bg:          15 23 42;
  --color-card:        30 41 59;
  --color-border:      51 65 85;
  --color-text:        248 250 252;
  --color-text-muted:  148 163 184;
  --color-sidebar-bg:  10 15 35;
}

* { @apply border-surface-border; }
body { @apply bg-surface-bg text-slate-800 dark:text-slate-100 font-sans antialiased; }
```

---

## Fase 3 — Layout Principal

Leia `references/layout.md` para detalhes completos.

### Estrutura geral

```
┌─────────────────────────────────────────────────────┐
│ AppSidebar (240px fixo | 64px colapsado | hidden mobile) │
├────────────────────────────────────────────────────┤
│ AppHeader (64px fixo no topo do content)            │
├────────────────────────────────────────────────────┤
│ <slot /> — conteúdo da página com scroll           │
└─────────────────────────────────────────────────────┘
```

### AppLayout.vue

```vue
<script setup lang="ts">
import { ref, provide } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppHeader from '@/components/layout/AppHeader.vue'

interface Props {
  title?: string          // título da página para o <Head>
  description?: string    // meta description
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Painel de Controle',
})

const sidebarCollapsed = ref(false)
const mobileSidebarOpen = ref(false)

provide('sidebarCollapsed', sidebarCollapsed)
provide('mobileSidebarOpen', mobileSidebarOpen)
</script>

<template>
  <!-- Head dinâmico — OBRIGATÓRIO em todo layout -->
  <Head :title="`${title} — Plannerate`" />

  <div class="flex h-screen overflow-hidden bg-surface-bg">
    <!-- Overlay mobile -->
    <div
      v-if="mobileSidebarOpen"
      class="fixed inset-0 z-20 bg-black/50 lg:hidden"
      @click="mobileSidebarOpen = false"
    />

    <!-- Sidebar -->
    <AppSidebar
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      @toggle-collapse="sidebarCollapsed = !sidebarCollapsed"
      @close-mobile="mobileSidebarOpen = false"
    />

    <!-- Área principal -->
    <div
      class="flex flex-1 flex-col overflow-hidden transition-all duration-300"
      :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-60'"
    >
      <AppHeader
        :title="title"
        @toggle-mobile-sidebar="mobileSidebarOpen = !mobileSidebarOpen"
      />

      <main class="flex-1 overflow-y-auto p-4 lg:p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
```

---

## Fase 4 — Sidebar

Leia `references/layout.md#sidebar` para detalhes.

### Características obrigatórias

- Fundo navy `#1A2035`, sidebar-width `240px`, colapsado `64px`
- Logo Plannerate/Raptor no topo com animação de colapso
- Grupos de navegação com label (`GERAL`, `CATÁLOGO`, etc.)
- Item ativo: background âmbar sutil + texto branco + ícone âmbar
- Itens com filhos: chevron rotacionado quando expandido
- Modo colapsado: só ícones com tooltip no hover
- Mobile: drawer deslizante com overlay + botão X
- Botão de toggle (hambúrguer ↔ seta) no rodapé do sidebar
- Scroll quando há muitos itens

### Fonte de dados do menu

O menu deve ser alimentado pelos dados do backend via `$page.props`:

```ts
// No Controller / HandleInertiaRequests.php
// share() deve incluir:
'navigation' => [
  [
    'group' => 'Geral',
    'items' => [
      ['label' => 'Painel de Controle', 'route' => 'dashboard', 'icon' => 'LayoutDashboard'],
    ]
  ],
  [
    'group' => 'Catálogo',
    'items' => [
      ['label' => 'Produtos',    'route' => 'products.index',   'icon' => 'Package',    'children' => []],
      ['label' => 'Categorias',  'route' => 'categories.index', 'icon' => 'Tag'],
      ['label' => 'Dimensões',   'route' => 'dimensions.index', 'icon' => 'Ruler'],
    ]
  ],
]
```

---

## Fase 5 — Header

### Características obrigatórias

```
[☰ mobile] [Breadcrumb: Painel > Seção > Página]    [Busca global] [🌙 tema] [🔔] [👤]
```

- Breadcrumb gerado automaticamente via `usePage().props.breadcrumbs` ou rota atual
- Busca global: input que faz `router.get(route('search'), { q })` com debounce 400ms
- Toggle de tema claro/escuro: persiste em `localStorage` + atualiza classe no `<html>`
- Notificações: badge com contagem, dropdown de recentes
- Avatar do usuário: dropdown com perfil, configurações, sair
- Mobile: colapsa busca para ícone, breadcrumb simplificado

---

## Fase 6 — Três Padrões de Listagem

Leia `references/list-patterns.md` para implementação completa de cada um.

### Padrão 1 — Card Rico (ex: Planogramas)

Usado para entidades visuais com imagem, progresso, múltiplas ações.

```
┌─────────────────────────────────────────────────┐
│ [DRAFT] • SUPERMERCADO                          │  ← badges de status
│ [imagem]  Bruda — Planograma Importados         │  ← título
│           📋 SUPERMERCADO                       │  ← subtítulo/contexto
│           Progresso ████░░ 65%  Início  Término │  ← métricas
│           [Editar] [Excluir] [Gerenciar] [+Gónd]│  ← ações
└─────────────────────────────────────────────────┘
```

### Padrão 2 — Linha Compacta (ex: Produtos)

Usado para listas densas de muitos itens com dados técnicos.

```
[img][toggle][✓] NOME DO PRODUTO          HIERARQUIA > CATEGORIA > SUB
                 SKU: 000 · Código ERP: 000  Estoque · Altura · Largura · Peso
```

### Padrão 3 — Grid de Dados (ex: Categorias)

Usado para entidades com campos estruturados e labels visíveis.

```
┌─────────────────────────────────────────────────┐
│ STATUS        NOME           CATEGORIA PAI      │
│ [toggle/✓]    MOÍDO          SUPERMERCADO > ...  │
│                                                 │
│ NÍVEL         NÍVEL NOME     CRIADO EM          │
│ 5             subcategoria   04/03/2026 11:41   │
│                                                 │
│ [Editar] [Excluir]                              │
└─────────────────────────────────────────────────┘
```

---

## Fase 7 — Vue Head e Meta Tags

**Todo layout e toda página** deve gerenciar o `<Head>` do Inertia:

```vue
<!-- No AppLayout.vue — título base -->
<Head :title="`${title} — Plannerate`" />

<!-- Em cada página — sobrescreve via prop title -->
<script setup>
defineProps<{ title: string }>()
</script>
```

**No controller Laravel — passar o título:**
```php
return Inertia::render('Products/Index', [
    'title' => __('Produtos'),
    'description' => __('Gerenciar Produtos'),
]);
```

**Convenção de títulos (PT-BR correto):**
```
Listagem:  "Produtos — Plannerate"
Criação:   "Novo Produto — Plannerate"
Edição:    "Editando: Nome do Produto — Plannerate"
Dashboard: "Painel de Controle — Plannerate"
```

---

## Fase 8 — Tema Claro / Escuro

Leia `references/theme.md` para implementação completa.

### Composable `useTheme`

```ts
// composables/useTheme.ts
import { ref, watch, onMounted } from 'vue'

type Theme = 'light' | 'dark' | 'system'

export function useTheme() {
  const theme = ref<Theme>('system')

  function applyTheme(t: Theme) {
    const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
    document.documentElement.classList.toggle('dark', isDark)
  }

  function setTheme(t: Theme) {
    theme.value = t
    localStorage.setItem('raptor-theme', t)
    applyTheme(t)
  }

  onMounted(() => {
    const saved = (localStorage.getItem('raptor-theme') as Theme) || 'system'
    theme.value = saved
    applyTheme(saved)
  })

  // Escuta mudança do sistema operacional
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (theme.value === 'system') applyTheme('system')
  })

  return { theme, setTheme }
}
```

---

## Fase 9 — Responsividade Mobile

Leia `references/mobile.md` para breakpoints e padrões.

### Regras obrigatórias

- **Sidebar**: oculto em mobile (`hidden lg:flex`), abre via botão hambúrguer como drawer
- **Header**: busca global colapsa para ícone em mobile (`hidden md:flex` → ícone de lupa)
- **Listas**: card rico → stack vertical; linha compacta → remove colunas secundárias; grid dados → 1 coluna
- **Ações**: botões de ação agrupados em dropdown `...` em mobile
- **Tabelas**: scroll horizontal com colunas essenciais fixadas (sticky left)
- **Padding**: `p-4` em mobile, `p-6` em desktop
- Breakpoints: `sm:640px`, `md:768px`, `lg:1024px`, `xl:1280px`

---

## Fase 10 — Traduções PT-BR

Leia `references/i18n.md` para lista completa de strings e integração.

### Estrutura de arquivos

```
lang/
├── pt_BR/
│   ├── app.php          # strings da aplicação
│   ├── auth.php         # autenticação (login, senha, etc.)
│   ├── pagination.php   # "Anterior", "Próximo"
│   ├── validation.php   # mensagens de validação
│   └── {recurso}.php    # por recurso: products.php, categories.php, etc.
└── en/
    └── ...              # inglês (opcional como fallback)
```

### Integração com Vue via Inertia

```php
// HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'translations' => fn () => [
            'common' => __('app'),      // strings comuns
            'auth'   => __('auth'),     // strings de auth
        ],
    ];
}
```

```ts
// composables/useTranslation.ts
import { usePage } from '@inertiajs/vue3'

export function useT() {
  const page = usePage()
  return (key: string, replacements: Record<string, string> = {}) => {
    const translations = (page.props.translations as any) || {}
    const [group, k] = key.split('.')
    let str: string = translations[group]?.[k] ?? key
    Object.entries(replacements).forEach(([find, replace]) => {
      str = str.replace(`:${find}`, replace)
    })
    return str
  }
}
```

### Gramática PT-BR — erros comuns a corrigir

Ao encontrar qualquer string no sistema, corrigir:

| ❌ Errado | ✅ Correto |
|-----------|-----------|
| Gerenciar Planogram | Gerenciar Planogramas |
| Listar | Listagem |
| Criar Novo | Novo |
| Excluir | Excluir (OK, mas confirmar sempre no dialog) |
| Dashboard | Painel de Controle |
| Items | Itens |
| Status | Status (OK) |
| SKU | SKU (OK — sigla) |
| Hierarquia | Hierarquia (OK) |
| Nível Nome | Nome do Nível |

---

## Fase 11 — Conversão shadcn-vue → Raptor

Para cada componente shadcn encontrado no projeto:

1. **Leia** o componente shadcn existente
2. **Identifique** a API (props, slots, emits)
3. **Mantenha** a mesma API externa (não quebre nada que usa o componente)
4. **Reescreva** a implementação visual seguindo a identidade Raptor
5. **Adicione** as capacidades universais da skill `laravel-raptor-components`
   (prefix/suffix com ação, modal, hint, error, animações)

**Prioridade de conversão** (dos mais usados aos menos):
1. `Button` — estilo Raptor (amber filled, dark outline, ghost)
2. `Input` — com prefix/suffix/error/hint padrão Raptor
3. `Select` — dropdown com busca, estilo Raptor
4. `Badge` — status (DRAFT/PUBLISHED) com cores Raptor
5. `Card` — card com shadow e hover Raptor
6. `Dialog/Modal` — com animação e trap focus
7. `Table` — com sticky header e responsive
8. `Toast` — posição e estilo Raptor

---

## Fase 12 — Checklist final antes de entregar

### Identidade
- [ ] Tokens de cor Raptor no tailwind.config (brand, sidebar, surface)
- [ ] CSS vars de tema claro E escuro em app.css
- [ ] Fonte Inter configurada

### Layout
- [ ] AppLayout.vue com Head dinâmico
- [ ] AppSidebar.vue: navy, colapsável, mobile drawer, menu via props
- [ ] AppHeader.vue: breadcrumb, busca global, tema toggle, avatar
- [ ] Transição suave ao colapsar/expandir sidebar

### Listagens
- [ ] Padrão card-rico implementado (Planogramas)
- [ ] Padrão linha-compacta implementado (Produtos)
- [ ] Padrão grid-de-dados implementado (Categorias)

### Qualidade
- [ ] Tema escuro funcional em todos os componentes
- [ ] Mobile responsivo: sidebar drawer, busca colapsada, ações em dropdown
- [ ] `<Head>` em todos os layouts com título PT-BR correto
- [ ] Traduções em `lang/pt_BR/` para strings comuns
- [ ] Gramática PT-BR corrigida em todas as strings
- [ ] Nenhum texto hardcoded em inglês visível ao usuário

---

## Referências

- `references/color-proposals.md` — 3 propostas de paleta para apresentar ao usuário antes de codar
- `references/identity.md` — paleta aprovada, tipografia, ícones, espaçamento, tokens
- `references/list-patterns.md` — os 3 padrões de listagem com código Vue completo
- `references/theme-mobile.md` — tema claro/escuro, useTheme, CSS vars, mobile/drawer
- `references/i18n.md` — traduções PT-BR, integração Laravel, correções gramaticais

---

## Notas sobre estrutura de pacote Laravel

Os caminhos dentro de um pacote seguem esta convenção — adaptar ao que for encontrado:

```
{PACKAGE_PATH}/
├── resources/
│   ├── js/
│   │   ├── layouts/         # ou Layouts/
│   │   ├── components/      # componentes Vue
│   │   ├── composables/     # composables TS
│   │   └── pages/           # ou Pages/
│   └── css/
│       └── app.css
├── lang/
│   └── pt_BR/               # ou pt-BR/
├── tailwind.config.ts        # ou .js
└── package.json
```

Se a estrutura for diferente → mapear o que existe e adaptar todos os paths desta skill
antes de gerar qualquer arquivo.