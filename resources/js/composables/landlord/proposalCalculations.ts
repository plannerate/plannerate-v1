/**
 * Núcleo de cálculo do Gerador de Propostas (portado de docs/preview.html).
 *
 * Tudo aqui é puro e sem dependência de Vue/Inertia de propósito: são as regras
 * comerciais (subtotais, descontos, bonificações) e por isso precisam ser testáveis
 * isoladamente no Vitest. Rótulos traduzidos ficam por conta do composable.
 */

export type ItemCategory = 'setup' | 'admin' | 'assistant' | 'store' | 'other';
export type BillingType = 'mensal' | 'unico';
export type DiscountType = 'none' | 'fixed' | 'percent';
export type UserDiscountType = 'none' | 'percent' | 'fixed' | 'bonus';

export interface ProposalItem {
    id: string;
    name: string;
    note: string;
    category: ItemCategory;
    qty: number;
    measure: string;
    unit: number;
    type: BillingType;
    installments: number;
    userDiscountType: UserDiscountType;
    userDiscountValue: number;
    unlimited: boolean;
}

export interface ProposalModule {
    name: string;
    desc: string;
}

/** Como o valor do item foi ajustado — `kind` vira rótulo traduzido no composable. */
export interface ItemAdjustment {
    original: number;
    final: number;
    discount: number;
    bonusQty: number;
    kind: 'none' | 'percent' | 'fixed' | 'bonus' | 'unlimited';
    percent: number;
}

export interface DiscountConfig {
    setupDiscountType: DiscountType;
    setupDiscountValue: number | string;
    monthlyDiscountType: DiscountType;
    monthlyDiscountValue: number | string;
    storeDiscountType: DiscountType;
    storeDiscountValue: number | string;
}

export interface ProposalTotals {
    setup: number;
    monthlyPlan: number;
    store: number;
    ds: number;
    dm: number;
    dl: number;
    sf: number;
    mf: number;
    mfPlan: number;
    storef: number;
    monthlyOriginal: number;
}

export interface ProposalState extends DiscountConfig {
    id: string;
    num: string;
    date: string;
    city: string;
    validity: number | string;
    plan: string;
    intro: string;
    client: string;
    cnpj: string;
    clientCity: string;
    contact: string;
    discountReason: string;
    discountTerm: string;
    payment: string;
    implementation: string;
    adjustment: string;
    due: string;
    seller: string;
    sellerRole: string;
    phone: string;
    email: string;
    site: string;
    items: ProposalItem[];
    mods: ProposalModule[];
    conds: string[];
    savedAt?: string;
}

