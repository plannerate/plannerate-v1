# Plannerate Signature Theme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aplicar a identidade visual Plannerate Signature — sidebar sempre escura, inputs filled/surface, botões gradiente global, badge lime e logo SVG inline.

**Architecture:** Mudanças puramente CSS e Vue — sem lógica de negócio, sem rotas, sem backend. Os tokens OKLch já existem em `resources/css/colors/plannerate.css`; as tarefas consistem em ajustar seus valores e mover regras CSS, trocar classes Tailwind nos componentes-base do Raptor, e substituir o logo PNG por SVG inline. O cascata de formulários (FormField* → Input.vue) garante que alterar apenas `Input.vue` propaga o novo estilo para todos os campos do sistema.

**Tech Stack:** Vue 3 + TailwindCSS v4 + OKLch CSS custom properties + pacote local `packages/callcocam/laravel-raptor`

---

## File Map

| # | Arquivo | O que muda |
|---|---------|------------|
| 1 | `resources/css/colors/plannerate.css` | Tokens sidebar sempre-escuros em `:root`; novos tokens de input; bloco `.btn-gradient` global |
| 2 | `resources/css/themes.css` | Remover bloco `.btn-gradient` de dentro de `.theme-plannerate` (linhas 542–618) |
| 3 | `resources/css/app.css` | Adicionar `@layer base` com estilos filled para `input`, `textarea`, `select` |
| 4 | `packages/callcocam/laravel-raptor/resources/js/components/ui/input/Input.vue` | Substituir classes de borda por filled style com tokens |
| 5 | `packages/callcocam/laravel-raptor/resources/js/components/RaptorHeader.vue` | `h-14` → `h-12`, `h-11` → `h-10` |
| 6 | `packages/callcocam/laravel-raptor/resources/js/components/NotificationDropdown.vue` | Badge: `bg-destructive text-destructive-foreground` → lime |
| 7 | `resources/js/components/AppLogoIcon.vue` | Substituir `<img>` + MutationObserver por SVG inline |

---

## Task 1: Tokens CSS — sidebar sempre-escuros + input + btn-gradient global

**Files:**
- Modify: `resources/css/colors/plannerate.css`

### Contexto

O arquivo atual define `--sidebar: oklch(0.985 0 0)` (quase-branco) no `:root`. Precisa ser substituído pelos tokens de identidade Plannerate: sidebar sempre slate-escuro em `:root` (exceção intencional ao light/dark), mais dois tokens novos de input, mais o bloco `.btn-gradient` global.

- [ ] **Step 1: Substituir os tokens de sidebar em `:root`**

No arquivo `resources/css/colors/plannerate.css`, localizar e substituir o bloco de tokens sidebar (linhas 27–34):

```css
/* ANTES (linhas 27–34): */
  --sidebar: oklch(0.985 0 0);
  --sidebar-foreground: oklch(0.141 0.005 285.823);
  --sidebar-primary: oklch(0.723 0.191 149.579);
  --sidebar-primary-foreground: oklch(0.985 0 0);
  --sidebar-accent: oklch(0.967 0.001 286.375);
  --sidebar-accent-foreground: oklch(0.21 0.006 285.885);
  --sidebar-border: oklch(0.92 0.004 286.32);
  --sidebar-ring: oklch(0.723 0.191 149.579);
```

```css
/* DEPOIS — sidebar sempre-escuro (Plannerate Signature identity) */
  --sidebar: oklch(0.21 0.014 258);
  --sidebar-foreground: oklch(0.94 0 0);
  --sidebar-primary: oklch(0.84 0.238 116);
  --sidebar-primary-foreground: oklch(0.14 0.012 258);
  --sidebar-accent: oklch(0.84 0.238 116 / 12%);
  --sidebar-accent-foreground: oklch(0.89 0.12 116);
  --sidebar-border: oklch(1 0 0 / 6%);
  --sidebar-ring: oklch(0.84 0.238 116);
```

- [ ] **Step 2: Remover tokens sidebar duplicados no bloco `.dark`**

