/**
 * Testes do contrato central de drag & drop do editor (dnd/transfer.ts).
 *
 * Primeiros testes JS do projeto — cobrem o módulo que substituiu as strings
 * MIME espalhadas pelos componentes (refatoração 2026-06, Etapa 9).
 */

import { describe, expect, it } from 'vitest';
import {
    DND_KEYS,
    getMultipleProductsDragData,
    getProductDragData,
    getSegmentDragData,
    hasMultipleProductsData,
    hasProductData,
    hasSegmentData,
    isCopyModifier,
    setMultipleProductsDragData,
    setProductDragData,
    setRejectedProductDragData,
    setSegmentDragData,
    setShelfDragData,
} from '@/composables/plannerate/dnd/transfer';
import type { Product } from '@/types/planogram';

/**
 * DataTransfer fake — node não tem a API de DnD do browser.
 * Implementa exatamente o subconjunto que o módulo usa.
 */
class FakeDataTransfer {
    public effectAllowed = 'uninitialized';
    public dropEffect = 'none';
    private data = new Map<string, string>();

    setData(key: string, value: string): void {
        this.data.set(key, value);
    }

    getData(key: string): string {
        return this.data.get(key) ?? '';
    }

    get types(): string[] {
        return [...this.data.keys()];
    }
}

const dt = () => new FakeDataTransfer() as unknown as DataTransfer;

const product = (overrides: Partial<Product> = {}): Product =>
    ({ id: '01PRODUTO', name: 'Produto Teste', status: 'published', ...overrides }) as Product;

describe('produto único', () => {
    it('round-trip: builder escreve e parser lê o mesmo produto', () => {
        const t = dt();
        setProductDragData(t, product(), 'Produto Teste');

        expect(t.effectAllowed).toBe('copy');
        expect(hasProductData(t)).toBe(true);
        expect(t.getData(DND_KEYS.PRODUCT_ID)).toBe('01PRODUTO');
        expect(getProductDragData(t)?.id).toBe('01PRODUTO');
    });

    it('parser devolve null sem payload ou com JSON inválido', () => {
        expect(getProductDragData(dt())).toBeNull();

        const t = dt();
        t.setData(DND_KEYS.PRODUCT, '{json quebrado');
        expect(getProductDragData(t)).toBeNull();
    });
});

describe('múltiplos produtos', () => {
    it('round-trip preserva o array e a flag de múltiplos', () => {
        const t = dt();
        setMultipleProductsDragData(t, [product(), product({ id: '01OUTRO' })], '2 produtos');

        expect(hasMultipleProductsData(t)).toBe(true);
        expect(getMultipleProductsDragData(t).map((p) => p.id)).toEqual(['01PRODUTO', '01OUTRO']);
    });

    it('parser devolve [] sem a flag, com JSON inválido ou payload não-array', () => {
        expect(getMultipleProductsDragData(dt())).toEqual([]);

        const quebrado = dt();
        quebrado.setData(DND_KEYS.PRODUCTS_MULTIPLE, 'true');
        quebrado.setData(DND_KEYS.PRODUCTS, '{nao é array}');
        expect(getMultipleProductsDragData(quebrado)).toEqual([]);

        const objeto = dt();
        objeto.setData(DND_KEYS.PRODUCTS_MULTIPLE, 'true');
        objeto.setData(DND_KEYS.PRODUCTS, '{"id":"x"}');
        expect(getMultipleProductsDragData(objeto)).toEqual([]);
    });
});

describe('segmento', () => {
    it('registra o modificador de cópia sem travar o effectAllowed', () => {
        const move = dt();
        setSegmentDragData(move, 'SEG1', 'SHELF1', false);
        expect(getSegmentDragData(move)).toEqual({
            segmentId: 'SEG1',
            sourceShelfId: 'SHELF1',
            isCopy: false,
        });

        const copy = dt();
        setSegmentDragData(copy, 'SEG2', 'SHELF2', true);
        expect(getSegmentDragData(copy)?.isCopy).toBe(true);
        expect(hasSegmentData(copy)).toBe(true);

        // 'copyMove' nos DOIS casos: travar em 'move' fazia o browser recusar
        // dropEffect='copy' quando o modificador só era pressionado no meio do
        // arraste — o caso normal no macOS, onde Ctrl+mousedown é clique secundário.
        expect(move.effectAllowed).toBe('copyMove');
        expect(copy.effectAllowed).toBe('copyMove');
    });

    it('aceita Alt (macOS), Ctrl e Cmd como modificador de cópia', () => {
        const event = (mods: Partial<MouseEvent>) =>
            ({
                altKey: false,
                ctrlKey: false,
                metaKey: false,
                ...mods,
            }) as MouseEvent;

        expect(isCopyModifier(event({ altKey: true }))).toBe(true);
        expect(isCopyModifier(event({ ctrlKey: true }))).toBe(true);
        expect(isCopyModifier(event({ metaKey: true }))).toBe(true);
        expect(isCopyModifier(event({}))).toBe(false);
    });

    it('parser devolve null quando o drag não carrega segmento', () => {
        const t = dt();
        setProductDragData(t, product(), 'x');

        expect(getSegmentDragData(t)).toBeNull();
        expect(hasSegmentData(t)).toBe(false);
    });
});

describe('rejeitado e prateleira', () => {
    it('rejeitado carrega produto completo + id do registro de rejeição', () => {
        const t = dt();
        setRejectedProductDragData(t, 'REJ1', product());

        // o drop na prateleira lê como produto normal…
        expect(getProductDragData(t)?.id).toBe('01PRODUTO');
        // …e o id da rejeição fica disponível para remoção da lista
        expect(t.getData(DND_KEYS.REJECTED_ID)).toBe('REJ1');
    });

    it('prateleira usa as chaves legadas shelfId/sectionId com effect move', () => {
        const t = dt();
        setShelfDragData(t, 'SHELF1', 'SEC1');

        expect(t.effectAllowed).toBe('move');
        expect(t.getData(DND_KEYS.SHELF_ID)).toBe('SHELF1');
        expect(t.getData(DND_KEYS.SECTION_ID)).toBe('SEC1');
    });
});
