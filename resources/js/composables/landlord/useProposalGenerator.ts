/**
 * Estado, persistência e modelo do documento do Gerador de Propostas.
 *
 * Portado de docs/preview.html: a versão original manipulava o DOM com innerHTML a cada
 * tecla; aqui o mesmo comportamento vem da reatividade do Vue. As regras comerciais
 * ficam em ./proposalCalculations (puras e testadas); este arquivo cuida do estado,
 * do localStorage e dos rótulos traduzidos.
 *
 * Persistência é 100% client-side, com as mesmas chaves da ferramenta original — quem
 * já tinha propostas salvas no navegador continua enxergando todas elas.
 */

import { computed, inject, reactive, ref } from 'vue';
import type { InjectionKey, Ref } from 'vue';
import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';
import {
    blankItem,
    brl,
    calculations,
    factoryDefaults,
    fmtDate,
    isUserItem,
    itemAdjustment,
    itemSubtotal,
    nextNum,
    normalizeItem,
    normalizeItemCategory,
    standardItem,
    today,
    uid,
} from './proposalCalculations';
import type {
    DiscountConfig,
    ItemAdjustment,
    ItemCategory,
    ProposalItem,
    ProposalModule,
    ProposalState,
} from './proposalCalculations';

const DRAFTS_KEY = 'plannerate-proposals-v10';
const LEGACY_DRAFTS_KEY = 'plannerate-proposals-v09';
const TEMPLATE_KEY = 'plannerate-template-v10';

/** Campos escalares do formulário (tudo que não é lista). */
type ProposalForm = Omit<ProposalState, 'items' | 'mods' | 'conds' | 'savedAt'>;

interface ProposalTemplate extends Partial<ProposalForm> {
    items?: ProposalItem[];
    mods?: ProposalModule[];
    conds?: string[];
    savedAt?: string;
}

function readStorage<T>(key: string, fallback: T): T {
    if (typeof window === 'undefined') {
        return fallback;
    }

    try {
        const raw = window.localStorage.getItem(key);

        return raw ? (JSON.parse(raw) as T) : fallback;
    } catch {
        return fallback;
    }
}

/**
 * Grava no localStorage e diz se conseguiu.
 *
 * O retorno importa: em aba anônima, com storage cheio ou com cookies bloqueados o
 * setItem lança e a proposta NÃO persiste. Sem esse retorno o usuário via "salvo com
 * sucesso" e perdia tudo no primeiro reload.
 */
function writeStorage(key: string, value: unknown): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        window.localStorage.setItem(key, JSON.stringify(value));

        return true;
    } catch {
        return false;
    }
}

