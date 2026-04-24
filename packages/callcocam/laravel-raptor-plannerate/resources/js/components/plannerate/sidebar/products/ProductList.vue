<template>
    <div class="space-y-2">
        <!-- Loading state -->
        <div v-if="isLoading" class="space-y-2">
            <div
                v-for="i in 5"
                :key="i"
                class="h-20 animate-pulse rounded-lg bg-muted"
            />
        </div>

        <!-- Products -->
        <template v-else>
            <CardProduct
                v-for="product in products"
                :key="product.id"
                :product="product"
            />

            <!-- Empty state -->
            <div
                v-if="!isLoading && products.length === 0"
                class="flex flex-col items-center justify-center gap-2 py-8 text-center"
            >
                <Package class="size-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    {{
                        searchQuery
                            ? 'Nenhum produto encontrado'
                            : 'Nenhum produto disponível'
                    }}
                </p>
            </div>

            <!-- Loading more -->
            <div v-if="isLoadingMore" class="py-4 text-center">
                <div
                    class="inline-block size-5 animate-spin rounded-full border-2 border-muted border-t-primary"
                />
            </div>

            <!-- End message -->
            <div
                v-if="showEndMessage"
                class="py-4 text-center text-xs text-muted-foreground"
            >
                Todos os produtos carregados
            </div>
        </template>
    </div>
</template>

<script setup lang="ts">
import type { Product } from '@/types/planogram';
import { Package } from 'lucide-vue-next';
import CardProduct from './Card.vue';

interface Props {
    products: Product[];
    isLoading: boolean;
    isLoadingMore: boolean;
    showEndMessage: boolean;
    searchQuery?: string;
}

defineProps<Props>();
</script>
