# Propostas de Paleta — Raptor/Plannerate

Quando o usuário pedir para propor uma identidade de cores, apresentar estas 3 opções
de forma visual e clara no chat, **antes de gerar qualquer código**.

---

## Como apresentar as opções

Mostrar ao usuário desta forma:

```
Aqui estão 3 propostas de identidade visual para o Raptor/Plannerate.
Qual combina mais com o seu sistema?

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OPÇÃO A — "Amber Dark" (manter a essência atual, refinada)
Sidebar: Navy #1A2035 | Acento: Amber #F59E0B | Base: Slate
Mais próxima do visual atual, só com refinamentos de espaçamento e tipografia.
Visual: profissional, sóbrio, com calor do amarelo.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OPÇÃO B — "Indigo Modern" (mais tecnológico, azul-roxo moderno)
Sidebar: Indigo #312E81 | Acento: Violet #7C3AED | Base: Slate
Tendência atual em SaaS B2B. Parece com Linear, Vercel, Railway.
Visual: moderno, tecnológico, premium.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OPÇÃO C — "Emerald Clean" (mais leve, relacionado ao varejo/mercado)
Sidebar: Slate #1E293B | Acento: Emerald #059669 | Base: Zinc
Verde comercial — relacionado a frescor, alimentos, varejo.
Visual: clean, moderno, acessível.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Você pode escolher uma das três, misturar elementos ("gostei do sidebar da B com o acento da A"),
ou descrever algo diferente.
```

---

## Paleta A — "Amber Dark" (refinamento do atual)

```ts
// tailwind.config.ts
colors: {
  brand: {
    50:  '#FFFBEB', 100: '#FEF3C7', 200: '#FDE68A',
    300: '#FCD34D', 400: '#FBBF24', 500: '#F59E0B',  // principal
    600: '#D97706', 700: '#B45309', 800: '#92400E', 900: '#78350F',
  },
  sidebar: {
    bg:           '#1A2035',
    hover:        '#242D45',
    active:       '#2D3954',
    border:       '#2A3450',
    text:         '#94A3B8',
    'text-active':'#FFFFFF',
    'accent-bg':  'rgba(245,158,11,0.12)',
    'accent-text':'#F59E0B',
  },
  surface: {
    bg:     '#F1F5F9',   // ligeiramente mais frio que o atual
    card:   '#FFFFFF',
    border: '#E2E8F0',
    input:  '#F8FAFC',
  },
}
```

```css
/* Tema escuro */
.dark {
  --surface-bg:    15 23 42;    /* slate-950 */
  --surface-card:  30 41 59;    /* slate-800 */
  --surface-border:51 65 85;    /* slate-700 */
}
```

---

## Paleta B — "Indigo Modern"

```ts
colors: {
  brand: {
    50:  '#EEF2FF', 100: '#E0E7FF', 200: '#C7D2FE',
    300: '#A5B4FC', 400: '#818CF8', 500: '#6366F1',  // indigo-500
    600: '#4F46E5', 700: '#4338CA', 800: '#3730A3', 900: '#312E81',
  },
  // Acento secundário: violet para CTAs primários
  accent: {
    DEFAULT: '#7C3AED',   // violet-600
    hover:   '#6D28D9',
    light:   '#EDE9FE',
  },
  sidebar: {
    bg:           '#1E1B4B',   // indigo-950
    hover:        '#312E81',   // indigo-900
    active:       '#3730A3',   // indigo-800
    border:       '#3730A3',
    text:         '#A5B4FC',   // indigo-300
    'text-active':'#FFFFFF',
    'accent-bg':  'rgba(124,58,237,0.15)',
    'accent-text':'#A78BFA',   // violet-400
  },
  surface: {
    bg:     '#F8FAFC',
    card:   '#FFFFFF',
    border: '#E2E8F0',
    input:  '#F1F5F9',
  },
}
```

---

## Paleta C — "Emerald Clean"

```ts
colors: {
  brand: {
    50:  '#ECFDF5', 100: '#D1FAE5', 200: '#A7F3D0',
    300: '#6EE7B7', 400: '#34D399', 500: '#10B981',  // emerald-500
    600: '#059669', 700: '#047857', 800: '#065F46', 900: '#064E3B',
  },
  sidebar: {
    bg:           '#1E293B',   // slate-800
    hover:        '#334155',   // slate-700
    active:       '#334155',
    border:       '#334155',
    text:         '#94A3B8',   // slate-400
    'text-active':'#FFFFFF',
    'accent-bg':  'rgba(16,185,129,0.12)',
    'accent-text':'#34D399',   // emerald-400
  },
  surface: {
    bg:     '#F9FAFB',   // zinc-50
    card:   '#FFFFFF',
    border: '#E4E4E7',   // zinc-200
    input:  '#F4F4F5',   // zinc-100
  },
}
```

---

## Aplicação após escolha

Após o usuário escolher (ou customizar), copiar os tokens da paleta escolhida para:

1. `tailwind.config.ts` — adicionar/substituir o bloco `colors`
2. `app.css` — atualizar as CSS vars de `:root` e `.dark`
3. Verificar se os componentes existentes usam as cores antigas hardcoded
   e substituir pelas novas classes (`text-brand-500`, `bg-sidebar-bg`, etc.)

Usar o comando para buscar hardcodes de cor nos arquivos Vue:
```bash
grep -r "#F59E0B\|#1A2035\|amber-500\|navy" {PACKAGE_PATH}/resources/js --include="*.vue" --include="*.ts"
```