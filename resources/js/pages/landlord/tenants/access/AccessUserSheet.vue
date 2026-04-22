<script setup lang="ts">
import { UserPlus } from 'lucide-vue-next';
import { Form } from '@inertiajs/vue3';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useT } from '@/composables/useT';

type TenantPayload = {
    id: string;
    name: string;
    slug: string;
    plan_user_limit: number | null;
    users_count: number;
    can_create_users: boolean;
    limit_message: string | null;
};

type UserAccessRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    deleted_at: string | null;
    role_names: string[];
};

type RoleOption = {
    id: string;
    name: string;
};

const props = defineProps<{
    open: boolean;
    mode: 'create' | 'edit';
    user: UserAccessRow | null;
    tenant: TenantPayload;
    roles: RoleOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const { t } = useT();

function getUserInitials(name: string): string {
    const tokens = name
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    if (tokens.length === 0) {
        return '';
    }

    return tokens.map((part) => part.charAt(0).toUpperCase()).join('');
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full p-0 sm:max-w-lg">
            <div class="flex h-full flex-col">
                <div class="shrink-0 border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                    <SheetHeader class="space-y-0 text-left">
                        <div class="flex items-center gap-3">
                            <Avatar class="size-10 border border-sidebar-border/70 dark:border-sidebar-border">
                                <AvatarFallback class="bg-primary/15 text-sm font-semibold text-primary">
                                    <template v-if="mode === 'edit' && user">
                                        {{ getUserInitials(user.name) }}
                                    </template>
                                    <UserPlus v-else class="size-4 text-primary" />
                                </AvatarFallback>
                            </Avatar>
                            <SheetTitle class="text-base">
                                {{ mode === 'create' ? t('app.landlord.tenant_access.create_user') : `${t('app.landlord.tenant_access.edit_user')}: ${user?.name}` }}
                            </SheetTitle>
                        </div>
                    </SheetHeader>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-6 py-6">
                    <Form
                        v-if="mode === 'create'"
                        v-bind="TenantUserAccessController.store.form(tenant.id)"
                        class="flex min-h-full flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-6">
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ t('app.landlord.users.fields.information') || 'Informações' }}
                                </p>
                                <Separator />
                            </div>

                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="create_name">{{ t('app.landlord.users.fields.name') }}</Label>
                                    <Input id="create_name" name="name" required />
                                    <InputError :message="errors.name" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="create_email">{{ t('app.landlord.users.fields.email') }}</Label>
                                    <Input id="create_email" name="email" type="email" required />
                                    <InputError :message="errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="create_password">{{ t('app.landlord.users.fields.password') }}</Label>
                                    <Input id="create_password" name="password" type="password" required />
                                    <InputError :message="errors.password" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="create_password_confirmation">{{ t('app.landlord.users.fields.password_confirmation') }}</Label>
                                    <Input id="create_password_confirmation" name="password_confirmation" type="password" required />
                                    <InputError :message="errors.password_confirmation" />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-1.5">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ t('app.landlord.tenant_access.roles') }}
                                    </p>
                                    <Separator />
                                </div>

                                <div class="grid gap-2">
                                    <label
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-input px-3 py-2.5 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary/60 has-[:checked]:bg-primary/5"
                                    >
                                        <input type="checkbox" name="role_names[]" :value="role.name" class="accent-primary" />
                                        <span class="font-medium">{{ role.name }}</span>
                                    </label>
                                </div>
                                <InputError :message="errors.role_names" />
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-1.5">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                                    <Separator />
                                </div>

                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-input px-3 py-2.5 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary/60 has-[:checked]:bg-primary/5">
                                    <input type="hidden" name="is_active" value="0" />
                                    <input id="create_is_active" name="is_active" type="checkbox" value="1" checked class="accent-primary" />
                                    <span class="font-medium">{{ t('app.landlord.users.fields.is_active') }}</span>
                                </label>
                            </div>

                            <InputError :message="errors.limit" />
                        </div>

                        <div class="sticky bottom-0 z-10 -mx-6 mt-6 border-t border-sidebar-border/70 bg-background/95 px-6 py-4 backdrop-blur dark:border-sidebar-border">
                            <div class="flex items-center gap-3">
                                <Button variant="gradient" :disabled="processing || !tenant.can_create_users">
                                    {{ t('app.actions.save') }}
                                </Button>
                                <Button type="button" variant="outline" @click="emit('update:open', false)">
                                    {{ t('app.actions.cancel') }}
                                </Button>
                            </div>
                        </div>
                    </Form>

                    <Form
                        v-else-if="user"
                        v-bind="TenantUserAccessController.update.form({ tenant: tenant.id, userId: user.id })"
                        class="flex min-h-full flex-col"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-6">
                            <div class="space-y-1.5">
                                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                    {{ t('app.landlord.users.fields.information') || 'Informações' }}
                                </p>
                                <Separator />
                            </div>

                            <div class="grid gap-4">
                                <div class="grid gap-2">
                                    <Label for="edit_name">{{ t('app.landlord.users.fields.name') }}</Label>
                                    <Input id="edit_name" name="name" :default-value="user.name" required />
                                    <InputError :message="errors.name" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="edit_email">{{ t('app.landlord.users.fields.email') }}</Label>
                                    <Input id="edit_email" name="email" type="email" :default-value="user.email" required />
                                    <InputError :message="errors.email" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="edit_password">{{ t('app.landlord.users.fields.password') }}</Label>
                                    <Input id="edit_password" name="password" type="password" />
                                    <p class="text-xs text-muted-foreground">{{ t('app.landlord.users.fields.password_hint') }}</p>
                                    <InputError :message="errors.password" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="edit_password_confirmation">{{ t('app.landlord.users.fields.password_confirmation') }}</Label>
                                    <Input id="edit_password_confirmation" name="password_confirmation" type="password" />
                                    <InputError :message="errors.password_confirmation" />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-1.5">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ t('app.landlord.tenant_access.roles') }}
                                    </p>
                                    <Separator />
                                </div>

                                <div class="grid gap-2">
                                    <label
                                        v-for="role in roles"
                                        :key="role.id"
                                        class="flex cursor-pointer items-center gap-3 rounded-lg border border-input px-3 py-2.5 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary/60 has-[:checked]:bg-primary/5"
                                    >
                                        <input
                                            type="checkbox"
                                            name="role_names[]"
                                            :value="role.name"
                                            :checked="user.role_names.includes(role.name)"
                                            class="accent-primary"
                                        />
                                        <span class="font-medium">{{ role.name }}</span>
                                    </label>
                                </div>
                                <InputError :message="errors.role_names" />
                            </div>

                            <div class="space-y-4">
                                <div class="space-y-1.5">
                                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                                    <Separator />
                                </div>

                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-input px-3 py-2.5 text-sm transition-colors hover:bg-accent has-[:checked]:border-primary/60 has-[:checked]:bg-primary/5">
                                    <input type="hidden" name="is_active" value="0" />
                                    <input
                                        id="edit_is_active"
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        :checked="user.is_active"
                                        class="accent-primary"
                                    />
                                    <span class="font-medium">{{ t('app.landlord.users.fields.is_active') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="sticky bottom-0 z-10 -mx-6 mt-6 border-t border-sidebar-border/70 bg-background/95 px-6 py-4 backdrop-blur dark:border-sidebar-border">
                            <div class="flex items-center gap-3">
                                <Button variant="gradient" :disabled="processing">
                                    {{ t('app.actions.save') }}
                                </Button>
                                <Button type="button" variant="outline" @click="emit('update:open', false)">
                                    {{ t('app.actions.cancel') }}
                                </Button>
                            </div>
                        </div>
                    </Form>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
