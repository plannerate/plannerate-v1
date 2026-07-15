<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { RefreshCw } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import ProductController from '@/actions/App/Http/Controllers/Tenant/ProductController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useT } from '@/composables/useT';

/**
 * Dispara a busca sob demanda das VENDAS de um produto em UMA loja (e,
 * opcionalmente, dos dados cadastrais do produto) na API do tenant. O trabalho
 * roda numa fila; ao concluir, o backend emite `product.sales.synced` no canal
 * do tenant e este componente recarrega a página.
 *
 * Reutilizável: basta passar `:product` e a lista de `:stores`.
 */
interface SyncableProduct {
    id: string;
    name?: string | null;
    ean?: string | null;
    codigo_erp?: string | null;
}

interface StoreOption {
    id: string;
    name: string;
}

const props = withDefaults(
    defineProps<{
        product: SyncableProduct;
        stores: StoreOption[];
        /** Datas selecionadas no formulário de filtros; vazio = janela padrão. */
        dateFrom?: string | null;
        dateTo?: string | null;
        label?: string;
        variant?: 'default' | 'gradient' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
        size?: 'default' | 'sm' | 'lg';
    }>(),
    {
        dateFrom: null,
        dateTo: null,
        label: undefined,
        variant: 'outline',
        size: 'sm',
    },
);

const emit = defineEmits<{ synced: [] }>();

const { t } = useT();
const page = usePage();

const open = ref(false);
const storeId = ref('');
const updateProduct = ref(false);
const isSyncing = ref(false);

let safetyTimer: ReturnType<typeof setTimeout> | null = null;

const canSubmit = computed(() => storeId.value !== '' && !isSyncing.value);

const hasPeriod = computed(() => Boolean(props.dateFrom) || Boolean(props.dateTo));

const periodLabel = computed(() =>
    hasPeriod.value
        ? t('app.tenant.products.sync.period_custom', {
              from: props.dateFrom ?? '…',
              to: props.dateTo ?? '…',
          })
        : t('app.tenant.products.sync.period_default'),
);

function stopSyncing(): void {
    isSyncing.value = false;

    if (safetyTimer !== null) {
        clearTimeout(safetyTimer);
        safetyTimer = null;
    }
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    isSyncing.value = true;

    router.post(
        ProductController.syncSingle.url(),
        {
            product: props.product.id,
            store_id: storeId.value,
            update_product: updateProduct.value,
            date_from: props.dateFrom ?? '',
            date_to: props.dateTo ?? '',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;

                // Sem Echo, o fim do job não chega por broadcast: libera o botão.
                if (!isEchoConfigured) {
                    stopSyncing();
                }
            },
            onError: () => stopSyncing(),
        },
    );

    // Trava de segurança: nunca deixa o botão preso se o broadcast não chegar.
    safetyTimer = setTimeout(stopSyncing, 60000);
}

// ── Recarrega quando o job DESTE produto conclui (broadcast) ──
const isEchoConfigured = typeof window !== 'undefined' && window.__plannerateEchoConfigured === true;

const tenantId = computed(() => {
    const tenant = (page.props.tenant ?? null) as { id?: string } | null;

    return typeof tenant?.id === 'string' && tenant.id !== '' ? tenant.id : null;
});

if (isEchoConfigured && tenantId.value) {
    useEcho(
        `tenant.${tenantId.value}`,
        '.product.sales.synced',
        (raw: { product_id?: string; status?: string; sales?: number; products?: number; message?: string | null }) => {
            if (raw.product_id !== props.product.id) {
                return;
            }

            stopSyncing();

            if (raw.status === 'failed') {
                toast.error(raw.message || t('app.tenant.products.sync.error_generic'));

                return;
            }

            toast.success(
                t('app.tenant.products.sync.success', {
                    products: String(raw.products ?? 0),
                    sales: String(raw.sales ?? 0),
                }),
            );
            emit('synced');
            router.reload({ preserveScroll: true });
        },
    );
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button type="button" :variant="variant" :size="size" :disabled="isSyncing">
                <RefreshCw class="size-3.5 shrink-0" :class="{ 'animate-spin': isSyncing }" />
                {{ isSyncing ? t('app.tenant.products.sync.loading') : (label ?? t('app.tenant.products.sync.button')) }}
            </Button>
        </PopoverTrigger>

        <PopoverContent align="end" class="w-72 space-y-3">
            <div class="space-y-1">
                <h4 class="text-sm font-medium text-foreground">{{ t('app.tenant.products.sync.title') }}</h4>
                <p class="text-xs text-muted-foreground">{{ t('app.tenant.products.sync.description') }}</p>
                <p class="text-xs" :class="hasPeriod ? 'text-foreground' : 'text-muted-foreground'">{{ periodLabel }}</p>
            </div>

            <div class="space-y-1.5">
                <Label class="text-xs">{{ t('app.tenant.products.sync.store_label') }}</Label>
                <Select v-model="storeId">
                    <SelectTrigger class="w-full">
                        <SelectValue :placeholder="t('app.tenant.products.sync.store_placeholder')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="store in stores" :key="store.id" :value="store.id">
                            {{ store.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-xs text-foreground">
                <Checkbox
                    :model-value="updateProduct"
                    @update:model-value="(value) => (updateProduct = value === true)"
                />
                <span>{{ t('app.tenant.products.sync.update_product') }}</span>
            </label>

            <Button type="button" size="sm" class="w-full" :disabled="!canSubmit" @click="submit">
                {{ t('app.tenant.products.sync.confirm') }}
            </Button>
        </PopoverContent>
    </Popover>
</template>
