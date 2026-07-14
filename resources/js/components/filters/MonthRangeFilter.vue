<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const emit = defineEmits<{
    complete: [];
    'update:startValue': [value: string];
    'update:endValue': [value: string];
}>();

const props = withDefaults(
    defineProps<{
        label: string;
        startName: string;
        endName: string;
        startValue?: string | null;
        endValue?: string | null;
        placeholder?: string;
    }>(),
    {
        startValue: '',
        endValue: '',
        placeholder: 'Selecionar mês/ano',
    },
);

const MONTHS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

const open = ref(false);
const today = new Date();

function parseYearMonth(value?: string | null): { year: number; month: number } | null {
    if (!value) {
return null;
}

    const match = value.match(/^(\d{4})-(\d{2})/);

    if (!match) {
return null;
}

    return { year: parseInt(match[1]), month: parseInt(match[2]) - 1 };
}

function lastDayOfMonth(year: number, month: number): string {
    return String(new Date(year, month + 1, 0).getDate()).padStart(2, '0');
}

function abs(year: number, month: number): number {
    return year * 12 + month;
}

const startYear = ref(today.getFullYear());
const startMonth = ref<number | null>(null);
const endYear = ref(today.getFullYear());
const endMonth = ref<number | null>(null);
const startNavYear = ref(today.getFullYear());
const endNavYear = ref(today.getFullYear());

watch(
    () => props.startValue,
    (val) => {
        const parsed = parseYearMonth(val);

        if (parsed) {
            startYear.value = parsed.year;
            startMonth.value = parsed.month;
            startNavYear.value = parsed.year;
        } else {
            startMonth.value = null;
        }
    },
    { immediate: true },
);

watch(
    () => props.endValue,
    (val) => {
        const parsed = parseYearMonth(val);

        if (parsed) {
            endYear.value = parsed.year;
            endMonth.value = parsed.month;
            endNavYear.value = parsed.year;
        } else {
            endMonth.value = null;
        }
    },
    { immediate: true },
);

const startInputValue = computed(() => {
    if (startMonth.value === null) {
return props.startValue ?? '';
}

    const m = String(startMonth.value + 1).padStart(2, '0');

    return `${startYear.value}-${m}-01`;
});

const endInputValue = computed(() => {
    if (endMonth.value === null) {
return props.endValue ?? '';
}

    const m = String(endMonth.value + 1).padStart(2, '0');

    return `${endYear.value}-${m}-${lastDayOfMonth(endYear.value, endMonth.value)}`;
});

const buttonLabel = computed(() => {
    const hasStart = startMonth.value !== null;
    const hasEnd = endMonth.value !== null;

    if (hasStart && hasEnd) {
        return `${MONTHS[startMonth.value!]}/${startYear.value} – ${MONTHS[endMonth.value!]}/${endYear.value}`;
    }

    if (hasStart) {
return `A partir de ${MONTHS[startMonth.value!]}/${startYear.value}`;
}

    if (hasEnd) {
return `Até ${MONTHS[endMonth.value!]}/${endYear.value}`;
}

    return props.placeholder;
});

function isStart(year: number, month: number): boolean {
    return startMonth.value !== null && startYear.value === year && startMonth.value === month;
}

function isEnd(year: number, month: number): boolean {
    return endMonth.value !== null && endYear.value === year && endMonth.value === month;
}

function isInRange(year: number, month: number): boolean {
    if (startMonth.value === null || endMonth.value === null) {
return false;
}

    const t = abs(year, month);

    return t > abs(startYear.value, startMonth.value) && t < abs(endYear.value, endMonth.value);
}

function selectStart(month: number): void {
    startYear.value = startNavYear.value;
    startMonth.value = month;

    if (endMonth.value !== null && abs(startNavYear.value, month) > abs(endYear.value, endMonth.value)) {
        endYear.value = startNavYear.value;
        endMonth.value = month;
    }

    emit('update:startValue', startInputValue.value);
    emit('update:endValue', endInputValue.value);

    if (endMonth.value !== null) {
        triggerComplete();
    }
}

