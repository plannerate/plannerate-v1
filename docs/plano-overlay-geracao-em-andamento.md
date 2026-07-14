# Plano — Overlay de "Geração em andamento" na gôndola

> **Para executar numa sessão nova.** Este documento é autocontido: assume que você não
> participou do trabalho anterior.
>
> **Escopo:** só frontend + traduções. Nenhuma rota nova, nenhuma migration, nenhum
> endpoint novo — o backend já entrega tudo que é preciso.

---

## 1. Por que isto existe (não é enfeite)

A geração de planograma virou **assíncrona** (roda em fila). Hoje, enquanto o job está na
fila ou rodando, o editor da gôndola **continua totalmente editável**.

**Isso é perda de dado silenciosa.** O usuário arrasta segmentos, ajusta frentes, salva — e o
job, ao terminar, **apaga e recria todos os segmentos**. O trabalho dele evapora sem aviso.

O único sinal hoje é uma **linha fina abaixo da gôndola** (um pontinho pulsando + texto), que
o usuário nem vê:

```
resources/js/pages/tenant/editor/Plannerate.vue:110-116
```

Então o overlay tem duas funções, nesta ordem:

1. **Proteger** — impedir edição enquanto a gôndola está para ser reconstruída.
2. **Informar** — dizer o que está acontecendo, quanto falta, e o que ele pode fazer.

---

## 2. O que JÁ existe (não reescrever)

### Backend — pronto, não precisa de nada

| Recurso | Onde |
|---|---|
| `PlanogramGenerationRun` (status, timestamps, relatório, erro) | `packages/.../src/Models/PlanogramGenerationRun.php` |
| `GET /api/gondolas/{gondola}/generation-runs/latest` | `packages/.../src/Http/Controllers/Generation/PlanogramGenerationRunController.php` |
| `GET .../generation-runs/pending` | idem |
| Status: `queued` \| `running` \| `completed` \| `failed` | `packages/.../src/Enums/GenerationRunStatus.php` |

O endpoint `latest` devolve o run com `is_pending`, `status`, `error_message`, `created_at`,
`finished_at`, `duration_ms` e o `capacity_report` completo.

### Frontend — existe e funciona; é para EVOLUIR, não substituir

```
packages/callcocam/laravel-raptor-plannerate/resources/js/composables/plannerate/generation/useGenerationRun.ts
```

Já faz:
- busca a última execução ao montar e ao trocar de gôndola;
- **polling a cada 3s** enquanto `is_pending`;
- `router.reload()` quando conclui;
- expõe `isGenerating`, `latestRun`, `capacityReport`, `validationReport`.

**Ele é a base. Estenda-o.** Não crie um segundo mecanismo de polling.

---

## 3. Desenho proposto — "a gôndola em obras"

Um spinner genérico no meio da tela não diz nada. A ideia aqui é que o **overlay fale a
linguagem do domínio**: a gôndola do usuário está sendo *remontada*, e ele deve poder ver isso.

### Composição visual

```
┌──────────────────────────────────────────────────────────┐
│  ▓▓▓▓ a gôndola real, visível por baixo, esmaecida ▓▓▓▓   │
│  ▓▓  (dessaturada + escurecida, NÃO borrada a ponto  ▓▓   │
│  ▓▓   de virar mingau — ele precisa reconhecer que   ▓▓   │
│  ▓▓   é a gôndola DELE)                              ▓▓   │
│                                                           │
│         ╭─────────────────────────────────────╮          │
│         │  ◐  Montando a gôndola…             │          │
│         │     Prateleira 3 de 4 · há 14s      │          │
│         │                                     │          │
│         │  Pode esperar aqui — a tela         │          │
│         │  atualiza sozinha quando terminar.  │          │
│         │                                     │          │
│         │  [ Voltar aos planogramas ]  [ ⌄ ]  │          │
│         ╰─────────────────────────────────────╯          │
│                                                           │
│  ═══════▶ varredura de luz percorrendo as prateleiras     │
└──────────────────────────────────────────────────────────┘
```

**A varredura**: um gradiente suave e claro que atravessa a gôndola da esquerda para a direita,
lentamente (2-3s por ciclo). É a metáfora do repositor passando pela prateleira. Comunica
"estamos remontando isto" muito melhor que um spinner — e usa a própria gôndola como palco.

> Cuidado: mantenha discreto. `opacity` baixa, `mix-blend-mode: overlay` ou um gradiente
> translúcido. Se piscar ou distrair, falhou. Respeite `prefers-reduced-motion`: com ele
> ligado, **sem** varredura — só o card e o texto.

### O botão `⌄` (minimizar)

