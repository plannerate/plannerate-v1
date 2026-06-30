/**
 * Helpers de URL para as rotas server-side de PDF do planograma (dompdf).
 *
 * Escritos manualmente (sem Wayfinder) porque o gerador não é rodado neste
 * projeto. As rotas vivem em `export/gondola-report/{gondola}/...`
 * (ver routes/export.php do pacote).
 */

const BASE = '/export/gondola-report';

/**
 * Monta uma URL com query string, omitindo parâmetros vazios/indefinidos.
 */
function buildUrl(
    path: string,
    params: Record<string, string | number | undefined>,
): string {
    const query = Object.entries(params)
        .filter(([, value]) => value !== undefined && value !== '')
        .map(
            ([key, value]) =>
                `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`,
        )
        .join('&');

    return query ? `${path}?${query}` : path;
}

/**
 * URL do PDF "em linha" (A4 landscape). `download=1` força o download.
 */
export function planogramRowPdfUrl(
    gondolaId: string,
    opts: { download?: boolean } = {},
): string {
    return buildUrl(`${BASE}/${gondolaId}/planogram-pdf`, {
        download: opts.download ? 1 : undefined,
    });
}

/**
 * URL do PDF "por módulo" (A4 portrait, 1 página por módulo). Aceita filtro de
 * módulos via `sectionIds` (CSV) e `download=1` para forçar o download.
 */
export function planogramModulesPdfUrl(
    gondolaId: string,
    opts: { download?: boolean; sectionIds?: string[] } = {},
): string {
    return buildUrl(`${BASE}/${gondolaId}/planogram-modules-pdf`, {
        download: opts.download ? 1 : undefined,
        sectionIds:
            opts.sectionIds && opts.sectionIds.length
                ? opts.sectionIds.join(',')
                : undefined,
    });
}
