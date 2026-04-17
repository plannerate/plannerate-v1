# Design Spec — Plannerate Signature Theme

**Data:** 2026-03-20
**Branch:** feature/melhoria-layout
**Escopo:** Redesign visual — CSS tokens, sidebar, header, inputs, botões, logo, badges

---

## 1. Visão Geral

Substituir a aparência genérica do Laravel base por uma identidade visual própria chamada **Plannerate Signature**: sidebar sempre escura em slate, gradiente dark→lime como acento de marca, inputs no estilo filled/surface, e logo SVG próprio.

A mudança é feita sobre a stack existente — Vue 3 + Inertia + TailwindCSS v4 + pacote `packages/callcocam/laravel-raptor` — sem trocar dependências.

> **Fora de escopo neste spec:** o redesign do card-rico da listagem de planogramas. O layout actual de listagem é renderizado pelo AbstractController do Raptor (`IndexPage.vue`) e requer uma spec própria. O mockup aprovado serve como referência visual para esse próximo spec.

---

## 2. Identidade Visual

### Paleta e tokens CSS

| Token CSS | Valor OKLch | Hex aprox. | Uso |
|-----------|-------------|------------|-----|
| `--sidebar` | `oklch(0.21 0.014 258)` | `#1e293b` | Sidebar — fixo escuro em `:root` |
| `--sidebar-foreground` | `oklch(0.985 0 0)` | `#fafafa` | Texto sobre sidebar escuro |
| `--sidebar-primary` | `oklch(0.84 0.238 116)` | `#a8ff3e` | Acento lime no sidebar |
| `--sidebar-accent` | `oklch(0.84 0.238 116 / 12%)` | lime 12% | Fundo do item ativo |
| `--sidebar-border` | `oklch(1 0 0 / 6%)` | branco 6% | Separadores do sidebar |
| `--color-input-surface` | `oklch(0.965 0.003 264)` | `#f1f5f9` | Fundo de input normal |
| `--color-input-focus` | `oklch(0.84 0.238 116)` | `#a8ff3e` | Borda de input em focus |

### Tipografia

- Fonte: **Instrument Sans** (já configurada)
- Títulos de página: `text-xl font-bold tracking-tight text-foreground`
- Labels de campo: `text-xs font-medium text-muted-foreground`

---

## 3. Mudanças por arquivo

### 3.1 `resources/css/colors/plannerate.css`

**A) Tokens sidebar sempre-escuros em `:root`:**

O sidebar do Plannerate é sempre escuro por identidade de marca — exceção intencional ao padrão light/dark. Os tokens abaixo vão para `:root` (fora do `.dark`), e o bloco `.dark` não precisa redefini-los (já são escuros):

```css
:root {
  --sidebar: oklch(0.21 0.014 258);
  --sidebar-foreground: oklch(0.94 0 0);
  --sidebar-primary: oklch(0.84 0.238 116);
  --sidebar-primary-foreground: oklch(0.14 0.012 258);
  --sidebar-accent: oklch(0.84 0.238 116 / 12%);
  --sidebar-accent-foreground: oklch(0.89 0.12 116);
  --sidebar-border: oklch(1 0 0 / 6%);
  --sidebar-ring: oklch(0.84 0.238 116);
}
```

**B) Tokens de input:**

```css
:root {
  --color-input-surface: oklch(0.965 0.003 264);
  --color-input-focus: oklch(0.84 0.238 116);
}
```

**C) `.btn-gradient` como seletor global (sem `.theme-plannerate`):**

O botão `default` do Raptor já inclui a classe `btn-gradient` no CVA (ver `packages/callcocam/laravel-raptor/resources/js/components/ui/button/index.ts`, linha 11). A regra `.btn-gradient` existe em `themes.css` apenas dentro de `.theme-plannerate`. Mover para `:root` global em `plannerate.css` para que funcione sem o wrapper de tema:

```css
/* Mover de .theme-plannerate em themes.css para :root em plannerate.css */
.btn-gradient,
[data-slot="button"].btn-gradient {
  background: linear-gradient(135deg, #1e293b, #a8ff3e) !important;
  color: white !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(30,41,59,0.25);
}
.btn-gradient:hover,
[data-slot="button"].btn-gradient:hover {
  opacity: 0.92;
  box-shadow: 0 4px 14px rgba(30,41,59,0.35);
}
```

Após adicionar este bloco em `plannerate.css`, **remover todo o bloco de regras de botão** de dentro de `.theme-plannerate` em `themes.css` (linhas ~543–618): inclui `.btn-gradient`, `.btn-plannerate`, e variantes de destructive/outline/secondary/ghost. Todos migram para `plannerate.css` como regras globais. O restante de `.theme-plannerate` (tokens OKLch, sidebar, etc.) permanece intacto.

### 3.2 `resources/css/app.css`

Adicionar no `@layer base` os estilos globais de input filled/surface usando os tokens definidos em 3.1:

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

### 3.3 `packages/callcocam/laravel-raptor/resources/js/components/ui/input/Input.vue`

