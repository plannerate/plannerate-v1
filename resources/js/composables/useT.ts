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

    return { t }
}
