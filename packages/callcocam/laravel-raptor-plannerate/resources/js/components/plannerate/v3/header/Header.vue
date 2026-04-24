<script setup lang="ts">
import GondolaCreateStepper from '@/components/plannerate/v3/form/GondolaCreateStepper.vue';
import GondolaEditForm from '@/components/plannerate/v3/form/GondolaEditForm.vue';
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
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { ArrowLeft, Edit, Plus, RefreshCcw, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
}
const props = withDefaults(defineProps<Props>(), {
    title: 'Planograma',
    status: 'draft',
    planogramId: '',
    tenant: {},
    availableUsers: () => [],
});
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
        <div class="flex h-16 items-center justify-between px-6">
            <!-- Left: Title -->
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold">{{ title }}</h1>
                <Badge :class="getStatusColor(status)" variant="outline">
                    {{ status }}
                </Badge>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" class="gap-2" @click="emit('importData')">
                    <ActionIconBox variant="outline">
                        <Upload />
                    </ActionIconBox>
                    Importar Dados
                </Button>

                <Button variant="outline" size="sm" class="gap-2" @click="openGondolaCreate" v-if="permissions.can_create_gondola">
                    <ActionIconBox variant="default">
                        <Plus />
                    </ActionIconBox>
                    Adicionar Gôndola
                </Button>

                <Button variant="outline" size="sm" class="gap-2" @click="openGondolaEdit" v-if="permissions.can_update_gondola">
                    <ActionIconBox variant="outline">
                        <Edit />
                    </ActionIconBox>
                    Editar Gôndola
                </Button>
                <!-- Atualizar imagens da gôndola -->
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-2"
                    @click="handleUpdateGondolaImages"
                >
                    <ActionIconBox variant="outline">
                        <RefreshCcw />
                    </ActionIconBox>
                    Atualizar Imagens
                </Button>

                <!-- Modal de confirmação: Atualizar Imagens -->
                <AlertDialog
                    :open="showUpdateImagesConfirm"
                    @update:open="(val) => (showUpdateImagesConfirm = val)"
                >
                    <AlertDialogContent class="z-[1000] sm:max-w-md">
                        <AlertDialogHeader>
                            <AlertDialogTitle
                                >Atualizar imagens da gôndola?</AlertDialogTitle
                            >
                            <AlertDialogDescription>
                                As imagens dos produtos na gôndola serão
                                atualizadas. Deseja continuar?
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel
                                @click="cancelUpdateGondolaImages"
                            >
                                Cancelar
                            </AlertDialogCancel>
                            <AlertDialogAction
                                @click="confirmUpdateGondolaImages"
                            >
                                Sim, atualizar
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <div class="mx-2 h-6 w-px bg-border" />

                <Button variant="ghost" size="sm" @click="emit('goBack')">
                    <ArrowLeft class="mr-2 size-4" />
                    Voltar
                </Button>
            </div>
        </div>
    </div>

    <!-- Sheet de Edição da Gôndola -->
    <Sheet v-model:open="showGondolaEdit">
        <SheetContent side="right" class="w-full p-0 sm:max-w-md">
            <SheetTitle class="sr-only">Editar Gôndola</SheetTitle>
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
