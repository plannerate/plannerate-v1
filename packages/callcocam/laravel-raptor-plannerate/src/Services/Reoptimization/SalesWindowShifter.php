<?php

namespace Callcocam\LaravelRaptorPlannerate\Services\Reoptimization;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Reposiciona a janela de vendas de uma configuração de geração no tempo.
 *
 * A reotimização reusa a configuração da última geração — a mesma estratégia, os mesmos cortes
 * ABC, os mesmos limites de participação. Só a JANELA DE VENDAS precisa andar: reprocessar com
 * as datas congeladas do formulário original devolveria exatamente o mesmo planograma, e a
 * feature inteira não teria propósito.
 *
 * O ponto sutil é COMO andar. A janela original não é necessariamente "os últimos N meses": o
 * próprio formulário orienta escolher o período do ANO ANTERIOR por causa de sazonalidade. Colar
 * a nova janela em "os últimos N meses" jogaria essa intenção fora sem avisar.
 *
 * Então preserva-se a DEFASAGEM: se o usuário configurou uma janela que terminava 12 meses antes
 * do dia em que gerou, a nova janela termina 12 meses antes de hoje — o análogo sazonal do
 * período que vem. Se ele escolheu o mês passado (defasagem curta), continua sendo o mês passado.
 * A intenção original é respeitada nos dois casos.
 *
 * Com `monthly_summaries` a janela é ancorada em meses FECHADOS: um mês em curso tem vendas
 * parciais, e uma janela terminando nele derrubaria as classes ABC de todo mundo com dados
 * incompletos — ou, no limite, zeraria as vendas e cancelaria a geração.
 */
final class SalesWindowShifter
{
    /**
     * @param  array<string, mixed>  $config  AutoGenerateConfigDTO->toArray()
     * @return array<string, mixed> O mesmo config com start_date/end_date deslocados
     */
    public function shift(array $config, CarbonInterface $configuredAt, CarbonInterface $now): array
    {
        $start = $config['start_date'] ?? null;
        $end = $config['end_date'] ?? null;

        // Sem janela definida, a seleção usa os defaults dela — não há o que deslocar.
        if (! is_string($start) || ! is_string($end) || $start === '' || $end === '') {
            return $config;
        }

        $start = CarbonImmutable::parse($start)->startOfDay();
        $end = CarbonImmutable::parse($end)->startOfDay();

        if ($end->lessThan($start)) {
            return $config;
        }

        $today = CarbonImmutable::parse($now)->startOfDay();
        $lagDays = max(0, (int) $end->diffInDays(CarbonImmutable::parse($configuredAt)->startOfDay(), absolute: false));

        $newEnd = $today->subDays($lagDays);

        if (($config['table_type'] ?? 'monthly_summaries') !== 'monthly_summaries') {
            // Vendas diárias: a janela é medida em dias e termina onde a defasagem mandar.
            $config['start_date'] = $newEnd->subDays((int) $start->diffInDays($end))->toDateString();
            $config['end_date'] = $newEnd->toDateString();

            return $config;
        }

        // Resumos mensais: a janela é medida em MESES, não em dias. Deslocá-la por dias faria
        // uma janela de 3 meses virar uma de 4 (ou 2), mudando silenciosamente o volume de
        // histórico que embasa a curva ABC.
        $months = (int) $start->startOfMonth()->diffInMonths($end->startOfMonth()) + 1;

        $newEnd = $newEnd->endOfMonth();

        // Só recua se o mês ainda não fechou. Um mês em curso tem vendas parciais, e terminar a
        // janela nele derrubaria as classes ABC com dados incompletos.
        if ($newEnd->greaterThanOrEqualTo($today)) {
            $newEnd = $newEnd->startOfMonth()->subDay()->endOfMonth();
        }

        $config['start_date'] = $newEnd->startOfMonth()->subMonths($months - 1)->toDateString();
        $config['end_date'] = $newEnd->toDateString();

        return $config;
    }
}
