    /**
     * Inverte a ordem dos segments de uma shelf
     */
    function invertSegmentsOrder(
        shelfId: string,
        recordChange: (change: any) => void,
    ): void {
        const found = findShelfById(shelfId);
        if (!found) return;
        const { shelf, section } = found;
        if (!shelf.segments || shelf.segments.length < 2) return;

        // Filtra segmentos não deletados e inverte
        const nonDeleted = shelf.segments.filter((s: any) => !s.deleted_at);
        const reversed = [...nonDeleted].reverse();

        // Atualiza ordering e posição no array
        reversed.forEach((segment, idx) => {
            segment.ordering = idx + 1;
        });

        // Substitui apenas os segmentos não deletados, mantendo os deletados nas mesmas posições
        let revIdx = 0;
        shelf.segments = shelf.segments.map((s) => {
            if (!s.deleted_at) {
                return reversed[revIdx++];
            }
            return s;
        });
        shelf.segments = [...shelf.segments];
        section.shelves = [...section.shelves];

        // Registra mudança para cada segmento
        reversed.forEach((segment) => {
            recordChange({
                type: 'segment_update',
                entityType: 'segment',
                entityId: segment.id,
                data: { ordering: segment.ordering },
            });
        });
    }
import { validateShelfWidth } from '@/lib/validation';
import { Layer, Segment, Shelf } from '@/types/planogram';
import { ulid } from 'ulid';
import { toast } from 'vue-sonner';
import { currentGondola } from './useGondolaState';
import { findSectionById, findShelfById } from './useLookupHelpers';
import {
    reorderShelvesByPosition,
    updateShelfReactive,
} from './useReactivityHelpers';

/**
 * Operações relacionadas a Shelves
 */
