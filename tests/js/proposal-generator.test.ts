/**
 * Regras comerciais do Gerador de Propostas (composables/landlord/proposalCalculations).
 *
 * Foco nos pontos onde o cálculo é fácil de quebrar: descontos por usuário (percentual,
 * valor, bonificação), assistente ilimitado e a separação setup / mensalidade / loja —
 * cada um com seu próprio desconto.
 */

import { describe, expect, it } from 'vitest';
import {
    blankItem,
    calcDiscount,
    calculations,
    itemAdjustment,
    itemSubtotal,
    nextNum,
    normalizeItemCategory,
} from '@/composables/landlord/proposalCalculations';
import type {
    DiscountConfig,
    ProposalItem,
} from '@/composables/landlord/proposalCalculations';

const item = (overrides: Partial<ProposalItem>): ProposalItem => ({
    ...blankItem(),
    ...overrides,
});

const noDiscounts: DiscountConfig = {
    setupDiscountType: 'none',
    setupDiscountValue: 0,
    monthlyDiscountType: 'none',
    monthlyDiscountValue: 0,
    storeDiscountType: 'none',
    storeDiscountValue: 0,
};

const admin = (extra: Partial<ProposalItem> = {}) =>
    item({
        name: 'Usuário administrativo',
        category: 'admin',
        type: 'mensal',
        qty: 2,
        unit: 1500,
        ...extra,
    });

describe('itemSubtotal', () => {
    it('multiplica quantidade pelo valor unitário', () => {
        expect(itemSubtotal(admin())).toBe(3000);
    });

    it('assistente ilimitado é cobrado como 1 pacote, ignorando a quantidade', () => {
        const unlimited = item({
            category: 'assistant',
            type: 'mensal',
            qty: 99,
            unit: 900,
            unlimited: true,
        });

        expect(itemSubtotal(unlimited)).toBe(900);
    });
});

describe('itemAdjustment', () => {
    it('não ajusta item que não é de usuário', () => {
        const store = item({
            name: 'Licença por loja',
            category: 'store',
            type: 'mensal',
            qty: 18,
            unit: 500,
        });
        const adj = itemAdjustment(store);

        expect(adj).toMatchObject({
            original: 9000,
            final: 9000,
            discount: 0,
            kind: 'none',
        });
    });

    it('aplica desconto percentual e limita em 100%', () => {
        expect(
            itemAdjustment(
                admin({ userDiscountType: 'percent', userDiscountValue: 10 }),
            ),
        ).toMatchObject({
            discount: 300,
            final: 2700,
            kind: 'percent',
            percent: 10,
        });

        // Acima de 100% o desconto para em 100% — nunca gera valor negativo.
        expect(
            itemAdjustment(
                admin({ userDiscountType: 'percent', userDiscountValue: 250 }),
            ),
        ).toMatchObject({
            discount: 3000,
            final: 0,
            percent: 100,
        });
    });

    it('aplica desconto em valor sem passar do subtotal', () => {
        expect(
            itemAdjustment(
                admin({ userDiscountType: 'fixed', userDiscountValue: 500 }),
            ),
        ).toMatchObject({
            discount: 500,
            final: 2500,
            kind: 'fixed',
        });

        expect(
            itemAdjustment(
                admin({ userDiscountType: 'fixed', userDiscountValue: 99999 }),
            ),
        ).toMatchObject({
            discount: 3000,
            final: 0,
        });
    });

    it('bonifica no máximo a quantidade contratada', () => {
        expect(
            itemAdjustment(
                admin({ userDiscountType: 'bonus', userDiscountValue: 1 }),
            ),
        ).toMatchObject({
            bonusQty: 1,
            discount: 1500,
            final: 1500,
            kind: 'bonus',
        });

        expect(
            itemAdjustment(
                admin({ userDiscountType: 'bonus', userDiscountValue: 10 }),
            ),
        ).toMatchObject({
            bonusQty: 2,
            discount: 3000,
            final: 0,
        });
    });

    it('assistente ilimitado ignora bonificação (não faz sentido bonificar ilimitado)', () => {
        const adj = itemAdjustment(
            item({
                category: 'assistant',
                type: 'mensal',
                qty: 5,
                unit: 900,
                unlimited: true,
                userDiscountType: 'bonus',
                userDiscountValue: 3,
            }),
        );

        expect(adj).toMatchObject({
            discount: 0,
            final: 900,
            bonusQty: 0,
            kind: 'unlimited',
        });
    });

    it('assistente ilimitado ainda aceita desconto percentual', () => {
        const adj = itemAdjustment(
            item({
                category: 'assistant',
                type: 'mensal',
                qty: 5,
                unit: 900,
                unlimited: true,
                userDiscountType: 'percent',
                userDiscountValue: 50,
            }),
        );

        expect(adj).toMatchObject({
            discount: 450,
            final: 450,
            kind: 'percent',
        });
    });
});