export const brl = (v: unknown): string =>
    (Number(v) || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

export const uid = (): string =>
    Date.now().toString(36) + Math.random().toString(36).slice(2, 7);

export function today(): string {
    const d = new Date();
    const o = d.getTimezoneOffset();

    return new Date(d.getTime() - o * 60000).toISOString().slice(0, 10);
}

export function fmtDate(v: string): string {
    if (!v) {
        return '';
    }

    return new Date(`${v}T12:00:00`).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Código da proposta: 10 caracteres de um alfabeto sem I/O/0/1 (evita confusão na
 * leitura) e que nunca colide com um código já usado.
 */
export function nextNum(usedCodes: string[] = []): string {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const used = new Set(usedCodes.map((code) => String(code ?? '')));
    let code = '';

    do {
        code = Array.from(
            { length: 10 },
            () => chars[Math.floor(Math.random() * chars.length)],
        ).join('');
    } while (used.has(code));

    return code;
}

/** Infere a categoria de itens legados (salvos antes do campo `category` existir). */
export function normalizeItemCategory(i: Partial<ProposalItem>): ItemCategory {
    if (i.category) {
        return i.category;
    }

    const name = String(i.name ?? '').toLowerCase();
    const measure = String(i.measure ?? '').toLowerCase();

    if (
        i.type === 'unico' ||
        name.includes('setup') ||
        name.includes('implantação') ||
        name.includes('implantacao')
    ) {
        return 'setup';
    }

    if (name.includes('assistente')) {
        return 'assistant';
    }

    if (name.includes('administrativ')) {
        return 'admin';
    }

    if (name.includes('loja') || measure.includes('loja')) {
        return 'store';
    }

    return 'other';
}

export function isUserItem(i: Partial<ProposalItem>): boolean {
    const c = normalizeItemCategory(i);

    return c === 'admin' || c === 'assistant';
}

export function isStoreItem(i: Partial<ProposalItem>): boolean {
    return i.type === 'mensal' && normalizeItemCategory(i) === 'store';
}

/** Assistente ilimitado é cobrado como 1 pacote, não por quantidade. */
export function itemSubtotal(i: Partial<ProposalItem>): number {
    const qty =
        normalizeItemCategory(i) === 'assistant' && i.unlimited
            ? 1
            : Number(i.qty) || 0;

    return qty * (Number(i.unit) || 0);
}

export function itemAdjustment(i: Partial<ProposalItem>): ItemAdjustment {
    const original = itemSubtotal(i);

    if (!isUserItem(i)) {
        return {
            original,
            final: original,
            discount: 0,
            bonusQty: 0,
            kind: 'none',
            percent: 0,
        };
    }

    const unlimited = normalizeItemCategory(i) === 'assistant' && !!i.unlimited;
    // Bonificar usuário não faz sentido quando a contratação é ilimitada.
    const type =
        unlimited && i.userDiscountType === 'bonus'
            ? 'none'
            : (i.userDiscountType ?? 'none');
    const value = Math.max(0, Number(i.userDiscountValue) || 0);

    let discount = 0;
    let bonusQty = 0;
    let percent = 0;
    let kind: ItemAdjustment['kind'] = 'none';

    if (type === 'percent') {
        percent = Math.min(value, 100);
        discount = (original * percent) / 100;
        kind = 'percent';
    } else if (type === 'fixed') {
        discount = Math.min(value, original);
        kind = 'fixed';
    } else if (type === 'bonus') {
        bonusQty = Math.min(Math.floor(value), Math.max(0, Number(i.qty) || 0));
        discount = bonusQty * (Number(i.unit) || 0);
        kind = 'bonus';
    }

    if (unlimited && kind === 'none') {
        kind = 'unlimited';
    }

    return {
        original,
        final: Math.max(0, original - discount),
        discount,
        bonusQty,
        kind,
        percent,
    };
}

export function itemCommercialSubtotal(i: Partial<ProposalItem>): number {
    return itemAdjustment(i).final;
}

export function calcDiscount(
    base: number,
    type: DiscountType,
    val: number,
): number {
    if (type === 'none') {
        return 0;
    }

    if (type === 'percent') {
        return (base * Math.min(val, 100)) / 100;
    }

    return Math.min(val, base);
}

/**
 * Totais da proposta. Setup (cobrança única), mensalidade do plano e licenças por loja
 * são somados separadamente porque cada um tem seu próprio desconto.
 */
export function calculations(
    items: ProposalItem[],
    cfg: DiscountConfig,
): ProposalTotals {
    let setup = 0;
    let monthlyPlan = 0;
    let store = 0;

    items.forEach((i) => {
        const subtotal = itemCommercialSubtotal(i);

        if (i.type === 'unico') {
            setup += subtotal;
        } else if (isStoreItem(i)) {
            store += subtotal;
        } else {
            monthlyPlan += subtotal;
        }
    });

    const ds = calcDiscount(
        setup,
        cfg.setupDiscountType,
        Number(cfg.setupDiscountValue) || 0,
    );
    const dm = calcDiscount(
        monthlyPlan,
        cfg.monthlyDiscountType,
        Number(cfg.monthlyDiscountValue) || 0,
    );
    const dl = calcDiscount(
        store,
        cfg.storeDiscountType,
        Number(cfg.storeDiscountValue) || 0,
    );

    const sf = Math.max(0, setup - ds);
    const mfPlan = Math.max(0, monthlyPlan - dm);
    const storef = Math.max(0, store - dl);

    return {
        setup,
        monthlyPlan,
        store,
        ds,
        dm,
        dl,
        sf,
        mf: mfPlan + storef,
        mfPlan,
        storef,
        monthlyOriginal: monthlyPlan + store,
    };
}

/** Item em branco usado pelo botão "adicionar item livre". */
export function blankItem(): ProposalItem {
    return {
        id: uid(),
        name: '',
        note: '',
        category: 'other',
        qty: 1,
        measure: '',
        unit: 0,
        type: 'mensal',
        installments: 1,
        userDiscountType: 'none',
        userDiscountValue: 0,
        unlimited: false,
    };
}

/**
 * Itens pré-configurados dos atalhos "+ Setup / + Usuário administrativo / ...".
 *
 * Descrições e valores são conteúdo-exemplo editável (dado, não rótulo de UI), por isso
 * ficam aqui e não no arquivo de tradução.
 */
export function standardItem(category: ItemCategory): ProposalItem {
    const presets: Record<ItemCategory, Partial<ProposalItem>> = {
        setup: {
            name: 'Setup inicial',
            note: 'Implantação, parametrização, integração e configuração inicial',
            qty: 1,
            measure: 'projeto',
            unit: 0,
            type: 'unico',
            installments: 1,
        },
        admin: {
            name: 'Usuário administrativo',
            note: 'Licença mensal por usuário com perfil administrativo',
            qty: 1,
            measure: 'usuários',
            unit: 0,
        },
        assistant: {
            name: 'Usuário assistente',
            note: 'Licença mensal por usuário com perfil assistente',
            qty: 1,
            measure: 'usuários',
            unit: 0,
        },
        store: {
            name: 'Licença por loja',
            note: 'Licença mensal conforme o número de lojas contratadas',
            qty: 1,
            measure: 'lojas',
            unit: 0,
        },
        other: {},
    };

    return { ...blankItem(), category, ...presets[category] };
}

/** Proposta zerada de fábrica — base do "Restaurar padrão inicial". */
export function factoryDefaults(usedCodes: string[] = []): ProposalState {
    return {
        id: uid(),
        num: nextNum(usedCodes),
        date: today(),
        city: 'Chapecó/SC',
        validity: 10,
        plan: 'Plannerate',
        intro: '',
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
        discountReason: 'Condição comercial negociada',
        discountTerm: '',
        payment: 'Implantação na assinatura e mensalidade por boleto bancário',
        implementation:
            'Até 30 dias após a disponibilização das informações necessárias',
        adjustment: 'Anual pelo IPCA',
        due: 'Todo dia 10',
        seller: 'Anderson Siqueira',
        sellerRole: 'Responsável comercial',
        phone: '',
        email: '',
        site: 'plannerate.com.br',
        items: [
            {
                id: uid(),
                name: 'Setup inicial',
                note: 'Implantação, parametrização, integração e configuração inicial',
                category: 'setup',
                qty: 1,
                measure: 'projeto',
                unit: 60000,
                type: 'unico',
                installments: 3,
                userDiscountType: 'none',
                userDiscountValue: 0,
                unlimited: false,
            },
            {
                id: uid(),
                name: 'Usuário administrativo',
                note: 'Licença mensal por usuário com perfil administrativo',
                category: 'admin',
                qty: 2,
                measure: 'usuários',
                unit: 1500,
                type: 'mensal',
                installments: 1,
                userDiscountType: 'none',
                userDiscountValue: 0,
                unlimited: false,
            },
            {
                id: uid(),
                name: 'Usuário assistente',
                note: 'Licença mensal por usuário com perfil assistente',
                category: 'assistant',
                qty: 0,
                measure: 'usuários',
                unit: 0,
                type: 'mensal',
                installments: 1,
                userDiscountType: 'none',
                userDiscountValue: 0,
                unlimited: false,
            },
            {
                id: uid(),
                name: 'Licença por loja',
                note: 'Licença mensal conforme o número de lojas contratadas',
                category: 'store',
                qty: 18,
                measure: 'lojas',
                unit: 500,
                type: 'mensal',
                installments: 1,
                userDiscountType: 'none',
                userDiscountValue: 0,
                unlimited: false,
            },
        ],
        mods: [
            {
                name: 'Planogramas',
                desc: 'Estruturação, organização e gestão de planogramas para padronização e execução nas lojas.',
            },
            {
                name: 'Trade Marketing',
                desc: 'Gestão das ações de trade marketing, comunicação no ponto de venda e apoio à execução comercial.',
            },
        ],
        conds: [
            'Os valores consideram a quantidade de lojas indicada na composição comercial.',
            'Novas lojas ou módulos adicionais serão cobrados conforme a tabela comercial vigente.',
            'Integrações e personalizações não descritas serão avaliadas mediante escopo técnico.',
        ],
    };
}

/** Normaliza um item vindo do localStorage/JSON importado (pode ser legado ou parcial). */
export function normalizeItem(i: Partial<ProposalItem>): ProposalItem {
    return {
        ...blankItem(),
        ...i,
        category: normalizeItemCategory(i),
        installments: Math.max(1, Number(i.installments) || 1),
        userDiscountType: i.userDiscountType ?? 'none',
        userDiscountValue: Number(i.userDiscountValue) || 0,
        unlimited: !!i.unlimited,
    };
}
