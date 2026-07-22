<?php

namespace App\Services\Integrations\Support;

/**
 * Monta a URL de uma chamada de integração a partir do `fallback_path` do path config.
 *
 * Além da concatenação com a base_url, resolve os placeholders que algumas APIs
 * exigem no PATH em vez da query string:
 *
 *   {cursor}         → cursor da paginação (ver PaginationMode::Cursor)
 *   {store_document} → documento (CNPJ só dígitos) da loja sendo importada
 *
 * Ex.: "/v3.2/produtounidade/listaprodutos/{cursor}/unidade/{store_document}/detalhado"
 *
 * Quando o placeholder do documento está no path, o valor NÃO deve ser repetido
 * na query — daí `consumesStoreDocumentInPath()`, consultado pelo
 * IntegrationPayloadBuilder.
 */
class IntegrationUrlBuilder
{
    public const CURSOR_PLACEHOLDER = '{cursor}';

    public const STORE_DOCUMENT_PLACEHOLDER = '{store_document}';

    /**
     * @param  array<string, mixed>  $config  Config da TenantIntegration
     * @param  array<string, mixed>  $pathConfig  Config do path (products/sales/...)
     */
    public static function build(
        array $config,
        array $pathConfig,
        ?string $cursor = null,
        ?string $storeDocument = null,
    ): string {
        $baseUrl = (string) data_get($config, 'connection.base_url', '');
        $path = (string) data_get($pathConfig, 'fallback_path', '');

        $path = str_replace(
            [self::CURSOR_PLACEHOLDER, self::STORE_DOCUMENT_PLACEHOLDER],
            [rawurlencode((string) ($cursor ?? '')), rawurlencode((string) ($storeDocument ?? ''))],
            $path,
        );

        return rtrim($baseUrl, '/').$path;
    }

    /** @param array<string, mixed> $pathConfig */
    public static function consumesStoreDocumentInPath(array $pathConfig): bool
    {
        return str_contains((string) data_get($pathConfig, 'fallback_path', ''), self::STORE_DOCUMENT_PLACEHOLDER);
    }
}
