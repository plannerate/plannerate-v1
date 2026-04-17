import { dashboard } from '@/routes';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface WorkflowFilterValues {
    flow_slug: string | null;
    date_from: string | null;
    date_to: string | null;
    responsible_id: string | null;
}

export function useDashboardWorkflowFilters(initialValues: WorkflowFilterValues) {
    const flowSlug = ref<string>(initialValues.flow_slug ?? '');
    const dateFrom = ref<string>(initialValues.date_from ?? '');
    const dateTo = ref<string>(initialValues.date_to ?? '');
    const responsibleId = ref<string>(initialValues.responsible_id ?? '');

    function applyWorkflowFilters(): void {
        router.get(
            dashboard().url,
            {
                flow_slug: flowSlug.value || undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
                responsible_id: responsibleId.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function clearWorkflowFilters(): void {
        flowSlug.value = '';
        dateFrom.value = '';
        dateTo.value = '';
        responsibleId.value = '';
        applyWorkflowFilters();
    }

    return {
        flowSlug,
        dateFrom,
        dateTo,
        responsibleId,
        applyWorkflowFilters,
        clearWorkflowFilters,
    };
}
