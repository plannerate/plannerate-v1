/**
 * Composable utilitário compartilhado para funções comuns do planograma
 *
 * Centraliza funções que são usadas em múltiplos composables para evitar duplicação
 */

/**
 * Verifica se deve mostrar modal de confirmação baseado no localStorage
 *
 * O usuário pode escolher não mostrar o modal por 5 minutos após confirmar uma exclusão.
 *
 * @param itemType - Tipo do item ('section', 'shelf', 'layer', etc.)
 * @returns true se deve mostrar o modal, false caso contrário
 */
export function shouldShowDeleteConfirm(itemType: string = 'section'): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    const storageKey = `planogram-delete-confirm-${itemType}`;
    const expiryTime = window.localStorage.getItem(storageKey);

    if (!expiryTime) {
        return true; // Sem preferência salva, mostra modal
    }

    const expiry = parseInt(expiryTime, 10);
    const now = Date.now();

    if (now > expiry) {
        // Expirou, remove e mostra modal
        window.localStorage.removeItem(storageKey);

        return true;
    }

    // Ainda dentro do período de 5 minutos, não mostra modal
    return false;
}