No bloco `.dark` (linhas 62–69), apagar as linhas:
```css
  --sidebar: oklch(0.21 0.006 285.885);
  --sidebar-foreground: oklch(0.985 0 0);
  --sidebar-primary: oklch(0.723 0.191 149.579);
  --sidebar-primary-foreground: oklch(0.985 0 0);
  --sidebar-accent: oklch(0.274 0.006 286.033);
  --sidebar-accent-foreground: oklch(0.985 0 0);
  --sidebar-border: oklch(1 0 0 / 10%);
  --sidebar-ring: oklch(0.723 0.191 149.579);
```

Esses tokens foram movidos para `:root` como valores always-dark; redefinir no `.dark` seria redundante.

- [ ] **Step 3: Adicionar tokens de input e `.btn-gradient` global ao final do arquivo**

Após o fechamento do bloco `.dark` (após `}` na linha ~70), adicionar:

```css
/* ── Input tokens — Plannerate Signature ───────────────────────────────── */
:root {
  --color-input-surface: oklch(0.965 0.003 264);
  --color-input-focus: oklch(0.84 0.238 116);
}

/* ── Botão gradiente global (sem wrapper .theme-plannerate) ─────────────── */
/* CVA default variant já inclui btn-gradient — esta regra global ativa o gradiente */
/* .btn-plannerate é mantido como alias para compatibilidade com chamadas legadas */
.btn-gradient,
.btn-plannerate,
[data-slot="button"].btn-gradient {
  background: linear-gradient(135deg, #1e293b, #a8ff3e) !important;
  color: white !important;
  border: none !important;
  box-shadow: 0 2px 8px rgb(30 41 59 / 25%);
}
.btn-gradient:hover,
[data-slot="button"].btn-gradient:hover {
  opacity: 0.92;
  box-shadow: 0 4px 14px rgb(30 41 59 / 35%);
}

/* Variante destructive */
[data-slot="button"][data-variant="destructive"].btn-gradient {
  background: linear-gradient(135deg, #7f1d1d, #ef4444) !important;
}
[data-slot="button"][data-variant="destructive"].btn-gradient:hover {
  background: linear-gradient(135deg, #991b1b, #f87171) !important;
  box-shadow: 0 2px 6px rgb(127 29 29 / 20%);
}

/* Variante outline */
[data-slot="button"][data-variant="outline"].btn-gradient {
  background: linear-gradient(135deg, transparent, #78c82d) !important;
  color: #78c82d !important;
  border: 1px solid #78c82d !important;
}
[data-slot="button"][data-variant="outline"].btn-gradient:hover {
  background: linear-gradient(135deg, #78c82d, #a8ff3e) !important;
  color: white !important;
}

/* Variante secondary */
[data-slot="button"][data-variant="secondary"].btn-gradient {
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0) !important;
  color: #1e293b !important;
}
[data-slot="button"][data-variant="secondary"].btn-gradient:hover {
  background: linear-gradient(135deg, #e2e8f0, #cbd5e1) !important;
  box-shadow: 0 2px 6px rgb(100 116 139 / 15%);
}

/* Variante ghost */
[data-slot="button"][data-variant="ghost"].btn-gradient {
  background: linear-gradient(135deg, transparent, #f1f5f9) !important;
  color: #1e293b !important;
}
[data-slot="button"][data-variant="ghost"].btn-gradient:hover {
  background: linear-gradient(135deg, #f1f5f9, #e2e8f0) !important;
  box-shadow: 0 1px 3px rgb(100 116 139 / 10%);
}
```

- [ ] **Step 4: Verificar o arquivo resultante**

Rodar build para confirmar que não há erros CSS:

```bash
./vendor/bin/sail npm run build 2>&1 | tail -20
```

Esperado: saída sem erros. Se houver erro de CSS, verificar o arquivo e corrigir a sintaxe.

- [ ] **Step 5: Commit**

```bash
git add resources/css/colors/plannerate.css
git commit -m "feat: sidebar always-dark tokens + input tokens + btn-gradient global"
```

---

## Task 2: Remover `.btn-gradient` do escopo `.theme-plannerate` em `themes.css`

**Files:**
- Modify: `resources/css/themes.css:542-618`

### Contexto

