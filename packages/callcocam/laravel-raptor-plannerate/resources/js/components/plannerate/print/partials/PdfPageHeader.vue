<script setup lang="ts">
import {
    ArrowRightIcon,
    Building2Icon,
    CalendarDaysIcon,
    FileTextIcon,
    InfoIcon,
    LayoutGridIcon,
    PackageIcon,
    StoreIcon,
    UserIcon,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useT } from '@/composables/useT';

interface GondolaMeta {
    name?: string;
    location?: string;
    side?: string;
    planogram?: {
        name?: string;
        start_date?: string;
        description?: string;
        category?: { name?: string } | null;
    } | null;
}

interface Props {
    gondola: GondolaMeta;
    tenantName?: string;
    responsavel?: string;
    flowLabel: string;
}

const props = defineProps<Props>();

const { t } = useT();

/**
 * Lista única de metadados exibida tanto inline (telas grandes) quanto
 * dentro do popover (telas menores), evitando duplicação de markup.
 */
const metaItems = computed<Array<{ icon: Component; label: string; value: string }>>(() => [
    {
        icon: StoreIcon,
        label: t('plannerate.print.labels.store'),
        value: props.gondola.location || '—',
    },
    {
        icon: FileTextIcon,
        label: t('plannerate.print.labels.sector'),
        value: props.gondola.side || '—',
    },
    {
        icon: PackageIcon,
        label: t('plannerate.print.labels.category'),
        value: props.gondola.planogram?.category?.name || '—',
    },
    {
        icon: CalendarDaysIcon,
        label: t('plannerate.print.labels.publication'),
        value: props.gondola.planogram?.start_date || '—',
    },
    {
        icon: UserIcon,
        label: t('plannerate.print.labels.responsible'),
        value: props.responsavel || '—',
    },
    {
        icon: ArrowRightIcon,
        label: t('plannerate.print.labels.flow'),
        value: props.flowLabel,
    },
]);

/**
 * Lista completa exibida no popover (telas menores). Inclui os campos que
 * em telas grandes ficam só na barra (Cliente, Planograma, Gôndola), para
 * que nada fique de fora quando os metadados colapsam.
 */
const popoverItems = computed<Array<{ icon: Component; label: string; value: string }>>(() => {
    const extras: Array<{ icon: Component; label: string; value: string }> = [];

    if (props.tenantName) {
        extras.push({
            icon: Building2Icon,
            label: t('plannerate.print.preview.client'),
            value: props.tenantName,
        });
    }

    if (props.gondola.planogram?.name) {
        extras.push({
            icon: LayoutGridIcon,
            label: t('plannerate.print.share.planogram'),
            value: props.gondola.planogram.name,
        });
    }

    if (props.gondola.name) {
        extras.push({
            icon: LayoutGridIcon,
            label: t('plannerate.print.share.module'),
            value: props.gondola.name,
        });
    }

    return [...extras, ...metaItems.value];
});
</script>

<template>
    <div class="flex items-center gap-5">
        <!-- Metadados inline: somente em telas grandes -->
        <!-- <div class="hidden items-center gap-5 2xl:flex">
            <div
                v-for="(item, idx) in metaItems"
                :key="idx"
                class="flex flex-col gap-0.5"
            >
                <div class="flex items-center gap-1">
                    <component :is="item.icon" class="h-3 w-3 shrink-0 text-primary" />
                    <span class="text-[9px] tracking-wider text-slate-400 uppercase">{{
                        item.label
                    }}</span>
                </div>
                <span
                    class="min-w-[60px] border-b border-dashed border-slate-300 pb-0.5 text-xs font-semibold text-slate-700 dark:text-slate-200"
                >
                    {{ item.value }}
                </span>
            </div>
        </div> -->

        <!-- Metadados em popover: telas menores p/ não esconder/sobrepor os botões -->
        <Popover>
            <PopoverTrigger as-child>
                <Button variant="outline" size="sm" class="2xl:hidden">
                    <InfoIcon class="mr-1.5 size-4" />
                    {{ t('plannerate.print.preview.info') }}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" class="max-h-[70vh] w-64 overflow-y-auto">
                <div class="flex flex-col gap-3">
                    <div v-for="(item, idx) in popoverItems" :key="idx" class="flex flex-col gap-0.5">
                        <div class="flex items-center gap-1">
                            <component :is="item.icon" class="h-3 w-3 shrink-0 text-primary" />
                            <span class="text-[9px] tracking-wider text-slate-400 uppercase">{{ item.label }}</span>
                        </div>
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ item.value }}
                        </span>
                    </div>
                </div>
            </PopoverContent>
        </Popover>

        <!-- Badge de versão: sempre visível -->
        <!-- <div
            class="flex shrink-0 flex-col items-center justify-center rounded-lg bg-primary px-3 py-2 text-primary-foreground"
        >
            <span
                class="text-[8px] leading-none tracking-wider uppercase opacity-80"
                >{{ t('plannerate.print.labels.version') }}</span
            >
            <span class="mt-0.5 text-base leading-none font-black">V1.0</span>
        </div> -->
    </div>
</template>
