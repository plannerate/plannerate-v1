<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { ref, watch } from 'vue';
import FormTextField from '@/components/form/FormTextField.vue';
import { toSlug } from '@/support/slug';

/**
 * Campo de formulário que gera automaticamente um slug a partir de um texto de
 * origem (normalmente o campo "nome").
 *
 * Enquanto o usuário não editar o slug manualmente, ele acompanha a origem em
 * tempo real. Ao ser editado à mão — ou quando já existe um valor inicial em
 * modo de edição (`lockOnEdit`) — a geração automática é desativada para não
 * sobrescrever o valor do usuário.
 */
const props = withDefaults(
    defineProps<{
        /** Valor de origem (ex.: o nome) do qual o slug é derivado. */
        source?: string;
        id?: string;
        name?: string;
        label?: string;
        /** Valor inicial do slug (usado em edição). */
        defaultValue?: string;
        error?: string;
        required?: boolean;
        disabled?: boolean;
        class?: HTMLAttributes['class'];
        /** Quando true, um valor inicial preenchido bloqueia a geração automática. */
        lockOnEdit?: boolean;
    }>(),
    {
        source: '',
        id: 'slug',
        name: 'slug',
        label: 'Slug',
        defaultValue: '',
        error: '',
        required: false,
        disabled: false,
        class: undefined,
        lockOnEdit: true,
    },
);

/** Slug atual exibido e enviado no formulário. */
const slug = ref(props.defaultValue);

/** Indica se a geração automática foi desativada (edição manual ou valor inicial). */
const touched = ref(props.lockOnEdit && props.defaultValue.trim() !== '');

/** Último valor gerado automaticamente — usado para distinguir edição manual. */
let lastAuto = slug.value;

// Regenera o slug sempre que a origem muda, enquanto não houver edição manual.
watch(
    () => props.source,
    (value) => {
        if (touched.value) {
            return;
        }

        lastAuto = toSlug(value ?? '');
        slug.value = lastAuto;
    },
);

// Qualquer alteração do slug que não venha da geração automática marca como manual.
watch(slug, (value) => {
    if (value !== lastAuto) {
        touched.value = true;
    }
});
</script>

<template>
    <FormTextField
        :id="id"
        v-model="slug"
        :name="name"
        :label="label"
        :required="required"
        :disabled="disabled"
        :error="error"
        :class="props.class"
    />
</template>
