export function wayfinderPath(url: string): string {
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
