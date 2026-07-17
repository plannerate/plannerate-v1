import { router } from '@inertiajs/vue3';
import { readonly, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    copyToGondola,
    transfer,
} from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Editor/SectionController';
import { useT } from '@/composables/useT';
import type { Section } from '@/types/planogram';
import { currentGondola } from '../core/useGondolaState';

/**
 * "Área de transferência" de módulo (Section) entre gôndolas — o análogo do
 * copiar/colar de segmento, mas para o módulo inteiro.
 *
 * Diferença crucial: trocar de gôndola é uma navegação Inertia (reload de
 * página), então o clipboard precisa ser PERSISTENTE (localStorage) para
 * sobreviver ao salto até a gôndola de destino. E, como a outra gôndola não
 * está em memória, o colar cross-gôndola passa pelo backend:
 * - `copy` → endpoint de cópia profunda (novos IDs, mantém produtos);
 * - `cut`  → endpoint de transferência (move a section).
 */

const STORAGE_KEY = 'plannerate:module_clipboard';
// Flag cross-página/cross-aba: um módulo recortado saiu desta gôndola de origem.
// Como trocar de gôndola é navegação (a página de origem pode vir do cache de
// histórico/bfcache do navegador) e a origem pode estar aberta em OUTRA aba do
// navegador, sinalizamos aqui. A aba de origem se atualiza sozinha ao reabrir
// (onMounted/watcher/pageshow) ou, se já estiver aberta, ao receber o evento
// `storage` desta chave (exportada como MODULE_MOVED_STORAGE_KEY).
export const MODULE_MOVED_STORAGE_KEY = 'plannerate:module_moved';
const MOVED_FLAG_KEY = MODULE_MOVED_STORAGE_KEY;
const MAX_AGE_MS = 24 * 60 * 60 * 1000; // 1 dia
const isBrowser = typeof window !== 'undefined';

export interface ModuleClipboard {
    sectionId: string;
    sourceGondolaId: string;
    sourcePlanogramId: string;
    sectionName: string;
    operation: 'copy' | 'cut';
    copiedAt: number;
}

interface MovedFlag {
    sourceGondolaId: string;
    sectionId: string;
}

