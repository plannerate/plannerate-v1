# Pesquisa de Dimensões — Pacote pra Plannerate

Pacote de prompts e instruções pra adicionar o pipeline de pesquisa de
dimensões de produto ao projeto Plannerate **já existente**.

## ⚠️ Importante

Este pacote pressupõe o projeto **já estabelecido** com:
- Multi-tenancy Spatie v4
- Docker compose personalizado (não Sail)
- Wayfinder pra rotas TypeScript
- Pest 4 + tenant de teste `albert`
- Pint com `--format agent`
- Convenções do Laravel Boost ativas
- Inertia v3 + Vue 3 + TypeScript + Tailwind v4
- Echo + Reverb + Horizon

Se algum desses não bate com seu projeto, ajuste antes de usar os prompts.

## Arquivos

```
.
├── CLAUDE-APPEND.md              # Anexar ao CLAUDE.md existente do projeto
├── PROMPTS-OPERACIONAIS.md       # Prompts pra colar no editor
├── prompts/
│   ├── agent-instructions.txt    # Vai em resources/ai/ do projeto
│   └── user-prompt-template.txt  # Template do Job
└── README.md                     # Este arquivo
```

## Como usar — 3 passos

### 1. Anexar contexto ao CLAUDE.md do projeto

Copie o conteúdo de `CLAUDE-APPEND.md` e cole no final do `CLAUDE.md`
existente do projeto. OU crie `.claude/dimension-research.md` com esse
conteúdo e referencie no CLAUDE.md principal.

### 2. Copiar instructions do agent

Crie a pasta `resources/ai/` no projeto e coloque
`prompts/agent-instructions.txt` lá como `dimension-researcher-instructions.txt`.

O agent vai ler esse arquivo via `file_get_contents()` em runtime — você
edita o comportamento sem precisar fazer deploy de código.

### 3. Rodar o scaffolding

Abra Claude Code (ou Cursor/Copilot Chat) na raiz do projeto. Cole o
**PROMPT 1** do `PROMPTS-OPERACIONAIS.md`.

O agent vai:
- Inspecionar o estado atual do banco
- Listar exatamente o que vai criar
- Pedir aprovação entre cada uma das 8 etapas
- Respeitar todas as convenções já estabelecidas (Docker, Wayfinder, Pint, etc.)

## Diferenças vs versão genérica

| Aspecto | Genérico | Plannerate |
|---|---|---|
| Tenant | Single-DB scope | **Spatie v4** (`tenants:artisan migrate`) |
| Cache de EAN | Tenant-scoped | **Central** (compartilha entre tenants) |
| Build frontend | `npm run build` | **Docker comando específico** |
| Rotas no Vue | Hardcoded | **Wayfinder** (`@/actions/...`) |
| Filas | Padrão | **Horizon** supervisor dedicado |
| Real-time | N/A | **Echo + Reverb** broadcast |
| Testes | PHPUnit | **Pest 4** com tenant `albert` |
| Estilo | Padrão | **Pint --format agent** |
| Skills | N/A | Ativa skills do Boost (pest, wayfinder, etc.) |
| `category_id` | Inferido | **FK explícita** (sem grouping_normalized) |

## Workflow contínuo no editor

```
CLAUDE.md + CLAUDE-APPEND.md ficam SEMPRE carregados.

Cada nova conversa no Claude Code já sabe:
  - Como rodar comandos (docker compose exec)
  - Quais skills ativar
  - Convenções de schema, código, testes
  - Pipeline em cascata
  - Regras de equivalência

Você só precisa colar prompts específicos pra:
  - Scaffolding inicial (1x)
  - Manutenção pontual (vários, prontos no PROMPTS-OPERACIONAIS.md)
```

## Pontos de atenção

**pgvector + SQLite em testes:** as migrations com SQL raw checam o driver.
Em SQLite (testes), a coluna vector e o índice HNSW são pulados. Os tools
que dependem de pgvector devem ter fallback no ambiente de teste (geralmente
mockados via `::fake()`).

**Rate limit do Gemini free:** 1.000 req/dia. Com pipeline em cascata, a
maioria dos produtos resolve na Etapa 1 (SQL puro) sem chamar LLM. O LLM
real só roda quando precisa de raciocínio sobre web search — então 1.000
chamadas/dia cobrem ~5.000-10.000 produtos novos/dia.

**Cache central de Cosmos:** compartilhar entre tenants é uma decisão
deliberada — o mesmo EAN tem as mesmas dimensões físicas em qualquer
supermercado. Economiza chamadas de API e reduz custo.

**Não trocar a convenção shelf_order:** o pipeline NÃO toca em lógica de
posicionamento. Só preenche dimensões. O cálculo
`num_shelves - shelf_order` segue intocado.
