<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useT } from '@/composables/useT';

const { t } = useT();

defineProps<{
    title?: string;
    description?: string;
    processing?: boolean;
    disabled?: boolean;
    cancelHref?: string;
    saveLabel?: string;
    cancelLabel?: string;
}>();

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
        class="overflow-hidden rounded-xl border border-border bg-card shadow-sm transition-shadow hover:shadow-md dark:shadow-black/20 dark:hover:shadow-black/40"
    >
        <!-- Gradient accent bar -->
        <div class="h-0.5 bg-linear-to-r from-primary via-primary/60 to-transparent" />

        <!-- Header -->
        <div v-if="title || $slots['header-extra'] || $slots.icon" class="flex items-center gap-4 border-b border-border bg-muted/20 px-6 py-4">
            <div
                v-if="$slots.icon"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary ring-1 ring-primary/20"
            >
                <slot name="icon" />
            </div>

            <div class="min-w-0 flex-1">
                <h2 v-if="title" class="truncate text-base font-semibold text-foreground">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-sm text-muted-foreground">{{ description }}</p>
            </div>

            <div v-if="$slots['header-extra']" class="flex shrink-0 items-center gap-2">
                <slot name="header-extra" />
            </div>
        </div>

        <!-- Before slot (alerts, banners) -->
        <div v-if="$slots.before" class="border-b border-border/60 px-6 py-4">
            <slot name="before" />
        </div>

        <!-- Body -->
        <div class="space-y-5 p-6">
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
        <div class="flex flex-wrap items-center gap-3 border-t border-border bg-muted/20 px-6 py-4">
            <slot name="actions">
                <Button
                    type="submit"
                    variant="gradient"
                    size="pill-sm"
                    :disabled="processing || disabled"
                >
                    {{ saveLabel ?? t('app.actions.save') }}
                </Button>

                <Button
                    v-if="cancelHref"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="cancelHref">{{ cancelLabel ?? t('app.actions.cancel') }}</Link>
                </Button>
            </slot>

            <slot name="footer-extra" />
        </div>
    </div>
</template>
