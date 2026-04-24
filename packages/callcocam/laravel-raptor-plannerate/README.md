# Laravel Raptor Plannerate (Private)

Pacote privado do domínio Plannerate (editor + backend relacionado).

## Fase 2: Migrations (safe mode)

As migrations do Plannerate ficam versionadas em:

- `database/migrations/clients/*` (dentro deste pacote)

Para sincronizar no app host (sem sobrescrever arquivos existentes):

```bash
php artisan plannerate:migrations:sync
```

Opções úteis:

- `--dry-run`: mostra o que será copiado sem alterar arquivos
- `--force`: sobrescreve arquivos já existentes no destino
- `--target=...`: altera o caminho de destino (padrão: `database/migrations/clients`)

Fluxo recomendado:

1. `php artisan plannerate:migrations:sync`
2. `php artisan tenant:migrate`

