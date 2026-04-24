<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Tenant\Plannerate;

use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\AutoGenerate\AutoGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\DTOs\Plannerate\IAGenerate\IAGenerateConfigDTO;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\AutoGeneratePlanogramRequest;
use Callcocam\LaravelRaptorPlannerate\Http\Requests\Tenant\Plannerate\IAGeneratePlanogramRequest;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\AutoGenerate\AutoPlanogramService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\IAGenerate\IAPlanogramService;
use Callcocam\LaravelRaptorPlannerate\Services\Plannerate\SectionGenerate\SectionPlanogramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Controller de Geração Automática de Planogramas
 *
 * Endpoints:
 * - POST .../gondolas/{gondola}/auto-generate (gôndola inteira)
 * - POST .../gondolas/{gondola}/ia-generate (IA Prism)
 * - POST .../gondolas/{gondola}/generate-by-sections (por section, regras)
 */
class AutoPlanogramController extends Controller
{
    protected const IA_SECTION_FALLBACK_SKU_THRESHOLD = 40;

    protected const IA_SECTION_FALLBACK_TOTAL_PRODUCTS_THRESHOLD = 100;

    public function __construct(
        protected AutoPlanogramService $autoPlanogramService,
        protected IAPlanogramService $iaPlanogramService,
        protected SectionPlanogramService $sectionPlanogramService,
    ) {}

