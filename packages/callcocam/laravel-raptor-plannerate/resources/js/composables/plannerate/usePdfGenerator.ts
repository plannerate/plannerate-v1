import html2canvas from 'html2canvas-pro'
import jsPDF from 'jspdf'
import { ref } from 'vue'

const MAX_CAPTURE_SCALE = 5
const MAX_CANVAS_SIDE = 16000
const MAX_CANVAS_PIXELS = 120_000_000
const CAPTURE_DELAY_MS = 120
const IMAGE_READY_TIMEOUT_MS = 12000

export interface PdfGeneratorOptions {
  filename?: string
  orientation?: 'portrait' | 'landscape'
  format?: 'a4' | 'a3' | 'letter'
  marginTop?: number
  marginSides?: number
  marginBottom?: number
  scale?: number
  quality?: number
  backgroundColor?: string
}

export interface LayoutOptions {
  mode: 'single' | 'multiple' // single = uma página, multiple = múltiplas páginas
  selector?: string // seletor CSS para os elementos
  containerSelector?: string // seletor do container
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function getElementRenderSize(element: HTMLElement): { width: number; height: number } {
  const width = Math.max(element.scrollWidth, element.offsetWidth, 1)
  const height = Math.max(element.scrollHeight, element.offsetHeight, 1)

  return { width, height }
}

function computeSafeCaptureScale(element: HTMLElement, requestedScale: number): number {
  const { width, height } = getElementRenderSize(element)
  const devicePixelRatio = typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1

  let safeScale = Math.max(requestedScale, devicePixelRatio)
  safeScale = Math.min(safeScale, MAX_CAPTURE_SCALE)

  const sideSafeScale = Math.min(MAX_CANVAS_SIDE / width, MAX_CANVAS_SIDE / height)
  const areaSafeScale = Math.sqrt(MAX_CANVAS_PIXELS / (width * height))
  const maxAllowedScale = Math.min(sideSafeScale, areaSafeScale)

  if (Number.isFinite(maxAllowedScale)) {
    safeScale = Math.min(safeScale, maxAllowedScale)
  }

  return Number(Math.max(1, safeScale).toFixed(2))
}

async function waitForFontsReady(): Promise<void> {
  if (typeof document === 'undefined') {
return
}

  const fontSet = (document as Document & { fonts?: { ready?: Promise<unknown> } }).fonts

  if (!fontSet?.ready) {
return
}

  try {
    await fontSet.ready
  } catch {
    // Ignora falhas de fonte para não bloquear exportação
  }
}

async function waitForImageReady(image: HTMLImageElement, timeoutMs: number): Promise<void> {
  // complete=true cobre imagens carregadas e também erros já resolvidos pelo browser.
  // Nesse caso, não bloqueamos a exportação esperando novos eventos.
  if (image.complete) {
    return
  }

  await new Promise<void>((resolve) => {
    let settled = false

    const finish = () => {
      if (settled) {
return
}

      settled = true
      image.removeEventListener('load', finish)
      image.removeEventListener('error', finish)
      resolve()
    }

    image.addEventListener('load', finish, { once: true })
    image.addEventListener('error', finish, { once: true })
    setTimeout(finish, timeoutMs)
  })

  if (typeof image.decode === 'function') {
    try {
      await image.decode()
    } catch {
      // Ignora decode falho e segue com fallback do browser
    }
  }
}

async function waitForImagesReady(element: HTMLElement, timeoutMs = IMAGE_READY_TIMEOUT_MS): Promise<void> {
  const images = Array.from(element.querySelectorAll('img'))

  if (images.length === 0) {
    return
  }

  await Promise.all(images.map((image) => waitForImageReady(image, timeoutMs)))
}

async function captureElementAsCanvas(
  element: HTMLElement,
  options: PdfGeneratorOptions = {}
): Promise<HTMLCanvasElement> {
  const {
    scale = 2,
    backgroundColor = '#ffffff',
  } = options

  await waitForFontsReady()
  await waitForImagesReady(element)
  await sleep(CAPTURE_DELAY_MS)

  const safeScale = computeSafeCaptureScale(element, scale)

  return html2canvas(element, {
    scale: safeScale,
    useCORS: true,
    allowTaint: false,
    backgroundColor,
    logging: false,
    imageTimeout: IMAGE_READY_TIMEOUT_MS,
  })
}

export function usePdfGenerator() {
  const isGenerating = ref(false)

  /**
   * Gera PDF de uma única página (landscape ou portrait)
   */
  async function generateSinglePagePdf(
    containerElement: HTMLElement,
    options: PdfGeneratorOptions = {}
  ): Promise<jsPDF> {
    const {
      orientation = 'landscape',
      format = 'a4',
      marginTop = 10,
      marginSides = 10,
      marginBottom = 10,
      scale = 2,
      quality = 0.95,
    } = options

    // Captura em alta resolução segura com espera de imagens/fontes
    const canvas = await captureElementAsCanvas(containerElement, {
      ...options,
      scale,
    })

    const imgData = canvas.toDataURL('image/png', quality)
    const imgWidth = canvas.width
    const imgHeight = canvas.height

    // Cria PDF
    const pdf = new jsPDF({
      orientation,
      unit: 'mm',
      format,
    })

    const pdfWidth = pdf.internal.pageSize.getWidth()
    const pdfHeight = pdf.internal.pageSize.getHeight()

    const availableWidth = pdfWidth - marginSides * 2
    const availableHeight = pdfHeight - marginTop - marginBottom

    // Calcula proporção mantendo aspect ratio
    const ratio = Math.min(availableWidth / imgWidth, availableHeight / imgHeight)

    const finalWidth = imgWidth * ratio
    const finalHeight = imgHeight * ratio

    // Centraliza
    const imgX = marginSides + (availableWidth - finalWidth) / 2
    const imgY = marginTop + (availableHeight - finalHeight) / 2

    pdf.addImage(imgData, 'PNG', imgX, imgY, finalWidth, finalHeight)

    return pdf
  }

  /**
   * Gera PDF com múltiplas páginas (um elemento por página)
   */
  async function generateMultiPagePdf(
    elements: HTMLElement[],
    options: PdfGeneratorOptions = {}
  ): Promise<jsPDF> {
    const {
      orientation = 'portrait',
      format = 'a4',
      marginTop = 20,
      marginSides = 10,
      marginBottom = 10,
      scale = 2,
      quality = 0.95,
    } = options

    const pdf = new jsPDF({
      orientation,
      unit: 'mm',
      format,
    })

    const pdfWidth = pdf.internal.pageSize.getWidth()
    const pdfHeight = pdf.internal.pageSize.getHeight()

    const availableWidth = pdfWidth - marginSides * 2
    const availableHeight = pdfHeight - marginTop - marginBottom

    for (let i = 0; i < elements.length; i++) {
      const element = elements[i]

      // Captura em alta resolução segura com espera de imagens/fontes
      const canvas = await captureElementAsCanvas(element, {
        ...options,
        scale,
      })

      const imgData = canvas.toDataURL('image/png', quality)
      const imgWidth = canvas.width
      const imgHeight = canvas.height

      // Calcula proporção mantendo aspect ratio
      const ratio = Math.min(availableWidth / imgWidth, availableHeight / imgHeight)

      const finalWidth = imgWidth * ratio
      const finalHeight = imgHeight * ratio

      // Centraliza horizontalmente e alinha ao topo
      const imgX = marginSides + (availableWidth - finalWidth) / 2
      const imgY = marginTop

      // Adiciona nova página se não for a primeira
      if (i > 0) {
        pdf.addPage()
      }

      pdf.addImage(imgData, 'PNG', imgX, imgY, finalWidth, finalHeight)
    }

    return pdf
  }

  /**
   * Gera PDF automaticamente baseado no layout
   */
  async function generatePdf(
    layoutOptions: LayoutOptions,
    pdfOptions: PdfGeneratorOptions = {},
    autoDownload = false,
    specificElements?: HTMLElement[] // Permite passar elementos específicos
  ): Promise<void> {
    isGenerating.value = true

    try {
      let pdf: jsPDF

      if (layoutOptions.mode === 'single') {
        // Modo single: captura todo o container em uma página
        const container = layoutOptions.containerSelector
          ? document.querySelector<HTMLElement>(layoutOptions.containerSelector)
          : document.querySelector<HTMLElement>('[data-module-section]')
              ?.parentElement

        if (!container) {
          throw new Error('Container não encontrado!')
        }

        pdf = await generateSinglePagePdf(container, pdfOptions)
      } else {
        // Modo multiple: um elemento por página
        let elements: HTMLElement[]
        
        if (specificElements && specificElements.length > 0) {
          // Usa elementos específicos passados como parâmetro
          elements = specificElements
        } else {
          // Busca todos os elementos pelo seletor
          const selector = layoutOptions.selector || '[data-module-section]'
          elements = Array.from(
            document.querySelectorAll<HTMLElement>(selector)
          )
        }

        if (elements.length === 0) {
          throw new Error('Nenhum elemento encontrado!')
        }

        pdf = await generateMultiPagePdf(elements, pdfOptions)
      }

      // Gera o filename
      const timestamp = new Date().toISOString().split('T')[0]
      const filename = pdfOptions.filename || `document_${timestamp}.pdf`

      if (autoDownload) {
        pdf.save(filename)
      } else {
        window.open(pdf.output('bloburl'), '_blank')
      }
    } catch (error) {
      console.error('Erro ao gerar PDF:', error)

      throw error
    } finally {
      isGenerating.value = false
    }
  }

  return {
    isGenerating,
    generatePdf,
    generateSinglePagePdf,
    generateMultiPagePdf,
  }
}
