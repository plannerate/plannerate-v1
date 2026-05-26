<?php

namespace App\Http\Controllers\Concerns;

use App\Jobs\ProcessEanReferenceImageJob;
use App\Models\EanReference;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Modules\ModuleSlug;
use App\Support\Modules\TenantModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Atualiza imagens dos produtos a partir do catálogo EAN.
 *
 * Fluxo:
 *  - EANs com imagem já cacheada em ean_references → atualiza products.url diretamente via
 *    Product::query() (tenant já ativo no request, sem filtro de módulo).
 *  - EANs sem imagem cacheada → ProcessEanReferenceImageJob faz o download em background e
 *    depois chama EanReferenceImageSyncService::syncOne() com o tenantId explícito.
 *
 * NÃO usa ProcessProductImagesByEansJob/DOProcessProductImageJob do package, pois esses
 * jobs resolvem produto por ID de schema legado e ignoram o cache do ean_references.
 */
trait InteractsWithSyncImageDownLoad
{
    protected function shouldSyncImageDownload(): bool
    {
        return true;
    }

    public function updateImages(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Product::class);

        $request->validate([
            'eans' => ['required', 'array', 'min:1'],
            'eans.*' => ['required', 'string', 'max:50'],
        ]);

        /** @var list<string> $rawEans */
        $rawEans = (array) $request->input('eans', []);

        // Normaliza e deduplica os EANs recebidos
        $normalizedEans = collect($rawEans)
            ->filter(fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '')
            ->map(fn (string $ean): string => EanReference::normalizeEan($ean))
            ->filter(fn (string $ean): bool => $ean !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($normalizedEans)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Nenhum EAN válido informado.']);

            return redirect()->back();
        }

        $tenant = Tenant::current();

        if (! $tenant) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Contexto de tenant não encontrado.']);

            return redirect()->back();
        }

        // Só processa se o tenant tiver o módulo image-bank habilitado
        if (! app(TenantModuleService::class)->tenantHasActiveModule($tenant, ModuleSlug::IMAGE_BANK)) {
            return redirect()->back();
        }

        $tenantId = (string) $tenant->id;

        // Separa EANs com imagem já cacheada dos que precisam de download
        $referencesWithImage = EanReference::query()
            ->whereIn('ean', $normalizedEans)
            ->whereNotNull('image_front_url')
            ->where('image_front_url', '!=', '')
            ->whereNull('deleted_at')
            ->get(['id', 'ean', 'image_front_url']);

        $eansWithImage = $referencesWithImage
            ->pluck('ean')
            ->map(fn (mixed $e): string => (string) $e)
            ->all();

        $eansNeedingDownload = array_values(array_diff($normalizedEans, $eansWithImage));

        $synced = 0;
        $queued = 0;

        // EANs com imagem cacheada: atualiza products.url diretamente no contexto do tenant.
        // Usa Product::query() (TenantScope ativo no request) para evitar filtro de módulo.
        foreach ($referencesWithImage as $reference) {
            $path = (string) $reference->image_front_url;
            $ean = (string) $reference->ean;

            // Atualiza apenas produtos cujo url está vazio ou diferente do path atual
            $count = Product::query()
                ->where('ean', $ean)
                ->where(fn ($q) => $q->whereNull('url')->orWhere('url', '!=', $path))
                ->update(['url' => $path, 'updated_at' => now()]);

            $synced += $count;
        }

        // EANs sem imagem: garante registro em ean_references e despacha download em background
        foreach ($eansNeedingDownload as $ean) {
            /** @var EanReference $reference */
            $reference = EanReference::firstOrCreate(['ean' => $ean]);

            ProcessEanReferenceImageJob::dispatch(
                eanReferenceId: (string) $reference->id,
                force: false,
                tenantIds: [$tenantId],
            );
            $queued++;
        }

        $parts = [];

        if ($synced > 0) {
            $parts[] = "{$synced} produto(s) com imagem atualizada";
        }

        if ($queued > 0) {
            $parts[] = "{$queued} EAN(s) em download em segundo plano";
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Atualização de imagens iniciada: '.implode(', ', $parts ?: ['nenhuma alteração']).'.',
        ]);

        return redirect()->back();
    }
}
