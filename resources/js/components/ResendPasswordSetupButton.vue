<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Mail } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useT } from '@/composables/useT';

withDefaults(defineProps<{
    resendUrl: string;
    userName: string;
    variant?: 'icon' | 'button';
}>(), {
    variant: 'button',
});

const { t } = useT();
const isOpen = ref(false);

/**
 * Reenvia o link de definição de senha após confirmação no diálogo.
 */
function onResend(resendUrl: string): void {
    router.post(resendUrl, {}, {
        preserveScroll: true,
        onFinish: () => {
            isOpen.value = false;
        },
    });
}
</script>

<template>
    <AlertDialog v-model:open="isOpen">
        <AlertDialogTrigger as-child>
            <button
                v-if="variant === 'icon'"
                type="button"
                class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-primary/10 hover:text-primary"
                :title="t('app.password_setup.resend.trigger')"
            >
                <Mail class="size-4" />
            </button>
            <Button v-else variant="outline" size="sm">
                {{ t('app.password_setup.resend.trigger') }}
            </Button>
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('app.password_setup.resend.title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('app.password_setup.resend.description', { name: userName }) }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('app.actions.cancel') }}</AlertDialogCancel>
                <AlertDialogAction @click="onResend(resendUrl)">
                    {{ t('app.password_setup.resend.confirm') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
