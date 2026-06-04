<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import { ArrowLeft, Edit, Plus, RefreshCcw, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GondolaCreateStepper from '@/components/plannerate/form/GondolaCreateStepper.vue';
import GondolaEditForm from '@/components/plannerate/form/GondolaEditForm.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useT } from '@/composables/useT';
import { wayfinderPath } from '../../../libs/wayfinderPath';

interface Props {
    title?: string;
    status?: string;
    planogramId?: string;
    tenant?: any;
    availableUsers?: Array<{ id: string; name: string }>;
    analysis?: {
        abc?: any;
        stock?: any;
    };
    permissions: {
        can_create_gondola: boolean;
        can_update_gondola: boolean;
    };
    backRoute?: string;
    sidebar?: boolean;
}
const props = withDefaults(defineProps<Props>(), {
    title: '',
    status: 'draft',
    planogramId: '',
    tenant: {},
    availableUsers: () => [],
    backRoute: '',
    sidebar: false,
});
const { t } = useT();
const emit = defineEmits<{
    closeProducts: [];
    closeProperties: [];
    goBack: [];
    importData: [];
    addGondola: [];
    updateGondolaImages: [];
}>();

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        draft: 'bg-yellow-500/10 text-yellow-500',
        published: 'bg-green-500/10 text-green-500',
        archived: 'bg-gray-500/10 text-gray-500',
    };

    return colors[status] || colors.draft;
};

const gondolaSettings = computed(() => {
    return props.tenant?.settings?.gondola || {};
});
const titleDisplay = computed(() => props.title || t('plannerate.header.planogram'));

// Sheet de edição da gôndola
const showGondolaEdit = ref(false);

function openGondolaEdit() {
    showGondolaEdit.value = true;
}

function closeGondolaEdit() {
    showGondolaEdit.value = false;
}

// Sheet de criação de gôndola
const showGondolaCreate = ref(false);

function openGondolaCreate() {
    showGondolaCreate.value = true;
}

function closeGondolaCreate() {
    showGondolaCreate.value = false;
}

function handleGondolaCreated() {
    closeGondolaCreate();
    // Emit evento para recarregar dados se necessário
    emit('addGondola');
}

const showUpdateImagesConfirm = ref(false);

function handleUpdateGondolaImages() {
    showUpdateImagesConfirm.value = true;
}

function confirmUpdateGondolaImages() {
    emit('updateGondolaImages');
    showUpdateImagesConfirm.value = false;
}

function cancelUpdateGondolaImages() {
    showUpdateImagesConfirm.value = false;
}
</script>

<template>
    <div class="border-b bg-background">
        <div :class="sidebar ? 'flex flex-col gap-3 p-4' : 'flex h-16 items-center justify-between px-6'">
            <!-- Title & Status -->
            <div class="flex items-center gap-2 flex-wrap">
                <h1 :class="sidebar ? 'text-base font-semibold truncate' : 'text-xl font-semibold'">{{ titleDisplay }}</h1>
                <Badge :class="getStatusColor(status)" variant="outline">
                    {{ status }}
                </Badge>
            </div>

            <!-- Actions -->
            <div :class="sidebar ? 'flex flex-wrap gap-2' : 'flex items-center gap-2'">
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    disabled
                    @click="emit('importData')"
                >
                    <Upload />
                    {{ t('plannerate.header.import_data') }}
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    @click="openGondolaCreate"
                    v-if="permissions.can_create_gondola"
                >
                    <Plus />
                    {{ t('plannerate.header.add_gondola') }}
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    @click="openGondolaEdit"
                    v-if="permissions.can_update_gondola"
                >
                    <Edit />
                    {{ t('plannerate.header.edit_gondola') }}
                </Button>

                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    @click="handleUpdateGondolaImages"
                >
                    <RefreshCcw />
                    {{ t('plannerate.header.update_images') }}
                </Button>

                <!-- Modal de confirmação: Atualizar Imagens -->
                <AlertDialog
                    :open="showUpdateImagesConfirm"
                    @update:open="(val) => (showUpdateImagesConfirm = val)"
                >
                    <AlertDialogContent class="z-[1000] sm:max-w-md">
                        <AlertDialogHeader>
                            <AlertDialogTitle
                                >{{ t('plannerate.header.confirm_update_images_title') }}</AlertDialogTitle
                            >
                            <AlertDialogDescription>
                                {{ t('plannerate.header.confirm_update_images_description') }}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel
                                @click="cancelUpdateGondolaImages"
                            >
                                {{ t('app.actions.cancel') }}
                            </AlertDialogCancel>
                            <AlertDialogAction
                                @click="confirmUpdateGondolaImages"
                            >
                                {{ t('plannerate.header.confirm_update_images_action') }}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <Link
                    v-if="backRoute"
                    :href="wayfinderPath(backRoute)"
                    class="flex items-center gap-2 text-sm text-muted-foreground hover:text-muted-foreground/80 cursor-pointer"
                >
                    <ArrowLeft class="size-4" />
                    {{ t('app.actions.back') }}
                </Link>
            </div>
        </div>
    </div>

    <!-- Sheet de Edição da Gôndola -->
    <Sheet v-model:open="showGondolaEdit">
        <SheetContent side="right" class="w-full p-0 sm:max-w-md">
            <SheetTitle class="sr-only">{{ t('plannerate.header.edit_gondola') }}</SheetTitle>
            <SheetDescription class="sr-only">
                Formulário para editar propriedades da gôndola
            </SheetDescription>
            <GondolaEditForm @close="closeGondolaEdit" />
        </SheetContent>
    </Sheet>

    <!-- Sheet de Criação de Gôndola -->
    <GondolaCreateStepper
        :open="showGondolaCreate"
        :planogram-id="planogramId"
        :available-users="availableUsers"
        :gondola-settings="gondolaSettings"
        @update:open="(val) => (showGondolaCreate = val)"
        @success="handleGondolaCreated"
    />
</template>
