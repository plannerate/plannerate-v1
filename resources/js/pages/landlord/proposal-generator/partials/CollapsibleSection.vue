<script setup lang="ts">
/** Seção sanfonada do editor (equivalente ao `tog()` da ferramenta original). */
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        title: string;
        /** Cor do marcador: '' (lima), 'p' (preto) ou 't' (teal). */
        dot?: '' | 'p' | 't';
        open?: boolean;
    }>(),
    { dot: '', open: false },
);

const isOpen = ref(props.open);

/** Permite ao pai revelar a seção — usado para mostrar o rascunho recém-salvo. */
defineExpose({
    openSection: () => {
        isOpen.value = true;
    },
});
</script>

<template>
    <section class="section" :class="{ open: isOpen }">
        <h3 @click="isOpen = !isOpen">
            <span><i class="dot" :class="dot" />{{ title }}</span>
            <span class="chev">›</span>
        </h3>
        <div class="body">
            <slot />
        </div>
    </section>
</template>
