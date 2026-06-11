<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\Synthesis;

use App\Models\Category;
use Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO\CategoryAbcSummary;
use Callcocam\LaravelRaptorPlannerate\Enums\CategoryRole;

/**
 * Infere o papel (CategoryRole) de uma subcategoria a partir dos dados de venda.
 * Sempre respeita categories.role quando configurado manualmente.
 *
 * Limiares definidos como constantes nomeadas — prontos para virar config por tenant.
 */
class CategoryRoleInferrer
{
    /** Participação de giro normalizada acima da qual a categoria é considerada de alto giro. */
    public const HIGH_QUANTITY_THRESHOLD = 0.6;

    /** Participação de margem normalizada acima da qual a categoria é considerada de alta margem. */
    public const HIGH_MARGEM_THRESHOLD = 0.6;

    /** Participação de margem normalizada abaixo da qual a categoria é considerada de baixa margem. */
    public const LOW_MARGEM_THRESHOLD = 0.3;

    /** Participação de giro normalizada abaixo da qual a categoria é considerada de baixo giro. */
    public const LOW_QUANTITY_THRESHOLD = 0.3;

    /**
     * Retorna o papel da categoria.
     * Se categories.role estiver definido, retorna imediatamente sem inferir.
     *
     * @param  CategoryAbcSummary  $summary  Métricas já normalizadas (0–1) da subcategoria.
     *                                       Espera-se que totalQuantity e totalMargem sejam
     *                                       valores normalizados pelo chamador antes de passar.
     */
    public function infer(Category $category, CategoryAbcSummary $summary): CategoryRole
    {
        if ($category->role !== null) {
            return $category->role;
        }

        return $this->inferFromSummary($summary);
    }

    /**
     * Aplica as heurísticas sobre o agregado da subcategoria.
     * Os valores de quantity e margem em $summary devem estar normalizados (0–1).
     */
    private function inferFromSummary(CategoryAbcSummary $summary): CategoryRole
    {
        $highQuantity = $summary->totalQuantity >= self::HIGH_QUANTITY_THRESHOLD;
        $highMargem = $summary->totalMargem >= self::HIGH_MARGEM_THRESHOLD;
        $lowQuantity = $summary->totalQuantity < self::LOW_QUANTITY_THRESHOLD;
        $lowMargem = $summary->totalMargem < self::LOW_MARGEM_THRESHOLD;

        // Alto giro + alta margem → categoria estratégica → destino
        if ($highQuantity && $highMargem) {
            return CategoryRole::Destino;
        }

        // Alto giro, margem média → rotina (fluxo constante, margem razoável)
        if ($highQuantity && ! $lowMargem) {
            return CategoryRole::Rotina;
        }

        // Margem alta, giro baixo → produto de compra por impulso ou ticket alto
        if ($highMargem && $lowQuantity) {
            return CategoryRole::Impulso;
        }

        // Giro e margem baixos → complementar (suporte ao mix, não atrai tráfego)
        if ($lowQuantity && $lowMargem) {
            return CategoryRole::Complementar;
        }

        return CategoryRole::Rotina;
    }
}