function loadFromStorage(): ModuleClipboard | null {
    if (!isBrowser) {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as ModuleClipboard;

        // Descarta clipboard sem id ou esquecido (evita colar algo de dias atrás)
        if (!parsed?.sectionId || Date.now() - (parsed.copiedAt ?? 0) > MAX_AGE_MS) {
            window.localStorage.removeItem(STORAGE_KEY);

            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

function persist(value: ModuleClipboard | null): void {
    if (!isBrowser) {
        return;
    }

    try {
        if (value) {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        } else {
            window.localStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // Ignora quota cheia — clipboard é conveniência, não dado crítico
    }
}

// Singleton module-level (sobrevive dentro da página; o localStorage sobrevive à
// navegação entre gôndolas — o ref é reidratado a cada load a partir dele).
const clipboard = ref<ModuleClipboard | null>(loadFromStorage());
const isPasting = ref(false);
let storageListenerAttached = false;

export function useModuleClipboard() {
    const { t } = useT();

    // Sincroniza entre abas do mesmo navegador (padrão do histórico do dialog)
    if (isBrowser && !storageListenerAttached) {
        storageListenerAttached = true;
        window.addEventListener('storage', (event) => {
            if (event.key === STORAGE_KEY) {
                clipboard.value = loadFromStorage();
            }
        });
    }

    /**
     * Copia (ou recorta) o módulo selecionado para o clipboard persistente.
     */
    function copyModule(section: Section, operation: 'copy' | 'cut'): void {
        const gondola = currentGondola.value;

        if (!section?.id || !gondola?.id) {
            return;
        }

        const sectionName =
            section.name || t('plannerate.editor.module_clipboard.unnamed_module');

        const entry: ModuleClipboard = {
            sectionId: section.id,
            sourceGondolaId: gondola.id,
            sourcePlanogramId: gondola.planogram_id ?? '',
            sectionName,
            operation,
            copiedAt: Date.now(),
        };

        clipboard.value = entry;
        persist(entry);

        toast.info(
            operation === 'copy'
                ? t('plannerate.editor.module_clipboard.module_copied', { name: sectionName })
                : t('plannerate.editor.module_clipboard.module_cut', { name: sectionName }),
        );
    }

    function clearClipboard(): void {
        clipboard.value = null;
        persist(null);
    }

    /**
     * Cola o módulo do clipboard na gôndola atualmente aberta (no fim). Copy
     * mantém o clipboard (permite colar várias cópias); cut limpa após mover.
     */
    function pasteIntoCurrentGondola(): void {
        const entry = clipboard.value;
        const gondola = currentGondola.value;

        if (!entry || !gondola?.id) {
            toast.info(t('plannerate.editor.module_clipboard.paste_no_target'));

            return;
        }

        // Recortar para a mesma gôndola não faz sentido (o backend também rejeita)
        if (entry.operation === 'cut' && entry.sourceGondolaId === gondola.id) {
            toast.warning(t('plannerate.editor.module_clipboard.same_gondola'));

            return;
        }

        if (isPasting.value) {
            return;
        }

        isPasting.value = true;

        const action = entry.operation === 'copy' ? copyToGondola : transfer;

        router.post(
            action.url({ section: entry.sectionId }),
            { gondola_id: gondola.id },
            {
                preserveScroll: true,
                preserveState: false, // força reload — a gôndola destino não está em memória
                onSuccess: () => {
                    if (entry.operation === 'copy') {
                        toast.success(t('plannerate.editor.module_clipboard.paste_success_copy'));
                    } else {
                        toast.success(t('plannerate.editor.module_clipboard.paste_success_cut'));
                        // Marca a gôndola de origem para se atualizar sozinha ao
                        // ser reaberta (o módulo saiu dela pelo move).
                        markModuleMoved(entry.sourceGondolaId, entry.sectionId);
                        clearClipboard();
                    }
                },
                onError: (errors) => {
                    const message =
                        typeof errors === 'string'
                            ? errors
                            : (errors as { error?: string })?.error ||
                              t('plannerate.editor.module_clipboard.paste_failed');
                    toast.error(message);
                },
                onFinish: () => {
                    isPasting.value = false;
                },
            },
        );
    }

    /**
     * Registra que um módulo saiu (por recorte/move) da gôndola de origem.
     */
    function markModuleMoved(sourceGondolaId: string, sectionId: string): void {
        if (!isBrowser) {
            return;
        }

        try {
            window.localStorage.setItem(
                MOVED_FLAG_KEY,
                JSON.stringify({ sourceGondolaId, sectionId } satisfies MovedFlag),
            );
        } catch {
            // Ignora quota cheia
        }
    }

    /**
     * Ao abrir uma gôndola, decide se ela está "velha" por um módulo recortado
     * que já saiu dela — caso em que a página veio do cache do navegador ainda
     * mostrando o módulo. Consome o flag IMEDIATAMENTE (à prova de loop de
     * reload) e retorna true só quando a gôndola ainda lista o módulo movido;
     * nesse caso o chamador deve buscar o record fresco do servidor.
     */
    function consumeStaleSourceReload(
        currentGondolaId: string,
        currentSectionIds: string[],
    ): boolean {
        if (!isBrowser) {
            return false;
        }

        let flag: MovedFlag | null = null;

        try {
            const raw = window.localStorage.getItem(MOVED_FLAG_KEY);
            flag = raw ? (JSON.parse(raw) as MovedFlag) : null;
        } catch {
            flag = null;
        }

        if (!flag || flag.sourceGondolaId !== currentGondolaId) {
            return false;
        }

        // Consome já — evita qualquer risco de reload em loop
        try {
            window.localStorage.removeItem(MOVED_FLAG_KEY);
        } catch {
            // Ignora
        }

        return currentSectionIds.includes(flag.sectionId);
    }

    return {
        clipboard: readonly(clipboard),
        isPasting: readonly(isPasting),
        copyModule,
        clearClipboard,
        pasteIntoCurrentGondola,
        consumeStaleSourceReload,
    };
}
