import { ref } from 'vue';

// Module-level callback — shared across the component tree regardless of hierarchy
const onProductPlacedCallback = ref<((productId: string) => void) | null>(null);

export function useRejectedProductsStore() {
    function setOnProductPlaced(cb: (productId: string) => void) {
        onProductPlacedCallback.value = cb;
    }

    function clearOnProductPlaced() {
        onProductPlacedCallback.value = null;
    }

    function notifyProductPlaced(productId: string) {
        onProductPlacedCallback.value?.(productId);
    }

    return { setOnProductPlaced, clearOnProductPlaced, notifyProductPlaced };
}
