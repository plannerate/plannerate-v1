<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Igual ao RefreshDatabase padrão, mas pula o migrate:fresh quando
 * PLANNERATE_FAST_TESTS está setado — nesse caso, scripts/test-fast.sh já
 * garantiu (via `test:fast-db:prepare`) que os arquivos SQLite de trabalho
 * estão com o schema migrado. Sem a env var, cai 100% no comportamento
 * padrão do Laravel (é o caso do CI, que nunca define essa var).
 */
trait FastRefreshDatabase
{
    use RefreshDatabase {
        RefreshDatabase::migrateDatabases as baseMigrateDatabases;
    }

    protected function migrateDatabases(): void
    {
        if (env('PLANNERATE_FAST_TESTS')) {
            return;
        }

        $this->baseMigrateDatabases();
    }
}
