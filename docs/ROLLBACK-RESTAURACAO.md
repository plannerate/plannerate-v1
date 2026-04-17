# Rollback e Restauracao de Branches (dev/main)

Este guia descreve como voltar rapidamente os branches `dev` e `main` para um estado anterior, usando:

- branch de backup (`backup/*`)
- tag de restauracao (`restore/*`)

## Contexto desta sincronizacao

Durante a sincronizacao para o estado da branch `feature/pkg-laravel-raptor-plannerate`, foram criados estes pontos de restauracao:

- `backup/dev-before-sync-20260414-092218` -> `52173dbce3b42c6568e67bfde6acc86168f5be27`
- `backup/main-before-sync-20260414-092218` -> `26465a5d9c26ccf42a65565b6542d957e2658478`
- `restore/dev-before-sync-20260414-092218` (tag)
- `restore/main-before-sync-20260414-092218` (tag)

## Quando usar rollback

- Deploy com erro critico apos sync de branch
- Regressao funcional relevante em producao/homologacao
- Falha de build/imagem que exija retorno imediato

## Pre-check (obrigatorio)

1. Verificar SHAs atuais no remoto:

```bash
git ls-remote --heads origin dev main
```

2. Validar que o ponto de restauracao existe:

```bash
git ls-remote --heads origin "backup/*"
git ls-remote --tags origin "restore/*"
```

## Opcao A: Restaurar via branch de backup

### Restaurar `dev`

```bash
git push --force-with-lease origin backup/dev-before-sync-20260414-092218:dev
```

### Restaurar `main`

```bash
git push --force-with-lease origin backup/main-before-sync-20260414-092218:main
```

## Opcao B: Restaurar via tag

### Restaurar `dev`

```bash
git push --force-with-lease origin restore/dev-before-sync-20260414-092218:dev
```

### Restaurar `main`

```bash
git push --force-with-lease origin restore/main-before-sync-20260414-092218:main
```

## Validacao pos-rollback

1. Confirmar SHAs finais:

```bash
git ls-remote --heads origin dev main
```

2. Comparar com o SHA esperado do rollback.

3. Validar pipeline de build/deploy no GitHub Actions.

4. Rodar smoke check da aplicacao.

## Comando para criar novo ponto de restauracao (modelo)

Use este modelo antes de qualquer sync forcado:

```bash
ts=$(date +%Y%m%d-%H%M%S)

sha_dev=$(git rev-parse origin/dev)
sha_main=$(git rev-parse origin/main)

git push origin "$sha_dev:refs/heads/backup/dev-before-sync-$ts"
git push origin "$sha_main:refs/heads/backup/main-before-sync-$ts"

git tag -a "restore/dev-before-sync-$ts" "$sha_dev" -m "Restore point dev before sync"
git tag -a "restore/main-before-sync-$ts" "$sha_main" -m "Restore point main before sync"
git push origin "restore/dev-before-sync-$ts" "restore/main-before-sync-$ts"
```

## Boas praticas

- Sempre usar `--force-with-lease` (nunca `--force` puro)
- Avisar o time antes do rollback (impacto em PRs e ambientes)
- Registrar o motivo do rollback no canal de engenharia/incidente
- Manter padrao de nomes com timestamp para auditoria