As regras de botão com gradiente existem hoje dentro do seletor `.theme-plannerate` (linhas 542–618), o que significa que só funcionam quando esse wrapper está presente no HTML. Após a Task 1, as regras existem globalmente em `plannerate.css`. O bloco antigo em `themes.css` deve ser removido para evitar conflitos de especificidade.

- [ ] **Step 1: Remover o bloco de botões de `themes.css`**

O seletor `.theme-plannerate` em `themes.css` contém duas partes:
1. **Tokens OKLch** — NÃO remover. Esses tokens definem as cores do tema e devem permanecer.
2. **Regras de botão** — REMOVER. São as regras `.btn-gradient` e variantes que agora existem globalmente em `plannerate.css`.

Remover **apenas** o bloco que começa com o comentário de botão e vai até o último fechamento de bloco da variante ghost. Usar esta delimitação exata:

**Início (remover a partir daqui — inclusive o comentário):**
```css
/* Botão com gradiente Plannerate - do escuro para o verde */
.theme-plannerate .btn-gradient,
.theme-plannerate [data-slot="button"].btn-gradient,
.btn-plannerate {
```

**Fim (remover até aqui — inclusive o `}` de fechamento):**
```css
.theme-plannerate [data-slot="button"][data-variant="ghost"].btn-gradient:hover,
.theme-plannerate .btn-gradient[data-variant="ghost"]:hover {
  background: linear-gradient(to right, rgb(241, 245, 249), rgb(226, 232, 240));
  box-shadow: 0 1px 3px rgb(100 116 139 / 10%);
}
```

O bloco `.theme-plannerate` **não é removido** — apenas as regras de botão dentro/após ele. Qualquer regra que venha depois (ex.: `.theme-plannerate .btn-primary`) permanece intacta.

- [ ] **Step 2: Build para verificar que não há erros CSS**

```bash
./vendor/bin/sail npm run build 2>&1 | tail -20
```

Esperado: zero erros.

- [ ] **Step 3: Commit**

```bash
git add resources/css/themes.css
git commit -m "refactor: move btn-gradient out of .theme-plannerate scope"
```

---

## Task 3: Estilo filled global para inputs em `app.css`

**Files:**
- Modify: `resources/css/app.css`

### Contexto

O arquivo `app.css` tem dois blocos `@layer base`. Adicionar um terceiro para aplicar o estilo filled/surface a todos os elementos de input nativos. Isso garante cobertura mesmo para inputs que não passam pelo componente `Input.vue` do Raptor (ex.: input de busca no header, selects de paginação).

- [ ] **Step 1: Adicionar o bloco `@layer base` de inputs filled**

Após o bloco `@layer base` existente (que termina com `body { @apply bg-background text-foreground; }` em torno da linha 110), adicionar:

```css
@layer base {
  input:not([type="checkbox"]):not([type="radio"]):not([type="range"]),
  textarea,
  select {
    background-color: var(--color-input-surface);
    border: 1.5px solid transparent;
    border-radius: 0.5rem;
    transition: border-color 0.15s, box-shadow 0.15s, background-color 0.15s;
  }
  input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):focus,
  textarea:focus,
  select:focus {
    background-color: var(--color-card);
    border-color: var(--color-input-focus);
    box-shadow: 0 0 0 3px color-mix(in oklch, var(--color-input-focus) 15%, transparent);
    outline: none;
  }
}
```

- [ ] **Step 2: Build para verificar que não há erros**

```bash
./vendor/bin/sail npm run build 2>&1 | tail -20
```

Esperado: zero erros.

- [ ] **Step 3: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: global filled input style in @layer base"
```

---

## Task 4: Input.vue — filled style com tokens

**Files:**
- Modify: `packages/callcocam/laravel-raptor/resources/js/components/ui/input/Input.vue:47-58`

### Contexto

O componente base `Input.vue` define as classes Tailwind do elemento `<input>`. Ele é usado por todos os campos do sistema via cadeia `FormFieldText → Input.vue`. Substituir as classes de borda genéricas pelas classes filled/surface que referenciam os tokens definidos na Task 1.

A classe base atual (linha 48) é:
```
'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs',
```

- [ ] **Step 1: Substituir o bloco de classes no `cn()` da linha 47**

Substituir o conteúdo do `cn(...)` (linhas 47–58) pelo seguinte:

```typescript
        :class="cn(
            'flex h-9 w-full min-w-0 rounded-lg bg-[var(--color-input-surface)] border-[1.5px] border-transparent px-3 py-1 text-sm',
            'text-foreground placeholder:text-muted-foreground',
            'transition-[color,border-color,box-shadow,background-color] outline-none',
            'focus:bg-card focus:border-[var(--color-input-focus)] focus:ring-2 focus:ring-[var(--color-input-focus)]/15',
            'dark:focus:ring-[var(--color-input-focus)]/20',
            'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
            'file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground',
            'selection:bg-primary selection:text-primary-foreground',
            'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
            props.class,
        )"
