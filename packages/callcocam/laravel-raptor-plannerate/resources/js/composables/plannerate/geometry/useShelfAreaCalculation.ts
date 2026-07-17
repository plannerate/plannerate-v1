import type { Shelf } from '@/types/planogram';

interface ShelfAreaOptions {
    shelf: Shelf;
    previousShelf?: Shelf;
    scale: number;
    minSpacing?: number;
    minAreaHeight?: number;
}

/**
 * Quanto a área da prateleira do TOPO pode crescer ACIMA do topo da seção (cm).
 * A prateleira de cima não tem prateleira acima para estender a área de drop,
 * então ela avança para o espaço vazio acima da gôndola — mas de forma limitada,
 * para não capturar hover/clique numa faixa grande acima do planograma.
 */
const MAX_TOP_OVERSHOOT_CM = 30;

interface ShelfAreaResult {
    areaStartCm: number;
    areaHeightCm: number;
    areaEndCm: number;
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

        const shelfPosition = shelf.shelf_position;
        const shelfHeightCm = shelf.shelf_height;

        // Início da área (após a prateleira anterior ou do chão)
        let areaStartCm = 0;

        if (previousShelf) {
            // Área começa após a prateleira anterior + altura dela
            const previousEnd =
                previousShelf.shelf_position + previousShelf.shelf_height;
            areaStartCm = previousEnd;

            // Garante que não ultrapasse a prateleira atual
            // Se a prateleira atual está muito próxima, limita o início da área
            const maxStart = shelfPosition - minSpacing;

            if (areaStartCm > maxStart) {
                areaStartCm = Math.max(shelfHeightCm, maxStart);
            }
        } else {
            // Primeira prateleira: área começa com espaçamento do topo
            // Permite área maior para melhor clicabilidade
            areaStartCm = Math.max(
                shelfHeightCm,
                shelfPosition - minAreaHeight,
            );
        }

        // Fim da área (posição desta prateleira + altura dela)
        const areaEndCm = shelfPosition + shelfHeightCm;

        // Altura total da área
        let areaHeightCm = areaEndCm - areaStartCm;

        // Garante altura mínima para área clicável
        if (areaHeightCm < minAreaHeight) {
            const targetStart = areaEndCm - minAreaHeight;

            if (previousShelf) {
                // Não pode começar acima da prateleira anterior nem do topo (0)
                const previousEnd =
                    previousShelf.shelf_position + previousShelf.shelf_height;

                areaStartCm = Math.max(previousEnd, Math.max(0, targetStart));
            } else {
                // Prateleira do TOPO: não há nada acima para estender, então a
                // área cresce PARA CIMA além do topo da seção (start negativo),
                // ocupando o espaço vazio acima da gôndola. Isso dá uma área de
                // drop utilizável sem mover nada visível: os produtos são
                // ancorados ao FUNDO da área (areaEnd, inalterado) e a base
                // física tem posição absoluta independente de areaStart.
                // Limitado a MAX_TOP_OVERSHOOT_CM acima do topo (start = 0).
                areaStartCm = Math.max(targetStart, -MAX_TOP_OVERSHOOT_CM);
            }

            areaHeightCm = areaEndCm - areaStartCm;
        }

        return {
            areaStartCm,
            areaHeightCm,
            areaEndCm,
        };
    };

    return {
        calculateShelfArea,
    };
}
