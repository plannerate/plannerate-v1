<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
                <MoreVertical class="mr-2 size-4" />
                {{ t('plannerate.dropdown.actions.title') }}
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 z-[9999]">
            <DropdownMenuItem @click="props.onAddModule?.()">
                <Plus class="mr-2 size-4" />
                {{ t('plannerate.toolbar.add_module') }}
            </DropdownMenuItem>
            <DropdownMenuItem @click="props.onTransferSection?.()">
                <ArrowRightLeft class="mr-2 size-4" />
                {{ t('plannerate.toolbar.transfer_section') }}
            </DropdownMenuItem>
            <DropdownMenuItem v-if="props.hasStore" @click="props.onOpenMap?.()">
                <MapPin class="mr-2 size-4" />
                {{ props.currentMapRegionId ? t('plannerate.toolbar.map_remove') : t('plannerate.toolbar.map_store') }}
            </DropdownMenuItem>
            <DropdownMenuItem v-if="props.canRemoveGondola" class="text-destructive" @click="props.onRemoveGondola?.()">
                <Trash2 class="mr-2 size-4" />
                {{ t('plannerate.toolbar.remove_gondola') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="showShareQRModal = true">
                <Share2 class="mr-2 size-4" />
                {{ t('plannerate.dropdown.actions.share_qr') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="handlePreviewPdf">
                <Eye class="mr-2 size-4" />
                {{ t('plannerate.dropdown.actions.preview_pdf') }}
            </DropdownMenuItem>
            <DropdownMenuItem @click="handleDownloadPdf">
                <Download class="mr-2 size-4" />
                {{ t('plannerate.dropdown.actions.download_pdf') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="editor.showReports()">
                <FileText class="mr-2 size-4" />
                {{ t('plannerate.dropdown.actions.reports') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
    <!-- ============================================================
         MODAL DE COMPARTILHAMENTO / QR CODE
         ============================================================ -->
    <ShareQRCodeModal v-model:open="showShareQRModal" :gondola-id="currentGondola?.id"
        :gondola-name="currentGondola?.name" />

</template>
<script setup lang="ts">
import { ArrowRightLeft, ChevronDown, Download, Eye, FileText, MapPin, MoreVertical, Plus, Share2, Trash2 } from 'lucide-vue-next';

import { ref } from 'vue';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { currentGondola } from '@/composables/plannerate/editor/useGondolaState';
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { useT } from '@/composables/useT';
import ShareQRCodeModal from './header/ShareQRCodeModal.vue';

const props = defineProps<{
    canRemoveGondola?: boolean;
    hasStore?: boolean;
    currentMapRegionId?: string | null;
    onAddModule?: () => void;
    onTransferSection?: () => void;
    onOpenMap?: () => void;
    onRemoveGondola?: () => void;
}>();

/**
 * Acessa o estado global do editor de planogramas
 * Singleton - mesma instância compartilhada entre todos os componentes
 */
const editor = usePlanogramEditor();
const { t } = useT();

/**
 * Estado do modal de compartilhamento/QR code
 */
const showShareQRModal = ref(false);

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
