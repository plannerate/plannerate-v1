<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
                <Share2 class="mr-2 size-4" />
                {{ t('plannerate.dropdown.distribution.title') }}
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 z-[9999]">
            <DropdownMenuItem @click="handleShareView">
                <ExternalLink class="mr-2 size-4" />
                {{ t('plannerate.dropdown.distribution.share_view') }}
            </DropdownMenuItem>
            <DropdownMenuItem @click="handlePreviewPdf">
                <Eye class="mr-2 size-4" />
                {{ t('plannerate.dropdown.distribution.preview_pdf') }}
            </DropdownMenuItem>
            <DropdownMenuItem @click="handleDownloadPdf">
                <Download class="mr-2 size-4" />
                {{ t('plannerate.dropdown.distribution.download_pdf') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="showShareQRModal = true">
                <QrCode class="mr-2 size-4" />
                {{ t('plannerate.dropdown.distribution.share_qr') }}
            </DropdownMenuItem>
            <DropdownMenuItem @click="showShareDimensionsModal = true">
                <Ruler class="mr-2 size-4" />
                {{ t('plannerate.dropdown.distribution.share_dimensions') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
    <!-- ============================================================
         MODAL DE COMPARTILHAMENTO / QR CODE
         ============================================================ -->
    <ShareQRCodeModal v-model:open="showShareQRModal" :gondola-id="currentGondola?.id"
        :gondola-name="currentGondola?.name" />

    <!-- ============================================================
         MODAL DE CORREÇÃO DE DIMENSÕES (link público)
         Escopo: a categoria do planograma — e não os produtos da gôndola, porque
         produto sem dimensão nunca chega a ser posicionado numa gôndola. O objetivo
         é justamente medir os que ainda estão fora dela.
         ============================================================ -->
    <ShareDimensionDialog v-model:open="showShareDimensionsModal" hide-trigger
        :category-id="planogramCategoryId" :category-label="planogramCategoryName" />
</template>
<script setup lang="ts">
import { ChevronDown, Download, ExternalLink, Eye, QrCode, Ruler, Share2 } from 'lucide-vue-next';

import { computed, ref } from 'vue';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import { show as gondolaShare } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaShareController';
import ShareDimensionDialog from '@/components/tenant/dimensions/ShareDimensionDialog.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { currentGondola } from '@/composables/plannerate/core/useGondolaState';
import { useT } from '@/composables/useT';
import ShareQRCodeModal from './header/ShareQRCodeModal.vue';

const { t } = useT();

/**
 * Estado do modal de compartilhamento/QR code
 */
const showShareQRModal = ref(false);

/**
 * Estado do modal de link público de correção de dimensões
 */
const showShareDimensionsModal = ref(false);

/**
 * Categoria do planograma — escopo do link de correção de dimensões.
 */
const planogramCategoryId = computed(() => currentGondola.value?.planogram?.category_id ?? null);
const planogramCategoryName = computed(() => currentGondola.value?.planogram?.category?.name ?? null);

// ============================================================================
// PDF EXPORT HANDLERS
// ============================================================================

/**
 * Abre a visualização em uma nova aba (preview/PDF)
 * Rota: /export/gondola/{gondola}/view (Wayfinder)
 */
function handlePreviewPdf() {
    if (!currentGondola.value?.id) {
        return;
    }

    const route = gondolaView(currentGondola.value.id);
    window.open(route.url, '_blank');
}

/**
 * Abre a visualização pública da gôndola em nova aba (sem auth)
 * Destinada a repositores, fornecedores e pessoas com o link
 */
function handleShareView() {
    if (!currentGondola.value?.id) {
        return;
    }

    const route = gondolaShare(currentGondola.value.id);
    window.open(route.url, '_blank');
}

/**
 * Abre a visualização para download do PDF
 * Mesma rota - o botão de baixar está na página de visualização
 */
function handleDownloadPdf() {
    if (!currentGondola.value?.id) {
        return;
    }

    const route = gondolaView(currentGondola.value.id);
    window.open(route.url, '_blank');
}
</script>
