<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

/**
 * Critério de priorização de produtos por zona térmica da prateleira.
 *
 * Zona quente (eye + hand): produtos de maior valor estratégico.
 * Zona fria (high + low): produtos complementares ou de menor rotatividade.
 * None: mantém o comportamento atual sem ajuste por zona.
 */
enum ZonePriority: string
{
    case None = 'none';

    // Zona quente — Eye + Hand
    case MaiorMargem = 'maior_margem';
    case MaiorGiro = 'maior_giro';
    case MaiorValorVendido = 'maior_valor_vendido';
    case CurvaA = 'curva_a';

    // Zona fria — High + Low
    case MenorMargem = 'menor_margem';
    case ComplementarFria = 'complementar_fria';
    case MaiorVolume = 'maior_volume';
    case MenorPrioridade = 'menor_prioridade';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Sem critério (padrão)',
            self::MaiorMargem => 'Maior margem',
            self::MaiorGiro => 'Maior giro (vendas)',
            self::MaiorValorVendido => 'Maior valor vendido',
            self::CurvaA => 'Curva A primeiro',
            self::MenorMargem => 'Menor margem (zona fria)',
            self::ComplementarFria => 'Complementar / sazonais',
            self::MaiorVolume => 'Maior volume físico',
            self::MenorPrioridade => 'Menor prioridade geral',
        };
    }

    /**
     * Indica para qual zona térmica este critério é mais adequado.
     * Retorna 'hot' | 'cold' | 'any'
     */
    public function suggestedZone(): string
    {
        return match ($this) {
            self::None => 'any',
            self::MaiorMargem, self::MaiorGiro, self::MaiorValorVendido, self::CurvaA => 'hot',
            self::MenorMargem, self::ComplementarFria, self::MaiorVolume, self::MenorPrioridade => 'cold',
        };
    }

    /** @return list<self> Critérios recomendados para zona quente */
    public static function hotOptions(): array
    {
        return [self::None, self::MaiorMargem, self::MaiorGiro, self::MaiorValorVendido, self::CurvaA];
    }

    /** @return list<self> Critérios recomendados para zona fria */
    public static function coldOptions(): array
    {
        return [self::None, self::MenorMargem, self::ComplementarFria, self::MaiorVolume, self::MenorPrioridade];
    }
}
