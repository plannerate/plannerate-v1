import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { update } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Api/ProductImageController';

export function useProductImage() {
    const isDownloading = ref(false);
    const updateAction = update;

    /**
     * Baixa e atualiza a imagem do produto a partir do servidor usando o EAN
     */
    async function downloadAndUpdateImage(
        productId: string,
        productEan?: string | null,
    ) { 
        if (!productEan || isDownloading.value) {
            return false;
        }

        isDownloading.value = true; 

        return new Promise<boolean>((resolve) => {
            if (!updateAction) {
                resolve(false);

                return;
            }

            const formData = new FormData();
            formData.append('product_id', productId);

            router.post(updateAction.url(), formData, {
                preserveState: false,
                preserveScroll: true,
                onSuccess: () => {
                    resolve(true);
                    isDownloading.value = false; 
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    const message = typeof firstError === 'string'
                        ? firstError
                        : 'Não foi possível atualizar a imagem.';
                    console.error('Erro ao atualizar imagem:', errors);
                    toast.error(message);
                    resolve(false);
                    isDownloading.value = false;
                },
                onFinish: () => {
                    isDownloading.value = false;
                },
            });
        });
    }

    return {
        isDownloading,
        downloadAndUpdateImage,
    };
}
