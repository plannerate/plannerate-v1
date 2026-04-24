<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { toast } from 'vue-sonner';
import { generateQrCode } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Export/GondolaExportController';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Tenant/Plannerate/GondolaPdfPreviewController';
import {
    Download,
    Copy,
    Share2,
    QrCode,
    Loader2,
    AlertCircle,
} from 'lucide-vue-next';

// Props
const props = defineProps<{
    open: boolean;
    gondolaId?: string;
    gondolaName?: string;
}>();

// Emits
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

// Composables
const success = (msg: string) => toast.success(msg);
const showError = (msg: string) => toast.error(msg);

// State
const qrCodeDataUri = ref<string>('');
const isLoading = ref(false);

// URL de compartilhamento usando Wayfinder
const shareUrl = computed(() => {
    if (!props.gondolaId) return '';
    const baseUrl = window.location.origin;
    const route = gondolaView(props.gondolaId);
    return `${baseUrl}${route.url}`;
});

// Watchers
watch(() => props.open, async (isOpen) => {
    if (isOpen && props.gondolaId) {
        await loadQRCode();
    }
});

// Methods
async function loadQRCode() {
    if (!props.gondolaId) return;
    
    isLoading.value = true;
    try {
        const action = generateQrCode(props.gondolaId);
        const response = await fetch(action.url);
        const data = await response.json();
        
        if (data.success && data.qr_code) {
            qrCodeDataUri.value = data.qr_code;
        }
    } catch (err) {
        console.error('Erro ao carregar QR Code:', err);
        showError('Não foi possível gerar o QR Code');
    } finally {
        isLoading.value = false;
    }
}

async function copyLink() {
    try {
        // Tenta usar a Clipboard API moderna
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(shareUrl.value);
            success('Link copiado para a área de transferência!');
        } else {
            // Fallback para método antigo (funciona em HTTP)
            const textarea = document.createElement('textarea');
            textarea.value = shareUrl.value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            success('Link copiado para a área de transferência!');
        }
    } catch (err) {
        console.error('Erro ao copiar link:', err);
        showError('Não foi possível copiar o link');
    }
}

async function shareLink() {
    if (navigator.share) {
        try {
            await navigator.share({
                title: props.gondolaName || 'Gôndola',
                text: `Confira esta gôndola: ${props.gondolaName}`,
                url: shareUrl.value,
            });
        } catch (error) {
            if ((error as Error).name !== 'AbortError') {
                console.error('Erro ao compartilhar:', error);
            }
        }
    } else {
        // Fallback: copiar link
        await copyLink();
    }
}

function downloadQRCode() {
    if (!qrCodeDataUri.value) return;
    
    const link = document.createElement('a');
    link.href = qrCodeDataUri.value;
    link.download = `qrcode-gondola-${props.gondolaId}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    success('QR Code baixado com sucesso!');
}

// function close() {
//     emit('update:open', false);
// }
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <QrCode class="size-5" />
                    Compartilhar Gôndola
                </DialogTitle>
                <DialogDescription>
                    {{ gondolaName || 'Gôndola sem nome' }}
                </DialogDescription>
            </DialogHeader>

            <!-- Error: No Gondola ID -->
            <div v-if="!gondolaId" class="space-y-4">
                <div class="flex items-center gap-3 p-4 bg-destructive/10 text-destructive rounded-lg border border-destructive/20">
                    <AlertCircle class="size-5 shrink-0" />
                    <div class="text-sm">
                        <p class="font-medium">Nenhuma gôndola selecionada</p>
                        <p class="text-xs opacity-90 mt-1">Selecione uma gôndola para gerar o QR Code</p>
                    </div>
                </div>
            </div>

            <div v-else class="space-y-4">
                <!-- QR Code Display -->
                <div class="flex justify-center p-4 bg-muted/50 rounded-lg">
                    <div v-if="isLoading" class="flex items-center justify-center h-64">
                        <Loader2 class="size-8 animate-spin text-muted-foreground" />
                    </div>
                    <img
                        v-else-if="qrCodeDataUri"
                        :src="qrCodeDataUri"
                        alt="QR Code"
                        class="w-64 h-64"
                    />
                    <div v-else class="flex items-center justify-center h-64 text-muted-foreground">
                        <p>QR Code não disponível</p>
                    </div>
                </div>

                <!-- Share URL -->
                <div class="space-y-2">
                    <Label for="share-url">Link de Acesso</Label>
                    <div class="flex gap-2">
                        <Input
                            id="share-url"
                            :model-value="shareUrl"
                            readonly
                            class="flex-1 font-mono text-xs"
                            placeholder="URL será gerada aqui..."
                        />
                        <Button
                            variant="outline"
                            size="icon"
                            @click="copyLink"
                            title="Copiar link"
                            :disabled="!shareUrl"
                        >
                            <Copy class="size-4" />
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Este link abre uma visualização somente leitura da gôndola
                    </p>
                </div>

                <Separator />

                <!-- Action Buttons -->
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        class="flex-1"
                        @click="downloadQRCode"
                        :disabled="!qrCodeDataUri"
                    >
                        <Download class="mr-2 size-4" />
                        Baixar QR Code
                    </Button>
                    <Button
                        variant="default"
                        class="flex-1"
                        @click="shareLink"
                        :disabled="!shareUrl"
                    >
                        <Share2 class="mr-2 size-4" />
                        Compartilhar
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
