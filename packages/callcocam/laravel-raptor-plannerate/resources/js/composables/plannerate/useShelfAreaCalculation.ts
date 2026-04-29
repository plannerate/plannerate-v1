import type { Shelf } from '@/types/planogram'

interface ShelfAreaOptions {
  shelf: Shelf
  previousShelf?: Shelf
  scale: number
  minSpacing?: number
  minAreaHeight?: number
}

interface ShelfAreaResult {
  areaStartCm: number
  areaHeightCm: number
  areaEndCm: number
}

/**
 * Calcula a área de uma prateleira considerando:
 * - Prateleira anterior (se houver)
 * - Espaçamento mínimo entre prateleiras
 * - Altura mínima da área para interação
 */
export function useShelfAreaCalculation() {
  const calculateShelfArea = ({
    shelf,
    previousShelf,
    scale, // Mantido por compatibilidade de interface (não usado atualmente)
    minSpacing = 2,
    minAreaHeight = 50,
  }: ShelfAreaOptions): ShelfAreaResult => {
    void scale; // Suprime warning de variável não usada
    
    const shelfPosition = shelf.shelf_position
    const shelfHeightCm = shelf.shelf_height

    // Início da área (após a prateleira anterior ou do chão)
    let areaStartCm = 0

    if (previousShelf) {
      // Área começa após a prateleira anterior + altura dela
      const previousEnd = previousShelf.shelf_position + previousShelf.shelf_height
      areaStartCm = previousEnd

      // Garante que não ultrapasse a prateleira atual
      // Se a prateleira atual está muito próxima, limita o início da área
      const maxStart = shelfPosition - minSpacing

      if (areaStartCm > maxStart) {
        areaStartCm = Math.max(shelfHeightCm, maxStart)
      }
    } else {
      // Primeira prateleira: área começa com espaçamento do topo
      // Permite área maior para melhor clicabilidade
      areaStartCm = Math.max(shelfHeightCm, shelfPosition - minAreaHeight)
    }

    // Fim da área (posição desta prateleira + altura dela)
    const areaEndCm = shelfPosition + shelfHeightCm

    // Altura total da área
    let areaHeightCm = areaEndCm - areaStartCm

    // Garante altura mínima para área clicável
    if (areaHeightCm < minAreaHeight) {
      // Ajusta o início para garantir altura mínima
      // Mas respeita o limite: não pode começar acima da prateleira anterior
      const newStart = Math.max(0, areaEndCm - minAreaHeight)

      // Se há prateleira anterior, não pode ultrapassar ela
      if (previousShelf) {
        const previousEnd = previousShelf.shelf_position + previousShelf.shelf_height
        areaStartCm = Math.max(previousEnd, newStart)
      } else {
        areaStartCm = newStart
      }

      areaHeightCm = areaEndCm - areaStartCm
    }

    return {
      areaStartCm,
      areaHeightCm,
      areaEndCm,
    }
  }

  return {
    calculateShelfArea,
  }
}
