<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import type { DateRange } from 'reka-ui';
import {
    RangeCalendarCell,
    RangeCalendarCellTrigger,
    RangeCalendarGrid,
    RangeCalendarGridBody,
    RangeCalendarGridHead,
    RangeCalendarGridRow,
    RangeCalendarHeadCell,
    RangeCalendarHeader,
    RangeCalendarHeading,
    RangeCalendarNext,
    RangeCalendarPrev,
    RangeCalendarRoot,
} from 'reka-ui';
import { computed, nextTick, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const emit = defineEmits<{ complete: [] }>();

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
        placeholder: 'Selecionar período',
    },
);

const open = ref(false);
const hasSelectionChanged = ref(false);

const selectedRange = ref<DateRange>({
    start: undefined,
    end: undefined,
});

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const startInputValue = computed(() =>
    hasSelectionChanged.value
        ? (selectedRange.value.start?.toString() ?? '')
        : (selectedRange.value.start?.toString() ?? props.startValue ?? ''),
);
const endInputValue = computed(() =>
    hasSelectionChanged.value
        ? (selectedRange.value.end?.toString() ?? '')
        : (selectedRange.value.end?.toString() ?? props.endValue ?? ''),
);
const buttonLabel = computed(() => {
    const start = startInputValue.value;
    const end = endInputValue.value;

    if (start && end) {
        return `${formatDate(start)} - ${formatDate(end)}`;
    }

    if (start) {
        return `A partir de ${formatDate(start)}`;
    }

    if (end) {
        return `Até ${formatDate(end)}`;
    }

    return props.placeholder;
});

function formatDate(value: string): string {
    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return dateFormatter.format(date);
}

function updateSelectedRange(value: DateRange): void {
    hasSelectionChanged.value = true;
    selectedRange.value = value;
    if (value.start && value.end) {
        open.value = false;
        nextTick(() => emit('complete'));
    }
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
                    <CalendarDays class="size-4 text-muted-foreground" />
                    <span
                        :class="
                            cn(
                                'min-w-0 truncate',
                                !startInputValue &&
                                    !endInputValue &&
                                    'text-muted-foreground',
                            )
                        "
                    >
                        {{ buttonLabel }}
                    </span>
                </Button>
            </PopoverTrigger>

            <PopoverContent align="start" class="w-auto p-3">
                <RangeCalendarRoot
                    :model-value="selectedRange"
                    locale="pt-BR"
                    weekday-format="short"
                    :number-of-months="2"
                    :fixed-weeks="true"
                    class="space-y-3"
                    @update:model-value="updateSelectedRange"
                >
                    <template #default="{ grid, weekDays }">
                        <RangeCalendarHeader
                            class="flex items-center justify-between gap-2"
                        >
                            <RangeCalendarPrev as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                >
                                    <ChevronLeft class="size-4" />
                                </Button>
                            </RangeCalendarPrev>
                            <RangeCalendarHeading class="text-sm font-medium" />
                            <RangeCalendarNext as-child>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                >
                                    <ChevronRight class="size-4" />
                                </Button>
                            </RangeCalendarNext>
                        </RangeCalendarHeader>

                        <div class="grid gap-4 md:grid-cols-2">
                            <RangeCalendarGrid
                                v-for="month in grid"
                                :key="month.value.toString()"
                                class="w-full border-collapse space-y-1"
                            >
                                <RangeCalendarGridHead>
                                    <RangeCalendarGridRow
                                        class="grid grid-cols-7"
                                    >
                                        <RangeCalendarHeadCell
                                            v-for="day in weekDays"
                                            :key="day"
                                            class="flex h-7 items-center justify-center text-[0.8rem] font-normal text-muted-foreground"
                                        >
                                            {{ day }}
                                        </RangeCalendarHeadCell>
                                    </RangeCalendarGridRow>
                                </RangeCalendarGridHead>

                                <RangeCalendarGridBody>
                                    <RangeCalendarGridRow
                                        v-for="(weekDates, index) in month.rows"
                                        :key="`week-${index}`"
                                        class="mt-1 grid grid-cols-7"
                                    >
                                        <RangeCalendarCell
                                            v-for="weekDate in weekDates"
                                            :key="weekDate.toString()"
                                            :date="weekDate"
                                            class="relative flex size-9 items-center justify-center p-0 text-center text-sm"
                                        >
                                            <RangeCalendarCellTrigger
                                                :day="weekDate"
                                                :month="month.value"
                                                class="inline-flex size-9 items-center justify-center rounded-md text-sm transition-colors outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-primary/40 data-[disabled]:pointer-events-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[outside-view]:text-muted-foreground/40 data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selection-end]:bg-primary data-[selection-end]:text-primary-foreground data-[selection-start]:bg-primary data-[selection-start]:text-primary-foreground data-[today]:border data-[today]:border-primary/50"
                                            />
                                        </RangeCalendarCell>
                                    </RangeCalendarGridRow>
                                </RangeCalendarGridBody>
                            </RangeCalendarGrid>
                        </div>
                    </template>
                </RangeCalendarRoot>
            </PopoverContent>
        </Popover>
    </div>
</template>
