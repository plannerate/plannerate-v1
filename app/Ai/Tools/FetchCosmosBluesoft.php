<?php

namespace App\Ai\Tools;

use App\Models\DimensionResearchCache;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Etapa 2 do pipeline: consulta a API Cosmos/Bluesoft por EAN.
 * Usa cache central (90 dias) compartilhado entre todos os tenants.
 */
class FetchCosmosBluesoft implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta dimensões físicas de um produto pelo código de barras EAN na API Cosmos/Bluesoft. '.
               'Retorna altura, largura, profundidade e peso da embalagem primária em centímetros e gramas.';
    }

    public function handle(Request $request): Stringable|string
    {
        $ean = trim((string) ($request['ean'] ?? ''));

        if ($ean === '') {
            return json_encode(['found' => false, 'reason' => 'EAN não fornecido']);
        }

        $cached = DimensionResearchCache::findValidByEan($ean);
        if ($cached !== null) {
            return json_encode(['found' => true, 'source' => 'cache', 'data' => $cached->dimensions]);
        }

        $response = $this->callCosmosApi($ean);

        return json_encode($response);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'ean' => $schema->string()
                ->description('Código EAN/GTIN do produto (8, 12, 13 ou 14 dígitos)')
                ->required(),
        ];
    }

    /** @return array<string, mixed> */
    private function callCosmosApi(string $ean): array
    {
        $token = config('services.cosmos.token', '');
        $baseUrl = config('services.cosmos.url', 'https://api.cosmos.bluesoft.com.br');

        if ($token === '') {
            return ['found' => false, 'reason' => 'COSMOS_TOKEN não configurado'];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->retry(3, 2000, fn (\Exception $e, $r) => $r?->status() === 429)
                ->get("{$baseUrl}/gtins/{$ean}");

            if ($response->status() === 404) {
                return ['found' => false, 'reason' => 'EAN não encontrado na base Cosmos'];
            }

            if ($response->status() === 429) {
                return ['found' => false, 'reason' => 'Rate limit da API Cosmos atingido'];
            }

            if (! $response->successful()) {
                Log::warning('Cosmos API retornou erro', ['ean' => $ean, 'status' => $response->status()]);

                return ['found' => false, 'reason' => "Erro HTTP {$response->status()} na API Cosmos"];
            }

            $data = $response->json();
            $dimensions = $this->extractDimensions($data);

            DimensionResearchCache::updateOrCreate(
                ['ean' => $ean],
                [
                    'dimensions' => $dimensions,
                    'source' => 'cosmos',
                    'confidence' => 'high',
                    'raw_response' => json_encode($data),
                    'cached_at' => now(),
                    'expires_at' => now()->addDays(90),
                ]
            );

            return ['found' => true, 'source' => 'cosmos', 'confidence' => 'high', 'data' => $dimensions];
        } catch (ConnectionException $e) {
            Log::error('Falha de conexão com Cosmos API', ['ean' => $ean, 'error' => $e->getMessage()]);

            return ['found' => false, 'reason' => 'Falha de conexão com a API Cosmos'];
        }
    }

    /** @param array<string, mixed> $data */
    private function extractDimensions(array $data): array
    {
        return [
            'width' => isset($data['width']) ? (float) $data['width'] : null,
            'height' => isset($data['height']) ? (float) $data['height'] : null,
            'depth' => isset($data['depth']) ? (float) $data['depth'] : null,
            'weight' => isset($data['gross_weight']) ? (float) $data['gross_weight'] * 1000 : null,
            'unit' => 'cm',
        ];
    }
}