export function useShelfOperations() {
    /**
     * Adiciona uma nova prateleira a uma seção
     * @param sectionId - ID da seção onde adicionar a prateleira
     * @param shelfData - Dados parciais da prateleira
     * @param recordChange - Função para registrar mudança
     * @returns Shelf criada ou null
     */
    function addShelf(
        sectionId: string,
        shelfData: Partial<Shelf>,
        recordChange: (change: any) => void,
    ): Shelf | null {
        const section = findSectionById(sectionId);
        if (!section) return null;

        // Cria a shelf
        const newShelf = {
            ...shelfData,
            id: shelfData.id || ulid(),
            _is_new: true,
        } as Shelf;

        // Adiciona à seção
        if (!section.shelves) section.shelves = [];
        section.shelves.push(newShelf);

        // Força reatividade
        section.shelves = [...section.shelves];

        // Reordena otimisticamente após criar
        reorderShelvesByPosition(section);

        // Força reatividade global
        if (currentGondola.value?.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        // Registra mudança
        recordChange({
            type: 'shelf_update',
            entityType: 'shelf',
            entityId: newShelf.id,
            data: newShelf,
        });

        return newShelf;
    }

    /**
     * Atualiza uma prateleira existente
     * @param shelfId - ID da prateleira
     * @param updates - Propriedades a atualizar
     * @param recordChange - Função para registrar mudança
     * @returns Shelf atualizada ou null
     */
    function updateShelf(
        shelfId: string,
        updates: Partial<any>,
        recordChange: (change: any) => void,
    ): Shelf | null {
        const found = findShelfById(shelfId);
        if (!found) return null;

        const { shelf, section: currentSection } = found;
        const shelfIndex = currentSection.shelves.findIndex(
            (s: any) => s.id === shelfId,
        );
        if (shelfIndex === -1) return null;

        // Verifica se está mudando de seção
        const isChangingSection =
            updates.section_id && updates.section_id !== currentSection.id;

        if (isChangingSection) {
            // Transfere shelf para nova seção
            const targetSection = currentGondola.value?.sections?.find(
                (s: any) => s.id === updates.section_id,
            );

            if (!targetSection) {
                console.error(
                    '❌ Seção de destino não encontrada:',
                    updates.section_id,
                );
                return null;
            }

            // Remove da seção atual
            currentSection.shelves.splice(shelfIndex, 1);
            currentSection.shelves = [...currentSection.shelves];

            // Adiciona na nova seção com updates aplicados
            const updatedShelf = { ...shelf, ...updates };
            if (!targetSection.shelves) targetSection.shelves = [];
            targetSection.shelves.push(updatedShelf);
            targetSection.shelves = [...targetSection.shelves];

            // Reordena otimisticamente ambas as seções
            reorderShelvesByPosition(currentSection);
            reorderShelvesByPosition(targetSection);

            // Força reatividade global
            if (currentGondola.value?.sections) {
                currentGondola.value.sections = [
                    ...currentGondola.value.sections,
                ];
            }

            // Registra mudança
            recordChange({
                type: 'shelf_transfer',
                entityType: 'shelf',
                entityId: shelfId,
                data: {
                    from_section_id: currentSection.id,
                    to_section_id: updates.section_id,
                    shelf_position: updates.shelf_position,
                },
            });

            return updatedShelf;
        } else {
            // Atualização normal dentro da mesma seção
            updateShelfReactive(currentSection, shelfIndex, updates);

            // Registra mudança
            recordChange({
                type: 'shelf_update',
                entityType: 'shelf',
                entityId: shelfId,
                data: updates,
            });

            return currentSection.shelves[shelfIndex];
        }
    }

    /**
     * Inverte a ordem visual das prateleiras de uma seção
     * @param sectionId - ID da seção
     * @param recordChange - Função para registrar mudança
     */
    function invertShelvesOrder(
        sectionId: string,
        recordChange: (change: any) => void,
    ): void {
        const section = findSectionById(sectionId);
        if (!section?.shelves || section.shelves.length === 0) {
            return;
        }

        // Filtra prateleiras não deletadas e ordena por shelf_position (de baixo para cima)
        const sortedShelves = [...section.shelves]
            .filter((s: any) => !s.deleted_at)
            .sort(
                (a: any, b: any) =>
                    (a.shelf_position || 0) - (b.shelf_position || 0),
            );

        if (sortedShelves.length < 2) {
            // Não há o que inverter se tiver menos de 2 prateleiras
            return;
        }

        // Prepara atualizações: troca as posições visuais (shelf_position)
        const updates: Array<{
            shelfId: string;
            newShelfPosition: number;
        }> = [];

        const positions = sortedShelves
            .map((s: any) => s.shelf_position || 0)
            .reverse();

        sortedShelves.forEach((shelf: any, index: number) => {
            const newShelfPosition = positions[index];

            // Só adiciona se houver mudança na posição
            if (shelf.shelf_position !== newShelfPosition) {
                updates.push({
                    shelfId: shelf.id,
                    newShelfPosition: newShelfPosition,
                });
            }
        });

        if (updates.length === 0) {
            return; // Nada para atualizar
        }

        // Atualiza apenas shelf_position de cada prateleira
        updates.forEach(({ shelfId, newShelfPosition }) => {
            const shelfIndex = section.shelves!.findIndex(
                (s: any) => s.id === shelfId,
            );
            if (shelfIndex !== -1) {
                section.shelves![shelfIndex].shelf_position = newShelfPosition;
            }
        });

        // Recalcula ordering de baixo para cima:
        // maior shelf_position (mais embaixo) recebe ordering 1
        const reordered = [...section.shelves]
            .filter((s: any) => !s.deleted_at)
            .sort(
                (a: any, b: any) =>
                    (b.shelf_position || 0) - (a.shelf_position || 0),
            );

        let ordering = 1;
        reordered.forEach((shelf: any) => {
            shelf.ordering = ordering;
            ordering++;
        });

        // Força reatividade
        section.shelves = [...section.shelves];
        if (currentGondola.value?.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        // Registra mudança individual para cada prateleira
        reordered.forEach((shelf: any) => {
            recordChange({
                type: 'shelf_update',
                entityType: 'shelf',
                entityId: shelf.id,
                data: {
                    shelf_position: shelf.shelf_position,
                    ordering: shelf.ordering,
                },
            });
        });
    }

    /**
     * Adiciona um produto a uma prateleira criando a hierarquia: Segment → Layer → Product
     * @param shelfId - ID da prateleira onde adicionar o produto
     * @param productId - ID do produto a adicionar
     * @param productData - Dados do produto (opcional, para criar referência completa)
     * @param onProductUsed - Callback opcional para notificar que produto foi usado
     * @param recordChange - Função para registrar mudança
     * @returns Segment criado ou null
     */
    function addProductToShelf(
        shelfId: string,
        productId: string,
        productData: any | undefined,
        onProductUsed: ((productId: string) => void) | undefined,
        recordChange: (change: any) => void,
    ): Segment | null {
        const found = findShelfById(shelfId);
        if (!found) {
            console.warn('Shelf não encontrada:', shelfId);
            return null;
        }

        const { shelf, section } = found;

        // Valida se o produto cabe na shelf antes de adicionar
        const validationLayer: Layer = {
            id: ulid(),
            segment_id: '', // Será preenchido depois
            product_id: productId,
            product: productData,
            quantity: 1, // Quantidade inicial
            height: productData?.height || 10,
            alignment: 'center',
            spacing: 0,
        };

        // Valida usando a função validateShelfWidth
        const validation = validateShelfWidth(
            shelf,
            section.width,
            null, // Não estamos alterando produto existente
            1, // Quantidade proposta (não usada neste caso)
            validationLayer, // Nova layer sendo adicionada
        );

        if (!validation.isValid) {
            toast.error('O produto não cabe na prateleira selecionada.');
            console.warn('❌ Produto não cabe na shelf:', {
                productName: productData?.name,
                totalWidth: validation.totalWidth,
                sectionWidth: validation.sectionWidth,
            });
            return null;
        }

        // Cria as entidades
        const segmentId = ulid();
        const layerId = ulid();

        const newSegment = {
            id: segmentId,
            shelf_id: shelfId,
            quantity: 1,
            ordering:
                ([...(shelf?.segments || [])].filter(
                    (s: Segment) => !s.deleted_at,
                ).length || 0) + 1,
            _is_new: true,
        } as Segment;

        const newLayer = {
            id: layerId,
            segment_id: segmentId,
            product_id: productId,
            product: productData,
            quantity: 1,
            height: productData?.height || 10,
            alignment: 'center',
            spacing: 0,
            _is_new: true,
        } as Layer;

        // Adiciona a layer ao segment
        newSegment.layer = newLayer;

        // Inicializa segments se não existir
        if (!shelf.segments) {
            shelf.segments = [];
        }

        // Adiciona novo segment
        shelf.segments.push(newSegment);

        // Força reatividade criando nova referência do array de segments
        shelf.segments = [...shelf.segments];

        // Atualiza a shelf na section com nova referência
        const shelfIndex = section.shelves.findIndex(
            (s: any) => s.id === shelfId,
        );
        if (shelfIndex !== -1) {
            section.shelves[shelfIndex] = { ...shelf };
            section.shelves = [...section.shelves];
        }

        // Força reatividade global recriando o array de sections
        if (currentGondola.value?.sections) {
            const sectionIndex = currentGondola.value.sections.findIndex(
                (s: any) => s.id === section.id,
            );
            if (sectionIndex !== -1) {
                currentGondola.value.sections[sectionIndex] = { ...section };
                currentGondola.value.sections = [
                    ...currentGondola.value.sections,
                ];
            }
        }
 

        // Notifica que produto foi usado (remove da lista)
        if (onProductUsed) {
            onProductUsed(productId);
        }

        // Registra mudança
        recordChange({
            type: 'layer_create',
            entityType: 'layer',
            entityId: segmentId,
            data: {
                segment: newSegment,
                layer: newLayer,
            },
        });

        return newSegment;
    }

    /**
     * Move uma shelf para uma nova posição dentro da mesma section
     */
    function moveShelfWithinSection(
        shelfId: string,
        newPosition: number,
        recordChange: (change: any) => void,
    ): boolean {
        const result = findShelfById(shelfId);
        if (!result) return false;

        const { shelf, section } = result;

        // Atualiza a posição
        shelf.shelf_position = newPosition;

        // Força reatividade
        section.shelves = [...(section.shelves || [])];

        // Registra mudança
        recordChange({
            type: 'shelf_move',
            entityType: 'shelf',
            entityId: shelfId,
            data: {
                section_id: section.id,
                shelf_position: newPosition,
            },
        });

        return true;
    }

    /**
     * Move uma shelf para outra section
     */
    function moveShelfToSection(
        shelfId: string,
        targetSectionId: string,
        newPosition: number,
        recordChange: (change: any) => void,
    ): boolean {
        if (!currentGondola.value) return false;

        const result = findShelfById(shelfId);
        if (!result) return false;

        const { shelf, section: sourceSection } = result;
        const targetSection = findSectionById(targetSectionId);

        if (!targetSection) return false;

        // Remove da section de origem
        if (sourceSection.shelves) {
            const index = sourceSection.shelves.findIndex(
                (s: any) => s.id === shelfId,
            );
            if (index > -1) {
                sourceSection.shelves.splice(index, 1);
                sourceSection.shelves = [...sourceSection.shelves];
            }
        }

        // Atualiza shelf
        shelf.section_id = targetSectionId;
        shelf.shelf_position = newPosition;

        // Adiciona à section de destino
        if (!targetSection.shelves) targetSection.shelves = [];
        targetSection.shelves.push(shelf);
        targetSection.shelves = [...targetSection.shelves];

        // Força reatividade global
        if (currentGondola.value.sections) {
            currentGondola.value.sections = [...currentGondola.value.sections];
        }

        // Registra mudança
        recordChange({
            type: 'shelf_transfer',
            entityType: 'shelf',
            entityId: shelfId,
            data: {
                from_section_id: sourceSection.id,
                to_section_id: targetSectionId,
                shelf_position: newPosition,
            },
        });

        return true;
    }

    return {
        addShelf,
        updateShelf,
        invertShelvesOrder,
        invertSegmentsOrder,
        addProductToShelf,
        moveShelfWithinSection,
        moveShelfToSection,
    };
}
