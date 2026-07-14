// ============================================================================
// REGISTRO CONFIGURÁVEL DE INDICADORES DE PRODUTO
// ----------------------------------------------------------------------------
// Define a lista de "indicadores" que podem ser exibidos como um selo (badge)
// na frente de cada produto na gôndola (ex.: Preço, Margem, Estoque, Ruptura).
//
// Para adicionar um novo indicador, basta inserir um objeto neste array — o
// dropdown da toolbar e o componente de selo (`ProductIndicatorBadge.vue`) são
// montados dinamicamente a partir daqui, sem precisar tocar em mais nada.
//
// Cada indicador tem uma FONTE de dados (`source`):
//  - 'product' → lê um campo direto do produto (ex.: estoque atual);
//  - 'sales'   → lê as métricas de vendas (preço/margem) carregadas em lote do
//                backend e keyed por EAN (veja `useSalesIndicators`).
// ============================================================================

import { AlertTriangle, Banknote, Coins, Package, Percent, Tag  } from 'lucide-vue-next';
import type {LucideIcon} from 'lucide-vue-next';
import type { SalesIndicatorData } from '@/composables/plannerate/analysis/useSalesIndicators';
import type { Product } from '@/types/planogram';

/** De onde o indicador lê seu valor. */
export type IndicatorSource = 'product' | 'sales';

/**
 * Contexto entregue aos callbacks do indicador: o produto e, quando a fonte é
 * 'sales', o resumo de vendas correspondente (por EAN).
 */
export interface IndicatorContext {
    product: Product;
    sales?: SalesIndicatorData;
}

/**
 * Configuração de um único indicador exibível na frente do produto.
 *
 * @property key        Identificador único do indicador (usado no estado global).
 * @property labelKey   Chave de tradução para o rótulo no dropdown.
 * @property icon       Ícone (lucide) exibido no dropdown e dentro do selo.
 * @property source     Fonte do dado ('product' padrão, ou 'sales').
 * @property field      Campo do produto lido diretamente (só para source 'product').
 * @property accessor   Deriva o valor a partir do contexto (campos calculados / vendas).
 * @property format     Formata o valor bruto para exibição no selo.
 * @property shouldShow Decide se o selo deve aparecer para este produto/valor.
 * @property badgeClass Classes Tailwind do selo (cor de fundo + texto).
 * @property iconClass  Classes Tailwind extras do ícone dentro do selo.
 */
export interface IndicatorConfig {
    key: string;
    labelKey: string;
    icon: LucideIcon;
    source?: IndicatorSource;
    field?: keyof Product;
    accessor?: (ctx: IndicatorContext) => number | string | null | undefined;
    format?: (value: unknown, ctx: IndicatorContext) => string;
    shouldShow?: (value: unknown, ctx: IndicatorContext) => boolean;
    badgeClass: string;
    iconClass?: string;
}

/** Formata um número como moeda brasileira (R$). */
function formatCurrency(value: unknown): string {
    const num = Number(value);

    if (!Number.isFinite(num)) {
        return '';
    }

    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(num);
}

/** Formata um número como percentual (ex.: 23,5%). */
function formatPercent(value: unknown): string {
    const num = Number(value);

    if (!Number.isFinite(num)) {
        return '';
    }

    return (
        new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 1,
        }).format(num) + '%'
    );
}

/** Formata um número inteiro de unidades (ex.: "12 un"). */
function formatUnits(value: unknown): string {
    const num = Number(value);

    if (!Number.isFinite(num)) {
        return '';
    }

    return `${Math.round(num)} un`;
}

/** Helper de visibilidade: valor numérico finito e positivo. */
function isPositiveNumber(value: unknown): boolean {
    const num = Number(value);

    return Number.isFinite(num) && num > 0;
}