Substituir as classes Tailwind hardcoded de borda (`border-input`, `focus-visible:border-ring`, etc.) pelas classes que referenciam os novos tokens. Não usar hex diretamente — usar `border-[var(--color-input-focus)]` e `bg-[var(--color-input-surface)]`.

Classe base resultante aproximada:
```
bg-[var(--color-input-surface)] border-[1.5px] border-transparent rounded-lg
focus:bg-card focus:border-[var(--color-input-focus)] focus:ring-2 focus:ring-[var(--color-input-focus)]/15
aria-invalid:border-destructive aria-invalid:ring-destructive/12
disabled:opacity-50 disabled:cursor-not-allowed
```

### 3.4 `packages/callcocam/laravel-raptor/resources/js/components/ui/button/Button.vue`

**Nenhuma alteração necessária — verificação apenas.** O componente já possui `data-slot="button"` e `:data-variant="variant ?? 'default'"`. O gradiente será aplicado automaticamente pelo CSS global da seção 3.1 quando `.btn-gradient` estiver no `@layer base` do `plannerate.css`.

### 3.5 `packages/callcocam/laravel-raptor/resources/js/components/RaptorHeader.vue`

Mudanças:
- Classe `h-14` → `h-12` (48px em repouso)
- Classe `group-has-data-[collapsible=icon]/sidebar-wrapper:h-11` → `:h-10` (modo colapsado: 40px)
- Badge de notificação: a contagem está dentro de `NotificationDropdown` (ver 3.6)

### 3.6 `packages/callcocam/laravel-raptor/resources/js/components/NotificationDropdown.vue`

O badge de contagem está na linha ~201. Trocar a classe de cor do badge por `bg-[var(--color-input-focus)] text-slate-900` (verde-lime com texto escuro).

### 3.7 `resources/js/components/AppLogoIcon.vue`

Arquivo atual: renderiza `<img>` com `/img/marca.png` e `/img/marcadark.png`, com `MutationObserver` para trocar no dark mode.

Substituir por SVG inline com o logo Plannerate Signature:
- Ícone grid 2×2: quadrante superior-esquerdo em `#a8ff3e`, demais em `rgba(168,255,62,X)` com X = 0.4, 0.4, 0.2
- Texto "Plan" em `currentColor` + "nerate" em `#a8ff3e`
- Remover `MutationObserver` e lógica de troca — SVG com `currentColor` adapta automaticamente ao dark mode sem necessidade de observer
- Manter `inheritAttrs: false` e `v-bind="$attrs"` para compatibilidade com o `AppLogo.vue` wrapper

---

## 4. Arquivos a tocar (lista completa)

| # | Arquivo | Tipo |
|---|---------|------|
| 1 | `resources/css/colors/plannerate.css` | Tokens sidebar + input + btn-gradient global |
| 2 | `resources/css/app.css` | Bloco `@layer base` para inputs filled |
| 3 | `packages/callcocam/laravel-raptor/resources/js/components/ui/input/Input.vue` | Classes filled style com tokens |
| 4 | `packages/callcocam/laravel-raptor/resources/js/components/ui/button/Button.vue` | Verificação apenas — sem alterações |
| 5 | `packages/callcocam/laravel-raptor/resources/js/components/RaptorHeader.vue` | Altura 48px/40px |
| 6 | `packages/callcocam/laravel-raptor/resources/js/components/NotificationDropdown.vue` | Cor do badge |
| 7 | `resources/js/components/AppLogoIcon.vue` | SVG logo (substituir PNG+observer) |
| 8 | `resources/css/themes.css` | Remover `.btn-gradient` de dentro de `.theme-plannerate` |

---

## 5. O que NÃO muda

- Estrutura de rotas, controllers, Form Requests
- Componentes de form field de alto nível (`FormFieldText`, `FormFieldSelect`, `FormFieldTextarea`, etc.)
- Sistema de dark mode — mantido via classe `.dark` no `<html>`
- `AppLogo.vue` — apenas seu filho `AppLogoIcon.vue` muda
- `AppSidebar.vue` — sem mudanças; os tokens CSS já fornecem as cores corretas
- Wayfinder e geração de tipos
- Design dos cards de planograma — escopo de spec futura

---

## 6. Critérios de Sucesso (verificáveis)

1. O valor computado de `--sidebar` no `:root` é `oklch(0.21 0.014 258)` tanto em modo claro quanto escuro (verificar via DevTools → Elements → Computed)
2. Qualquer `<Button variant="default">` exibe gradiente dark→lime sem a classe `.theme-plannerate` no elemento pai
3. Todos os `<input>`, `<textarea>` e `<select>` exibem fundo `var(--color-input-surface)` e ao receber foco exibem borda `var(--color-input-focus)` — verificável em qualquer formulário do sistema
4. Badge de notificação exibe cor lime
5. Logo exibe SVG inline (não `<img>`) sem `MutationObserver`
6. Header tem altura visual de 48px (verificar via DevTools)
7. `./vendor/bin/sail npm run build` executa sem erros de TypeScript nem warnings de Vite nos arquivos modificados
