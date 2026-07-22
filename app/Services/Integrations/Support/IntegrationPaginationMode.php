<?php

namespace App\Services\Integrations\Support;

/**
 * Modo de paginação da API.
 *
 * - `page` (default, comportamento histórico): a resposta traz o total de
 *   páginas, a descoberta sonda esse total e despacha as páginas em paralelo.
 * - `cursor`: a resposta NÃO traz total nem last_page; a próxima página é
 *   identificada pelo id do último item (`cursor_item_path`), que entra no
 *   placeholder `{cursor}` do `fallback_path`. É inerentemente sequencial: cada
 *   fetch encadeia o próximo, como já acontece no modo diário (`autoPage`).
 *
 * Lido do topo de `requests` (`pagination_mode`), com override por path.
 */
class IntegrationPaginationMode
{
    public const PAGE = 'page';

    public const CURSOR = 'cursor';

    /**
     * @param  array<string, mixed>  $requests  IntegrationApi->requests
     * @param  array<string, mixed>  $pathConfig  Config do path (products/sales/...)
     */
    public static function resolve(array $requests, array $pathConfig): string
    {
        $mode = (string) (data_get($pathConfig, 'pagination_mode') ?? data_get($requests, 'pagination_mode', self::PAGE));

        return $mode === self::CURSOR ? self::CURSOR : self::PAGE;
    }

    /**
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $pathConfig
     */
    public static function isCursor(array $requests, array $pathConfig): bool
    {
        return self::resolve($requests, $pathConfig) === self::CURSOR;
    }

    /**
     * Cursor inicial do path (o valor que significa "do começo"). A API da RP
     * Info usa "0"; deixamos configurável porque outras usam string vazia.
     *
     * @param  array<string, mixed>  $pathConfig
     */
    public static function initialCursor(array $pathConfig): string
    {
        return (string) (data_get($pathConfig, 'cursor_initial') ?? '0');
    }

    /**
     * Extrai o cursor da próxima página a partir dos itens BRUTOS da resposta.
     *
     * Usa os itens brutos (não os registros mapeados) de propósito: o campo do
     * cursor quase nunca é mapeado, e uma página inteira rejeitada pelas
     * validações não pode interromper a cadeia — senão o import para no primeiro
     * lote de registros cancelados.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @param  array<string, mixed>  $pathConfig
     */
    public static function nextCursor(array $rawItems, array $pathConfig): ?string
    {
        $cursorPath = (string) data_get($pathConfig, 'cursor_item_path', '');

        if ($cursorPath === '' || $rawItems === []) {
            return null;
        }

        $lastItem = $rawItems[array_key_last($rawItems)];
        $cursor = data_get($lastItem, $cursorPath);

        if (! is_scalar($cursor) || (string) $cursor === '') {
            return null;
        }

        return (string) $cursor;
    }
}
