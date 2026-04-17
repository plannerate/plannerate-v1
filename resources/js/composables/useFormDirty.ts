import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

interface DirtyForm {
    isDirty: boolean;
}

/**
 * Detecta mudanças não salvas em um Inertia form e avisa o usuário ao tentar sair.
 *
 * Registra:
 *  - beforeunload (fechar aba / recarregar)
 *  - Inertia router before (navegação interna)
 *
 * @example
 * const form = useForm({ name: '', status: 'draft' })
 * const { isDirty } = useFormDirty(form)
 */
export function useFormDirty(form: DirtyForm) {
    const message = 'Você tem alterações não salvas. Deseja sair mesmo assim?';

    function handleBeforeUnload(event: BeforeUnloadEvent): string | undefined {
        if (form.isDirty) {
            event.preventDefault();
            // Necessário para alguns navegadores mostrarem o diálogo nativo
            event.returnValue = message;
            return message;
        }
        return undefined;
    }

    let removeRouterHandler: (() => void) | null = null;

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload);

        // Intercepta navegação interna do Inertia
        removeRouterHandler = router.on('before', () => {
            if (form.isDirty) {
                return window.confirm(message);
            }
            return true;
        });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);
        if (removeRouterHandler) {
            removeRouterHandler();
        }
    });

    return {
        /** Mesmo isDirty do form Inertia, exposto para uso no template */
        get isDirty() {
            return form.isDirty;
        },
    };
}
