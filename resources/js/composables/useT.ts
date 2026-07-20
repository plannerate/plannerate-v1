import { usePage } from '@inertiajs/vue3'

type TranslationNode = Record<string, unknown>

export function useT() {
    const page = usePage()

    function t(key: string, replace: Record<string, string> = {}): string {
        const keys = key.split('.')
        let value: unknown = (page.props.translations as TranslationNode | undefined) ?? {}

        for (const currentKey of keys) {
            if (value && typeof value === 'object' && currentKey in value) {
                value = (value as TranslationNode)[currentKey]
            } else {
                return key
            }
        }

        let result = String(value)

        for (const [placeholder, replacement] of Object.entries(replace)) {
            result = result.replace(`:${placeholder}`, replacement)
        }

        return result
    }

    /**
     * Lê uma chave cujo valor é uma LISTA no arquivo de tradução (ex.: as ações
     * recomendadas de cada quadrante BCG). `t()` não serve: ele faz String(value)
     * e um array viraria "[object Object]"/itens concatenados.
     *
     * Devolve `[]` quando a chave não existe ou não é uma lista.
     */
    function tList(key: string): string[] {
        const keys = key.split('.')
        let value: unknown = (page.props.translations as TranslationNode | undefined) ?? {}

        for (const currentKey of keys) {
            if (value && typeof value === 'object' && currentKey in value) {
                value = (value as TranslationNode)[currentKey]
            } else {
                return []
            }
        }

        return Array.isArray(value) ? value.map(String) : []
    }

    return { t, tList }
}
