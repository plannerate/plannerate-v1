<script setup lang="ts">
/**
 * Escuta eventos Echo do tenant ligados ao utilizador (canal privado App.Models.User).
 * Colocado no layout global; o Echo já está em resources/js/app.ts (configureEcho + Reverb).
 */
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { toast } from 'vue-sonner';
import { useT } from '@/composables/useT';

type ImportFinishedPayload = {
    tenant_id: string;
    tenant_slug: string;
    rows_processed: number;
    categories_created: number;
    categories_updated: number;
    products_linked: number;
    warnings_count: number;
    errors_count: number;
};

const page = usePage();
const { t } = useT();

const auth = page.props.auth as { user: { id: string } };
const channel = `App.Models.User.${auth.user.id}`;

if (typeof window !== 'undefined') {
    useEcho(channel, '.categories.import.finished', (raw: ImportFinishedPayload) => {
        const sub = window.location.hostname.split('.')[0] ?? '';

        if (raw.tenant_slug !== '' && raw.tenant_slug !== sub) {
            return;
        }

        const message = t('app.tenant.categories.messages.import_finished_detail', {
            rows: String(raw.rows_processed),
            created: String(raw.categories_created),
            updated: String(raw.categories_updated),
            linked: String(raw.products_linked),
            warnings: String(raw.warnings_count),
            errors: String(raw.errors_count),
        });

        if (raw.errors_count > 0) {
            toast.warning(message);

            return;
        }

        toast.success(message);
    });
}
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
