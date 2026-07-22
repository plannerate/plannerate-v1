<?php

namespace App\Services\Integrations\Support;

/**
 * Resolve os nomes das tabelas do schema do tenant que o motor lê e escreve.
 *
 * O motor é genérico, mas alcança tabelas do domínio da aplicação hospedeira
 * (`products`, `sales`, `layers`…). Cada nome passa por `integrations.tables.*`,
 * tendo o próprio nome como default — config ausente mantém o comportamento atual.
 */
final class IntegrationTables
{
    public static function name(string $table): string
    {
        $configured = config('integrations.tables.'.$table);

        return is_string($configured) && $configured !== '' ? $configured : $table;
    }

    /**
     * Compara um nome de tabela vindo do blueprint com um papel conhecido do motor
     * (ex.: saber se o target é a pivot produto↔loja mesmo que ela tenha outro nome).
     */
    public static function is(string $table, string $role): bool
    {
        return $table === self::name($role);
    }
}