export function useProposalGenerator() {
    const { t } = useT();
    /** Monta a chave de tradução (aqui `k` devolve a CHAVE; nos componentes devolve o texto). */
    const k = (suffix: string) => `app.landlord.proposal_generator.${suffix}`;

    const items = ref<ProposalItem[]>([]);
    const mods = ref<ProposalModule[]>([]);
    const conds = ref<string[]>([]);
    const drafts = ref<ProposalState[]>([]);
    const warnings = ref<string[]>([]);
    const templateSavedAt = ref<string | null>(null);

    const form = reactive<ProposalForm>({ ...stripLists(factoryDefaults()) });

    /** Só os campos escalares — listas são aplicadas separadamente nos seus refs. */
    function stripLists(state: Partial<ProposalState>): ProposalForm {
        const rest = { ...state };

        delete rest.items;
        delete rest.mods;
        delete rest.conds;
        delete rest.savedAt;

        return rest as ProposalForm;
    }

    // ── Persistência ────────────────────────────────────────────────────────────

    /** Lê os rascunhos, migrando os da v09 na primeira vez que a página abre. */
    function loadDrafts(): void {
        if (typeof window === 'undefined') {
            drafts.value = [];

            return;
        }

        try {
            const current = window.localStorage.getItem(DRAFTS_KEY);

            if (current) {
                drafts.value = JSON.parse(current) as ProposalState[];

                return;
            }

            const previous = readStorage<ProposalState[]>(
                LEGACY_DRAFTS_KEY,
                [],
            );

            if (previous.length) {
                writeStorage(DRAFTS_KEY, previous);
            }

            drafts.value = previous;
        } catch {
            drafts.value = [];
        }
    }

    function templateData(): ProposalTemplate | null {
        return readStorage<ProposalTemplate | null>(TEMPLATE_KEY, null);
    }

    function usedCodes(): string[] {
        return drafts.value.map((d) => String(d.num ?? ''));
    }

    /** Proposta nova: base de fábrica sobreposta pelo modelo padrão salvo, se houver. */
    function defaults(): ProposalState {
        const base = factoryDefaults(usedCodes());
        const tpl = templateData();

        if (!tpl) {
            return base;
        }

        return {
            ...base,
            ...tpl,
            id: uid(),
            num: nextNum(usedCodes()),
            date: today(),
            client: '',
            cnpj: '',
            clientCity: '',
            contact: '',
            setupDiscountType: 'none',
            setupDiscountValue: 0,
            monthlyDiscountType: 'none',
            monthlyDiscountValue: 0,
            storeDiscountType: 'none',
            storeDiscountValue: 0,
            discountReason: base.discountReason,
            discountTerm: '',
            items: (tpl.items ?? base.items).map((i) => ({
                ...normalizeItem(i),
                id: uid(),
            })),
            mods: (tpl.mods ?? base.mods).map((m) => ({ ...m })),
            conds: [...(tpl.conds ?? base.conds)],
        };
    }

    function state(): ProposalState {
        return {
            ...JSON.parse(JSON.stringify(form)),
            items: JSON.parse(JSON.stringify(items.value)),
            mods: JSON.parse(JSON.stringify(mods.value)),
            conds: [...conds.value],
            savedAt: new Date().toISOString(),
        };
    }

    function apply(s: Partial<ProposalState>): void {
        Object.entries(stripLists(s)).forEach(([key, value]) => {
            if (key in form && value !== undefined) {
                (form as Record<string, unknown>)[key] = value;
            }
        });

        form.id = s.id ?? uid();
        items.value = (s.items ?? []).map((i) => normalizeItem(i));
        mods.value = s.mods ?? [];
        conds.value = s.conds ?? [];
    }

    function newProposal(): void {
        apply(defaults());
        warnings.value = [];
    }

    /** @returns true quando a proposta realmente foi gravada no navegador. */
    function saveDraft(): boolean {
        if (!validate()) {
            // Sem isso o clique em "Salvar" parecia não fazer nada quando o aviso
            // ficava fora da área visível do editor.
            toast.error(t(k('messages.validation_title')), {
                description: warnings.value.join(' '),
            });

            return false;
        }

        const s = state();
        const list = [...drafts.value];
        const idx = list.findIndex((x) => x.id === s.id);
        const isUpdate = idx >= 0;

        if (isUpdate) {
            list[idx] = s;
        } else {
            list.unshift(s);
        }

        if (!writeStorage(DRAFTS_KEY, list)) {
            toast.error(t(k('messages.storage_failed')));

            return false;
        }

        drafts.value = list;
        toast.success(
            t(k(isUpdate ? 'messages.draft_updated' : 'messages.draft_saved'), {
                num: s.num,
            }),
            {
                description: t(k('messages.draft_saved_hint'), {
                    client: s.client,
                }),
            },
        );

        return true;
    }

    function loadDraft(index: number): void {
        const draft = drafts.value[index];

        if (draft) {
            apply(draft);
        }
    }

    function deleteDraft(index: number): void {
        const list = [...drafts.value];
        const [removed] = list.splice(index, 1);

        if (!writeStorage(DRAFTS_KEY, list)) {
            toast.error(t(k('messages.storage_failed')));

            return;
        }

        drafts.value = list;
        toast.success(
            t(k('messages.draft_deleted'), { num: removed?.num ?? '' }),
        );
    }

    // ── Modelo padrão ───────────────────────────────────────────────────────────

    function saveTemplate(): void {
        const s = state();
        const tpl: ProposalTemplate = {
            city: s.city,
            validity: s.validity,
            plan: s.plan,
            intro: s.intro,
            payment: s.payment,
            implementation: s.implementation,
            adjustment: s.adjustment,
            due: s.due,
            seller: s.seller,
            sellerRole: s.sellerRole,
            phone: s.phone,
            email: s.email,
            site: s.site,
            items: s.items.map((i) => ({ ...i, id: uid() })),
            mods: s.mods.map((m) => ({ ...m })),
            conds: [...s.conds],
            savedAt: new Date().toISOString(),
        };

        if (!writeStorage(TEMPLATE_KEY, tpl)) {
            toast.error(t(k('messages.storage_failed')));

            return;
        }

        templateSavedAt.value = tpl.savedAt ?? null;
        toast.success(t(k('messages.template_saved')), {
            description: t(k('messages.template_saved_hint')),
        });
    }

    function restoreFactoryTemplate(): void {
        if (!window.confirm(t(k('messages.template_restore_confirm')))) {
            return;
        }

        if (typeof window !== 'undefined') {
            window.localStorage.removeItem(TEMPLATE_KEY);
        }

        templateSavedAt.value = null;
        newProposal();
        toast.success(t(k('messages.template_restored')));
    }

    const templateStatus = computed(() =>
        templateSavedAt.value
            ? t(k('template.status_custom'), {
                  date: new Date(templateSavedAt.value).toLocaleString('pt-BR'),
              })
            : t(k('template.status_default')),
    );

    // ── Import / export / impressão ─────────────────────────────────────────────

    function exportJSON(): void {
        const s = state();
        const blob = new Blob([JSON.stringify(s, null, 2)], {
            type: 'application/json',
        });
        const link = document.createElement('a');

        const filename = `${s.num}-${(s.client || 'cliente').replace(/[^a-z0-9]+/gi, '-')}.json`;

        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);

        toast.success(t(k('messages.exported'), { file: filename }));
    }

    function importJSON(input: HTMLInputElement): void {
        const file = input.files?.[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();

        reader.onload = () => {
            try {
                apply(JSON.parse(String(reader.result)) as ProposalState);
                toast.success(t(k('messages.imported'), { num: form.num }));
            } catch {
                toast.error(t(k('messages.import_invalid')));
            }
        };

        reader.readAsText(file);
        input.value = '';
    }

    function validate(): boolean {
        const errors: string[] = [];

        if (!form.client.trim()) {
            errors.push(t(k('messages.client_required')));
        }

        if (!items.value.some((i) => i.name.trim() && itemSubtotal(i) > 0)) {
            errors.push(t(k('messages.item_required')));
        }

        if (!form.date) {
            errors.push(t(k('messages.date_required')));
        }

        warnings.value = errors;

        return errors.length === 0;
    }

    /** Zera o título só durante a impressão para não sujar o cabeçalho do PDF. */
    function printProposal(): void {
        if (!validate()) {
            toast.error(t(k('messages.validation_title')), {
                description: warnings.value.join(' '),
            });

            return;
        }

        const oldTitle = document.title;
        document.title = '';

        const restore = () => {
            document.title = oldTitle;
            window.removeEventListener('afterprint', restore);
        };

        window.addEventListener('afterprint', restore);
        window.print();
        window.setTimeout(() => {
            document.title = oldTitle;
        }, 1500);
    }

    // ── Edição de itens / módulos / condições ───────────────────────────────────

    function addItem(): void {
        items.value.push(blankItem());
    }

    function addStandardItem(category: ItemCategory): void {
        items.value.push(standardItem(category));
    }

    function removeItem(index: number): void {
        items.value.splice(index, 1);
    }

    /** Trocar a categoria reconfigura cobrança e zera ajustes que deixaram de fazer sentido. */
    function onCategoryChange(
        item: ProposalItem,
        category: ItemCategory,
    ): void {
        item.category = category;

        if (category === 'setup') {
            item.type = 'unico';
        } else {
            item.type = 'mensal';
            item.installments = 1;
        }

        if (category !== 'admin' && category !== 'assistant') {
            item.userDiscountType = 'none';
            item.userDiscountValue = 0;
        }

        if (category !== 'assistant') {
            item.unlimited = false;
        }
    }

    function onBillingTypeChange(
        item: ProposalItem,
        type: ProposalItem['type'],
    ): void {
        item.type = type;

        if (type !== 'unico') {
            item.installments = 1;
        }
    }

    function onUnlimitedToggle(item: ProposalItem, checked: boolean): void {
        item.unlimited = checked;

        if (!checked) {
            return;
        }

        if (item.userDiscountType === 'bonus') {
            item.userDiscountType = 'none';
        }

        if (item.userDiscountType === 'none') {
            item.userDiscountValue = 0;
        }
    }

    function onUserDiscountTypeChange(
        item: ProposalItem,
        type: ProposalItem['userDiscountType'],
    ): void {
        item.userDiscountType = type;

        if (type === 'none') {
            item.userDiscountValue = 0;
        }
    }

    function addMod(): void {
        mods.value.push({ name: '', desc: '' });
    }

    function removeMod(index: number): void {
        mods.value.splice(index, 1);
    }

    function addCond(): void {
        conds.value.push('');
    }

    function removeCond(index: number): void {
        conds.value.splice(index, 1);
    }

    // ── Rótulos e modelo do documento ───────────────────────────────────────────

    function adjustmentLabel(adj: ItemAdjustment): string {
        switch (adj.kind) {
            case 'percent':
                return t(k('document.discount_of_percent'), {
                    percent: String(adj.percent),
                });
            case 'fixed':
                return t(k('document.discount_of_value'), {
                    value: brl(adj.discount),
                });
            case 'bonus':
                return `${adj.bonusQty} ${adj.bonusQty === 1 ? t(k('document.bonus_user')) : t(k('document.bonus_users'))}`;
            case 'unlimited':
                return t(k('document.unlimited_assistants'));
            default:
                return '';
        }
    }

    const totals = computed(() =>
        calculations(items.value, form as DiscountConfig),
    );

    /** Linhas do bloco "Descontos e bonificações por usuário" (só admin/assistente). */
    const userDiscountRows = computed(() =>
        items.value
            .map((item, index) => ({ item, index }))
            .filter(({ item }) => isUserItem(item))
            .map(({ item, index }) => {
                const adj = itemAdjustment(item);
                const isUnlimited =
                    item.category === 'assistant' && item.unlimited;

                return {
                    item,
                    index,
                    label: t(k(`options.category.${item.category}`)),
                    fieldLabel:
                        item.userDiscountType === 'bonus'
                            ? t(k('fields.bonus_quantity'))
                            : item.userDiscountType === 'percent'
                              ? t(k('fields.discount_percent'))
                              : t(k('fields.discount_value')),
                    allowBonus: !isUnlimited,
                    summary: isUnlimited
                        ? t(k('commercial.unlimited_summary'), {
                              value: brl(adj.final),
                          })
                        : item.userDiscountType === 'bonus'
                          ? t(k('commercial.bonus_summary'), {
                                bonus: String(
                                    Math.min(
                                        Math.floor(
                                            Number(item.userDiscountValue) || 0,
                                        ),
                                        Number(item.qty) || 0,
                                    ),
                                ),
                                total: String(Number(item.qty) || 0),
                            })
                          : t(k('commercial.current_value'), {
                                value: brl(adj.final),
                            }),
                };
            }),
    );

    const documentModel = computed(() => {
        const c = totals.value;

        const rows = items.value
            .filter((i) => i.name.trim())
            .map((i, index) => ({ item: i, index }))
            .sort((a, b) => {
                const pa = a.item.type === 'unico' ? 0 : 1;
                const pb = b.item.type === 'unico' ? 0 : 1;

                return pa - pb || a.index - b.index;
            })
            .map(({ item }) => {
                const adj = itemAdjustment(item);
                const parc = Math.max(1, Number(item.installments) || 1);

                return {
                    key: item.id,
                    name: item.name,
                    note: item.note,
                    adjustLabel: adjustmentLabel(adj),
                    originalText: adj.discount > 0 ? brl(adj.original) : '',
                    parcelText:
                        item.type === 'unico' && parc > 1
                            ? t(k('document.installment_line'), {
                                  count: String(parc),
                                  value: brl(adj.final / parc),
                              })
                            : '',
                    qtyDisplay:
                        normalizeItemCategory(item) === 'assistant' &&
                        item.unlimited
                            ? t(k('document.unlimited'))
                            : `${item.qty} ${item.measure}`.trim(),
                    unitText: brl(item.unit),
                    typeLabel:
                        item.type === 'unico'
                            ? t(k('document.type_unico'))
                            : t(k('document.type_mensal')),
                    typeClass: item.type,
                    totalText: brl(adj.final),
                };
            });

        const clientLines = (
            [
                [t(k('document.client_label')), form.client],
                [t(k('document.cnpj_label')), form.cnpj],
                [t(k('document.city_label')), form.clientCity],
                [t(k('document.contact_label')), form.contact],
            ] as Array<[string, string]>
        )
            .filter(([, value]) => value && value.trim())
            .map(([label, value]) => ({ label, value }));

        // Mantém só as condições que têm conteúdo depois dos dois-pontos.
        const conditions = [
            t(k('document.condition_payment'), { value: form.payment }),
            t(k('document.condition_implementation'), {
                value: form.implementation,
            }),
            t(k('document.condition_adjustment'), { value: form.adjustment }),
            t(k('document.condition_due'), { value: form.due }),
            ...conds.value,
        ].filter((x) => x && x.split(':').pop()?.trim());

        const hasAnyDiscount = !!(c.ds || c.dm || c.dl);
        const setupChanged = c.ds > 0;
        const monthlyChanged = c.dm + c.dl > 0;
        const maxInstallments = Math.max(
            1,
            ...items.value
                .filter((i) => i.type === 'unico')
                .map((i) => Number(i.installments) || 1),
        );
        const installmentsNote =
            maxInstallments > 1
                ? t(k('document.installments_multi'), {
                      count: String(maxInstallments),
                  })
                : t(k('document.installments_single'));
        const planLabel = form.plan || t(k('document.plan_fallback'));

        return {
            num: form.num,
            city: form.city,
            dateText: fmtDate(form.date),
            clientLines,
            planLabel,
            intro: form.intro,
            modules: mods.value.filter((m) => m.name.trim()),
            rows,
            conditions,
            hasAnyDiscount,
            setupChanged,
            monthlyChanged,
            installmentsNote,
            setupOriginal: brl(c.setup),
            setupFinal: brl(c.sf),
            monthlyOriginal: brl(c.monthlyOriginal),
            monthlyFinal: brl(c.mf),
            discountConditionMonths:
                hasAnyDiscount && form.discountTerm ? form.discountTerm : '',
            signClient: form.client || t(k('document.sign_client_fallback')),
            signContact: form.contact,
            sellerLine: [form.seller, form.sellerRole]
                .filter(Boolean)
                .join(' · '),
            validityDays: Number(form.validity) || 7,
            contacts: [form.phone, form.email, form.site].filter(Boolean),
        };
    });

    function subtotalText(item: ProposalItem): string {
        return brl(itemAdjustment(item).final);
    }

    function itemAdjustLabel(item: ProposalItem): string {
        return adjustmentLabel(itemAdjustment(item));
    }

    function init(): void {
        loadDrafts();
        templateSavedAt.value = templateData()?.savedAt ?? null;
        newProposal();
    }

    return {
        // estado
        form,
        items: items as Ref<ProposalItem[]>,
        mods,
        conds,
        drafts,
        warnings,
        // derivados
        totals,
        documentModel,
        userDiscountRows,
        templateStatus,
        subtotalText,
        itemAdjustLabel,
        // ações
        init,
        newProposal,
        saveDraft,
        loadDraft,
        deleteDraft,
        saveTemplate,
        restoreFactoryTemplate,
        exportJSON,
        importJSON,
        printProposal,
        addItem,
        addStandardItem,
        removeItem,
        onCategoryChange,
        onBillingTypeChange,
        onUnlimitedToggle,
        onUserDiscountTypeChange,
        addMod,
        removeMod,
        addCond,
        removeCond,
    };
}

export type ProposalGenerator = ReturnType<typeof useProposalGenerator>;

/**
 * A página cria o gerador e o disponibiliza para o editor e o documento. É injeção em
 * vez de prop porque o editor escreve no estado — passar por prop seria mutar prop.
 */
export const PROPOSAL_GENERATOR_KEY: InjectionKey<ProposalGenerator> =
    Symbol('proposalGenerator');

export function useInjectedProposalGenerator(): ProposalGenerator {
    const generator = inject(PROPOSAL_GENERATOR_KEY);

    if (!generator) {
        throw new Error(
            'useInjectedProposalGenerator() exige um provide(PROPOSAL_GENERATOR_KEY) acima na árvore.',
        );
    }

    return generator;
}
