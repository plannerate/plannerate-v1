import { useT } from '@/composables/useT'
import { AXIS_LABEL_KEYS } from './types'
import type {BcgAxis, BcgQuadrant, BcgSpaceAction} from './types';

/**
 * Rótulos, cores e descrições dos quadrantes da Análise de Quadrante.
 *
 * Fica num só lugar porque a lista, o painel de seleção e o selo da gôndola precisam
 * exatamente da mesma composição.
 *
 * O rótulo é FIXO: os quatro nomes ("Alto desempenho — Proteger", "Incentivo — Margem",
 * "Incentivo — Giro", "Baixo desempenho — Revisar") valem em qualquer combinação de
 * eixos. É a nomenclatura que o usuário reconhece; trocá-la por um texto derivado das
 * métricas ("Alto em Valor de Venda e Margem") quebra o reconhecimento sem ganhar
 * precisão.
 */
export function useBcgLabels() {
    const { t, tList } = useT()

    const axisLabel = (axis: BcgAxis): string => t(AXIS_LABEL_KEYS[axis])

    /**
     * Rótulo do quadrante — sempre os mesmos nomes, independentemente dos eixos.
     *
     * Os eixos continuam na assinatura porque as chamadas os passam e porque a
     * granularidade de eixo pode voltar a importar; hoje não influenciam o rótulo.
     */
    const quadrantLabel = (quadrant: BcgQuadrant, _xAxis?: BcgAxis, _yAxis?: BcgAxis): string =>
        t(`plannerate.analysis.bcg_results.canonical.${quadrant}`)

    /** Descrição estratégica do quadrante (não é ordem de ação — ver docs/BCG-PLANO.md). */
    const quadrantDescription = (quadrant: BcgQuadrant): string =>
        t(`plannerate.analysis.bcg_selection.${quadrant}_desc`)

    /**
     * Ações recomendadas do quadrante — sempre presentes, como o rótulo.
     *
     * A lista acompanha o quadrante, não o par de eixos: se o produto é lido como
     * "Incentivo — Margem" em qualquer configuração, a recomendação que vem junto
     * também tem de aparecer. Deixá-la vazia fora de quantidade × margem dava um
     * painel com diagnóstico e sem encaminhamento.
     */
    const quadrantActions = (quadrant: BcgQuadrant, _xAxis?: BcgAxis, _yAxis?: BcgAxis): string[] =>
        tList(`plannerate.analysis.bcg_results.canonical_actions.${quadrant}`)

    /** Título da lista de ações recomendadas. */
    const actionsTitle = (): string => t('plannerate.analysis.bcg_results.actions_title')

    /** Símbolo do quadrante — usado no filtro e no selo da gôndola. */
    const quadrantIcon = (quadrant: BcgQuadrant): string => {
        const icons: Record<BcgQuadrant, string> = {
            alto_alto: '★',
            forte_x: '▶',
            forte_y: '▲',
            baixo_baixo: '▽',
        }

        return icons[quadrant] ?? ''
    }

    const quadrantBadgeClass = (quadrant: BcgQuadrant): string => {
        const classes: Record<BcgQuadrant, string> = {
            alto_alto: 'border-green-300 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900/40 dark:text-green-200',
            forte_x: 'border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
            forte_y: 'border-yellow-300 bg-yellow-100 text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-200',
            baixo_baixo: 'border-red-300 bg-red-100 text-red-800 dark:border-red-700 dark:bg-red-900/40 dark:text-red-200',
        }

        return classes[quadrant] ?? ''
    }

    const quadrantStyle = (quadrant: BcgQuadrant): { border: string; bg: string; text: string } => {
        const styles: Record<BcgQuadrant, { border: string; bg: string; text: string }> = {
            alto_alto: {
                border: 'border-green-300 dark:border-green-700',
                bg: 'bg-green-50 dark:bg-green-950/30',
                text: 'text-green-700 dark:text-green-300',
            },
            forte_x: {
                border: 'border-blue-300 dark:border-blue-700',
                bg: 'bg-blue-50 dark:bg-blue-950/30',
                text: 'text-blue-700 dark:text-blue-300',
            },
            forte_y: {
                border: 'border-yellow-300 dark:border-yellow-700',
                bg: 'bg-yellow-50 dark:bg-yellow-950/30',
                text: 'text-yellow-700 dark:text-yellow-300',
            },
            baixo_baixo: {
                border: 'border-red-300 dark:border-red-700',
                bg: 'bg-red-50 dark:bg-red-950/30',
                text: 'text-red-700 dark:text-red-300',
            },
        }

        return styles[quadrant] ?? styles.baixo_baixo
    }

    const rowClass = (quadrant: BcgQuadrant, isSelected: boolean): string => {
        const base = isSelected ? 'ring-1 ring-primary/40 ' : ''

        const classes: Record<BcgQuadrant, string> = {
            alto_alto: 'bg-green-50/70 dark:bg-green-950/25',
            forte_x: 'bg-blue-50/70 dark:bg-blue-950/25',
            forte_y: 'bg-yellow-50/70 dark:bg-yellow-950/25',
            baixo_baixo: 'bg-red-50/70 dark:bg-red-950/25',
        }

        return base + (classes[quadrant] ?? '')
    }

    /** Rótulo da ação de espaço; null (produto sem dimensão) vira travessão. */
    const spaceActionLabel = (action: BcgSpaceAction): string =>
        action ? t(`plannerate.analysis.bcg_results.action.${action}`) : '—'

    /** Seta da ação — a ação é lida por ÍCONE + TEXTO, nunca por cor. */
    const spaceActionIcon = (action: BcgSpaceAction): string => {
        const icons: Record<string, string> = {
            aumentar: '↑',
            reduzir: '↓',
            manter: '=',
        }

        return action ? (icons[action] ?? '') : '—'
    }

    /**
     * A ação NÃO usa matiz.
     *
     * As cores dos quadrantes (verde/azul/amarelo/vermelho) já ocupam o canal de matiz
     * nesta tela. Pintar a ação de verde/âmbar faria dois significados diferentes
     * disputarem a mesma cor na mesma linha da tabela — e um verde de "ação" seria
     * confundido com o verde do quadrante. A ação é carregada por seta + texto.
     */
    const spaceActionClass = (action: BcgSpaceAction): string =>
        action === 'manter' || action === null
            ? 'border-dashed border-border text-muted-foreground'
            : 'border-border bg-muted font-semibold text-foreground'

    return {
        axisLabel,
        quadrantLabel,
        quadrantDescription,
        quadrantActions,
        actionsTitle,
        quadrantIcon,
        quadrantBadgeClass,
        quadrantStyle,
        rowClass,
        spaceActionLabel,
        spaceActionIcon,
        spaceActionClass,
    }
}
