<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
                <MoreVertical class="mr-2 size-4" />
                {{ t('plannerate.dropdown.actions.title') }}
                <ChevronDown class="ml-1 size-3" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56 z-[9999]">
            <DropdownMenuItem @click="props.onAddModule?.()">
                <Plus class="mr-2 size-4" />
                {{ t('plannerate.toolbar.add_module') }}
            </DropdownMenuItem>
            <DropdownMenuItem v-if="props.hasStore" @click="props.onOpenMap?.()">
                <MapPin class="mr-2 size-4" />
                {{ props.currentMapRegionId ? t('plannerate.toolbar.map_remove') : t('plannerate.toolbar.map_store') }}
            </DropdownMenuItem>
            <template v-if="props.canRemoveGondola">
                <DropdownMenuSeparator />
                <DropdownMenuItem class="text-destructive" @click="props.onRemoveGondola?.()">
                    <Trash2 class="mr-2 size-4" />
                    {{ t('plannerate.toolbar.remove_gondola') }}
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
<script setup lang="ts">
import { ChevronDown, MapPin, MoreVertical, Plus, Trash2 } from 'lucide-vue-next';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useT } from '@/composables/useT';

const props = defineProps<{
    canRemoveGondola?: boolean;
    hasStore?: boolean;
    currentMapRegionId?: string | null;
    onAddModule?: () => void;
    onOpenMap?: () => void;
    onRemoveGondola?: () => void;
}>();

const { t } = useT();
</script>
