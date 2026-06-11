# Refatoração do pacote `laravel-raptor-plannerate` (reconstrução do zero + migração do AutoPlanograma)

## Contexto

Estamos trabalhando no projeto **Plannerate** (Laravel + Vue 3 + Inertia.js + TypeScript + TailwindCSS). Dentro dele existe um pacote local que precisa ser **reconstruído do zero**, e um serviço fora do pacote que precisa ser **migrado para dentro dele**.

- **Pacote a refatorar:** `packages/callcocam/laravel-raptor-plannerate`
- **Serviço a migrar para dentro do pacote:** `app/Services/AutoPlanogram`

O pacote é **usado localmente** dentro deste projeto (path repository no `composer.json`), não é distribuído publicamente. Ele **está funcionando hoje** — o objetivo é melhorar a qualidade do código, organização e arquitetura, **sem perder nenhuma funcionalidade**.

> **Atenção (branches):** o pacote vive dentro deste projeto. Você pode **estudar** as branches atuais (`development` e `main`), mas **toda a codificação da refatoração deve acontecer numa branch nova e isolada**. **NÃO** altere `development` nem `main`. Elas só serão tocadas (merge) no dia em que a refatoração estiver **100% funcional e validada**.

---

## ⚠️ Ordem de trabalho (IMPORTANTE)

**Você NÃO vai direto para o código.** O fluxo é:

1. **Fase 1 — Estudo do pacote** → entrega um relatório.
2. **Fase 2 — Estudo do AutoPlanograma + análise de dependências** → entrega um relatório.
3. **Fase 3 — Documento de plano de refatoração passo a passo** → entrega o plano completo e **PARA**, aguardando minha aprovação.
4. **Só depois que eu aprovar o plano**, você inicia a **Fase 4 (implementação)** numa branch nova.
5. **Fase 5 — Validação e limpeza** do projeto principal.

As Fases 1, 2 e 3 são **somente documentação**. Nenhum arquivo de código de produção é escrito antes da aprovação.

---

## Princípio fundamental

> **Reconstruir do zero, com liberdade de implementação, mas preservando 100% das funcionalidades e da estrutura de dados.**

**O que você TEM liberdade para mudar:**

- Estilo e organização do código (PHP, JS/TS, estrutura de classes, nomes internos de métodos/variáveis).
- Padrões internos, arquitetura, divisão de responsabilidades, organização de pastas e componentes Vue.
- A implementação técnica — não precisa ficar igual ao código atual. Pode (e deve) ficar melhor.

**O que você NÃO pode mudar / NÃO pode perder:**

- **Tabelas, colunas, chaves estrangeiras e relacionamentos** — mantenha exatamente os mesmos nomes e estrutura (`products`, lojas, e todas as demais existentes). Use todos os recursos e tabelas que existem hoje.
- **Todas as funcionalidades do editor de planograma** precisam continuar existindo e funcionando, incluindo:
  - Arrastar e soltar (drag & drop).
  - Criar novo, duplicar, excluir.
  - Criação e manipulação de **gôndolas, módulos, seções, prateleiras (e camadas/segmentos)**, colunas — toda a hierarquia do planograma.
  - Tudo que existe hoje na construção do planograma.
- **Todas as funcionalidades do AutoPlanograma** (geração automática e por template): ABC classification, síntese de template, slot placement, validação de capacidade física, etc.
- **Contratos públicos** dos quais o projeto já depende (rotas, endpoints, eventos, jobs) — a não ser que o plano aprovado documente explicitamente uma mudança.

Resumo: **mesma funcionalidade, mesmos dados, mesmos recursos — código novo e melhor.**

---

## Fase 1 — Estudo do pacote (somente relatório, NÃO escreva código)

Faça um estudo completo e me apresente um relatório. Leia e mapeie:

