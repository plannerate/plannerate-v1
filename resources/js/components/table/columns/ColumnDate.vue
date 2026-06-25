<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    date?: string | null;
    from?: string | null;
    to?: string | null;
}>();

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '-';
    }

    // Strings date-only (YYYY-MM-DD) são parseadas como UTC pelo construtor Date,
    // o que provoca off-by-one em fusos negativos (ex.: UTC-3 mostra o dia anterior).
    // Por isso adicionamos T00:00:00 para forçar interpretação no horário local.
    const dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(value);
    const normalized = dateOnly
        ? `${value}T00:00:00`
        : value.includes('T')
          ? value
          : value.replace(' ', 'T');
    const parsed = new Date(normalized);

    if (Number.isNaN(parsed.getTime())) {
        return '-';
    }

    return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' }).format(
        parsed,
    );
}

const display = computed((): string => {
    if (props.from || props.to) {
        return `${formatDate(props.from)} → ${formatDate(props.to)}`;
    }

    if (props.date) {
        return formatDate(props.date);
    }

    return '—';
});
</script>

<template>
    <div class="flex items-start gap-1.5 text-muted-foreground">
        <CalendarDays class="mt-0.5 size-3.5 shrink-0" />
        <span class="leading-snug text-sm">{{ display }}</span>
    </div>
</template>
