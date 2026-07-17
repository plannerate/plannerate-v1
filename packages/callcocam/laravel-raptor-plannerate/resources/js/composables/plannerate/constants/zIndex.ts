/**
 * Escala de empilhamento do CANVAS do editor de planogramas.
 *
 * Todos os valores vivem dentro do stacking context do wrapper de seções
 * (`Canvas.vue` → wrapper `relative z-0`) — números altos aqui NÃO sobem
 * acima do grid do canvas nem dos painéis laterais; a ordenação é local.
 *
 * Regras:
 * - Novos elementos do canvas devem usar estas constantes via `:style`
 *   (`:style="{ zIndex: Z.X }"`), ou — quando a classe Tailwind for estática —
 *   referenciar a constante em comentário ao lado da classe.
 * - Não introduzir valores fora da escala sem registrar aqui.
 *
 * Camada por componente:
 * - PRODUCT (20)        → Layer.vue (imagens/placeholder do produto)
 * - CREMALHEIRA (30)    → Cremalheira.vue
 * - PAPER_BADGE (30)    → Segment.vue (selo de papel, topo do segmento)
 * - DECK (40)           → Shelf.vue (tampo 3D decorativo, atrás dos produtos)
 * - SEGMENTS (50)       → Shelf.vue (container de segmentos; também o ring de
 *                         segmento selecionado e o indicador de drop/swap)
 * - STOCK (80)          → StockIndicator.vue (overlay sobre o produto)
 * - BADGES (90)         → Segment.vue (wrappers dos selos ABC/BCG)
 * - INDICATOR (91)      → ProductIndicatorBadge.vue (acima de STOCK e dos
 *                         selos — desempata o antigo empate triplo em 90)
 * - BADGE_HOVER (95)    → Segment.vue (wrapper do BCG quando o selo interno
 *                         está em hover — tooltip expandido vence os vizinhos)
 * - SHELF_DRAGGING (120)→ Shelf.vue (base física durante o arraste)
 * - SHELF_BASE (130)    → Shelf.vue (base física em repouso)
 * - ZONE (135)          → Shelf.vue (faixa lateral de zona)
 * - DROP_OVERLAY (140)  → Shelf.vue (overlay "Solte aqui")
 * - SHELF_DROP_PREVIEW (145) → Section.vue (linha do furo de destino)
 * - SHELF_HOVER (200)   → Shelf.vue (área em hover; resolve sobreposição de
 *                         áreas de prateleiras fisicamente próximas)
 */
export const Z = {
    PRODUCT: 20,
    CREMALHEIRA: 30,
    PAPER_BADGE: 30,
    DECK: 40,
    SEGMENTS: 50,
    STOCK: 80,
    BADGES: 90,
    INDICATOR: 91,
    BADGE_HOVER: 95,
    SHELF_DRAGGING: 120,
    SHELF_BASE: 130,
    ZONE: 135,
    DROP_OVERLAY: 140,
    SHELF_DROP_PREVIEW: 145,
    SHELF_HOVER: 200,
} as const;