Colapsa o card num **pill flutuante** no canto (`◐ Gerando… há 14s`). O overlay de proteção
**continua** — a gôndola segue não-editável —, mas o usuário consegue **olhar** a gôndola atual
antes de ela ser substituída. É um detalhe pequeno que evita frustração real: às vezes ele quer
conferir como estava antes.

---

## 4. Os estados (a parte que costuma ser esquecida)

Não basta "carregando" e "pronto". O overlay precisa cobrir **cinco** estados, e hoje três deles
não existem em lugar nenhum:

| # | Estado | Gatilho | O que mostrar |
|---|---|---|---|
| 1 | **Na fila** | `status === 'queued'` | "Na fila — aguardando um processador livre." |
| 2 | **Montando** | `status === 'running'` | "Montando a gôndola…" + tempo decorrido |
| 3 | **Concluída** | `status === 'completed'` | Estado de sucesso por ~1s, depois `router.reload()` |
| 4 | **Falhou** ⚠️ | `status === 'failed'` | **Não existe hoje.** Card vermelho + `error_message` + botões "Tentar novamente" e "Fechar". Hoje o polling simplesmente para e o banner some — o usuário fica sem explicação nenhuma. |
| 5 | **Travada** ⚠️ | `queued` por mais de ~60s | **Não existe hoje.** "A fila parece parada." + permitir fechar o overlay. **Este é o cenário mais perigoso:** se o Horizon não estiver consumindo a fila, o run fica em `queued` **para sempre** e o overlay giraria eternamente, prendendo o usuário numa tela morta. |

**O estado 5 não é hipotético.** Aconteceu durante o desenvolvimento: o worker do Horizon estava
rodando código antigo e a geração ficou pendurada. Um overlay sem escape teria travado a tela.

**Regra inegociável: o usuário tem que conseguir sair, sempre.** Nada de modal que prende foco
sem saída. O `Esc` e o botão "Voltar aos planogramas" funcionam em todos os estados.

---

## 5. Passos de implementação

### 5.1 Estender `useGenerationRun.ts`

Adicionar ao retorno:

- `elapsedMs` — `ref` atualizado a cada 1s a partir de `latestRun.created_at`. É o que alimenta
  o "há 14s". Sem isso o usuário fica no escuro e acha que travou.
- `isStuck` — `computed`: `status === 'queued'` e `elapsedMs > STUCK_THRESHOLD_MS` (comece com
  60_000). Dispara o estado 5.
- `hasFailed` — `computed`: `status === 'failed'`.
- `dismissed` — `ref<boolean>`, com `dismiss()`. Permite fechar o overlay nos estados de falha e
  travamento **sem** parar o polling (a geração pode se recuperar).
- **Cap de polling**: pare depois de N tentativas (ex.: 200 ≈ 10 min, alinhado ao `timeout = 600`
  do job) para não deixar um `setTimeout` rodando indefinidamente numa aba esquecida aberta.

**Não mexa** na lógica de `fetchLatest` / `startPolling` que já funciona — só acrescente.

### 5.2 Novo componente

```
packages/callcocam/laravel-raptor-plannerate/resources/js/components/plannerate/generation/GenerationOverlay.vue
```

Props: `run: GenerationRun | null`, `elapsedMs: number`, `isStuck: boolean`, `backRoute: string`.
Emits: `dismiss`, `retry`.

Sem lógica de negócio dentro — ele **recebe** o estado e desenha. Toda a decisão fica no composable.

### 5.3 Ligar no `Plannerate.vue`

Arquivo: `resources/js/pages/tenant/editor/Plannerate.vue`

1. Envolver o `<component :is="editorComponent" ...>` (linha ~100) num wrapper `relative`.
2. Dentro dele, o `<GenerationOverlay v-if="isGenerating && !dismissed">` em `absolute inset-0`.
3. **A trava (o mais importante):** enquanto `isGenerating`, o editor recebe
   `pointer-events-none` + `aria-hidden="true"` + `inert`. É isto que impede a perda de trabalho.
4. **Remover** o banner das linhas 110-116 — o overlay o substitui.

### 5.4 Traduções

`lang/pt_BR/plannerate/generation.php` — **nenhuma string escrita direto no Vue** (regra do
projeto). Use o composable `useT()` (`import { useT } from '@/composables/useT'`), não o
`useI18n` direto.

Chaves sugeridas, sob `plannerate.generation.overlay.*`:
`queued`, `running`, `completed`, `failed_title`, `stuck_title`, `stuck_hint`, `wait_here`,
`back_to_planograms`, `retry`, `dismiss`, `minimize`, `elapsed`.

---

## 6. Armadilhas que já custaram caro (leia antes de começar)

### 6.1 O ambiente local está QUEBRADO até o `~/docker/shared` subir