1. **Estrutura de pastas e arquivos** de `packages/callcocam/laravel-raptor-plannerate`.
2. **`composer.json`** do pacote — namespace, autoload, dependências, service providers registrados.
3. **Service Providers** — o que é registrado, bindings, publishables, rotas, migrations, views, assets.
4. **Models** — tabelas usadas, relacionamentos, traits (ULID/`HasUlids`, `SoftDeletes`, `HasSlug`), casts, enums.
5. **Controllers / Actions / Services** — responsabilidades, fluxo, contratos públicos.
6. **Rotas** — todas as rotas expostas pelo pacote.
7. **Migrations** — quais tabelas o pacote cria/altera.
8. **Frontend** (Vue/Inertia/TS) — componentes, páginas, composables, e **todas as funcionalidades do editor** (drag & drop, criar/duplicar/excluir, gôndolas, módulos, seções, prateleiras, colunas), como os assets são compilados.
9. **Configs e arquivos publicáveis.**
10. **Testes existentes** (Pest), se houver.

**Entregue um relatório de arquitetura atual** com: mapa de pastas, lista de models→tabelas, lista de rotas, **inventário completo de funcionalidades** (cada recurso do editor e do autoplanograma), lista de contratos públicos, e dependências internas/externas.

---

## Fase 2 — Estudo do AutoPlanograma e análise de dependências (somente relatório)

O serviço `app/Services/AutoPlanogram` precisa ser **migrado para dentro do pacote**. Antes de migrar:

1. **Mapeie tudo** dentro de `app/Services/AutoPlanogram` — classes, pipeline (ABC classification, template synthesis, slot placement, validação de capacidade física, etc.), e como elas se conectam.
2. **Análise de acoplamento (CRÍTICO):** identifique **toda** dependência cruzada:
   - O que dentro do `AutoPlanogram` depende de algo do pacote?
   - O que dentro do `AutoPlanogram` depende de algo **fora** do pacote (no `app/` do projeto, models, helpers, configs)?
   - O que no projeto depende do `AutoPlanogram`? (controllers, jobs, rotas, comandos que o chamam)
   - O pacote depende de algo que está fora dele e que deveria, idealmente, vir junto?
3. Para cada dependência cruzada, **decida e documente**:
   - Trazer para **dentro do pacote**, ou
   - Trazer para **dentro do AutoPlanograma** (que passará a viver no pacote), ou
   - Manter no projeto e expor via contrato/interface (quando for algo legitimamente do app).
4. Entregue um **relatório de migração** mostrando o "antes → depois" de cada arquivo e cada dependência, deixando claro **o que entra no pacote e como nenhuma funcionalidade será perdida**.

> Não deixe nada "escondido": se existe algo no projeto ou no AutoPlanograma que o pacote precisa para funcionar e que hoje está espalhado, aponte onde está e proponha como consolidar de forma limpa.

---

## Fase 3 — Documento do plano de refatoração passo a passo (somente documento — PARE e aguarde aprovação)

Com base nas Fases 1 e 2, **crie um documento detalhado** que instrua, passo a passo, como a refatoração será feita. Esse documento é o entregável principal antes de qualquer código. Ele deve conter:

1. **Visão geral da nova arquitetura** do pacote (com o AutoPlanograma já integrado).
2. **Estrutura de pastas proposta** (árvore de diretórios do novo pacote).
3. **Mapa de equivalência** "estrutura/arquivo antigo → novo", deixando claro onde cada funcionalidade vai morar.
4. **Passo a passo da implementação** — ordem das tarefas, em etapas pequenas e verificáveis (ex: 1) service provider e bootstrap, 2) models, 3) migrations, 4) services do core, 5) AutoPlanograma, 6) controllers/rotas, 7) frontend Vue, 8) testes).
5. **Plano de preservação de funcionalidades** — checklist de cada recurso do editor (drag & drop, criar/duplicar/excluir, gôndolas, módulos, seções, prateleiras, colunas) e de cada etapa do autoplanograma, mostrando como cada um será garantido.
6. **Plano de testes** (Pest) para validar que nada se perdeu.
7. **Riscos e pontos de atenção.**

**Ao terminar o documento, PARE e aguarde minha aprovação. Não comece a codificar antes disso.**

---

## Fase 4 — Implementação (só após aprovação do plano)

> **Branch:** crie uma **branch nova e isolada** para toda esta fase. **Nada** é feito em `development` ou `main`. O merge só acontece quando tudo estiver 100% funcional.

