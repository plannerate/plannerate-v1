/**
 * Converte um texto arbitrário em um slug amigável para URL/identificador.
 *
 * Remove acentos, coloca em minúsculas, elimina caracteres inválidos e troca
 * espaços por hífens (colapsando hífens repetidos).
 *
 * @param value Texto de origem (ex.: o nome do registro).
 * @returns Slug normalizado (ex.: "Café com Leite" -> "cafe-com-leite").
 */
export function toSlug(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}