function selectEnd(month: number): void {
    const newAbs = abs(endNavYear.value, month);

    if (startMonth.value !== null && newAbs < abs(startYear.value, startMonth.value)) {
        endYear.value = startYear.value;
        endMonth.value = startMonth.value;
        startYear.value = endNavYear.value;
        startMonth.value = month;
    } else {
        endYear.value = endNavYear.value;
        endMonth.value = month;
    }

    if (startMonth.value !== null) {
        emit('update:startValue', startInputValue.value);
        emit('update:endValue', endInputValue.value);
        triggerComplete();
    }
}

function triggerComplete(): void {
    open.value = false;
    nextTick(() => emit('complete'));
}

function monthCellClass(year: number, month: number, side: 'start' | 'end'): string {
    const selected = side === 'start' ? isStart(year, month) : isEnd(year, month);
    const otherSelected = side === 'start' ? isEnd(year, month) : isStart(year, month);
    const inRange = isInRange(year, month);

    return cn(
        'relative h-9 w-full rounded-md text-sm font-medium transition-all outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
        selected
            ? 'bg-primary text-primary-foreground shadow-sm hover:bg-primary/90'
            : otherSelected
              ? 'bg-primary/20 text-primary font-semibold hover:bg-primary/25'
              : inRange
                ? 'bg-primary/10 text-foreground rounded-none hover:bg-primary/20'
                : 'text-foreground hover:bg-accent hover:text-accent-foreground',
    );
}
</script>

<template>
    <div class="flex min-w-62 flex-col gap-1">
        <span class="text-xs text-muted-foreground">{{ label }}</span>

        <input type="hidden" :name="startName" :value="startInputValue" />
        <input type="hidden" :name="endName" :value="endInputValue" />

        <Popover v-model:open="open">
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    class="h-9 w-full justify-start rounded-lg border-border bg-background px-3 text-left font-normal"
                >
                    <CalendarDays class="size-4 shrink-0 text-muted-foreground" />
                    <span
                        :class="
                            cn(
                                'min-w-0 truncate',
                                startMonth === null && endMonth === null && 'text-muted-foreground',
                            )
                        "
                    >
                        {{ buttonLabel }}
                    </span>
                </Button>
            </PopoverTrigger>

            <PopoverContent align="start" class="z-700 w-auto p-0 shadow-lg">
                <div class="flex divide-x divide-border">
                    <!-- Start picker -->
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex items-center gap-1.5">
                            <span class="size-1.5 rounded-full bg-primary" />
                            <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Início
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                @click="startNavYear--"
                            >
                                <ChevronLeft class="size-4" />
                            </Button>
                            <span class="w-12 text-center text-sm font-semibold tabular-nums">
                                {{ startNavYear }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                @click="startNavYear++"
                            >
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>

                        <div class="grid grid-cols-3 gap-1">
                            <button
                                v-for="(name, index) in MONTHS"
                                :key="name"
                                type="button"
                                :class="monthCellClass(startNavYear, index, 'start')"
                                @click="selectStart(index)"
                            >
                                {{ name }}
                            </button>
                        </div>

                        <p class="text-center text-[11px] text-muted-foreground/70">
                            {{ startInputValue ? `01/${String(startMonth! + 1).padStart(2, '0')}/${startYear}` : 'Não selecionado' }}
                        </p>
                    </div>

                    <!-- End picker -->
                    <div class="flex flex-col gap-3 p-4">
                        <div class="flex items-center gap-1.5">
                            <span class="size-1.5 rounded-full bg-primary/50" />
                            <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Fim
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                @click="endNavYear--"
                            >
                                <ChevronLeft class="size-4" />
                            </Button>
                            <span class="w-12 text-center text-sm font-semibold tabular-nums">
                                {{ endNavYear }}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                @click="endNavYear++"
                            >
                                <ChevronRight class="size-4" />
                            </Button>
                        </div>

                        <div class="grid grid-cols-3 gap-1">
                            <button
                                v-for="(name, index) in MONTHS"
                                :key="name"
                                type="button"
                                :class="monthCellClass(endNavYear, index, 'end')"
                                @click="selectEnd(index)"
                            >
                                {{ name }}
                            </button>
                        </div>

                        <p class="text-center text-[11px] text-muted-foreground/70">
                            {{
                                endInputValue
                                    ? `${lastDayOfMonth(endYear, endMonth!)}/${String(endMonth! + 1).padStart(2, '0')}/${endYear}`
                                    : 'Não selecionado'
                            }}
                        </p>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    </div>
</template>
