<?php

use App\Models\IntegrationApi;
use Illuminate\Database\Migrations\Migration;

/**
 * Blueprint da API **RP Services** (ERP RP Info — linhas RP One, Flex, Mix e Cxo).
 *
 * Documentação: https://servicosflex.rpinfo.com.br:7443/v1.0/documentacao
 * Payloads reais medidos: `storage/app/private/rpinfo/`.
 *
 * Particularidades que motivaram as extensões do motor (ver `.claude/nova-integracao.md`):
 *
 * - **Paginação por cursor**: a resposta não traz total/last_page. A próxima página é o
 *   id do último item, que entra no placeholder `{cursor}` do path. Daí
 *   `pagination_mode: cursor` + `cursor_item_path`.
 * - **CNPJ da unidade no path** (`{store_document}`) no endpoint de produtos; nas vendas
 *   ele vai na query (`unidade`), e a própria API aceita o CNPJ ali — por isso o
 *   `store_document_field` padrão do motor funciona sem adaptação.
 * - **Token em header próprio** (`token: <jwt>`), configurado por tenant em `auth.token_header`.
 * - **Datas dd/MM/yyyy na resposta** (transform `date_dmy`) e **dd-MM-yyyy na query**
 *   (`date_query_format`); com ISO a API responde HTTP 200 e `status: "error"`.
 * - **Erro com HTTP 200**: daí `error_status_path` no `response`.
 *
 * ## Escolha do endpoint de vendas
 *
 * Usa `/v1.9/movimentoprodutos/listarmovimentos` com `tipoconsulta=CODIGO_DCTO`, e NÃO o
 * `/v1.3/.../listarmovimentosevp` da coleção Postman. Medido no mesmo dia (15/07/2026):
 * o EVP devolve linha de cupom — 7.150 linhas para 2.404 pares (unidade, produto) — e o
 * upsert do motor é last-write-wins sem agregação, então ~66% do valor vendido sumiria em
 * silêncio. Além disso o EVP não traz custo. O v1.9 devolve exatamente 1 linha por
 * (unidade, produto, dia) — 2.396 linhas, zero repetição — com ctCompra/ctMedio/ctFiscal.
 *
 * O código do documento (`valores`, ex.: 7300 = "Importação - Vendas De Produtos PDV's")
 * varia por cliente: fica em `connection.params` do tenant, não aqui. Descobre-se em
 * `GET /v1.0/documentos/ativo_usuario?tipo=EVP`.
 *
 * ## O que NÃO é mapeado em produtos, e por quê
 *
 * Taxa de preenchimento medida em 2.500 produtos reais. O upsert reescreve toda coluna
 * presente no registro mapeado, então campo vazio grava null por cima a cada import:
 *
 * - `Altura`/`Largura`/`Profundidade` → **0%** preenchido, e `width`/`height`/`depth` são
 *   do pipeline de pesquisa de dimensões (`.claude/dimension-research.md`). Mapear zeraria.
 * - `Peso`/`PesoLiquido` → o mesmo pipeline é dono de `weight`, em **gramas**; o `Peso` da
 *   RP é fator de kg (1.0 para "Uva Niagara Kg"). Mapear corromperia o dado pesquisado.
 * - `Departamento`, `descricaoEcommerce`, `marcaEcommerce`, `Extra*`, `Frentes`, `Espaco` → 0%.
 * - `Grupo`/`CodigoDepartamento` → `products` não tem coluna texto de categoria, só `category_id` (FK).
 * - `QuantidadeEmbalagem` → é quantidade da caixa de compra (12, 24, 6…), não conteúdo da
 *   embalagem de venda; gravaria número errado em `packaging_content`.
 *
 * Em vendas, `promotion` fica sem source: o v1.9 não traz indicador de oferta (o EVP traz,
 * mas foi descartado). A coluna permanece null e o id determinístico segue estável.
 *
 * Guardada e idempotente: não recria o blueprint se o slug já existir.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    private const SLUG = 'rpinfo';

    public function up(): void
    {
        if (IntegrationApi::query()->where('slug', self::SLUG)->exists()) {
            return;
        }

        IntegrationApi::query()->create([
            'name' => 'RP Info',
            'slug' => self::SLUG,
            'description' => 'RP Services (RP One / Flex / Mix). Paginação por cursor (lastId no path) e token em header.',
            'requests' => $this->requests(),
            'response' => $this->response(),
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        IntegrationApi::query()->where('slug', self::SLUG)->forceDelete();
    }

    /** @return array<string, mixed> */
    private function requests(): array
    {
        return [
            'method' => 'GET',
            'pagination_mode' => 'cursor',
            'store_document_field' => 'unidade',
            'paths' => [
                'products' => $this->productsPath(),
                'sales' => $this->salesPath(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function productsPath(): array
    {
        return [
            'target_table' => 'products',
            'fallback_path' => '/v3.2/produtounidade/listaprodutos/{cursor}/unidade/{store_document}/detalhado',
            'items_path' => 'response.produtos',
            'cursor_item_path' => 'Codigo',
            'cursor_initial' => 0,
            'unique_by' => ['ean'],
            'include_integration_in_id' => false,
            'field_map' => [
                ['target' => 'codigo_erp', 'source' => 'Codigo', 'transforms' => ['string', 'alnum', 'not_null']],
                ['target' => 'ean', 'source' => 'CodigoBarras', 'transforms' => ['string', 'ean', 'not_null']],
                ['target' => 'name', 'source' => 'Descricao', 'transforms' => ['string']],
                ['target' => 'auxiliary_description', 'source' => 'Complemento', 'transforms' => ['string']],
                ['target' => 'brand', 'source' => 'Marca', 'transforms' => ['string']],
                ['target' => 'measurement_unit', 'source' => 'TipoEmbalagem', 'transforms' => ['string']],
                ['target' => 'current_stock', 'source' => 'Estoque1', 'transforms' => ['decimal']],
                // DtUltComp vem como "13-03-2026" (com hífen): o transform `date` dá conta.
                ['target' => 'last_purchase_date', 'source' => 'DtUltComp', 'transforms' => ['date']],
            ],
            'pivot_tables' => [
                [
                    'table' => 'product_store',
                    'local_key' => 'id',
                    'foreign_key' => 'product_id',
                    'related_key' => 'store_id',
                    'unique_by' => ['tenant_id', 'product_id', 'store_id'],
                ],
            ],
            'validations' => [
                ['type' => 'all_of', 'sources' => ['Status'], 'allowed_values' => ['NORMAL']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function salesPath(): array
    {
        return [
            'target_table' => 'sales',
            'fallback_path' => '/v1.9/movimentoprodutos/listarmovimentos/lastid/{cursor}',
            'items_path' => 'response.movimentos',
            'cursor_item_path' => 'id',
            'cursor_initial' => 0,
            'id_prefix' => 'S1',
            'unique_by' => ['codigo_erp', 'sale_date', 'promotion'],
            'include_store_in_id' => true,
            'initial_days' => 200,
            'last_date_column' => 'sale_date',
            'date_fields' => ['start' => 'datainicial', 'end' => 'datafinal'],
            'date_query_format' => 'd-m-Y',
            'field_map' => [
                ['target' => 'codigo_erp', 'source' => 'codigoProduto', 'transforms' => ['string', 'not_null']],
                ['target' => 'sale_date', 'source' => 'data', 'transforms' => ['date_dmy', 'not_null']],
                ['target' => 'total_sale_quantity', 'source' => 'quantidadeUnitaria', 'transforms' => ['decimal']],
                ['target' => 'total_sale_value', 'source' => 'valor', 'transforms' => ['decimal']],
                ['target' => 'sale_price', 'source' => 'valor', 'transforms' => ['decimal']],
                ['target' => 'acquisition_cost', 'source' => 'ctCompra', 'transforms' => ['decimal']],
                // ctMedio é o custo TOTAL da linha (48.18 para 22 un a 2,19), não o unitário.
                [
                    'target' => 'margem_contribuicao',
                    'source' => 'valor - valorPIS - valorCofins - valorIcms - ctMedio',
                    'transforms' => ['round2'],
                ],
            ],
            'validations' => [
                ['type' => 'all_of', 'sources' => ['status'], 'allowed_values' => ['NORMAL']],
                ['type' => 'all_of', 'sources' => ['tipoMovimento'], 'allowed_values' => ['SAIDA']],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function response(): array
    {
        return [
            // Cada endpoint tem seu envelope; o caminho real fica no path config
            // (`items_path`). Este global é só o fallback.
            'items_path' => 'response',
            'error_status_path' => 'response.status',
            'error_status_values' => ['error'],
            'error_message_path' => 'response.messages',
        ];
    }
};
