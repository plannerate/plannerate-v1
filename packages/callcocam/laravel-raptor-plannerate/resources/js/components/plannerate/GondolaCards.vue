<script setup lang="ts">
import {
    calculateAbc,
    calculateTargetStock,
} from '@/actions/Callcocam/LaravelRaptorPlannerate/Http/Controllers/Tenant/GondolaAnalysisController';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import type { Gondola } from '@/types/planogram';
import { router } from '@inertiajs/vue3';
import {
    ArrowRightLeft,
    BarChart3,
    Calendar,
    ChevronRight,
    Hash,
    Layers,
    LayoutGrid,
    MapPin,
    MoreVertical,
    Package,
    Ruler,
} from 'lucide-vue-next';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { Button } from '~/components/ui/button';

interface Props {
    gondolas: Gondola[];
    planogramId: string;
}

defineProps<Props>();

const getStatusColor = (status: string | undefined) => {
    return status === 'published'
        ? 'bg-green-500/10 text-green-500'
        : 'bg-yellow-500/10 text-yellow-500';
};

const getFlowLabel = (flow: string | undefined) => {
    const flows: Record<string, string> = {
        right_to_left: 'Direita → Esquerda',
        left_to_right: 'Esquerda → Direita',
    };
    return flows[flow || ''] || flow || 'N/A';
};

const getTotalShelves = (gondola: Gondola) => {
    return (gondola.sections ?? []).reduce(
        (total, section) => total + (section.shelves ?? []).length,
        0,
    );
};

const handleAbcAnalysis = (gondolaId: string) => {
    const routeDefinition = calculateAbc(gondolaId);
    router.visit(routeDefinition.url);
};

const calculateEstoqueAlvo = (gondolaId: string) => {
    const routeDefinition = calculateTargetStock(gondolaId);
    router.visit(routeDefinition.url);
};

const calculateBcg = (_gondolaId: string) => {
    void _gondolaId;
    // TODO: Implementar quando o serviço estiver disponível
};
</script>

<template>
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <Card
            v-for="gondola in gondolas"
            :key="gondola.id"
            class="group relative overflow-hidden transition-all hover:shadow-lg"
        >
            <div class="block">
                <CardHeader class="pb-3">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <CardTitle
                                class="flex items-center gap-2 text-lg transition-colors group-hover:text-primary"
                            >
                                <LayoutGrid class="size-5" />
                                {{ gondola.name }}
                            </CardTitle>
                            <CardDescription class="mt-1.5">
                                {{ gondola.slug }}
                            </CardDescription>
                        </div>
                        <Badge
                            :class="getStatusColor(gondola.status)"
                            variant="outline"
                        >
                            {{ gondola.status }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="space-y-4">
                    <!-- Informações principais -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="flex items-center gap-2">
                            <Hash class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Módulos
                                </p>
                                <p class="font-medium">
                                    {{ gondola.num_modulos }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <MapPin class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Lado
                                </p>
                                <p class="font-medium">{{ gondola.side }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <MapPin class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Localização
                                </p>
                                <p class="font-medium">
                                    {{ gondola.location }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Ruler class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Escala
                                </p>
                                <p class="font-medium">
                                    {{ gondola.scale_factor }}x
                                </p>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Fluxo -->
                    <div class="flex items-center gap-2 text-sm">
                        <ArrowRightLeft class="size-4 text-muted-foreground" />
                        <div>
                            <p class="text-xs text-muted-foreground">Fluxo</p>
                            <p class="font-medium">
                                {{ getFlowLabel(gondola.flow) }}
                            </p>
                        </div>
                    </div>

                    <Separator />

                    <!-- Seções e Prateleiras -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <Layers class="size-4 text-muted-foreground" />
                                <span class="font-medium">Módulos</span>
                            </div>
                            <Badge variant="secondary">{{
                                (gondola.sections ?? []).length
                            }}</Badge>
                        </div>

                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <Layers class="size-4 text-muted-foreground" />
                                <span class="font-medium"
                                    >Total Prateleiras</span
                                >
                            </div>
                            <Badge variant="secondary">{{
                                getTotalShelves(gondola)
                            }}</Badge>
                        </div>
                    </div>

                    <!-- Detalhes das seções -->
                    <div
                        v-if="(gondola.sections ?? []).length > 0"
                        class="space-y-2"
                    >
                        <Separator />
                        <div class="space-y-1.5">
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Detalhes das Seções
                            </p>
                            <div class="max-h-52 space-y-1 overflow-y-auto">
                                <div
                                    v-for="section in gondola.sections"
                                    :key="section.id"
                                    class="flex items-center justify-between rounded-md bg-muted/50 px-2 py-1.5 text-xs"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{
                                            section.name
                                        }}</span>
                                        <span class="text-muted-foreground">{{
                                            section.code
                                        }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{ section.width }}x{{
                                                section.height
                                            }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="text-xs"
                                        >
                                            {{ (section.shelves ?? []).length }}
                                            prateleiras
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Footer com botões de ação -->
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Calendar class="size-3" />
                            <span>{{
                                gondola.updated_at
                                    ? new Date(
                                          gondola.updated_at,
                                      ).toLocaleDateString('pt-BR')
                                    : 'N/A'
                            }}</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Dropdown de Cálculos -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="h-8 w-8"
                                    >
                                        <ActionIconBox variant="outline">
                                            <MoreVertical />
                                        </ActionIconBox>
                                        <span class="sr-only"
                                            >Abrir menu de cálculos</span
                                        >
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel
                                        >Cálculos</DropdownMenuLabel
                                    >
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        @click="handleAbcAnalysis(gondola.id)"
                                    >
                                        <BarChart3 class="mr-2 size-4" />
                                        <span>Análise ABC</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="
                                            calculateEstoqueAlvo(gondola.id)
                                        "
                                    >
                                        <Package class="mr-2 size-4" />
                                        <span>Estoque Alvo</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="calculateBcg(gondola.id)"
                                        disabled
                                    >
                                        <BarChart3 class="mr-2 size-4" />
                                        <span>Matriz BCG</span>
                                        <span
                                            class="ml-auto text-xs text-muted-foreground"
                                            >Em breve</span
                                        >
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Botão Abrir -->
                            <a
                                target="_blank"
                                :href="gondola.route_gondolas"
                                class="flex items-center gap-1 text-sm font-medium text-primary transition-all group-hover:gap-2"
                            >
                                <span>Abrir Edição</span>
                                <ChevronRight class="size-4" />
                            </a>
                        </div>
                    </div>
                </CardContent>
            </div>
        </Card>
    </div>
</template>
