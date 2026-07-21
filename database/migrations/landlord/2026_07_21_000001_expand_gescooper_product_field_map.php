<?php

use App\Models\IntegrationApi;
use Illuminate\Database\Migrations\Migration;

/**
 * Amplia o `field_map` de produtos do blueprint GesCooper (ERP por trás da API da Coasgo).
 *
 * O mapeamento original trazia apenas `codigo_erp`, `ean`, `name` e `last_purchase_date`,
 * deixando de fora atributos que o feed entrega preenchidos em 100% da amostra
 * (`storage/app/private/coasgo/resposta-produtos.json`): marca, unidade de medida,
 * tipo de embalagem, descrição auxiliar, tipo, estoque e situação do produto.
 *
 * Deliberadamente NÃO mapeados, porque o upsert reescreve toda coluna presente no
 * registro e gravaria null/zero por cima de dado bom a cada import:
 *
 * - `altura`/`largura`/`profundidade` → o feed manda null (998/1000) ou 0 (2/1000),
 *   enquanto `width`/`height`/`depth` são preenchidos pelo pipeline de pesquisa de
 *   dimensões (ver `.claude/dimension-research.md`). Mapear zeraria essa pesquisa.
 * - `submarca`, `tamanho_embalagem`, `informacao_adicional`, `referencia`,
 *   `fragrancia`, `sabor`, `cor` → 0% de preenchimento no feed; mapear apagaria o
 *   que for preenchido manualmente na UI.
 * - `custo_aquisicao`/`preco_venda` → 0% no feed, variam por filial e não constam do
 *   whitelist de `products` em `config/integrations.php`; virão junto com vendas.
 *
 * Idempotente e guardada: não faz nada se o blueprint não existir, se ainda não houver
 * `field_map` de produtos, ou se o target já estiver mapeado.
 */
return new class extends Migration
{
    protected $connection = 'landlord';

    /**
     * Campos acrescentados ao mapeamento de produtos.
     *
     * @var array<int, array{target: string, source: string, transforms: array<int, string>}>
     */
    private const ADDED_FIELDS = [
        ['target' => 'brand', 'source' => 'marca', 'transforms' => ['string']],
        ['target' => 'measurement_unit', 'source' => 'unidade_medida', 'transforms' => ['string']],
        ['target' => 'packaging_type', 'source' => 'tipo_embalagem', 'transforms' => ['string']],
        ['target' => 'auxiliary_description', 'source' => 'descricao_auxiliar', 'transforms' => ['string']],
        ['target' => 'type', 'source' => 'tipo', 'transforms' => ['string']],
        ['target' => 'current_stock', 'source' => 'estoque_atual', 'transforms' => ['decimal']],
        ['target' => 'sales_status', 'source' => 'status_produto', 'transforms' => ['string']],
    ];

    public function up(): void
    {
        $api = IntegrationApi::query()->where('slug', 'gescooper')->first();

        if ($api === null) {
            return;
        }

        $requests = $api->requests ?? [];
        $fieldMap = (array) data_get($requests, 'paths.products.field_map', []);

        if ($fieldMap === []) {
            return;
        }

        $mappedTargets = array_column($fieldMap, 'target');

        foreach (self::ADDED_FIELDS as $field) {
            if (in_array($field['target'], $mappedTargets, true)) {
                continue;
            }

            $fieldMap[] = $field;
        }

        data_set($requests, 'paths.products.field_map', array_values($fieldMap));

        $api->requests = $requests;
        $api->save();
    }

    public function down(): void
    {
        $api = IntegrationApi::query()->where('slug', 'gescooper')->first();

        if ($api === null) {
            return;
        }

        $requests = $api->requests ?? [];
        $fieldMap = (array) data_get($requests, 'paths.products.field_map', []);

        if ($fieldMap === []) {
            return;
        }

        $addedTargets = array_column(self::ADDED_FIELDS, 'target');

        $fieldMap = array_values(array_filter(
            $fieldMap,
            fn (array $field): bool => ! in_array($field['target'] ?? '', $addedTargets, true),
        ));

        data_set($requests, 'paths.products.field_map', $fieldMap);

        $api->requests = $requests;
        $api->save();
    }
};
