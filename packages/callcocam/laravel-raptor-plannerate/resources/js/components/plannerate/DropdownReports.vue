<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
                <FileText class="mr-2 size-4" />
                {{ t('plannerate.dropdown.reports.title') }}
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 z-[9999]">
            <DropdownMenuItem @click="handlePurchaseExcel">
                <FileSpreadsheet class="mr-2 size-4" />
                {{ t('plannerate.dropdown.reports.purchase_excel') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem @click="handleRestockExcel">
                <FileSpreadsheet class="mr-2 size-4" />
                {{ t('plannerate.dropdown.reports.restock_excel') }}
            </DropdownMenuItem>
            <DropdownMenuItem @click="handleRestockPdf">
                <FileText class="mr-2 size-4" />
                {{ t('plannerate.dropdown.reports.restock_pdf') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronDown, FileSpreadsheet, FileText } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

import {
    generateCompraReport,
    generateExcelReport,
    generatePdfReport,
} from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Export/GondolaReportController';
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

const { t } = useT();

/**
 * Tipo das actions de relatório: recebem o id da gôndola e devolvem a rota.
 */
type ReportAction = (gondola: string) => { url: string };

/**
 * Enfileira a geração do relatório (POST via router do Inertia).
 *
 * A geração roda em fila no backend; ao concluir, o usuário recebe uma
 * notificação em tela e no sino com o link de download. Aqui só disparamos e
 * confirmamos o enfileiramento com um toast. Sem efeito se não houver gôndola.
 */
function queueReport(action: ReportAction): void {
    const gondolaId = currentGondola.value?.id;

    if (!gondolaId) {
        return;
    }

    router.post(action(gondolaId).url, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success(t('plannerate.reports.queued')),
        onError: () => toast.error(t('plannerate.reports.queue_failed')),
    });
}

/**
 * Enfileira o Relatório de Compra em formato Excel.
 * Rota: export/gondola-report/{gondola}/compra
 */
function handlePurchaseExcel(): void {
    queueReport(generateCompraReport);
}

/**
 * Enfileira o Relatório de Reposição em formato Excel.
 * Rota: export/gondola-report/{gondola}/excel
 */
function handleRestockExcel(): void {
    queueReport(generateExcelReport);
}

/**
 * Enfileira o Relatório de Reposição em formato PDF.
 * Rota: export/gondola-report/{gondola}/pdf
 */
function handleRestockPdf(): void {
    queueReport(generatePdfReport);
}
</script>
