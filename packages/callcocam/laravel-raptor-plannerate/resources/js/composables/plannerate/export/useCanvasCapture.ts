// ============================================================================
// UTILITÁRIO COMPARTILHADO DE CAPTURA DE CANVAS
// Usado por usePdfGenerator e usePlanogramEditor.exportAsImage
// ============================================================================

import html2canvas from 'html2canvas-pro';

const MAX_CAPTURE_SCALE = 5;
const MAX_CANVAS_SIDE = 16000;
const MAX_CANVAS_PIXELS = 120_000_000;
const CAPTURE_DELAY_MS = 120;
const IMAGE_READY_TIMEOUT_MS = 12000;

export interface CanvasCaptureOptions {
    scale?: number;
    backgroundColor?: string;
}

/**
 * Aguarda um intervalo em milissegundos (usado como delay antes da captura)
 */
function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Retorna as dimensões de renderização real do elemento (inclui scroll overflow)
 */
function getElementRenderSize(element: HTMLElement): { width: number; height: number } {
    const width = Math.max(element.scrollWidth, element.offsetWidth, 1);
    const height = Math.max(element.scrollHeight, element.offsetHeight, 1);

    return { width, height };
}

/**
 * Calcula o scale seguro para captura, respeitando limites de dimensão e pixels do canvas.
 * Evita erros de "canvas too large" em planogramas grandes.
 */
function computeSafeCaptureScale(element: HTMLElement, requestedScale: number): number {
    const { width, height } = getElementRenderSize(element);
    const devicePixelRatio = typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1;

    let safeScale = Math.max(requestedScale, devicePixelRatio);
    safeScale = Math.min(safeScale, MAX_CAPTURE_SCALE);

    const sideSafeScale = Math.min(MAX_CANVAS_SIDE / width, MAX_CANVAS_SIDE / height);
    const areaSafeScale = Math.sqrt(MAX_CANVAS_PIXELS / (width * height));
    const maxAllowedScale = Math.min(sideSafeScale, areaSafeScale);

    if (Number.isFinite(maxAllowedScale)) {
        safeScale = Math.min(safeScale, maxAllowedScale);
    }

    return Number(Math.max(1, safeScale).toFixed(2));
}

/**
 * Aguarda o carregamento das fontes do documento antes de capturar
 */
async function waitForFontsReady(): Promise<void> {
    if (typeof document === 'undefined') {
        return;
    }

    const fontSet = (document as Document & { fonts?: { ready?: Promise<unknown> } }).fonts;

    if (!fontSet?.ready) {
        return;
    }

    try {
        await fontSet.ready;
    } catch {
        // Ignora falhas de fonte para não bloquear exportação
    }
}

/**
 * Aguarda uma imagem individual estar carregada (com timeout de segurança)
 */
async function waitForImageReady(image: HTMLImageElement, timeoutMs: number): Promise<void> {
    // complete=true cobre imagens carregadas e também erros já resolvidos pelo browser
    if (image.complete) {
        return;
    }

    await new Promise<void>((resolve) => {
        let settled = false;

        const finish = () => {
            if (settled) {
                return;
            }

            settled = true;
            image.removeEventListener('load', finish);
            image.removeEventListener('error', finish);
            resolve();
        };

        image.addEventListener('load', finish, { once: true });
        image.addEventListener('error', finish, { once: true });
        setTimeout(finish, timeoutMs);
    });

    if (typeof image.decode === 'function') {
        try {
            await image.decode();
        } catch {
            // Ignora decode falho e segue com fallback do browser
        }
    }
}

/**
 * Aguarda todas as imagens dentro de um elemento estarem carregadas
 */
async function waitForImagesReady(
    element: HTMLElement,
    timeoutMs = IMAGE_READY_TIMEOUT_MS,
): Promise<void> {
    const images = Array.from(element.querySelectorAll('img'));

    if (images.length === 0) {
        return;
    }

    await Promise.all(images.map((image) => waitForImageReady(image, timeoutMs)));
}

/**
 * Captura um elemento HTML como HTMLCanvasElement usando html2canvas-pro.
 * Aguarda fontes e imagens antes de capturar para garantir fidelidade visual.
 * Usa html2canvas-pro que suporta oklch nativamente (sem workarounds de cor).
 */
export async function captureElementAsCanvas(
    element: HTMLElement,
    options: CanvasCaptureOptions = {},
): Promise<HTMLCanvasElement> {
    const { scale = 2, backgroundColor = '#ffffff' } = options;

    await waitForFontsReady();
    await waitForImagesReady(element);
    await sleep(CAPTURE_DELAY_MS);

    const safeScale = computeSafeCaptureScale(element, scale);

    return html2canvas(element, {
        scale: safeScale,
        useCORS: true,
        allowTaint: false,
        backgroundColor,
        logging: false,
        imageTimeout: IMAGE_READY_TIMEOUT_MS,
    });
}
