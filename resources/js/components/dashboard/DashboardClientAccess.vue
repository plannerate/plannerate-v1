<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '~/components/ui/button';
import ActionIconBox from '~/components/ui/ActionIconBox.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { getClientStatusLabel } from '@/lib/status';
import type { ClientWithDomain } from '@/types/dashboard';

interface Props {
    clientsWithDomains?: ClientWithDomain[];
    isTenantPrincipal: boolean;
}

defineProps<Props>();
</script>

<template>
    <Card
        v-if="isTenantPrincipal && clientsWithDomains && clientsWithDomains.length > 0"
        class="col-span-full"
    >
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="lucide lucide-building-2 size-5 text-muted-foreground"
                >
                    <path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z" />
                    <path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" />
                    <path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2" />
                    <path d="M10 6h4" />
                    <path d="M10 10h4" />
                    <path d="M10 14h4" />
                    <path d="M10 18h4" />
                </svg>
                Clientes com acesso direto
            </CardTitle>
            <CardDescription>
                Clientes/lojas com domínios registrados. Clique para acessar diretamente.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="client in clientsWithDomains"
                    :key="client.id"
                    class="group relative overflow-hidden transition-all hover:shadow-md"
                >
                    <CardHeader class="pb-3">
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="text-base">
                                {{ client.name }}
                            </CardTitle>
                            <Badge
                                :variant="client.status === 'active' ? 'default' : 'secondary'"
                                class="shrink-0"
                            >
                                {{ getClientStatusLabel(client.status) }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 pt-0">
                        <div v-if="client.domain" class="space-y-2">
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="lucide lucide-globe shrink-0"
                                >
                                    <circle cx="12" cy="12" r="10" />
                                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20" />
                                    <path d="M2 12h20" />
                                </svg>
                                <span class="truncate font-mono text-xs">
                                    {{ client.domain.domain }}
                                </span>
                            </div>
                            <Button
                                as="a"
                                :href="client.domain.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                variant="default"
                                size="sm"
                                class="w-full gap-2"
                            >
                                <ActionIconBox variant="default">
                                    <ExternalLink class="size-4" />
                                </ActionIconBox>
                                Acessar
                            </Button>
                        </div>
                        <div v-else class="text-sm text-muted-foreground">
                            Nenhum domínio configurado
                        </div>
                    </CardContent>
                </Card>
            </div>
        </CardContent>
    </Card>
</template>
