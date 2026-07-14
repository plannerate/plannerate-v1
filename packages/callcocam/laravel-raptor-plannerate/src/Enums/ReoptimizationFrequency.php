<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

use Carbon\CarbonInterface;

/**
 * Com que frequência uma gôndola é reprocessada contra os dados de venda atualizados.
 *
 * Sem opção diária de propósito: o planograma é executado na loja por pessoas repondo prateleira.
 * Uma proposta por dia geraria mais churn operacional do que ganho de venda — e treinaria o usuário
 * a ignorar as propostas.
 */
enum ReoptimizationFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quinzenal',
            self::Monthly => 'Mensal',
        };
    }

    public function days(): int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Biweekly => 14,
            self::Monthly => 30,
        };
    }

    /**
     * Próxima execução a partir de uma data-base.
     */
    public function nextRunFrom(CarbonInterface $from): CarbonInterface
    {
        return $from->copy()->addDays($this->days())->startOfDay();
    }
}
