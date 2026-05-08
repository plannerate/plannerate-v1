<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Cloud, Loader2, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type CloudflareRecordNotFound = { exists: false; cname_target: string };
type CloudflareRecordFound = { exists: true; id: string; name: string; content: string; cname_target: string };
type CloudflareRecord = CloudflareRecordNotFound | CloudflareRecordFound;

const props = defineProps<{
    cloudflareRecord: CloudflareRecord | null | undefined;
    createHref: string;
    deleteHref: string;
    host: string;
}>();

const isLoading = computed(() => props.cloudflareRecord === undefined);
const recordExists = computed(
    () => props.cloudflareRecord !== null && props.cloudflareRecord !== undefined && props.cloudflareRecord.exists === true,
);
const record = computed(() =>
    recordExists.value ? (props.cloudflareRecord as CloudflareRecordFound) : null,
);
const cnameTarget = computed(
    () => (props.cloudflareRecord as CloudflareRecord | null)?.cname_target ?? '',
);

const createOpen = ref(false);
const deleteOpen = ref(false);

const createForm = useForm({});
const deleteForm = useForm({});

function confirmCreate(): void {
    createForm.post(props.createHref, {
        onSuccess: () => {
            createOpen.value = false;
        },
    });
}

function confirmDelete(): void {
    deleteForm.delete(props.deleteHref, {
        onSuccess: () => {
            deleteOpen.value = false;
        },
    });
}
</script>

<template>
    <!-- Only render if cloudflareRecord is successfully loaded (not null/undefined) -->
    <div v-if="cloudflareRecord && cloudflareRecord !== null && cloudflareRecord !== undefined" class="rounded-lg border border-border">
        <!-- Loaded state only - no skeleton to avoid interfering with form -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <Cloud class="size-4 shrink-0 text-muted-foreground" />
                <span class="text-sm font-medium">DNS Cloudflare</span>

                <span
                    v-if="recordExists"
                    class="inline-flex items-center gap-1 rounded-full border border-green-500/30 bg-green-500/10 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400"
                >
                    <span class="size-1.5 rounded-full bg-green-500" />
                    DNS Ativo
                </span>
                <span
                    v-else
                    class="inline-flex items-center gap-1 rounded-full border border-yellow-500/30 bg-yellow-500/10 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:text-yellow-400"
                >
                    <span class="size-1.5 rounded-full bg-yellow-500" />
                    Sem registro DNS
                </span>

                <span class="font-mono text-xs text-muted-foreground">
                    <template v-if="record">{{ record.name }} → {{ record.content }}</template>
                    <template v-else>{{ host }} → {{ cnameTarget }}</template>
                </span>
            </div>

            <!-- Create action -->
            <Dialog v-if="!recordExists" v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button size="sm" variant="outline" class="inline-flex items-center gap-1.5">
                        <Cloud class="size-3.5" />
                        Criar CNAME
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Criar registro CNAME?</DialogTitle>
                        <DialogDescription>
                            O seguinte registro será criado no Cloudflare:
                        </DialogDescription>
                    </DialogHeader>
                    <div class="rounded-md border border-border bg-muted/40 px-4 py-3 font-mono text-sm">
                        {{ host }} → {{ cnameTarget }}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" :disabled="createForm.processing" @click="createOpen = false">
                            Cancelar
                        </Button>
                        <Button :disabled="createForm.processing" @click="confirmCreate">
                            <Loader2 v-if="createForm.processing" class="mr-1.5 size-3.5 animate-spin" />
                            {{ createForm.processing ? 'Criando...' : 'Criar' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete action -->
            <Dialog v-if="recordExists" v-model:open="deleteOpen">
                <DialogTrigger as-child>
                    <Button size="sm" variant="destructive" class="inline-flex items-center gap-1.5">
                        <TriangleAlert class="size-3.5" />
                        Remover
                    </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                                <TriangleAlert class="size-5 text-destructive" />
                            </div>
                            <div>
                                <DialogTitle>Remover registro DNS?</DialogTitle>
                                <DialogDescription class="mt-0.5">
                                    Remove o CNAME <strong>{{ record?.name }}</strong> do Cloudflare. Esta ação não pode ser desfeita.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" :disabled="deleteForm.processing" @click="deleteOpen = false">
                            Cancelar
                        </Button>
                        <Button variant="destructive" :disabled="deleteForm.processing" @click="confirmDelete">
                            <Loader2 v-if="deleteForm.processing" class="mr-1.5 size-3.5 animate-spin" />
                            {{ deleteForm.processing ? 'Removendo...' : 'Remover' }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
