<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const quote = page.props.quote as { message: string; author: string };

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex h-dvh items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-5 lg:px-0"
        style="background-color: oklch(0 0 0)"
    >
        <div
            class="relative hidden h-full flex-1 flex-col p-10 text-white lg:col-span-4 lg:flex dark:border-r"
        >
            <div
                class="absolute inset-0 mx-auto max-w-7xl bg-cover bg-center md:bg-contain"
                style="
                    background-image: url('/img/marca.jpg');
                    background-repeat: no-repeat;
                "
            />
            <div v-if="quote" class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <p class="text-lg">&ldquo;{{ quote.message }}&rdquo;</p>
                    <footer class="text-sm text-neutral-300">
                        {{ quote.author }}
                    </footer>
                </blockquote>
            </div>
        </div>
        <div
            class="flex h-full items-center justify-center bg-background lg:p-8 dark:bg-background"
        >
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[250px] md:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1
                        class="text-xl font-medium tracking-tight text-foreground"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