/**
 * Lista de indicadores disponíveis. A ORDEM aqui é a ordem exibida no dropdown.
 *
 * Cores seguem a convenção visual do editor:
 * - Preço  → verde  (positivo / comercial)
 * - Margem → roxo   (rentabilidade)
 * - Estoque→ azul   (operacional)
 * - Ruptura→ vermelho (alerta crítico)
 */
export const PRODUCT_INDICATORS: IndicatorConfig[] = [
    {
        key: 'price',
        labelKey: 'plannerate.dropdown.indicators.price',
        icon: Tag,
        source: 'sales',
        // Preço médio por unidade vindo das vendas (faturamento ÷ quantidade).
        accessor: (ctx) => ctx.sales?.avgPrice,
        format: (value) => formatCurrency(value),
        shouldShow: (value) => isPositiveNumber(value),
        badgeClass: 'bg-green-600 text-white',
        iconClass: 'text-white/90',
    },
    {
        key: 'cost',
        labelKey: 'plannerate.dropdown.indicators.cost',
        icon: Coins,
        source: 'sales',
        // Custo médio por unidade (custo de aquisição ÷ quantidade) vindo das vendas.
        accessor: (ctx) => ctx.sales?.avgCost,
        format: (value) => formatCurrency(value),
        shouldShow: (value) => isPositiveNumber(value),
        badgeClass: 'bg-amber-600 text-white',
        iconClass: 'text-white/90',
    },
    {
        key: 'margin',
        labelKey: 'plannerate.dropdown.indicators.margin',
        icon: Percent,
        source: 'sales',
        // Margem líquida (%) vinda das vendas.
        accessor: (ctx) => ctx.sales?.netMarginPct,
        format: (value) => formatPercent(value),
        shouldShow: (value) => Number.isFinite(Number(value)) && Number(value) !== 0,
        badgeClass: 'bg-purple-600 text-white',
        iconClass: 'text-white/90',
    },
    {
        key: 'margin_unit',
        labelKey: 'plannerate.dropdown.indicators.margin_unit',
        icon: Banknote,
        source: 'sales',
        // Margem líquida por unidade (margem de contribuição ÷ quantidade), em R$.
        accessor: (ctx) => ctx.sales?.avgMargin,
        format: (value) => formatCurrency(value),
        shouldShow: (value) => Number.isFinite(Number(value)) && Number(value) !== 0,
        badgeClass: 'bg-indigo-600 text-white',
        iconClass: 'text-white/90',
    },
    {
        key: 'stock',
        labelKey: 'plannerate.dropdown.indicators.stock',
        icon: Package,
        source: 'product',
        field: 'current_stock',
        format: (value) => formatUnits(value),
        shouldShow: (value) => value !== null && value !== undefined && Number.isFinite(Number(value)),
        badgeClass: 'bg-blue-600 text-white',
        iconClass: 'text-white/90',
    },
    {
        key: 'rupture',
        labelKey: 'plannerate.dropdown.indicators.rupture',
        icon: AlertTriangle,
        source: 'product',
        // Ruptura = sem estoque. O valor bruto é o estoque atual.
        accessor: (ctx) => ctx.product.current_stock,
        format: () => 'Ruptura',
        // Só aparece quando o estoque é zero/negativo (produto em ruptura).
        shouldShow: (value) => value !== null && value !== undefined && Number(value) <= 0,
        badgeClass: 'bg-red-600 text-white',
        iconClass: 'text-white',
    },
];

/** Chave especial que representa "nenhum indicador selecionado". */
export const INDICATOR_NONE = 'none';

/** Busca a configuração de um indicador pela sua chave. */
export function getIndicatorConfig(key: string | null): IndicatorConfig | undefined {
    if (!key || key === INDICATOR_NONE) {
        return undefined;
    }

    return PRODUCT_INDICATORS.find((indicator) => indicator.key === key);
}

/** Indica se o indicador depende dos dados de vendas (carregamento em lote). */
export function indicatorNeedsSales(key: string | null): boolean {
    return getIndicatorConfig(key)?.source === 'sales';
}
