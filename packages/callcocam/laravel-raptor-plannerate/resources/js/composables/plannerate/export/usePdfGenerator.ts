import jsPDF from 'jspdf';
import { ref } from 'vue';
import { useT } from '@/composables/useT';
import { captureElementAsCanvas } from './useCanvasCapture';

export interface PdfGeneratorOptions {
    filename?: string;
    orientation?: 'portrait' | 'landscape';
    format?: 'a4' | 'a3' | 'letter';
    marginTop?: number;
    marginSides?: number;
    marginBottom?: number;
    scale?: number;
    quality?: number;
    backgroundColor?: string;
}

export interface LayoutOptions {
    mode: 'single' | 'multiple'; // single = uma página, multiple = múltiplas páginas
    selector?: string; // seletor CSS para os elementos
    containerSelector?: string; // seletor do container
}

export function usePdfGenerator() {
    const { t } = useT();
    const isGenerating = ref(false);

    /**
     * Gera PDF de uma única página (landscape ou portrait).
     *
     * Quando o container tem módulos (`[data-module-section]`), monta a página
     * por TILING: captura cada faixa isoladamente (cabeçalho, indicador de
     * fluxo, cada módulo e rodapé — canvas pequeno, poucas imagens, sempre
     * confiável) e reposiciona em mm na página A4. Isso evita a captura única e
     * larga da gôndola inteira, que o html2canvas renderiza mal (descarta
     * imagens dos produtos e desloca elementos posicionados de forma absoluta).
     *
     * Sem módulos, mantém a captura única tradicional do container.
     */
    async function generateSinglePagePdf(
        containerElement: HTMLElement,
        options: PdfGeneratorOptions = {},
    ): Promise<jsPDF> {
        const {
            orientation = 'landscape',
            format = 'a4',
            marginTop = 10,
            marginSides = 10,
            marginBottom = 10,
            scale = 2,
            quality = 0.95,
        } = options;

        const pdf = new jsPDF({ orientation, unit: 'mm', format });
        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();
        const availableWidth = pdfWidth - marginSides * 2;
        const availableHeight = pdfHeight - marginTop - marginBottom;

        const moduleElements = Array.from(
            containerElement.querySelectorAll<HTMLElement>(
                '[data-module-section]',
            ),
        );

        // --- Caminho tradicional: sem módulos, captura única do container. ---
        if (moduleElements.length === 0) {
            const canvas = await captureElementAsCanvas(containerElement, {
                ...options,
                scale,
            });

            const ratio = Math.min(
                availableWidth / canvas.width,
                availableHeight / canvas.height,
            );
            const finalWidth = canvas.width * ratio;
            const finalHeight = canvas.height * ratio;
            const imgX = marginSides + (availableWidth - finalWidth) / 2;
            const imgY = marginTop + (availableHeight - finalHeight) / 2;

            pdf.addImage(
                canvas.toDataURL('image/jpeg', quality),
                'JPEG',
                imgX,
                imgY,
                finalWidth,
                finalHeight,
            );

            return pdf;
        }

        // --- Caminho por tiling: captura cada faixa isoladamente. ---
        const capture = (element: HTMLElement) =>
            captureElementAsCanvas(element, { ...options, scale });

        const headerElement =
            containerElement.querySelector<HTMLElement>('[data-pdf-header]');
        const flowElement =
            containerElement.querySelector<HTMLElement>('[data-pdf-flow]');
        const footerElement =
            containerElement.querySelector<HTMLElement>('[data-pdf-footer]');

        const headerCanvas = headerElement ? await capture(headerElement) : null;
        const flowCanvas = flowElement ? await capture(flowElement) : null;
        const footerCanvas = footerElement ? await capture(footerElement) : null;

        const moduleCanvases: HTMLCanvasElement[] = [];

        for (const element of moduleElements) {
            moduleCanvases.push(await capture(element));
        }

        // Altura em mm de uma faixa de largura cheia (mantém o aspect ratio).
        const fullWidthBandHeight = (canvas: HTMLCanvasElement | null): number =>
            canvas ? availableWidth * (canvas.height / canvas.width) : 0;

        const GAP = 2; // espaçamento vertical (mm) entre as faixas
        const headerHeight = fullWidthBandHeight(headerCanvas);
        const flowHeight = fullWidthBandHeight(flowCanvas);
        const footerHeight = fullWidthBandHeight(footerCanvas);

        // Espaço vertical restante para a fila de módulos.
        const modulesBandHeight = Math.max(
            availableHeight * 0.35,
            availableHeight - headerHeight - flowHeight - footerHeight - GAP * 3,
        );

        // Escala uniforme (mm por pixel) que faz a fila inteira caber tanto em
        // largura quanto na altura disponível — preserva os tamanhos relativos
        // dos módulos e permite alinhar todos pela base (chão da gôndola).
        const totalModulesPx = moduleCanvases.reduce(
            (sum, canvas) => sum + canvas.width,
            0,
        );
        const tallestModulePx = moduleCanvases.reduce(
            (max, canvas) => Math.max(max, canvas.height),
            1,
        );
        const mmPerPixel = Math.min(
            availableWidth / totalModulesPx,
            modulesBandHeight / tallestModulePx,
        );

        // Desenha cabeçalho e indicador de fluxo no topo (largura cheia).
        let y = marginTop;

        if (headerCanvas) {
            pdf.addImage(
                headerCanvas.toDataURL('image/jpeg', quality),
                'JPEG',
                marginSides,
                y,
                availableWidth,
                headerHeight,
            );
            y += headerHeight + GAP;
        }

        if (flowCanvas) {
            pdf.addImage(
                flowCanvas.toDataURL('image/jpeg', quality),
                'JPEG',
                marginSides,
                y,
                availableWidth,
                flowHeight,
            );
            y += flowHeight + GAP;
        }

        // Fila de módulos: alinhada pela base, centralizada horizontalmente.
        const modulesTotalWidth = totalModulesPx * mmPerPixel;
        const modulesBottom = y + modulesBandHeight;
        let x = marginSides + (availableWidth - modulesTotalWidth) / 2;

        for (const canvas of moduleCanvases) {
            const width = canvas.width * mmPerPixel;
            const height = canvas.height * mmPerPixel;

            pdf.addImage(
                canvas.toDataURL('image/jpeg', quality),
                'JPEG',
                x,
                modulesBottom - height,
                width,
                height,
            );
            x += width;
        }

        // Rodapé fixo na base da página (largura cheia).
        if (footerCanvas) {
            pdf.addImage(
                footerCanvas.toDataURL('image/jpeg', quality),
                'JPEG',
                marginSides,
                pdfHeight - marginBottom - footerHeight,
                availableWidth,
                footerHeight,
            );
        }

        return pdf;
    }

    /**
     * Gera PDF com múltiplas páginas (um elemento por página)
     */
    async function generateMultiPagePdf(
        elements: HTMLElement[],
        options: PdfGeneratorOptions = {},
    ): Promise<jsPDF> {
        const {
            orientation = 'portrait',
            format = 'a4',
            marginTop = 20,
            marginSides = 10,
            marginBottom = 10,
            scale = 2,
            quality = 0.95,
        } = options;

        const pdf = new jsPDF({
            orientation,
            unit: 'mm',
            format,
        });

        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();

        const availableWidth = pdfWidth - marginSides * 2;
        const availableHeight = pdfHeight - marginTop - marginBottom;

        for (let i = 0; i < elements.length; i++) {
            const element = elements[i];

            // Captura em alta resolução segura com espera de imagens/fontes
            const canvas = await captureElementAsCanvas(element, {
                ...options,
                scale,
            });

            const imgData = canvas.toDataURL('image/jpeg', quality);
            const imgWidth = canvas.width;
            const imgHeight = canvas.height;

            // Calcula proporção mantendo aspect ratio
            const ratio = Math.min(
                availableWidth / imgWidth,
                availableHeight / imgHeight,
            );

            const finalWidth = imgWidth * ratio;
            const finalHeight = imgHeight * ratio;

            // Centraliza horizontalmente e alinha ao topo
            const imgX = marginSides + (availableWidth - finalWidth) / 2;
            const imgY = marginTop;

            // Adiciona nova página se não for a primeira
            if (i > 0) {
                pdf.addPage();
            }

            pdf.addImage(imgData, 'JPEG', imgX, imgY, finalWidth, finalHeight);
        }

        return pdf;
    }

    /**
     * Gera PDF automaticamente baseado no layout
     */
    async function generatePdf(
        layoutOptions: LayoutOptions,
        pdfOptions: PdfGeneratorOptions = {},
        autoDownload = false,
        specificElements?: HTMLElement[], // Permite passar elementos específicos
    ): Promise<void> {
        isGenerating.value = true;

        try {
            let pdf: jsPDF;

            if (layoutOptions.mode === 'single') {
                // Modo single: captura todo o container em uma página
                const container = layoutOptions.containerSelector
                    ? document.querySelector<HTMLElement>(
                          layoutOptions.containerSelector,
                      )
                    : document.querySelector<HTMLElement>(
                          '[data-module-section]',
                      )?.parentElement;

                if (!container) {
                    throw new Error(
                        t(
                            'plannerate.composables.pdf_generator.container_not_found',
                        ),
                    );
                }

                pdf = await generateSinglePagePdf(container, pdfOptions);
            } else {
                // Modo multiple: um elemento por página
                let elements: HTMLElement[];

                if (specificElements && specificElements.length > 0) {
                    // Usa elementos específicos passados como parâmetro
                    elements = specificElements;
                } else {
                    // Busca todos os elementos pelo seletor
                    const selector =
                        layoutOptions.selector || '[data-module-section]';
                    elements = Array.from(
                        document.querySelectorAll<HTMLElement>(selector),
                    );
                }

                if (elements.length === 0) {
                    throw new Error(
                        t(
                            'plannerate.composables.pdf_generator.no_elements_found',
                        ),
                    );
                }

                pdf = await generateMultiPagePdf(elements, pdfOptions);
            }

            // Gera o filename
            const timestamp = new Date().toISOString().split('T')[0];
            const filename = pdfOptions.filename || `document_${timestamp}.pdf`;

            if (autoDownload) {
                pdf.save(filename);
            } else {
                const blob = pdf.output('blob');
                const blobUrl = URL.createObjectURL(blob);
                window.open(blobUrl, '_blank');
                setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000);
            }
        } catch (error) {
            console.error('Erro ao gerar PDF:', error);

            throw error;
        } finally {
            isGenerating.value = false;
        }
    }

    return {
        isGenerating,
        generatePdf,
        generateSinglePagePdf,
        generateMultiPagePdf,
    };
}
