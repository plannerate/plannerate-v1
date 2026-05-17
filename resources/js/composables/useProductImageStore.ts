import { effectScope, reactive, watchEffect } from 'vue';
import { router } from '@inertiajs/vue3';
import { echo, echoIsConfigured } from '@laravel/echo-vue';

const _store = reactive<Record<string, string>>({});

let _individualListenerSetup = false;
let _isIndividualListening = false;
let _batchListenerSetup = false;
let _isBatchListening = false;

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

    // Escuta eventos individuais (cobre os que chegam antes da race condition fechar).
    function listenForProductImages(tenantId: string): void {
        if (_individualListenerSetup || !tenantId) return;
        _individualListenerSetup = true;

        _scope.run(() => {
            watchEffect(() => {
                if (!echoIsConfigured() || _isIndividualListening) return;
                _isIndividualListening = true;
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

    // Escuta o evento de conclusão do batch. Quando todos os produtos são processados,
    // faz reload parcial do Inertia para atualizar todos os image_url de uma vez.
    function listenForBatchComplete(userId: string): void {
        if (_batchListenerSetup || !userId) return;
        _batchListenerSetup = true;

        _scope.run(() => {
            watchEffect(() => {
                if (!echoIsConfigured() || _isBatchListening) return;
                _isBatchListening = true;
                echo()
                    .private(`App.Models.User.${userId}`)
                    .listen('.plannerate.gondola.product-images.updated', () => {
                        router.reload({ only: ['recordData'] });
                    });
            });
        });
    }

    return { setImage, getImage, listenForProductImages, listenForBatchComplete };
}
