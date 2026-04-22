<script setup lang="ts">
import { SlidersHorizontal, Search } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import TenantUserAccessController from '@/actions/App/Http/Controllers/Landlord/TenantUserAccessController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type FilterOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    tenantId: string;
    filters: { search: string; status: string };
    statusOptions: FilterOption[];
    usersFrom: number | null;
    usersTo: number | null;
    usersTotal: number;
    filterLabel: string;
    clearLabel: string;
}>();
</script>

<template>
    <form
        :action="TenantUserAccessController.edit.url(tenantId)"
        method="get"
        class="rounded-xl border border-border bg-card p-3"
    >
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative min-w-0 flex-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    name="search"
                    :default-value="filters.search"
                    placeholder="Buscar por nome, email ou perfil..."
                    class="h-9 w-full rounded-lg border-border bg-background pl-9 text-sm focus-visible:border-primary/60 focus-visible:ring-primary/20"
                />
            </div>

            <!-- Status select -->
            <select
                name="status"
                :value="filters.status"
                class="h-9 rounded-lg border border-border bg-background px-3 text-sm text-foreground outline-none transition focus:border-primary/60 focus:ring-2 focus:ring-primary/20"
            >
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>

            <!-- Filter button -->
            <Button
                type="submit"
                variant="outline"
                size="sm"
                class="h-9 gap-2 rounded-lg border-border"
            >
                <SlidersHorizontal class="size-4" />
                {{ filterLabel }}
            </Button>

            <!-- Clear button -->
            <Button
                variant="ghost"
                size="sm"
                as-child
                class="h-9 rounded-lg text-muted-foreground hover:text-foreground"
            >
                <Link :href="TenantUserAccessController.edit.url(tenantId)">
                    {{ clearLabel }}
                </Link>
            </Button>

            <!-- User count -->
            <p v-if="usersTotal > 0" class="ml-auto shrink-0 text-sm text-muted-foreground">
                Exibindo <span class="font-medium text-foreground">{{ usersTotal }}</span>
                {{ usersTotal === 1 ? 'usuário' : 'usuários' }}
            </p>
        </div>
    </form>
</template>
