/**
 * Testes do espelho TypeScript do AlterationClassifier (PHP).
 *
 * Este módulo é um CONTRATO DUPLO: as constantes precisam bater com
 * src/AutoPlanogram/AlterationClassifier.php do pacote. Os testes congelam a
 * semântica do lado TS (a paridade com o PHP é coberta por
 * tests/Unit/.../ReorderRedistributeTest.php).
 */

import { describe, expect, it } from 'vitest';
import {
    classifyAlteration,
    diffSlotFields,
    REDISTRIBUTE_FIELDS,
    REGENERATE_FIELDS,
    REORDER_FIELDS,
} from '@/components/planogram-templates/alteration-classifier';

describe('classifyAlteration', () => {
    it('classifica cada família de campo no seu nível', () => {
        expect(classifyAlteration(['price_order'])).toBe('reorder');
        expect(classifyAlteration(['brand_exposure'])).toBe('redistribute');
        expect(classifyAlteration([REGENERATE_FIELDS[0]])).toBe('regenerate');
    });

    it('precedência: regenerate > redistribute > reorder', () => {
        expect(classifyAlteration(['price_order', 'brand_exposure'])).toBe('redistribute');
        expect(classifyAlteration(['price_order', 'brand_exposure', REGENERATE_FIELDS[0]])).toBe('regenerate');
    });

    it('campos desconhecidos ou lista vazia não exigem nada', () => {
        expect(classifyAlteration([])).toBeNull();
        expect(classifyAlteration(['campo_que_nao_existe'])).toBeNull();
    });

    it('as três famílias não se sobrepõem (cada campo tem um único nível)', () => {
        const all = [...REORDER_FIELDS, ...REDISTRIBUTE_FIELDS, ...REGENERATE_FIELDS];
        expect(new Set(all).size).toBe(all.length);
    });
});

describe('diffSlotFields', () => {
    it('detecta só os campos classificados que mudaram', () => {
        const before = { price_order: 'asc', brand_exposure: 'mixed', nome: 'A' };
        const after = { price_order: 'desc', brand_exposure: 'mixed', nome: 'B' };

        // 'nome' não é campo classificado — ignorado mesmo tendo mudado
        expect(diffSlotFields(before, after)).toEqual(['price_order']);
    });

    it('null e ausente são equivalentes (campo não setado ≠ mudança)', () => {
        expect(diffSlotFields({ price_order: null }, {})).toEqual([]);
        expect(diffSlotFields({}, { price_order: null })).toEqual([]);
    });

    it('compara estruturas profundas por valor (visual_criteria JSON)', () => {
        const a = { visual_criteria: [{ key: 'marca', direction: 'asc' }] };
        const b = { visual_criteria: [{ key: 'marca', direction: 'asc' }] };
        const c = { visual_criteria: [{ key: 'marca', direction: 'desc' }] };

        expect(diffSlotFields(a, b)).toEqual([]);
        expect(diffSlotFields(a, c)).toEqual(['visual_criteria']);
    });
});