describe('calcDiscount', () => {
    it.each([
        ['none' as const, 100, 0],
        ['percent' as const, 10, 600],
        ['fixed' as const, 500, 500],
        ['fixed' as const, 99999, 6000],
    ])('tipo %s com valor %s', (type, value, expected) => {
        expect(calcDiscount(6000, type, value)).toBe(expected);
    });
});

describe('calculations', () => {
    const items = [
        item({
            name: 'Setup inicial',
            category: 'setup',
            type: 'unico',
            qty: 1,
            unit: 60000,
            installments: 3,
        }),
        admin(),
        item({
            name: 'Licença por loja',
            category: 'store',
            type: 'mensal',
            qty: 18,
            unit: 500,
        }),
    ];

    it('separa setup, mensalidade do plano e licenças por loja', () => {
        const c = calculations(items, noDiscounts);

        expect(c).toMatchObject({
            setup: 60000,
            monthlyPlan: 3000,
            store: 9000,
            monthlyOriginal: 12000,
            sf: 60000,
            mf: 12000,
        });
    });

    it('aplica cada desconto na sua própria base', () => {
        const c = calculations(items, {
            ...noDiscounts,
            setupDiscountType: 'percent',
            setupDiscountValue: 10,
            storeDiscountType: 'fixed',
            storeDiscountValue: 1000,
        });

        expect(c.ds).toBe(6000);
        expect(c.sf).toBe(54000);
        expect(c.dl).toBe(1000);
        expect(c.storef).toBe(8000);
        // Mensalidade final = plano (sem desconto) + lojas com desconto.
        expect(c.mf).toBe(11000);
        // O valor original da mensalidade não muda com o desconto.
        expect(c.monthlyOriginal).toBe(12000);
    });

    it('desconto por usuário entra no total antes do desconto da mensalidade', () => {
        const c = calculations(
            [admin({ userDiscountType: 'percent', userDiscountValue: 50 })],
            noDiscounts,
        );

        expect(c.monthlyPlan).toBe(1500);
    });
});

describe('normalizeItemCategory', () => {
    it.each([
        [{ name: 'Setup inicial', type: 'unico' as const }, 'setup'],
        [{ name: 'Implantação do sistema' }, 'setup'],
        [{ name: 'Usuário assistente' }, 'assistant'],
        [{ name: 'Usuário administrativo' }, 'admin'],
        [{ name: 'Licença por loja' }, 'store'],
        [{ name: 'Qualquer coisa', measure: 'lojas' }, 'store'],
        [{ name: 'Consultoria avulsa' }, 'other'],
    ])('infere a categoria de item legado %o', (legacy, expected) => {
        expect(normalizeItemCategory(legacy)).toBe(expected);
    });

    it('respeita a categoria já gravada', () => {
        expect(
            normalizeItemCategory({
                name: 'Licença por loja',
                category: 'other',
            }),
        ).toBe('other');
    });
});

describe('nextNum', () => {
    it('gera código de 10 caracteres sem letras/dígitos ambíguos', () => {
        const code = nextNum();

        expect(code).toHaveLength(10);
        expect(code).toMatch(/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{10}$/);
    });

    it('nunca repete um código já usado', () => {
        const used = Array.from({ length: 50 }, () => nextNum());

        for (let i = 0; i < 50; i++) {
            expect(used).not.toContain(nextNum(used));
        }
    });
});
