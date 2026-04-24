<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
                <MoreVertical class="mr-2 size-4" />
                Ações
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 z-[9999]">
            <DropdownMenuItem @click="showShareQRModal = true">
                <Share2 class="mr-2 size-4" />
                Compartilhar / QR Code
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="handlePreviewPdf">
                <Eye class="mr-2 size-4" />
                Visualizar PDF
            </DropdownMenuItem>
            <DropdownMenuItem @click="handleDownloadPdf">
                <Download class="mr-2 size-4" />
                Baixar PDF
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="editor.showReports()">
                <FileText class="mr-2 size-4" />
                Relatórios
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
import { usePlanogramEditor } from '@/composables/plannerate/usePlanogramEditor';
import { ChevronDown, Download, Eye, FileText, MoreVertical, Share2 } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { currentGondola } from '@/composables/plannerate/editor/useGondolaState';
import { show as gondolaView } from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/GondolaPdfPreviewController';
import { ref } from 'vue';
import ShareQRCodeModal from './header/ShareQRCodeModal.vue';

/**
 * Acessa o estado global do editor de planogramas
 * Singleton - mesma instância compartilhada entre todos os componentes
 */
const editor = usePlanogramEditor();

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
    if (!currentGondola.value?.id) return;
    const route = gondolaView(currentGondola.value.id);
    window.open(route.url, '_blank');
}

/**
 * Abre a visualização para download do PDF
 * Mesma rota - o botão de baixar está na página de visualização
 */
function handleDownloadPdf() {
    if (!currentGondola.value?.id) return;
    const route = gondolaView(currentGondola.value.id);
    window.open(route.url, '_blank');
}
</script>
