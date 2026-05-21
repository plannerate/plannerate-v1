import jsPDF from 'jspdf';
import { ref } from 'vue';
import { useT } from '@/composables/useT';
import { captureElementAsCanvas } from './export/useCanvasCapture';

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
     * Gera PDF de uma única página (landscape ou portrait)
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

        // Captura em alta resolução segura com espera de imagens/fontes
        const canvas = await captureElementAsCanvas(containerElement, {
            ...options,
            scale,
        });

        const imgData = canvas.toDataURL('image/jpeg', quality);
        const imgWidth = canvas.width;
        const imgHeight = canvas.height;

        // Cria PDF
        const pdf = new jsPDF({
            orientation,
            unit: 'mm',
            format,
        });

        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();

        const availableWidth = pdfWidth - marginSides * 2;
        const availableHeight = pdfHeight - marginTop - marginBottom;

        // Calcula proporção mantendo aspect ratio
        const ratio = Math.min(
            availableWidth / imgWidth,
            availableHeight / imgHeight,
        );

        const finalWidth = imgWidth * ratio;
        const finalHeight = imgHeight * ratio;

        // Centraliza
        const imgX = marginSides + (availableWidth - finalWidth) / 2;
        const imgY = marginTop + (availableHeight - finalHeight) / 2;

        pdf.addImage(imgData, 'JPEG', imgX, imgY, finalWidth, finalHeight);

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
