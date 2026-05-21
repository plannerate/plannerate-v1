// ============================================================================
// TIPOS PARA ESTADOS DE SNAPSHOT (before/after) DO HISTÓRICO
// ============================================================================
// Cada tipo representa o shape exato do estado capturado por captureBeforeState
// e captureAfterState em usePlanogramEditor para cada ActionType.
// ============================================================================

import type { Layer, Section, Segment } from '@/types/planogram';

/**
 * Estado de prateleira capturado em ações do tipo
 * shelf_position | shelf_update | shelf_transfer
 */
export interface ShelfSnapshotState {
    shelf_position: number;
    section_id: string;
    shelf_height: number;
    shelf_depth: number;
}

/**
 * Estado de gôndola capturado em ações do tipo
 * gondola_update | gondola_scale | gondola_alignment | gondola_flow
 */
export interface GondolaAttributeState {
    scale_factor: number | null;
    alignment: string | null;
    flow: string | null;
}

/**
 * Estado de reordenação de seções capturado em ações do tipo
 * sections_reorder — mapeia sectionId → ordering
 */
export type SectionsReorderState = Record<string, number>;

/**
 * Estado capturado após um segment_transfer:
 * contém os arrays de segmentos das duas prateleiras afetadas
 */
export interface SegmentTransferAfterState {
    sourceShelfId: string;
    targetShelfId: string;
    segmentId: string;
    sourceShelfSegments: Segment[];
    targetShelfSegments: Segment[];
}

/**
 * Estado capturado após um segment_copy:
 * contém o array de segmentos da prateleira de destino
 */
export interface SegmentCopyAfterState {
    targetShelfId: string;
    targetShelfSegments: Segment[];
}

/**
 * União de todos os possíveis tipos de estado before/after num snapshot do histórico.
 * Substitui `any` nas funções captureBeforeState / captureAfterState.
 */
export type SnapshotState =
    | ShelfSnapshotState
    | GondolaAttributeState
    | SectionsReorderState
    | SegmentTransferAfterState
    | SegmentCopyAfterState
    | Segment
    | Section
    | Layer
    | null;
