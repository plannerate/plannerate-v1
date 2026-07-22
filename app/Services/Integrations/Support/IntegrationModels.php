<?php

namespace App\Services\Integrations\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Resolve os models da aplicação hospedeira que o motor precisa alcançar.
 *
 * O pipeline de importação não conhece nenhum model — trabalha sobre `Connection` e arrays.
 * Os pontos que precisam de Eloquent (lookup de produto avulso, sync com a base de EANs,
 * notificação de conclusão) chegam à classe por `integrations.models.*`, nunca por import.
 *
 * O model de tenant é exceção: já tem ponto de configuração canônico no Spatie
 * (`multitenancy.tenant_model`), e duplicá-lo aqui criaria duas fontes de verdade.
 */
final class IntegrationModels
{
    public static function product(): string
    {
        return self::classFor('product');
    }

    public static function store(): string
    {
        return self::classFor('store');
    }

    public static function user(): string
    {
        return self::classFor('user');
    }

    public static function eanReference(): string
    {
        return self::classFor('ean_reference');
    }

    public static function tenant(): string
    {
        $class = config('multitenancy.tenant_model');

        if (! is_string($class) || ! class_exists($class)) {
            throw new RuntimeException('multitenancy.tenant_model não aponta para uma classe válida.');
        }

        return $class;
    }

    /**
     * Query builder do model configurado, para não espalhar `$class::query()` pelo motor.
     */
    public static function query(string $key): Builder
    {
        $class = self::classFor($key);

        return $class::query();
    }

    /**
     * @return class-string<Model>
     */
    private static function classFor(string $key): string
    {
        $class = config('integrations.models.'.$key);

        if (! is_string($class) || ! class_exists($class)) {
            throw new RuntimeException(sprintf(
                'integrations.models.%s não aponta para uma classe válida (recebido: %s).',
                $key,
                get_debug_type($class),
            ));
        }

        return $class;
    }
}