O projeto migrou para infraestrutura Docker compartilhada. Postgres, Redis e Mailpit **não sobem
mais** dentro deste projeto. Antes de qualquer coisa:

```bash
cd ~/docker/shared && docker compose up -d
~/docker/shared/newdb.sh plannerate-v1 --createdb   # --createdb é obrigatório
```

Sem isso, `docker compose up` falha e **nenhum teste roda**.

### 6.2 Horizon roda em container PRÓPRIO e guarda o PHP em memória

Se você mexer em qualquer coisa de backend: **`docker compose restart horizon`**. Sem isso o
worker continua executando o código antigo, e você vai concluir que seu fix "não funcionou".
Isto já aconteceu — custou uma rodada inteira de medição.

*(Neste plano o escopo é frontend, então provavelmente não te afeta. Mas se for testar a geração
de ponta a ponta, vale.)*

### 6.3 `router.reload()` no Inertia v3 **não aceita** `preserveScroll`

Não existe em `ReloadOptions`. O `vue-tsc` reclama. Só `router.reload()`.

### 6.4 ⚠️ Verificar se o `router.reload()` realmente repinta a gôndola

O editor tem estado local (`useGondolaState`, `currentGondola`) hidratado das props. **Um
`router.reload()` atualiza as props, mas não há garantia de que a store local re-hidrate** se o
componente não remontar.

**Teste isto explicitamente**: gere, espere concluir, e confirme que os segmentos novos aparecem
**sem F5**. Se não aparecerem, é preciso forçar a re-hidratação (um `key` no componente do editor,
ou um `router.visit` na mesma URL). É o tipo de coisa que passa despercebida e faz o usuário achar
que a geração não fez nada.

### 6.5 Não rode `wayfinder:generate`

Preferência registrada do projeto. Este plano não precisa: **não há rota nova**. Os endpoints da
API já existem e o composable monta a URL à mão de propósito (mesmo padrão do
`useRejectedProductsModule`).

### 6.6 Não aposte no Echo/Reverb para o tempo real — ele está QUEBRADO hoje

Seria elegante substituir o polling por um push via WebSocket. **Mas o broadcast da notificação de
conclusão falha em produção hoje:**

```
GenerateAutoPlanogramJob: falha no broadcast (notificação já persistida)
error: "Pusher error: Payload too large.."
```

A notificação chega no sino (canal `database`), mas o push em tempo real **não sai**. Se você
construir o overlay em cima do Echo, ele nunca vai receber o evento de conclusão.

**Faça o polling primeiro** (funciona hoje, e já está pronto). Se quiser tempo real depois, é uma
melhoria **separada**: diagnosticar o payload, emitir um evento enxuto (só `run_id` + `status`, sem
o `capacity_report`), e usar o Echo como **atalho** com o polling como rede de segurança — nunca
como única fonte.

---

## 7. Critérios de aceitação

- [ ] Com uma geração em andamento, **é impossível arrastar/editar** qualquer segmento da gôndola.
- [ ] O overlay aparece também quando o usuário **reabre o editor** no meio de uma geração já em
      curso (não só logo após clicar em gerar). *O `fetchLatest` já cobre isso — confirme.*
- [ ] Ao concluir, a gôndola **repinta sozinha, sem F5**, com os segmentos novos (ver 6.4).
- [ ] "Voltar aos planogramas" sai da tela e a geração **continua**; a notificação chega no sino.
- [ ] Geração que **falha** mostra o motivo e deixa o usuário sair — não some em silêncio.
- [ ] Run preso em `queued` por 60s+ mostra "a fila parece parada" e **permite fechar**.
- [ ] `Esc` fecha em qualquer estado. Ninguém fica preso.
- [ ] Com `prefers-reduced-motion`, sem varredura animada.
- [ ] Nenhuma string escrita direto no Vue — tudo em `lang/pt_BR/`.

## 8. Testes

- **Pest browser** (o projeto usa Pest 4): um smoke test que abre o editor com um run `queued`
  na base e afirma que o overlay aparece e que a gôndola está `inert`.
- Estados de `failed` e `stuck` são fáceis de simular: crie um `PlanogramGenerationRun` com o
  status desejado (e `created_at` no passado, para o `stuck`) e abra o editor.
- Rode o que o CI roda, não a suíte inteira — **a suíte completa tem ~390 falhas pré-existentes no
  `main`** e não serve de sinal. A lista curada está em `.github/workflows/tests.yml`.

## 9. Contexto de fundo

O trabalho que tornou a geração assíncrona (e que criou tudo o que este plano consome) está em
[`docs/gondola-precisao-automatica/04-relatorio-final.md`](gondola-precisao-automatica/04-relatorio-final.md).
Leia a seção 8 (caveats operacionais) — são 5 linhas e evitam duas horas de confusão.
