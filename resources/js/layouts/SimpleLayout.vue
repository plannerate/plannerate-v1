<script setup lang="ts">
import { Toaster } from '@/components/ui/sonner';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    title?: string;
    message?: string;
    maxWidth?: string;
}

const props = withDefaults(defineProps<Props>(), {
    maxWidth: '7xl',
    title: 'Dashboard'
});

// Título da página
const pageTitle = computed(() => props.title || 'Dashboard');

// Configuração de layout com largura máxima 
</script>

<template>
  <Head :title="pageTitle" />

  <div class="min-h-screen bg-background"> 
    <Toaster />
    
    <div class="w-full mx-auto px-4 sm:px-6 lg:px-8" :class="`max-w-${props.maxWidth}`">
      <slot>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
          <slot name="content">
            <!-- Conteúdo padrão se nenhum slot for fornecido -->
            <div class="text-muted-foreground">
              {{ message }}
            </div>
          </slot>
        </div>
      </slot>
    </div>
  </div>
</template>