    /**
     * Gerar planograma automaticamente
     */
    public function generate(string $gondolaId, AutoGeneratePlanogramRequest $request): RedirectResponse
    {
        try {
            // 1. Criar DTO de configuração a partir do request validado
            $config = AutoGenerateConfigDTO::fromArray($request->validated());

            Log::info('📥 Requisição de geração automática recebida', [
                'gondola_id' => $gondolaId,
                'config' => $config->toArray(),
                'user_id' => auth()->id(),
            ]);

            // 2. Gerar planograma para a gôndola específica
            $result = $this->autoPlanogramService->generate($gondolaId, $config);
            $jsonFilePath = $this->persistGenerationResultJson(
                mode: 'auto-generate',
                gondolaId: $gondolaId,
                config: $config->toArray(),
                result: $result,
            );

            // 3. Retornar com mensagem de sucesso
            return back()->with('success',
                "✅ Planograma gerado com sucesso!\n\n".
                "Produtos alocados: {$result->totalAllocated}\n".
                "Produtos não alocados: {$result->totalUnallocated}\n".
                'Prateleiras usadas: '.count($result->shelves)."\n".
                "JSON completo: storage/app/{$jsonFilePath}"
            )->with('generation_result_json_path', $jsonFilePath);

        } catch (\RuntimeException $e) {
            // Erros de validação/configuração (não são erros técnicos)
            if (str_contains($e->getMessage(), 'Nenhum produto encontrado') ||
                str_contains($e->getMessage(), 'Categoria') ||
                str_contains($e->getMessage(), 'não encontrada')) {
                Log::info('⚠️  Geração cancelada: problema de configuração', [
                    'gondola_id' => $gondolaId,
                    'reason' => $e->getMessage(),
                ]);

                return back()->with('warning', '⚠️ '.$e->getMessage());
            }

            // Outras RuntimeExceptions
            Log::warning('⚠️  Erro de validação ao gerar planograma', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', '⚠️ '.$e->getMessage());
        } catch (\Exception $e) {
            // Erros técnicos inesperados
            Log::error('❌ Erro técnico ao gerar planograma automaticamente', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error',
                'Erro ao gerar planograma: '.$e->getMessage()
            );
        }
    }

    /**
     * Gerar planograma usando IA (Prism PHP)
     */
    public function iaGenerate(string $gondolaId, IAGeneratePlanogramRequest $request): RedirectResponse
    {
        try {
            // 1. Criar DTO de configuração a partir do request validado
            $validated = $request->validated();
            $config = IAGenerateConfigDTO::fromArray($validated);

            Log::info('🤖 Requisição de geração com IA recebida', [
                'gondola_id' => $gondolaId,
                'config' => $config->toArray(),
                'user_id' => auth()->id(),
            ]);

            $complexity = $this->iaPlanogramService->estimateSelectionComplexity($gondolaId, $config);
            $estimatedUniqueSkus = (int) ($complexity['unique_skus'] ?? 0);
            $estimatedTotalSelected = (int) ($complexity['total_selected'] ?? 0);

            if (
                $estimatedUniqueSkus > self::IA_SECTION_FALLBACK_SKU_THRESHOLD
                || $estimatedTotalSelected > self::IA_SECTION_FALLBACK_TOTAL_PRODUCTS_THRESHOLD
            ) {
                Log::info('🧩 Fallback automático para IA por section ativado', [
                    'gondola_id' => $gondolaId,
                    'estimated_unique_skus' => $estimatedUniqueSkus,
                    'estimated_total_selected' => $estimatedTotalSelected,
                    'unique_threshold' => self::IA_SECTION_FALLBACK_SKU_THRESHOLD,
                    'total_threshold' => self::IA_SECTION_FALLBACK_TOTAL_PRODUCTS_THRESHOLD,
                ]);

                $sectionConfig = AutoGenerateConfigDTO::fromArray($validated);
                $sectionResult = $this->sectionPlanogramService->generateBySections($gondolaId, $sectionConfig, true);

                $jsonFilePath = $this->persistGenerationResultJson(
                    mode: 'ia-generate-by-sections-auto',
                    gondolaId: $gondolaId,
                    config: array_merge($sectionConfig->toArray(), [
                        'model' => $config->model,
                        'max_tokens' => $config->maxTokens,
                        'temperature' => $config->temperature,
                        'estimated_unique_skus' => $estimatedUniqueSkus,
                        'estimated_total_selected' => $estimatedTotalSelected,
                        'fallback_unique_threshold' => self::IA_SECTION_FALLBACK_SKU_THRESHOLD,
                        'fallback_total_threshold' => self::IA_SECTION_FALLBACK_TOTAL_PRODUCTS_THRESHOLD,
                    ]),
                    result: $sectionResult,
                );

                return back()->with('success',
                    "🤖 Planograma gerado por IA em modo por section (fallback automático)!\n\n".
                    "SKUs únicos estimados: {$estimatedUniqueSkus}\n".
                    "Produtos selecionados: {$estimatedTotalSelected}\n".
                    "Sections processadas: {$sectionResult->sectionsProcessed}\n".
                    "Produtos alocados: {$sectionResult->totalAllocated}\n".
                    "Produtos não alocados: {$sectionResult->totalUnallocated}\n\n".
                    "JSON completo: storage/app/{$jsonFilePath}"
                )->with('generation_result_json_path', $jsonFilePath);
            }

            // 2. Gerar planograma usando IA
            $result = $this->iaPlanogramService->generate($gondolaId, $config);
            $jsonFilePath = $this->persistGenerationResultJson(
                mode: 'ia-generate',
                gondolaId: $gondolaId,
                config: $config->toArray(),
                result: $result,
            );

            // 3. Retornar com mensagem de sucesso detalhada
            return back()->with('success',
                "🤖 Planograma gerado com IA!\n\n".
                "✅ Produtos alocados: {$result->totalAllocated}\n".
                "❌ Produtos não alocados: {$result->totalUnallocated}\n".
                '📊 Prateleiras usadas: '.count($result->shelves)."\n".
                '🎯 Confiança: '.round($result->confidence * 100, 1)."%\n".
                "💬 Tokens usados: {$result->tokensUsed}\n".
                '⏱️ Tempo: '.round($result->executionTime, 2)."s\n\n".
                "💡 Raciocínio da IA:\n".substr($result->reasoning, 0, 200)."...\n\n".
                "JSON completo: storage/app/{$jsonFilePath}"
            )->with('generation_result_json_path', $jsonFilePath);

        } catch (\RuntimeException $e) {
            // Erros de validação/configuração (não são erros técnicos)
            if (str_contains($e->getMessage(), 'Nenhum produto encontrado') ||
                str_contains($e->getMessage(), 'Categoria') ||
                str_contains($e->getMessage(), 'não encontrada')) {
                Log::info('⚠️  Geração IA cancelada: problema de configuração', [
                    'gondola_id' => $gondolaId,
                    'reason' => $e->getMessage(),
                ]);

                return back()->with('warning', '⚠️ '.$e->getMessage());
            }

            // Outras RuntimeExceptions
            Log::warning('⚠️  Erro de validação ao gerar planograma com IA', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', '⚠️ '.$e->getMessage());
        } catch (\Exception $e) {
            // Erros técnicos inesperados
            Log::error('❌ Erro técnico ao gerar planograma com IA', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error',
                '❌ Erro ao gerar planograma com IA: '.$e->getMessage()
            );
        }
    }

    /**
     * Gerar planograma por section (módulo) com regras de merchandising.
     */
    public function generateBySections(string $gondolaId, AutoGeneratePlanogramRequest $request): RedirectResponse
    {
        try {
            $config = AutoGenerateConfigDTO::fromArray($request->validated());
            $useAi = $request->boolean('use_ai', false);
            Log::info('📐 Requisição de geração por sections recebida', [
                'gondola_id' => $gondolaId,
                'use_ai' => $useAi,
                'user_id' => auth()->id(),
            ]);

            Log::info('Configurações de geração por sections', [
                'config' => $config->toArray(),
                'use_ai' => $useAi,
            ]);

            $result = $this->sectionPlanogramService->generateBySections($gondolaId, $config, $useAi);
            $jsonFilePath = $this->persistGenerationResultJson(
                mode: 'generate-by-sections',
                gondolaId: $gondolaId,
                config: array_merge($config->toArray(), ['use_ai' => $useAi]),
                result: $result,
            );

            $qualityLines = '';
            if ($result->qualityMetrics !== []) {
                $fillRate = (float) ($result->qualityMetrics['fill_rate'] ?? 0.0);
                $unallocatedRate = (float) ($result->qualityMetrics['unallocated_rate'] ?? 0.0);
                $concentrationRate = (float) ($result->qualityMetrics['allocation_concentration_rate'] ?? 0.0);

                $qualityLines =
                    "\nMétricas de qualidade:\n"
                    .'Fill rate: '.number_format($fillRate, 2, ',', '.')."%\n"
                    .'Taxa não alocados: '.number_format($unallocatedRate, 2, ',', '.')."%\n"
                    .'Concentração por section: '.number_format($concentrationRate, 2, ',', '.')."%\n";
            }

            return back()->with('success',
                "✅ Planograma gerado por sections!\n\n".
                "Sections processadas: {$result->sectionsProcessed}\n".
                "Produtos alocados: {$result->totalAllocated}\n".
                "Produtos não alocados: {$result->totalUnallocated}\n"
                .$qualityLines
                ."JSON completo: storage/app/{$jsonFilePath}"
            )->with('generation_result_json_path', $jsonFilePath);
        } catch (\RuntimeException $e) {
            // Erros de validação/configuração (não são erros técnicos)
            if (str_contains($e->getMessage(), 'Nenhum produto encontrado')) {
                Log::info('⚠️  Geração cancelada: sem produtos disponíveis', [
                    'gondola_id' => $gondolaId,
                    'category_id' => $config->categoryId ?? 'N/A',
                ]);

                return back()->with('warning',
                    "⚠️ Não foi possível gerar o planograma\n\n".
                    "📦 Nenhum produto encontrado na categoria selecionada.\n\n".
                    "💡 Verifique:\n".
                    "• Se os produtos foram importados para este cliente\n".
                    "• Se a categoria está correta no planograma\n".
                    "• Se os produtos têm a categoria_id correta\n".
                    '• Se há análise de vendas disponível (quando necessário)'
                );
            }

            // Outras RuntimeExceptions
            Log::warning('⚠️  Erro de validação ao gerar planograma', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
            ]);

            return back()->with('warning', '⚠️ '.$e->getMessage());
        } catch (\Exception $e) {
            // Erros técnicos inesperados
            Log::error('❌ Erro técnico ao gerar planograma por sections', [
                'gondola_id' => $gondolaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', '❌ Erro inesperado ao gerar planograma: '.$e->getMessage());
        }
    }

    /**
     * Gera e persiste JSON completo da execução para análise posterior.
     */
    protected function persistGenerationResultJson(string $mode, string $gondolaId, array $config, mixed $result): string
    {
        $payload = [
            'meta' => [
                'mode' => $mode,
                'gondola_id' => $gondolaId,
                'user_id' => auth()->id(),
                'generated_at' => now()->toIso8601String(),
            ],
            'config' => $config,
            'result' => $this->normalizeForJson($result),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Falha ao serializar JSON da geração.');
        }

        $safeGondolaId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $gondolaId) ?? 'unknown';
        $filePath = sprintf(
            'planogram-generation-results/%s_%s_%s.json',
            now()->format('Ymd_His_u'),
            $mode,
            $safeGondolaId,
        );

        Storage::disk('local')->put($filePath, $json);

        Log::info('JSON completo da geração salvo', [
            'mode' => $mode,
            'gondola_id' => $gondolaId,
            'file_path' => $filePath,
        ]);

        return $filePath;
    }

    protected function normalizeForJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalizeForJson($item), $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $this->normalizeForJson($value->toArray());
            }

            return $this->normalizeForJson(get_object_vars($value));
        }

        return $value;
    }
}
