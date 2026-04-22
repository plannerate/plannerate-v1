/**
 * Wayfinder gera URLs absolutas com host derivado do APP_URL na geração (`//{subdomain}.…`).
 * Em dev (Valet, Sail, hosts diferentes) o host real pode não coincidir — links e forms
 * precisam usar path relativo para manter o mesmo host/porta do navegador.
 */
export function tenantWayfinderPath(url: string): string {
    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            const parsed = new URL(url);

            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        } catch {
            return url;
        }
    }

    return url.replace(/^\/\/[^/]+/, '');
}
