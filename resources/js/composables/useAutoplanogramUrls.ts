import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

function stripDomain(url: string): string {
    return url.replace(/^\/\/[^/]+/, '');
}

export function useAutoplanogramUrls(gondolaId: string) {
    const page = usePage<{ subdomain?: string }>();

    const subdomain = computed(() => {
        const s = page.props.subdomain?.toString().trim();

        if (s) return s;

        if (typeof window !== 'undefined') {
            return window.location.hostname.split('.')[0] || '';
        }

        return '';
    });

    function rejectedProductsUrl(): string {
        return stripDomain(
            `//${subdomain.value}.plannerate.localhost/api/gondolas/${gondolaId}/rejected-products`,
        );
    }

    function swapProductUrl(): string {
        return stripDomain(
            `//${subdomain.value}.plannerate.localhost/api/gondolas/${gondolaId}/swap-product`,
        );
    }

    return { subdomain, rejectedProductsUrl, swapProductUrl };
}
