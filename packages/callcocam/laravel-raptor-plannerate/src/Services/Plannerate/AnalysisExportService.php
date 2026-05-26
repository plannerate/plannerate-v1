<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Services\Plannerate;

use Callcocam\LaravelRaptorPlannerate\Models\Editor\GondolaAnalysis;
use Illuminate\Http\Response;

/**
 * Serviço responsável por gerar relatórios CSV das análises de planogramas.
 * Suporta exportação da Análise ABC e da Análise de Estoque Alvo.
 */
class AnalysisExportService
{
    /**
     * Gera e retorna a resposta HTTP com o CSV da análise ABC da gôndola.
     */
    public function exportAbcToCsv(string $gondolaId): Response
    {
        $analysis = GondolaAnalysis::getLatestAbcAnalysis($gondolaId);

        if (! $analysis) {
            abort(404, 'Análise ABC não encontrada para esta gôndola.');
        }

        $results = $analysis->data['results'] ?? [];

        $headers = [
            'EAN',
            'Produto',
            'Categoria',
            'Media Ponderada',
            '% Individual',
            '% Acumulado',
            'Classe ABC',
            'Ranking',
            'Retirar do Mix',
            'Status',
            'Motivo',
        ];

        $rows = array_map(fn ($item) => [
            $item['ean'] ?? '',
            $item['product_name'] ?? '',
            $item['category_name'] ?? '',
            number_format((float) ($item['media_ponderada'] ?? 0), 2, ',', '.'),
            number_format((float) ($item['percentual_individual'] ?? 0), 2, ',', '.').'%',
            number_format((float) ($item['percentual_acumulado'] ?? 0), 2, ',', '.').'%',
            $item['classificacao'] ?? '',
            (string) ($item['ranking'] ?? ''),
            ($item['retirar_do_mix'] ?? false) ? 'Sim' : 'Não',
            $item['status']['status'] ?? '',
            $item['status']['motivo'] ?? '',
        ], $results);

        return $this->buildCsvResponse($headers, $rows, 'analise_abc');
    }

    /**
     * Gera e retorna a resposta HTTP com o CSV da análise de estoque alvo da gôndola.
     */
    public function exportStockToCsv(string $gondolaId): Response
    {
        $analysis = GondolaAnalysis::getLatestStockAnalysis($gondolaId);

        if (! $analysis) {
            abort(404, 'Análise de Estoque Alvo não encontrada para esta gôndola.');
        }

        $results = $analysis->data['results'] ?? [];

        $headers = [
            'EAN',
            'Produto',
            'Classe ABC',
            'Demanda Media',
            'Desvio Padrao',
            'Cobertura (dias)',
            'Nivel de Servico',
            'Z-score',
            'Estoque Seguranca',
            'Estoque Minimo',
            'Estoque Alvo',
            'Estoque Atual',
            'Permite Frentes',
            'Alerta Variabilidade',
        ];

        $rows = array_map(fn ($item) => [
            $item['ean'] ?? '',
            $item['product_name'] ?? '',
            $item['classificacao'] ?? '',
            number_format((float) ($item['demanda_media'] ?? 0), 2, ',', '.'),
            number_format((float) ($item['desvio_padrao'] ?? 0), 2, ',', '.'),
            (string) ($item['cobertura_dias'] ?? ''),
            number_format((float) ($item['nivel_servico'] ?? 0), 1, ',', '.'),
            number_format((float) ($item['z_score'] ?? 0), 3, ',', '.'),
            (string) ($item['estoque_seguranca'] ?? ''),
            (string) ($item['estoque_minimo'] ?? ''),
            (string) ($item['estoque_alvo'] ?? ''),
            (string) ($item['estoque_atual'] ?? ''),
            $item['permite_frentes'] ?? '',
            ($item['alerta_variabilidade'] ?? false) ? 'Sim' : 'Não',
        ], $results);

        return $this->buildCsvResponse($headers, $rows, 'estoque_alvo');
    }

    /**
     * Constrói a resposta HTTP com cabeçalhos de download para o arquivo CSV.
     */
    private function buildCsvResponse(array $headers, array $rows, string $filenamePrefix): Response
    {
        $csv = $this->buildCsvContent($headers, $rows);
        $timestamp = now()->format('Y-m-d_H-i');

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filenamePrefix}_{$timestamp}.csv\"",
        ]);
    }

    /**
     * Gera o conteúdo CSV com BOM UTF-8 para compatibilidade com Excel.
     * Usa ponto-e-vírgula como separador (padrão PT-BR).
     */
    private function buildCsvContent(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        // BOM UTF-8: garante que o Excel abre o arquivo com a codificação correta
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