```

Diferenças-chave:
- `border border-input bg-transparent` → `bg-[var(--color-input-surface)] border-[1.5px] border-transparent`
- `rounded-md` → `rounded-lg`
- `shadow-xs` removido (filled não tem shadow)
- `focus-visible:*` → `focus:*` (funciona com mouse + teclado)
- Removidas as classes dark específicas de borda/background (o global `app.css` da Task 3 cobre; o componente só precisa das variações de ring)

- [ ] **Step 2: Build para verificar TypeScript e Vite sem erros**

```bash
./vendor/bin/sail npm run build 2>&1 | tail -30
```

Esperado: zero erros TypeScript e zero warnings Vite nos arquivos modificados.

- [ ] **Step 3: Commit**

```bash
git add packages/callcocam/laravel-raptor/resources/js/components/ui/input/Input.vue
git commit -m "feat: input filled style with OKLch tokens"
```

---

## Task 5: RaptorHeader.vue — reduzir altura do header

**Files:**
- Modify: `packages/callcocam/laravel-raptor/resources/js/components/RaptorHeader.vue:38`

### Contexto

O header em repouso usa `h-14` (56px). O spec define `h-12` (48px) como mais compacto e moderno. No modo colapsado (`group-has-data-[collapsible=icon]/sidebar-wrapper:h-11` → 44px) deve passar a `h-10` (40px).

A linha atual (linha 38) é:
```html
        class="flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background/95 px-4 backdrop-blur-md transition-[height] group-has-data-[collapsible=icon]/sidebar-wrapper:h-11"
```

- [ ] **Step 1: Alterar as classes de altura**

Substituir `h-14` por `h-12` e `h-11` por `h-10` na linha 38.

Resultado:
```html
        class="flex h-12 shrink-0 items-center gap-2 border-b border-border bg-background/95 px-4 backdrop-blur-md transition-[height] group-has-data-[collapsible=icon]/sidebar-wrapper:h-10"
```

- [ ] **Step 2: Build e verificação**

```bash
./vendor/bin/sail npm run build 2>&1 | tail -10
```

Esperado: zero erros.

- [ ] **Step 3: Commit**

```bash
git add packages/callcocam/laravel-raptor/resources/js/components/RaptorHeader.vue
git commit -m "feat: reduce header height to 48px / 40px collapsed"
```

---

## Task 6: NotificationDropdown.vue — badge lime

**Files:**
- Modify: `packages/callcocam/laravel-raptor/resources/js/components/NotificationDropdown.vue:199`

### Contexto

O badge de notificações não lidas usa `bg-destructive text-destructive-foreground` (vermelho). O spec define cor lime (`--color-input-focus`) para alinhar com o acento de marca.

A linha atual (linha 199) é:
```html
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-0.5 text-[9px] font-bold leading-none text-destructive-foreground"
```

- [ ] **Step 1: Alterar as classes de cor do badge**

Substituir `bg-destructive` por `bg-[var(--color-input-focus)]` e `text-destructive-foreground` por `text-slate-900`:

```html
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[var(--color-input-focus)] px-0.5 text-[9px] font-bold leading-none text-slate-900"
```

- [ ] **Step 2: Build e verificação**

```bash
./vendor/bin/sail npm run build 2>&1 | tail -10
```

Esperado: zero erros.

- [ ] **Step 3: Commit**

```bash
git add packages/callcocam/laravel-raptor/resources/js/components/NotificationDropdown.vue
git commit -m "feat: notification badge uses lime brand color"
```

---

## Task 7: AppLogoIcon.vue — SVG inline substituindo PNG + MutationObserver

**Files:**
- Modify: `resources/js/components/AppLogoIcon.vue`

### Contexto

O componente atual renderiza `<img :src="logoSrc">` com `MutationObserver` para trocar entre dois PNGs quando o dark mode muda. Isso é frágil e não escala. Substituir por SVG inline com `currentColor` para texto — o SVG adapta automaticamente ao dark mode sem JS adicional.

O `AppLogo.vue` passa `class="h-8 w-auto max-w-[200px] object-contain object-left"` via `$attrs`. Manter `inheritAttrs: false` e `v-bind="$attrs"` no elemento SVG raiz.

Design do SVG:
- Grid 2×2: quadrante superior-esquerdo `#a8ff3e` (lime sólido); os outros em opacidade 0.4, 0.4, 0.2
- Texto "Plan" em `currentColor` (adapta ao dark) + "nerate" em `#a8ff3e`
- ViewBox `0 0 168 24` — altura 24 com `h-8 w-auto` resulta em ~32px de altura renderizada

