/**
 * Actions Wayfinder (manuais) para o GondolaReportController.
 *
 * Este arquivo é mantido à mão — o gerador do Wayfinder não é executado neste
 * projeto, então escrevemos o mapeamento das rotas diretamente. Mantém a mesma
 * forma das actions geradas: cada método é uma função `action(gondola)` que
 * devolve `{ url, method }`, e também expõe `action.url(gondola)`.
 *
 * Rotas (routes/export.php, grupo `export/gondola-report`):
 *   GET export/gondola-report/{gondola}/excel    -> generateExcelReport   (reposição)
 *   GET export/gondola-report/{gondola}/pdf       -> generatePdfReport     (reposição)
 *   GET export/gondola-report/{gondola}/compra    -> generateCompraReport  (compra)
 *   GET export/gondola-report/{gondola}/dimensao  -> generateDimensaoReport
 *   GET export/gondola-report/{gondola}/image     -> generateImageReport
 *   GET export/gondola-report/{gondola}/data      -> getReportData
 *
 * @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Export\GondolaReportController
 */

type GondolaArg = { gondola: string | number } | [gondola: string | number] | string | number;

interface RouteDefinition {
    url: string;
    method: 'get';
}

/**
 * Normaliza o argumento da gôndola para a string do id.
 */
function resolveGondola(args: GondolaArg): string {
    if (typeof args === 'string' || typeof args === 'number') {
        return args.toString();
    }

    if (Array.isArray(args)) {
        return args[0].toString();
    }

    return args.gondola.toString();
}

/**
 * Cria um par de helpers (callable + `.url`) para um endpoint do relatório.
 */
function makeReportAction(path: string) {
    const url = (args: GondolaArg): string =>
        `/export/gondola-report/${resolveGondola(args)}/${path}`;

    const action = (args: GondolaArg): RouteDefinition => ({
        url: url(args),
        method: 'get',
    });

    action.url = url;
    action.get = action;

    return action;
}

/** Relatório de Reposição (Excel). */
export const generateExcelReport = makeReportAction('excel');

/** Relatório de Reposição (PDF). */
export const generatePdfReport = makeReportAction('pdf');

/** Relatório de Compra (Excel). */
export const generateCompraReport = makeReportAction('compra');

/** Relatório de Dimensão (Excel). */
export const generateDimensaoReport = makeReportAction('dimensao');

/** Relatório de Imagem (Excel). */
export const generateImageReport = makeReportAction('image');

/** Dados brutos do relatório (JSON). */
export const getReportData = makeReportAction('data');

const GondolaReportController = {
    generateExcelReport,
    generatePdfReport,
    generateCompraReport,
    generateDimensaoReport,
    generateImageReport,
    getReportData,
};

export default GondolaReportController;
