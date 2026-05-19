<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\Tenant;
use Callcocam\LaravelRaptorPlannerate\Jobs\ProcessProductImagesByEansJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $eans = array_values(array_filter(
            (array) $request->input('eans', []),
            fn (mixed $ean): bool => is_string($ean) && trim($ean) !== '',
        ));

        if (empty($eans)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Nenhum EAN válido informado.']);

            return redirect()->back();
        }

        $tenant = Tenant::current();
        $database = $tenant?->database ?? config('database.connections.tenant.database');

        if (! $database) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Database do tenant não configurado.']);

            return redirect()->back();
        }

        ProcessProductImagesByEansJob::dispatch(
            $eans,
            (string) $database,
            '',
            (string) auth()->id(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Atualização de imagens em segundo plano iniciada. '.count($eans).' produto(s) na fila.',
        ]);

        return redirect()->back();
    }
}