Diretrizes:

- Crie os arquivos **diretamente no caminho do pacote** (`packages/callcocam/laravel-raptor-plannerate`) — não preciso copiar/colar nada manualmente.
- Reconstrua a estrutura inteira do zero, organizada e limpa, **mantendo nomes de tabelas e colunas idênticos** e **todas as funcionalidades**. O código pode ser diferente, desde que o resultado funcional seja o mesmo (ou melhor).
- Integre o `AutoPlanograma` **dentro do pacote**, no local que fizer mais sentido arquiteturalmente (ex: `src/Services/AutoPlanogram` ou módulo equivalente), seguindo o plano aprovado.
- Siga as convenções do projeto Plannerate:
  - `./vendor/bin/sail` para qualquer comando (Artisan, Composer, etc.).
  - **Nunca** `migrate:fresh`, `migrate:reset` ou `db:wipe`.
  - Chaves primárias ULID com `HasUlids`; `SoftDeletes`; `HasSlug` quando aplicável.
  - `foreignUlid()` para FKs.
  - Enums tipados com `label()` / `color()`.
  - Multi-tenancy Spatie v4 conforme já usado no projeto.
  - Inertia + Vue 3 + TypeScript + TailwindCSS no frontend, preservando o estilo atual (a base de estilo é boa — fique o mais parecido possível).
  - Pest 4 para testes; Pint (`--format agent`) para formatação.
- Mantenha o Service Provider registrando tudo corretamente (rotas, migrations, views, assets, publishables) para que o pacote continue funcionando exatamente como antes.

---

## Fase 5 — Validação e limpeza do projeto principal

### Validação

1. Liste o que mudou de lugar e confirme que **nenhuma tabela, coluna ou funcionalidade** foi perdida.
2. Rode o checklist de funcionalidades da Fase 3 (cada recurso do editor + cada etapa do autoplanograma) e confirme que todos funcionam.
3. Garanta que o autoload do `composer.json` do pacote cobre a nova estrutura (incluindo o AutoPlanograma migrado).
4. Aponte os comandos que eu devo rodar para validar (ex: `./vendor/bin/sail composer dump-autoload`, testes Pest, etc.).
5. Liste/ajuste os imports antigos no projeto raiz (ex: `App\Services\AutoPlanogram\...` que agora apontam para o namespace do pacote).

### Limpeza (só após merge e validação — DESTRUTIVA)

1. Prepare um **plano de limpeza** do `app/`: tudo que migrou para o pacote e que **não será mais usado** no projeto raiz — começando por `app/Services/AutoPlanogram` — deve ser removido.
2. Liste todo "lixo" residual: imports antigos, classes órfãs, rotas/registros duplicados, configs que apontam para o caminho antigo.
3. Faça a **limpeza completa do projeto principal**, confirmando cada remoção contra os usos reais para não apagar nada ainda em uso.
4. Ao final, o `AutoPlanograma` deve existir **somente dentro do pacote**, e o projeto raiz limpo, sem referências mortas.

> Esta limpeza só acontece depois de tudo validado e mergeado. **Apresente o plano de remoção para aprovação antes de apagar qualquer coisa.**

---

## Regras de ouro (resumo)

1. **Documento e relatórios primeiro; código só depois da aprovação do plano.**
2. **Liberdade no código — mas zero perda de funcionalidade ou de dados.**
3. **Tabelas e colunas: intocáveis.**
4. **Todos os recursos do editor (drag & drop, criar/duplicar/excluir, gôndolas, módulos, seções, prateleiras, colunas) e do autoplanograma: preservados.**
5. **Toda a codificação numa branch nova e isolada — `development` e `main` só no merge final, quando estiver 100% funcional.**
6. **AutoPlanograma entra para dentro do pacote, com dependências resolvidas explicitamente.**
7. **Estilo e convenções do projeto: preservados o máximo possível.**
8. **Nada de dependência escondida — tudo mapeado e documentado.**
9. **Limpeza do `app/` é a última fase — destrutiva, só após merge, validação e aprovação do plano de remoção.**