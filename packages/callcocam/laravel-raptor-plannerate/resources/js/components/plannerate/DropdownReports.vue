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
            <DropdownMenuItem @click="handlePurchasePdf">
                <FileText class="mr-2 size-4" />
                {{ t('plannerate.dropdown.reports.purchase_pdf') }}
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
import { ChevronDown, FileSpreadsheet, FileText } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useT } from '@/composables/useT';

const { t } = useT();

// ============================================================================
// REPORT HANDLERS (PLACEHOLDER)
// Endpoints de backend ainda não implementados — funções stub para serem
// ligadas às rotas de exportação quando o backend existir.
// ============================================================================

/**
 * Gera o Relatório de Compra em formato Excel.
 * TODO: ligar à rota de exportação quando o backend estiver disponível.
 */
function handlePurchaseExcel(): void {
    // TODO: implementar exportação do relatório de compra (Excel)
}

/**
 * Gera o Relatório de Compra em formato PDF.
 * TODO: ligar à rota de exportação quando o backend estiver disponível.
 */
function handlePurchasePdf(): void {
    // TODO: implementar exportação do relatório de compra (PDF)
}

/**
 * Gera o Relatório de Reposição em formato Excel.
 * TODO: ligar à rota de exportação quando o backend estiver disponível.
 */
function handleRestockExcel(): void {
    // TODO: implementar exportação do relatório de reposição (Excel)
}

/**
 * Gera o Relatório de Reposição em formato PDF.
 * TODO: ligar à rota de exportação quando o backend estiver disponível.
 */
function handleRestockPdf(): void {
    // TODO: implementar exportação do relatório de reposição (PDF)
}
</script>
