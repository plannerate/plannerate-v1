<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, Copy, Link2, Loader2, Share2, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { destroy, store } from '@/actions/App/Http/Controllers/Tenant/DimensionShareTokenController';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useT } from '@/composables/useT';

const props = withDefaults(
    defineProps<{
        categoryId?: string | null;
        /** Nome da categoria, para rotular o escopo antes de gerar o link. */
        categoryLabel?: string | null;
        /** Oculta o botão embutido — use quando o dialog é aberto de fora (menu, etc.). */
        hideTrigger?: boolean;
    }>(),
    {
        categoryId: null,
        categoryLabel: null,
        hideTrigger: false,
    },
);

const { t } = useT();

// defineModel para que o dialog funcione tanto sozinho (com o próprio trigger)
// quanto controlado de fora, quando aberto por um item de menu.
const open = defineModel<boolean>('open', { default: false });
const generating = ref(false);
const revoking = ref(false);
const shareUrl = ref('');
const tokenId = ref('');
const categoryName = ref<string | null>(null);
const errorMessage = ref('');

type GenerateResponse = {
    token_id: string;
    url: string;
    category_name: string | null;
    expires_at: string | null;
};

const generateHttp = useHttp<{ category_id: string | null }, GenerateResponse>({ category_id: null });
const revokeHttp = useHttp<Record<string, never>, { ok: boolean }>({});

const scopeLabel = computed(() => {
    // Antes de gerar não há resposta do servidor: cai na prop para já mostrar o escopo.
    const category = categoryName.value ?? props.categoryLabel;

    if (category) {
        return t('app.tenant.dimensions.share.scope_category', { name: category });
    }

    return t('app.tenant.dimensions.share.scope_all');
});

// Ao fechar o diálogo, limpa o estado para não vazar o link em uma próxima abertura.
watch(open, (isOpen) => {
    if (!isOpen) {
        shareUrl.value = '';
        tokenId.value = '';
        categoryName.value = null;
        errorMessage.value = '';
    }
});

async function generate(): Promise<void> {
    if (generating.value) {
        return;
    }

    generating.value = true;
    errorMessage.value = '';

    try {
        generateHttp.category_id = props.categoryId ?? null;
        const payload = await generateHttp.submit({ url: store.url(), method: 'post' });

        shareUrl.value = payload.url;
        tokenId.value = payload.token_id;
        categoryName.value = payload.category_name;
    } catch {
        errorMessage.value = t('app.tenant.dimensions.share.error');
    } finally {
        generating.value = false;
    }
}

async function copyLink(): Promise<void> {
    try {
        await navigator.clipboard.writeText(shareUrl.value);
        toast.success(t('app.tenant.dimensions.share.copied'));
    } catch {
        errorMessage.value = t('app.tenant.dimensions.share.error');
    }
}

function shareWhatsapp(): void {
    const text = `${t('app.tenant.dimensions.share.whatsapp_message')} ${shareUrl.value}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank', 'noopener,noreferrer');
}

async function revoke(): Promise<void> {
    if (!tokenId.value || revoking.value) {
        return;
    }

    if (!window.confirm(t('app.tenant.dimensions.share.revoke_confirm'))) {
        return;
    }

    revoking.value = true;

    try {
        await revokeHttp.submit({ url: destroy.url(tokenId.value), method: 'delete' });
        shareUrl.value = '';
        tokenId.value = '';
        toast.success(t('app.tenant.dimensions.share.revoked'));
    } catch {
        errorMessage.value = t('app.tenant.dimensions.share.error');
    } finally {
        revoking.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="!hideTrigger" as-child>
            <button type="button"
                class="flex h-9 items-center gap-1.5 rounded-lg border border-border bg-background px-3 text-sm text-foreground transition hover:bg-muted">
                <Share2 class="size-3.5 shrink-0" />
                {{ t('app.tenant.dimensions.share.button') }}
            </button>
        </DialogTrigger>

        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('app.tenant.dimensions.share.title') }}</DialogTitle>
                <DialogDescription>{{ t('app.tenant.dimensions.share.description') }}</DialogDescription>
            </DialogHeader>

            <div class="grid gap-4">
                <div class="flex items-center gap-2 rounded-lg bg-muted/50 px-3 py-2 text-sm text-muted-foreground">
                    <Link2 class="size-4 shrink-0" />
                    <span>{{ scopeLabel }}</span>
                </div>

                <!-- Antes de gerar -->
                <button v-if="!shareUrl" type="button" :disabled="generating"
                    class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary text-sm font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                    @click="generate">
                    <Loader2 v-if="generating" class="size-4 animate-spin" />
                    {{ generating ? t('app.tenant.dimensions.share.generating') : t('app.tenant.dimensions.share.generate') }}
                </button>

                <!-- Após gerar -->
                <div v-else class="grid gap-3">
                    <div class="grid gap-1.5">
                        <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {{ t('app.tenant.dimensions.share.url_label') }}
                        </span>
                        <input :value="shareUrl" readonly
                            class="h-10 w-full rounded-lg border border-border bg-muted/40 px-3 font-mono text-xs text-foreground outline-none"
                            @focus="($event.target as HTMLInputElement).select()" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <button type="button"
                            class="flex h-10 items-center justify-center gap-2 rounded-lg border border-border bg-background text-sm font-medium transition hover:bg-muted"
                            @click="copyLink">
                            <Copy class="size-4" />
                            {{ t('app.tenant.dimensions.share.copy') }}
                        </button>
                        <button type="button"
                            class="flex h-10 items-center justify-center gap-2 rounded-lg bg-[#25D366] text-sm font-semibold text-white transition hover:bg-[#1eb757]"
                            @click="shareWhatsapp">
                            <Share2 class="size-4" />
                            {{ t('app.tenant.dimensions.share.whatsapp') }}
                        </button>
                    </div>

                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <Check class="size-3.5 text-green-600" />
                        {{ t('app.tenant.dimensions.share.expires_in') }}
                    </p>

                    <button type="button" :disabled="revoking"
                        class="flex h-9 items-center justify-center gap-2 rounded-lg text-sm text-destructive transition hover:bg-destructive/10 disabled:opacity-50"
                        @click="revoke">
                        <Loader2 v-if="revoking" class="size-3.5 animate-spin" />
                        <Trash2 v-else class="size-3.5" />
                        {{ t('app.tenant.dimensions.share.revoke') }}
                    </button>
                </div>

                <p v-if="errorMessage" class="text-sm text-destructive">{{ errorMessage }}</p>
            </div>
        </DialogContent>
    </Dialog>
</template>
