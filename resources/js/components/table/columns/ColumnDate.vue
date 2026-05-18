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

    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
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
