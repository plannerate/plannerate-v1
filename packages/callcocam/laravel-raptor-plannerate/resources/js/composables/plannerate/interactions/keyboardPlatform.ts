/**
 * Helpers de plataforma para atalhos de teclado do editor.
 *
 * Isola o "cross-OS" (Mac vs Windows/Linux) num só lugar, seguindo o padrão puro
 * e testável de `dnd/transfer.ts::isCopyModifier`.
 */

/**
 * Detecta macOS. Impuro (lê `navigator`) — por isso fica separado de
 * `isDeleteShortcut`, que recebe `isMac` explícito e permanece testável em node.
 */
export function isMacPlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const platform =
        (navigator as unknown as { userAgentData?: { platform?: string } })
            .userAgentData?.platform ||
        navigator.platform ||
        navigator.userAgent ||
        '';

    return /Mac|iPhone|iPad|iPod/i.test(platform);
}

/**
 * True quando a tecla deve deletar o item selecionado.
 *
 * No macOS a tecla rotulada "delete" do notebook emite `Backspace` (o MacBook não
 * tem tecla forward-Delete), então aceitamos `Backspace` SÓ no Mac. Em
 * Windows/Linux mantém apenas `Delete` (têm tecla Delete dedicada; ali `Backspace`
 * é "voltar" e é fácil apertar sem querer).
 */
export function isDeleteShortcut(event: KeyboardEvent, isMac: boolean): boolean {
    if (event.key === 'Delete') {
        return true;
    }

    return isMac && event.key === 'Backspace';
}