- [ ] **Step 1: Substituir o conteúdo completo do arquivo**

Reescrever `resources/js/components/AppLogoIcon.vue`:

```vue
<script setup lang="ts">
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 168 24"
        fill="none"
        aria-label="Plannerate"
        v-bind="$attrs"
    >
        <!-- Grid 2×2 icon -->
        <rect x="0" y="2" width="10" height="10" rx="2" fill="#a8ff3e" />
        <rect x="13" y="2" width="10" height="10" rx="2" fill="#a8ff3e" fill-opacity="0.4" />
        <rect x="0" y="14" width="10" height="10" rx="2" fill="#a8ff3e" fill-opacity="0.4" />
        <rect x="13" y="14" width="10" height="10" rx="2" fill="#a8ff3e" fill-opacity="0.2" />
        <!-- Logotipo texto -->
        <text
            x="30"
            y="19"
            font-family="Instrument Sans, ui-sans-serif, sans-serif"
            font-size="16"
            font-weight="600"
            letter-spacing="-0.3"
        >
            <tspan fill="currentColor">Plan</tspan><tspan fill="#a8ff3e">nerate</tspan>
        </text>
    </svg>
</template>
```

**Por que funciona:**
- `v-bind="$attrs"` aplica as classes do `AppLogo.vue` diretamente no `<svg>`, incluindo `h-8 w-auto`
- `fill="currentColor"` no `<tspan>` herda a cor do texto do elemento pai — branco no dark, slate-900 no light
- `#a8ff3e` no "nerate" é explícito e não muda com o tema (intenção de marca)
- Zero JavaScript — nenhum observer, nenhum `ref`, nenhum `computed`

- [ ] **Step 2: Build final e verificação TypeScript**

```bash
./vendor/bin/sail npm run build 2>&1 | tail -30
```

Esperado: zero erros TypeScript e zero warnings Vite.

- [ ] **Step 3: Commit final**

```bash
git add resources/js/components/AppLogoIcon.vue
git commit -m "feat: replace PNG logo with inline SVG — no MutationObserver"
```

---

## Verificação Final (Critérios de Sucesso)

Após todas as tasks, verificar no browser (com `npm run dev` ou após `npm run build`):

| # | Verificação | Como testar |
|---|-------------|-------------|
| 1 | `--sidebar` é `oklch(0.21 0.014 258)` em modo claro e escuro | DevTools → Elements → `:root` → Computed → `--sidebar` |
| 2 | `<Button variant="default">` exibe gradiente dark→lime sem `.theme-plannerate` no pai | Qualquer botão de ação no sistema |
| 3 | Inputs exibem fundo cinza suave; ao focar exibem borda lime | Qualquer formulário do sistema |
| 4 | Badge de notificação exibe cor lime | Clicar no sino com notificações pendentes |
| 5 | Logo é `<svg>` inline (não `<img>`) | DevTools → Elements → inspecionar header |
| 6 | Header tem ~48px de altura | DevTools → Elements → computed height do `<header>` |
| 7 | Build sem erros TypeScript | `./vendor/bin/sail npm run build` sem linhas vermelhas |
