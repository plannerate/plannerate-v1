<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import StoreMapDetailsModal from './StoreMapDetailsModal.vue';
import { MapIcon, Settings, Eye } from 'lucide-vue-next';

interface MapRegion {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    shape?: 'rectangle' | 'circle';
    type: string;
    color: string;
    label: string;
    gondola_id?: string | null;
    gondola?: {
        id: string;
        name: string;
        slug: string;
        planogram_id: string;
        planogram_name?: string;
        edit_url: string;
    } | null;
}

interface Store {
    id: string;
    name: string;
    code: string;
    can_edit_store?: boolean;
    map_image_path?: string;
    map_regions?: MapRegion[];
    maps_integration?: {
        image_url?: string;
        regions?: MapRegion[];
    };
}

interface Props {
    stores: Store[];
}

const props = defineProps<Props>();

const ALL_STORES_FILTER = 'all';

const selectedStoreFilterId = ref<string>(ALL_STORES_FILTER);
const modalStoreId = ref<string | null>(null);
const isModalOpen = ref(false);

const storesWithMaps = computed(() => {
    return props.stores.filter(store => store.maps_integration?.image_url);
});

const filteredStores = computed(() => {
    if (selectedStoreFilterId.value === ALL_STORES_FILTER) {
        return storesWithMaps.value;
    }

    return storesWithMaps.value.filter(store => store.id === selectedStoreFilterId.value);
});

const selectedModalStore = computed(() => {
    if (!modalStoreId.value) {
        return null;
    }

    return storesWithMaps.value.find(store => store.id === modalStoreId.value) || null;
});

const linkedCountByStore = computed(() => {
    return storesWithMaps.value.reduce<Record<string, number>>((carry, store) => {
        carry[store.id] = store.map_regions?.filter(region => region.gondola).length ?? 0;

        return carry;
    }, {});
});

const storeLabel = (store: Store): string => {
    return `${store.name} (Loja ${store.code})`;
};

const updateStoreFilter = (value: string | undefined): void => {
    selectedStoreFilterId.value = value || ALL_STORES_FILTER;
};

const openMapModal = (storeId: string): void => {
    modalStoreId.value = storeId;
    isModalOpen.value = true;
};

const handleModalOpenChange = (open: boolean): void => {
    isModalOpen.value = open;

    if (!open) {
        modalStoreId.value = null;
    }
};

const handleEditStore = (storeId: string): void => {
    router.visit(`/stores/${storeId}/edit`);
};
</script>

<template>
    <div class="space-y-6 w-full">
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="min-w-64">
                        <label class="block text-sm font-medium mb-1">Selecione a Loja</label>
                        <Select :model-value="selectedStoreFilterId" @update:model-value="(value: any) => updateStoreFilter(value as string)">
                            <SelectTrigger>
                                <SelectValue placeholder="Filtrar por loja" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL_STORES_FILTER">
                                    Todas as lojas
                                </SelectItem>
                                <SelectItem
                                    v-for="store in storesWithMaps"
                                    :key="store.id"
                                    :value="store.id"
                                >
                                    {{ storeLabel(store) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Badge variant="secondary">
                        {{ filteredStores.length }} mapa(s)
                    </Badge>
                </div>
            </CardHeader>
        </Card>

        <Card v-if="storesWithMaps.length === 0" class="h-full">
            <CardContent class="flex items-center justify-center h-96">
                <div class="text-center text-muted-foreground">
                    <MapIcon class="mx-auto h-12 w-12 mb-4 opacity-50" />
                    <p class="text-lg font-medium">Nenhuma loja com mapa disponível</p>
                </div>
            </CardContent>
        </Card>

        <Card v-else-if="filteredStores.length === 0" class="h-full">
            <CardContent class="flex items-center justify-center h-72">
                <div class="text-center text-muted-foreground">
                    <MapIcon class="mx-auto h-10 w-10 mb-3 opacity-50" />
                    <p class="text-base font-medium">Nenhum mapa encontrado para o filtro selecionado</p>
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <Card
                v-for="store in filteredStores"
                :key="store.id"
            >
                <CardHeader>
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="flex  flex-col gap-y-1 flex-1 min-w-0">
                            <div>
                                <p class="font-semibold truncate">{{ store.name }}</p>
                                <p class="text-sm text-muted-foreground">Loja {{ store.code }}</p>
                            </div>

                            <Badge variant="secondary">
                                {{ store.map_regions?.length || 0 }} regiões
                            </Badge>

                            <Badge v-if="(linkedCountByStore[store.id] || 0) > 0" variant="default">
                                {{ linkedCountByStore[store.id] }} vinculada(s)
                            </Badge>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                @click="openMapModal(store.id)"
                            >
                                <ActionIconBox variant="outline">
                                    <Eye />
                                </ActionIconBox>
                                Ver detalhes
                            </Button>

                            <Button
                                v-if="store.can_edit_store"
                                variant="outline"
                                size="sm"
                                class="gap-2"
                                @click="handleEditStore(store.id)"
                            >
                                <ActionIconBox variant="outline">
                                    <Settings />
                                </ActionIconBox>
                                Editar Loja
                            </Button>
                        </div>
                    </div>
                </CardHeader>

                <CardContent>
                    <div class="border rounded-lg overflow-hidden bg-muted/40 h-56 lg:h-64 p-2">
                        <img
                            :src="store.maps_integration?.image_url"
                            :alt="`Mapa da ${store.name}`"
                            class="w-full h-full object-contain"
                            draggable="false"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <StoreMapDetailsModal
            :open="isModalOpen"
            :store="selectedModalStore"
            @update:open="handleModalOpenChange"
        />
    </div>
</template>
