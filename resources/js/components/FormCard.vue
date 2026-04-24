<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';
import { CornerUpLeft, Save } from 'lucide-vue-next';

const { t } = useT();

withDefaults(defineProps<{
    title?: string;
    description?: string;
    processing?: boolean;
    disabled?: boolean;
    cancelHref?: string;
    saveLabel?: string;
    cancelLabel?: string;
    maxWidth?: string;
}>(), {
    maxWidth: () => 'max-w-6xl',
});

defineSlots<{
    default(): unknown;
    icon(): unknown;
    'header-extra'(): unknown;
    before(): unknown;
    after(): unknown;
    actions(): unknown;
    'footer-extra'(): unknown;
}>();
</script>

<template>
    <div
        :class="['mx-auto w-full overflow-hidden rounded border border-border/50 bg-card ', maxWidth]"
    >
        <!-- Gradient accent bar -->
        <div
            class="h-px bg-linear-to-r from-primary/90 via-primary/45 to-transparent"
        />

        <!-- Header -->
        <div
            v-if="title || $slots['header-extra'] || $slots.icon"
            class="flex items-center gap-4 border-b border-border/60 bg-muted/35 px-6 py-5"
        >
            <div
                v-if="$slots.icon"
                class="flex size-10 shrink-0 items-center justify-center rounded bg-primary/12 text-primary ring-1 ring-primary/15"
            >
                <slot name="icon" />
            </div>

            <div class="min-w-0 flex-1">
                <h2
                    v-if="title"
                    class="truncate text-lg font-semibold tracking-tight text-foreground"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-muted-foreground/90"
                >
                    {{ description }}
                </p>
            </div>

            <div
                v-if="$slots['header-extra']"
                class="flex shrink-0 items-center gap-2"
            >
                <slot name="header-extra" />
            </div>
        </div>

        <!-- Before slot (alerts, banners) -->
        <div v-if="$slots.before" class="border-b border-border/50 px-6 py-4">
            <slot name="before" />
        </div>

        <!-- Body -->
        <div class="space-y-5 bg-background/20 px-6 py-6">
            <slot />
        </div>

        <!-- After slot (danger zone, extra sections) -->
        <template v-if="$slots.after">
            <Separator />
            <div class="px-6 py-5">
                <slot name="after" />
            </div>
        </template>

        <!-- Footer -->
        <div
            class="sticky bottom-0 z-20 flex flex-wrap items-center justify-end gap-3 border-t border-border/60 bg-card/90 px-6 py-4 backdrop-blur supports-[backdrop-filter]:bg-card/80"
        >
            <slot name="footer-extra" />
            <slot name="actions">
                <Button
                    v-if="cancelHref"
                    variant="outline"
                    size="pill-sm"
                    as-child
                >
                    <Link :href="cancelHref">
                        <CornerUpLeft class="size-4" />
                        <span>
                            {{ cancelLabel ?? t('app.actions.cancel') }}
                        </span>
                    </Link>
                </Button>
                <Button
                    type="submit"
                    variant="gradient"
                    size="pill-sm"
                    :disabled="processing || disabled"
                >
                    <Save class="size-4" />
                    <span>
                        {{ saveLabel ?? t('app.actions.save') }}
                    </span>
                </Button>
            </slot>
        </div>
    </div>
</template>
