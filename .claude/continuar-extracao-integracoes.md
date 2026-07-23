# Extração do motor de integrações — CONCLUÍDA (23/07/2026)

✅ **Fases 0, 1, 2 e 3 todas em produção.** `main` = `ca8edf66`. Nada pendente neste plano.

Estado final verificado em produção: `integration:health` 🟢 tudo saudável, filas em 0,
quarentena 0, runs `✓` concluídos, `integration_sync_days` dropada, shim de aliases fora do
autoloader, `failed_jobs` = 75 (só de outros domínios).

Backups guardados em `storage/app/private/` no volume `prod_storage_data` (sobrevive a
recriação de container): `integration_sync_days_backup_2026-07-23.json` (156 linhas) e
`failed_jobs_integrations_backup_2026-07-23.json` (124 jobs).

O histórico abaixo fica como registro do que foi feito e das armadilhas pagas.

Plano completo (com histórico de decisões e desvios):
`/home/caltj/.claude/plans/execute-o-claude-orientacao-extrair-paco-floofy-sunrise.md`

---

## Onde as coisas estão

| O quê | Onde |
|---|---|
| Pacote | `github.com/callcocam/laravel-integrations` (**privado**), branch `main`, 3 commits + `e7e0ca7` |
| Clone local do pacote | `/home/caltj/projects/laravel-integrations` |
| PR de corte no app | branch `feat/extrair-pacote-integracoes`, commit `ba3a17ba` — **não mergeado, não deployado** |
| Contrato de instalação | `README.md` do pacote |

O pacote tem 78 classes, 46 arquivos de teste, **215 testes verdes** numa invocação só,
pint limpo. O app consome por `repositories` VCS (HTTPS, não SSH — não há chave para o
GitHub nesta máquina).

## Rodar as coisas

**App** (sempre via Docker, nunca PHP direto):

```bash
docker compose exec php php artisan test --compact <path>
docker compose exec php vendor/bin/pint --dirty --format agent
docker compose exec php php artisan wayfinder:generate --with-form
VITE_ENABLE_WAYFINDER=false npm run build
```

**Pacote** (não há PHP no host; roda em container descartável):

```bash
cd /home/caltj/projects/laravel-integrations
docker run --rm -v "$PWD":/app -w /app -u 1000:1000 php:8.4-cli php vendor/bin/pest --compact --order-by=random
docker run --rm -v "$PWD":/app -w /app -u 1000:1000 php:8.4-cli php vendor/bin/pint --format agent
```

`composer` no app precisa do token para alcançar o repo privado:

```bash
TOKEN=$(gh auth token)
docker compose exec -T -e COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$TOKEN\"}}" \
  php composer update callcocam/laravel-integrations
```

## O que falta, em ordem

### ~~1. Acesso ao repo privado onde roda `composer install`~~ — **FEITO (22/07/2026)**

- **GitHub Actions**: secret `COMPOSER_GITHUB_TOKEN` criado no nível do repo. Hoje ele
  guarda o token OAuth do `gh` CLI (escopo `repo`+`workflow`) — funciona, mas o certo é
  trocar por um PAT fine-grained com leitura de Contents só em `laravel-integrations`.
  **O token some quando o `gh` for reautenticado**; se o CI começar a falhar no clone, é isso.
- **`Dockerfile.prod`**: estágio `vendor` recebe o JSON de `COMPOSER_AUTH` por
  `--mount=type=secret` (nunca entra em layer); `vps-v2-build-push.yml` monta o secret a
  partir de `COMPOSER_GITHUB_TOKEN` e **falha cedo** se ele não existir.
  Commit `49678fa7` na branch. Verificado de verdade: build do target `vendor` resolveu
  `callcocam/laravel-integrations` do repo privado.
- Máquinas de dev novas: `composer config --global github-oauth.github.com <token>`.

### 2. Deploy da Fase 0 e um ciclo diário observado em produção

