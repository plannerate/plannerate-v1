/**
 * Estado e persistência do Gerador de Propostas (useProposalGenerator).
 *
 * O núcleo de cálculo já é coberto em proposal-generator.test.ts; aqui o alvo é a
 * "cola": montagem do documento, rascunhos no localStorage, migração v09→v10,
 * modelo padrão e validação.
 *
 * O runner roda em `node` (sem DOM), então window/localStorage são dublês locais —
 * é o mesmo caminho de código que o navegador executa, só sem jsdom.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

// useT lê as traduções de usePage(); sem tradução carregada ele devolve a própria
// chave, o que basta para exercitar a lógica.
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { translations: {} } }),
}));

// vi.hoisted porque vi.mock sobe para o topo do arquivo e o spy precisa existir antes.
const toastSpy = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));
vi.mock('vue-sonner', () => ({ toast: toastSpy }));

const { useProposalGenerator } =
    await import('@/composables/landlord/useProposalGenerator');

const DRAFTS_KEY = 'plannerate-proposals-v10';
const LEGACY_KEY = 'plannerate-proposals-v09';
const TEMPLATE_KEY = 'plannerate-template-v10';

function fakeStorage() {
    const map = new Map<string, string>();

    return {
        getItem: (key: string) => map.get(key) ?? null,
        setItem: (key: string, value: string) => void map.set(key, value),
        removeItem: (key: string) => void map.delete(key),
        clear: () => map.clear(),
    };
}

let storage: ReturnType<typeof fakeStorage>;

beforeEach(() => {
    storage = fakeStorage();
    toastSpy.success.mockClear();
    toastSpy.error.mockClear();
    vi.stubGlobal('window', {
        localStorage: storage,
        confirm: vi.fn(() => true),
    });
});

describe('proposta inicial', () => {
    it('carrega a composição de fábrica', () => {
        const gen = useProposalGenerator();
        gen.init();

        expect(gen.items.value).toHaveLength(4);
        expect(gen.items.value.map((i) => i.category)).toEqual([
            'setup',
            'admin',
            'assistant',
            'store',
        ]);
        expect(gen.mods.value).toHaveLength(2);
        expect(gen.conds.value).toHaveLength(3);
        expect(gen.form.num).toMatch(/^[A-Z2-9]{10}$/);
    });

    it('soma os totais da composição padrão', () => {
        const gen = useProposalGenerator();
        gen.init();

        expect(gen.totals.value).toMatchObject({
            setup: 60000,
            monthlyPlan: 3000,
            store: 9000,
            monthlyOriginal: 12000,
        });
    });
});

describe('documentModel', () => {
    it('lista itens com nome, colocando cobrança única antes da mensal', () => {
        const gen = useProposalGenerator();
        gen.init();

        const rows = gen.documentModel.value.rows;

        expect(rows).toHaveLength(4);
        expect(rows[0].name).toBe('Setup inicial');
        // O restante mantém a ordem original da composição.
        expect(rows.slice(1).map((r) => r.name)).toEqual([
            'Usuário administrativo',
            'Usuário assistente',
            'Licença por loja',
        ]);
    });

    it('esconde item sem descrição', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.items.value[1].name = '   ';

        expect(gen.documentModel.value.rows).toHaveLength(3);
    });

    it('marca os cards como obsoletos quando há desconto', () => {
        const gen = useProposalGenerator();
        gen.init();

        expect(gen.documentModel.value.hasAnyDiscount).toBe(false);
        expect(gen.documentModel.value.setupChanged).toBe(false);

        gen.form.setupDiscountType = 'percent';
        gen.form.setupDiscountValue = 10;

        const doc = gen.documentModel.value;

        expect(doc.hasAnyDiscount).toBe(true);
        expect(doc.setupChanged).toBe(true);
        // Mensalidade não foi tocada, então segue sem card de "novo valor".
        expect(doc.monthlyChanged).toBe(false);
        expect(doc.setupFinal).toContain('54.000');
    });

    it('mostra assistente ilimitado sem quantidade numérica', () => {
        const gen = useProposalGenerator();
        gen.init();

        const assistant = gen.items.value[2];
        assistant.unit = 900;
        gen.onUnlimitedToggle(assistant, true);

        const row = gen.documentModel.value.rows.find(
            (r) => r.name === 'Usuário assistente',
        );

        expect(row?.qtyDisplay).toBe(
            'app.landlord.proposal_generator.document.unlimited',
        );
    });
});

describe('validação', () => {
    it('sem cliente, não salva o rascunho e avisa', () => {
        const gen = useProposalGenerator();
        gen.init();

        gen.saveDraft();

        expect(gen.drafts.value).toHaveLength(0);
        expect(gen.warnings.value).toContain(
            'app.landlord.proposal_generator.messages.client_required',
        );
    });

    it('sem item com valor, não salva o rascunho', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.client = 'Cliente A';
        gen.items.value.forEach((i) => {
            i.unit = 0;
        });

        gen.saveDraft();

        expect(gen.drafts.value).toHaveLength(0);
        expect(gen.warnings.value).toContain(
            'app.landlord.proposal_generator.messages.item_required',
        );
    });

    it('com cliente e item válido, o aviso some', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.client = 'Cliente A';

        gen.saveDraft();

        expect(gen.warnings.value).toHaveLength(0);
        expect(gen.drafts.value).toHaveLength(1);
    });
});

describe('rascunhos', () => {
    it('salva, relê do localStorage e remove', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.client = 'Supermercado ACME';

        gen.saveDraft();

        expect(gen.drafts.value).toHaveLength(1);
        expect(JSON.parse(storage.getItem(DRAFTS_KEY)!)).toHaveLength(1);

        // Uma segunda gravação da MESMA proposta atualiza em vez de duplicar.
        gen.form.clientCity = 'Chapecó/SC';
        gen.saveDraft();
        expect(gen.drafts.value).toHaveLength(1);
        expect(gen.drafts.value[0].clientCity).toBe('Chapecó/SC');

        gen.deleteDraft(0);
        expect(gen.drafts.value).toHaveLength(0);
        expect(JSON.parse(storage.getItem(DRAFTS_KEY)!)).toHaveLength(0);
    });

    it('não mente que salvou quando o localStorage recusa a gravação', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.client = 'Cliente A';

        // Aba anônima / storage cheio: setItem lança.
        storage.setItem = () => {
            throw new Error('QuotaExceededError');
        };

        expect(gen.saveDraft()).toBe(false);
        expect(gen.drafts.value).toHaveLength(0);
        expect(toastSpy.error).toHaveBeenCalled();
        expect(toastSpy.success).not.toHaveBeenCalled();
    });

    it('avisa com o número da proposta ao salvar e ao atualizar', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.client = 'Cliente A';

        expect(gen.saveDraft()).toBe(true);
        expect(toastSpy.success).toHaveBeenCalledWith(
            'app.landlord.proposal_generator.messages.draft_saved',
            expect.anything(),
        );

        toastSpy.success.mockClear();
        expect(gen.saveDraft()).toBe(true);
        expect(toastSpy.success).toHaveBeenCalledWith(
            'app.landlord.proposal_generator.messages.draft_updated',
            expect.anything(),
        );
    });

    it('recarrega um rascunho salvo por cima da proposta atual', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.client = 'Cliente A';
        gen.items.value[1].qty = 7;
        gen.saveDraft();

        gen.newProposal();
        expect(gen.form.client).toBe('');

        gen.loadDraft(0);
        expect(gen.form.client).toBe('Cliente A');
        expect(gen.items.value[1].qty).toBe(7);
    });

    it('migra rascunhos da v09 na primeira abertura', () => {
        storage.setItem(
            LEGACY_KEY,
            JSON.stringify([
                {
                    id: 'antigo',
                    num: 'ABCDEFGHJK',
                    client: 'Cliente legado',
                    items: [],
                    mods: [],
                    conds: [],
                },
            ]),
        );

        const gen = useProposalGenerator();
        gen.init();

        expect(gen.drafts.value).toHaveLength(1);
        expect(gen.drafts.value[0].client).toBe('Cliente legado');
        // E a lista passa a viver na chave nova.
        expect(JSON.parse(storage.getItem(DRAFTS_KEY)!)).toHaveLength(1);
    });
});

describe('modelo padrão', () => {
    it('usa o modelo salvo como base das próximas propostas, sem levar o cliente', () => {
        const gen = useProposalGenerator();
        gen.init();

        gen.form.plan = 'Plano Enterprise';
        gen.form.client = 'Não deve vazar';
        gen.items.value = [gen.items.value[0]];
        gen.saveTemplate();

        expect(JSON.parse(storage.getItem(TEMPLATE_KEY)!).plan).toBe(
            'Plano Enterprise',
        );

        gen.newProposal();

        expect(gen.form.plan).toBe('Plano Enterprise');
        expect(gen.items.value).toHaveLength(1);
        // Dados do cliente e descontos sempre nascem zerados.
        expect(gen.form.client).toBe('');
        expect(gen.form.setupDiscountType).toBe('none');
    });

    it('restaurar o padrão inicial descarta o modelo salvo', () => {
        const gen = useProposalGenerator();
        gen.init();
        gen.form.plan = 'Plano Enterprise';
        gen.saveTemplate();

        gen.restoreFactoryTemplate();

        expect(storage.getItem(TEMPLATE_KEY)).toBeNull();
        expect(gen.form.plan).toBe('Plannerate');
        expect(gen.items.value).toHaveLength(4);
    });
});
