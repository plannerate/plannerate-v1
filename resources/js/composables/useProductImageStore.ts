import { effectScope, reactive, watchEffect } from 'vue';
import { echo, echoIsConfigured } from '@laravel/echo-vue';

const _store = reactive<Record<string, string>>({});
let _listenerSetup = false;
let _isListening = false;
const _scope = effectScope(true);

export function useProductImageStore() {
    function setImage(ean: string, productId: string, imageUrl: string): void {
        if (ean) _store[ean] = imageUrl;
        if (productId) _store[productId] = imageUrl;
    }

    function getImage(ean?: string | null, productId?: string | null): string | null {
        if (ean && _store[ean]) return _store[ean];
        if (productId && _store[productId]) return _store[productId];
        return null;
    }

    function listenForProductImages(tenantId: string): void {
        if (_listenerSetup || !tenantId) return;
        _listenerSetup = true;

        _scope.run(() => {
            watchEffect(() => {
                if (!echoIsConfigured() || _isListening) return;
                _isListening = true;
                echo()
                    .private(`tenant.${tenantId}`)
                    .listen(
                        '.product.image.processed',
                        (raw: { ean?: string; product_id?: string; image_url?: string | null }) => {
                            if (raw.image_url && (raw.ean || raw.product_id)) {
                                setImage(raw.ean ?? '', raw.product_id ?? '', raw.image_url);
                            }
                        },
                    );
            });
        });
    }

    return { setImage, getImage, listenForProductImages };
}