**Staging: FEITO (22/07/2026).** `a098cfce` foi cherry-pickado em cima de `origin/dev`
(→ `26e57c52`, sem conflito) e empurrado para `dev`. CI verde (`integration-import`,
`planogram-domain`, `frontend-build`), `vps-v2-build-push` verde em 7m57s,
`vps-v2-deploy-staging` verde em 1m28s, health check passou em `/up` na 1ª tentativa —
ou seja, o app sobe com o binding novo do `StoresProvider` e o `config/integrations.php`
ampliado. **Produção continua sem a Fase 0.**

**Conferido: a Fase 0 NÃO está em `main`.** `origin/main` = `88a46335`; a Fase 0 é o commit
`a098cfce`, e seu **pai é exatamente `origin/main`** — ou seja, subir a Fase 0 sozinha é um
fast-forward de um commit só, sem cherry-pick nem conflito.

⚠️ **Push em `main` = deploy automático em produção.** `vps-v2-build-push` dispara em
`main`/`dev` e `vps-v2-deploy-production` encadeia via `workflow_run`. Não existe merge
"só para registrar". `dev` → staging, pelo mesmo encadeamento.

⚠️ **O rename BCG está duplicado**: `e017c6b7` em `origin/dev` e `a0c85052` (mesmo
conteúdo, SHA diferente) no topo da branch de extração. Resolver antes de integrar a
branch, ou vira conflito.

Ela precisa rodar um ciclo completo em produção antes do corte: `integration:run` 06:00 →
`sync:post-import` 07:30 → `integration:health` 08:15. Capturar `integration:status` e as
contagens de produtos/vendas como baseline.

A Fase 0 **não** depende do pacote privado (o `composer.json` só muda no commit do corte),
então esse deploy não depende do token acima.

### ~~3. Deploy do corte, em janela controlada~~ — **FEITO (23/07/2026, 01:50 UTC)**

`main` = `07e03096`. O corte está em produção. `vps-v2-build-push`, `vps-v2-deploy-production`
e `tests` verdes. Como foi:

- **Conflito resolvido por rebase sobre `origin/dev`** — os dois commits duplicados
  (Fase 0 e BCG) tinham patch-id idêntico ao que já estava em `dev`, então o rebase os
  derrubou sozinho. Resultado linear: `dev` + corte + 3 commits de infra.
- **`horizon:terminate` manual desnecessário**: o deploy faz `up -d --force-recreate`,
  recriando o container do Horizon com binding fresco. O shim de alias legado cobre
  payloads de job antigos em voo.
- **A lacuna que quase passou batido**: o deploy só roda `migrate --path=database/migrations/landlord`,
  que **não alcança as migrations do pacote** (vivem em `vendor/`). Adicionei um passo
  guardado de `integrations:migrate --force` em produção e staging (commit no histórico).
  Confirmado no log do deploy: rodou `seed_sysmo_and_gescooper_blueprints` e
  `add_empty_units_to_integration_import_runs_table` em produção; o resto "Nothing to migrate".
- **CI**: `tests.yml` autentica o repo privado nos 3 jobs (antes só `integration-boundary`);
  sem isso `frontend-build` e `planogram-domain` batiam 404 no clone assim que o corte
  entrou em `main`.

**Falta o smoke manual** (não dá para fazer sem sessão de browser autenticada):
`/integration-apis` (salvar um blueprint sem perder chave) e
`/tenants/{tenant}/integration` (config decriptada + "Testar conexão" contra a RP Info).

**Falta observar o ciclo diário em produção** (`integration:run` 06:00 →
`sync:post-import` 07:30 → `integration:health` 08:15) — é o gate da Fase 3. Lembrar que o
`integration:health` agora pode dar exit 1 legítimo por **dia vazio suspeito** (loja sem
dado enquanto a irmã vendeu); não confundir com falha de pipeline.

### ~~4. Fase 3 — estabilização~~ — **FEITO (23/07/2026, commit `ca8edf66`)**

