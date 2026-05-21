import { computed, shallowRef } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * Opções para configurar a store de análise por EAN.
 *
 * @template T - Tipo do valor armazenado por EAN
 */
export interface EanAnalysisStoreOptions<T> {
    /**
     * Extrai a classificação ABC do item para os contadores de stats (classA/B/C).
     * Se omitido, os contadores ficam em 0.
     */
    getClassificacao?: (item: T) => 'A' | 'B' | 'C' | undefined;
}

/**
 * Retorno público do composable criado pelo factory.
 *
 * @template T - Tipo do valor armazenado por EAN
 */
export interface EanAnalysisStore<T> {
    set(ean: string, value: T): void;
    setBatch(entries: Array<{ ean: string; value: T }>): void;
    get(ean: string | undefined): T | undefined;
    has(ean: string | undefined): boolean;
    clear(): void;
    remove(ean: string): void;
    toggleVisibility(): void;
    setVisibility(visible: boolean): void;
    /** Array reativo dos valores armazenados — para computed derivados no composable pai */
    rawValues: ComputedRef<T[]>;
    /** Estatísticas: total, classA/B/C e timestamp da última análise */
    stats: ComputedRef<{
        total: number;
        classA: number;
        classB: number;
        classC: number;
        lastAnalysis: Date | null;
    }>;
    hasData: ComputedRef<boolean>;
    isVisible: ComputedRef<boolean>;
    lastAnalysisDate: ComputedRef<Date | null>;
}

/**
 * Factory para criar stores reativas de análise por EAN.
 *
 * Gerencia um `Map<EAN, T>` singleton (estado compartilhado entre todos os
 * componentes que chamam o composable retornado) com suporte a:
 * - CRUD por EAN (set/setBatch/get/has/clear/remove)
 * - Controle de visibilidade dos indicadores visuais
 * - Timestamp da última análise (atualizado no setBatch)
 * - Estatísticas com contagem por classificação ABC
 *
 * Usa `shallowRef` para o Map a fim de evitar que o Vue tente desembrulhar
 * o tipo genérico T, o que causaria conflitos de tipo com `UnwrapRefSimple<T>`.
 *
 * @param name    - Nome identificador (usado em avisos de log)
 * @param options - Callbacks opcionais para personalizar o comportamento
 * @returns Composable function que fecha sobre o estado singleton
 *
 * @example
 * const useAbcStore = createEanAnalysisStore<'A' | 'B' | 'C'>('abc', {
 *     getClassificacao: (item) => item,
 * });
 * export function useAbcClassification() {
 *     const store = useAbcStore();
 *     // ...expõe métodos com nomes de domínio
 * }
 */
export function createEanAnalysisStore<T>(
    name: string,
    options?: EanAnalysisStoreOptions<T>,
): () => EanAnalysisStore<T> {
    // shallowRef evita que Vue tente desembrulhar T (corrige UnwrapRefSimple<T> vs T)
    const _data = shallowRef<Map<string, T>>(new Map());
    const _lastAnalysisDate = shallowRef<Date | null>(null);
    const _isVisible = shallowRef<boolean>(true);

    /**
     * Composable que fecha sobre o estado singleton acima.
     * Pode ser chamado em qualquer componente; todos compartilham o mesmo Map.
     */
    return function useStore(): EanAnalysisStore<T> {
        /**
         * Armazena o valor para um EAN.
         * Não atualiza lastAnalysisDate — use setBatch para isso.
         */
        function set(ean: string, value: T): void {
            if (!ean) {
                console.warn(`⚠️ [${name}] EAN vazio ao salvar entrada`);
                return;
            }
            // Cria novo Map para disparar reatividade do shallowRef
            const next = new Map(_data.value);
            next.set(ean, value);
            _data.value = next;
        }

        /**
         * Armazena múltiplos valores em lote e registra a data/hora da análise.
         * Entradas com EAN vazio são ignoradas silenciosamente.
         */
        function setBatch(entries: Array<{ ean: string; value: T }>): void {
            const next = new Map(_data.value);
            entries.forEach(({ ean, value }) => {
                if (ean) next.set(ean, value);
            });
            _data.value = next;
            _lastAnalysisDate.value = new Date();
        }

        /**
         * Retorna o valor armazenado para o EAN.
         * Retorna undefined se o EAN for vazio ou não existir na store.
         */
        function get(ean: string | undefined): T | undefined {
            if (!ean) return undefined;
            return _data.value.get(ean);
        }

        /**
         * Verifica se existe entrada para o EAN.
         */
        function has(ean: string | undefined): boolean {
            if (!ean) return false;
            return _data.value.has(ean);
        }

        /**
         * Remove todas as entradas e reseta o timestamp da última análise.
         */
        function clear(): void {
            _data.value = new Map();
            _lastAnalysisDate.value = null;
        }

        /**
         * Remove a entrada de um EAN específico.
         */
        function remove(ean: string): void {
            if (!ean) return;
            const next = new Map(_data.value);
            next.delete(ean);
            _data.value = next;
        }

        /**
         * Alterna entre visível e oculto para os indicadores visuais.
         */
        function toggleVisibility(): void {
            _isVisible.value = !_isVisible.value;
        }

        /**
         * Define explicitamente a visibilidade dos indicadores visuais.
         */
        function setVisibility(visible: boolean): void {
            _isVisible.value = visible;
        }

        /** Array reativo dos valores — para computed derivados no composable pai */
        const rawValues: ComputedRef<T[]> = computed(() =>
            Array.from(_data.value.values()),
        );

        /**
         * Estatísticas: total de entradas, contagem por classe ABC e
         * data/hora da última análise.
         */
        const stats = computed(() => {
            const values = rawValues.value;
            const getClass = options?.getClassificacao;

            return {
                total: values.length,
                classA: getClass ? values.filter((v) => getClass(v) === 'A').length : 0,
                classB: getClass ? values.filter((v) => getClass(v) === 'B').length : 0,
                classC: getClass ? values.filter((v) => getClass(v) === 'C').length : 0,
                lastAnalysis: _lastAnalysisDate.value,
            };
        });

        const hasData: ComputedRef<boolean> = computed(() => _data.value.size > 0);

        return {
            set,
            setBatch,
            get,
            has,
            clear,
            remove,
            toggleVisibility,
            setVisibility,
            rawValues,
            stats,
            hasData,
            isVisible: computed(() => _isVisible.value),
            lastAnalysisDate: computed(() => _lastAnalysisDate.value),
        };
    };
}
