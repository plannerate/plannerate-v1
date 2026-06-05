import type { AbcResult } from '@/components/plannerate/analysis/abc/types';
import type { PaperResult } from '@/components/plannerate/analysis/paper/types';
import type { TargetStockResult } from '@/components/plannerate/analysis/target-stock/types';

/**
 * Composable para exportar relatórios de análise para CSV diretamente no browser.
 *
 * Exporta os dados já carregados no componente (dados filtrados/exibidos),
 * sem necessidade de nova requisição ao servidor. Usa BOM UTF-8 para garantir
 * compatibilidade com Excel e outros programas no padrão PT-BR.
 */
export function useAnalysisExport() {
    /**
     * Gera e dispara o download do CSV da análise ABC a partir dos resultados exibidos.
     *
     * @param results - Array de resultados ABC (normalmente os filtrados/exibidos na tela)
     * @param filename - Prefixo do nome do arquivo; padrão: 'analise_abc'
     */
    function exportAbcToCsv(results: AbcResult[], filename: string = 'analise_abc'): void {
        const headers = [
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

        const rows = results.map((item) => [
            item.ean,
            item.product_name,
            item.category_name ?? '',
            item.media_ponderada.toFixed(2).replace('.', ','),
            `${item.percentual_individual.toFixed(2).replace('.', ',')}%`,
            `${item.percentual_acumulado.toFixed(2).replace('.', ',')}%`,
            item.classificacao,
            String(item.ranking),
            item.retirar_do_mix ? 'Sim' : 'Não',
            item.status?.status ?? '',
            item.status?.motivo ?? '',
        ]);

        downloadCsv(headers, rows, filename);
    }

    /**
     * Gera e dispara o download do CSV da análise de estoque alvo a partir dos resultados exibidos.
     *
     * @param results - Array de resultados de estoque alvo (normalmente os filtrados/exibidos na tela)
     * @param filename - Prefixo do nome do arquivo; padrão: 'estoque_alvo'
     */
    function exportStockToCsv(results: TargetStockResult[], filename: string = 'estoque_alvo'): void {
        const headers = [
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

        const rows = results.map((item) => [
            item.ean,
            item.product_name,
            item.classificacao,
            item.demanda_media.toFixed(2).replace('.', ','),
            item.desvio_padrao.toFixed(2).replace('.', ','),
            String(item.cobertura_dias),
            item.nivel_servico.toFixed(1).replace('.', ','),
            item.z_score.toFixed(3).replace('.', ','),
            String(item.estoque_seguranca),
            String(item.estoque_minimo),
            String(item.estoque_alvo),
            String(item.estoque_atual),
            item.permite_frentes,
            item.alerta_variabilidade ? 'Sim' : 'Não',
        ]);

        downloadCsv(headers, rows, filename);
    }

    /**
     * Constrói o conteúdo CSV e dispara o download no browser.
     * Usa ponto-e-vírgula como separador (padrão PT-BR) e BOM UTF-8.
     *
     * @param headers - Linha de cabeçalho do CSV
     * @param rows    - Linhas de dados
     * @param filenamePrefix - Prefixo do arquivo; a data é adicionada automaticamente
     */
    /**
     * Gera e dispara o download do CSV da Análise de Papel a partir dos resultados exibidos.
     *
     * @param results  - Array de resultados da Análise de Papel (normalmente os filtrados/exibidos na tela)
     * @param filename - Prefixo do nome do arquivo; padrão: 'analise_papel'
     */
    function exportPaperToCsv(results: PaperResult[], filename: string = 'analise_papel'): void {
        const roleLabels: Record<string, string> = {
            leader:  'Lider',
            anchor:  'Ancora',
            rising:  'Ascendente',
            lagging: 'Retardatario',
        };

        const headers = [
            'EAN',
            'Produto',
            'Categoria',
            'Papel Estrategico',
            'Market Share (%)',
            'Crescimento (%)',
            'Valor Atual (R$)',
            'Valor Anterior (R$)',
            'Limiar de Share (%)',
        ];

        const rows = results.map((item) => [
            item.ean,
            item.product_name,
            item.category_name ?? '',
            roleLabels[item.role] ?? item.role,
            item.market_share.toFixed(2).replace('.', ','),
            item.growth_rate.toFixed(2).replace('.', ','),
            item.total_value_current.toFixed(2).replace('.', ','),
            item.total_value_previous.toFixed(2).replace('.', ','),
            item.share_threshold.toFixed(2).replace('.', ','),
        ]);

        downloadCsv(headers, rows, filename);
    }

    function downloadCsv(headers: string[], rows: string[][], filenamePrefix: string): void {
        const separator = ';';

        /** Escapa células que contêm o separador, aspas ou quebras de linha */
        const escapeCell = (cell: string): string => {
            if (cell.includes(separator) || cell.includes('"') || cell.includes('\n')) {
                return `"${cell.replace(/"/g, '""')}"`;
            }
            return cell;
        };

        const csvLines = [
            headers.map(escapeCell).join(separator),
            ...rows.map((row) => row.map(escapeCell).join(separator)),
        ];

        // BOM UTF-8: necessário para o Excel abrir com codificação correta
        const bom = '﻿';
        const csvContent = bom + csvLines.join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);

        const link = document.createElement('a');
        const date = new Date().toISOString().slice(0, 10);
        link.href = url;
        link.download = `${filenamePrefix}_${date}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }

    return { exportAbcToCsv, exportStockToCsv, exportPaperToCsv };
}
