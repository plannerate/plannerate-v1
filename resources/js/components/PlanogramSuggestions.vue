<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

interface SlotSuggestionData {
    largura_livre: number;
    percentual_uso: number;
    grouping: string;
    shelf_order: number;
    module_number: number;
}

interface Suggestion {
    tipo: 'espaco_disponivel' | 'capacidade_excedida';
    prioridade: 'alta' | 'media';
    slot_id?: string;
    mensagem: string;
    acao: string;
    dados: SlotSuggestionData & {
        total_rejeitados?: number;
        groupings_cheios?: string[];
        produtos_fora?: string[];
    };
}

const props = defineProps<{
    suggestions: Suggestion[];
    templateId: string;
}>();

const page = usePage();
const expanded = ref(true);

function slotsUrl(query: Record<string, string | number> = {}): string {
    const tenant = page.props.tenant as { slug?: string } | undefined;
    const subdomain = tenant?.slug || (typeof window !== 'undefined' ? window.location.hostname.split('.')[0] : '');
    const base = `//${subdomain}.plannerate.localhost/planogram-templates/${props.templateId}/slots`;
    const params = new URLSearchParams();

    for (const [key, val] of Object.entries(query)) {
        params.set(key, String(val));
    }

    const qs = params.toString();

    return qs ? `${base}?${qs}` : base;
}

function goToTemplateSlot(dados: SlotSuggestionData): void {
    router.visit(slotsUrl({ module: dados.module_number, shelf: dados.shelf_order }));
}

function goToAddSubtemplate(): void {
    router.visit(slotsUrl({ action: 'add-subtemplate' }));
}
</script>

<template>
    <div v-if="suggestions.length" class="rounded-lg border border-border bg-background shadow-sm">
        <!-- Header colapsável -->
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/50"
            @click="expanded = !expanded"
        >
            <div class="flex items-center gap-2">
                <span class="text-base">💡</span>
                <span class="text-sm font-semibold">
                    {{ suggestions.length }} sugestão{{ suggestions.length > 1 ? 'ões' : '' }} de otimização
                </span>
                <span
                    v-if="suggestions.some((s) => s.prioridade === 'alta')"
                    class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700"
                >
                    Alta prioridade
                </span>
            </div>
            <ChevronUp v-if="expanded" class="size-4 shrink-0 text-muted-foreground" />
            <ChevronDown v-else class="size-4 shrink-0 text-muted-foreground" />
        </button>

        <!-- Lista de sugestões -->
        <div v-show="expanded" class="divide-y divide-border border-t border-border">
            <div
                v-for="(suggestion, i) in suggestions"
                :key="i"
                class="flex items-start gap-3 px-4 py-3"
                :class="{
                    'bg-red-50/50 dark:bg-red-950/20': suggestion.prioridade === 'alta',
                    'bg-amber-50/50 dark:bg-amber-950/20': suggestion.prioridade === 'media',
                }"
            >
                <!-- Ícone por tipo -->
                <span class="mt-0.5 shrink-0 text-base">
                    {{ suggestion.tipo === 'espaco_disponivel' ? '📐' : '⚠️' }}
                </span>

                <div class="min-w-0 flex-1 space-y-1">
                    <p class="text-sm font-medium text-foreground">{{ suggestion.mensagem }}</p>
                    <p class="text-xs text-muted-foreground">{{ suggestion.acao }}</p>

                    <!-- Produtos fora da gôndola -->
                    <details
                        v-if="
                            suggestion.tipo === 'capacidade_excedida' &&
                            suggestion.dados.produtos_fora?.length
                        "
                        class="mt-1"
                    >
                        <summary class="cursor-pointer text-xs font-medium text-muted-foreground hover:text-foreground">
                            Ver produtos fora ({{ suggestion.dados.produtos_fora.length }})
                        </summary>
                        <ul class="mt-1 space-y-0.5 pl-3">
                            <li
                                v-for="nome in suggestion.dados.produtos_fora"
                                :key="nome"
                                class="text-xs text-muted-foreground"
                            >
                                · {{ nome }}
                            </li>
                        </ul>
                    </details>
                </div>

                <!-- Ação rápida -->
                <div class="shrink-0">
                    <Button
                        v-if="suggestion.tipo === 'espaco_disponivel'"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        @click="goToTemplateSlot(suggestion.dados as SlotSuggestionData)"
                    >
                        Editar slot →
                    </Button>
                    <Button
                        v-if="suggestion.tipo === 'capacidade_excedida'"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        @click="goToAddSubtemplate"
                    >
                        Criar subtemplate →
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