Os dois gates foram conferidos em produção **antes** de mexer:

- **ciclo diário verde**: o ciclo das 06:00 rodou já com o corte (deploy 01:58), runs `✓`
  concluídos, filas 0, quarentena 0, **zero falhas novas** desde o deploy;
- **`failed_jobs` sem FQCN antigo**: eram 124 (121 unique-violation + 3 Pusher), todos de
  15/07 ou antes. Antes de apagar, provei que não eram dado perdido — **18/18 e 7/7 dias de
  venda íntegros**, porque `resolveMissingDays` re-deriva os dias faltantes da tabela alvo.
  Apagados só esses 124 (backup antes); os 75 de outros domínios foram preservados.

Feito: shim + entrada em `autoload.files` removidos, `IntegrationSyncDay.php` deletado,
migration de drop de `integration_sync_days` (156 linhas exportadas antes; `down()` recria o
schema). Confirmado em produção depois do deploy: tabela dropada, shim fora do autoloader,
backups preservados, `integration:health` 🟢.

⚠️ **Havia extração do pacote `laravel-raptor-trade` acontecendo em paralelo na árvore.** O
commit da Fase 3 levou **só** os 4 arquivos dela — o `composer.json` foi staged de forma
cirúrgica (blob montado a partir do HEAD com apenas a remoção do `autoload.files`), para não
varrer o `require`/`repositories` do trade. Ver `.claude/continuar-extracao-trade.md`.

---

## Armadilhas já pagas — não repetir

- **`wayfinder:generate` sem `--with-form`** deixa o build **verde** e quebra a página em
  runtime com `update.form is not a function`. Os componentes do pacote usam `.form()`.
  CI e `Dockerfile.prod` já passam a flag; o risco é local.
- **Nunca `-u root`** no `wayfinder:generate` — deixa `resources/js/{actions,routes}` como
  root e quebra git/checkout. (Esses dois diretórios são gerados e ignorados pelo git.)
- **Nunca renomear migration** ao mexer no pacote: a tabela `migrations` guarda só o nome
  do arquivo; renomear = re-executar em produção.
- **`integrations.routes.middleware` precisa listar `SetPermissionTeamContext`.** O default
  do pacote é só `['web','auth']`; sem isso a autorização decide fora do time do tenant.
- **Mudança de namespace quebra classe usada sem import** e o PHP não acusa. O pacote tem
  `tests/Unit/UnresolvedClassReferenceTest.php` varrendo `src/` contra isso — se aparecer
  código novo com esse padrão, o teste pega.
- **Há trabalho de terceiros não commitado na árvore** (rename "BCG → Análise de Quadrante"
  em `lang/pt_BR/plannerate/` e `packages/callcocam/laravel-raptor-plannerate/`, mais um
  redesign de `resources/js/layouts/auth/AuthSplitLayout.vue`). **Não varrer para dentro de
  commits da extração.**

## Deliberadamente fora de escopo

- `tests/Feature/Landlord/TenantIntegrationTest.php` e vizinhos estão **parcialmente podres
  e nunca estiveram no CI**. Corrigi o que tinha resposta objetiva (ID vs slug em
  `integration_type`, domínio do tenant no fixture); o resto espera decisão de produto
  (`test-connection` hoje devolve redirect, não JSON). Está tudo documentado no topo do
  arquivo. A fronteira que importa está coberta por
  `tests/Feature/Integrations/IntegrationsPackageContractTest.php`, esse sim no CI.
- 7 arquivos de teste com `markTestSkipped` global desde 2026-06-11 foram **deletados** em
  vez de portados: referenciavam classes que não existem mais e nunca rodaram.
- Comandos de base legada (`sync:import-legacy-dimensions-to-ean-references`,
  `ImportLegacyProducts`, `ImportLegacyBaseClient`) ficaram no app — dependem da conexão
  `mysql_legacy`, são migração pontual, não motor.
