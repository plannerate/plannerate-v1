<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUpdateRequest;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreSettingsController extends Controller
{
    /**
     * Exibe a página de configurações da store.
     */
    public function edit(Request $request): Response
    {
        $storeId = config('app.current_store_id');
        
        if (!$storeId) {
            abort(404, 'Store não encontrada.');
        }

        $store = Store::with(['address', 'client'])->findOrFail($storeId);

        return Inertia::render('settings/StoreInfo', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'code' => $store->code,
                'document' => $store->document,
                'phone' => $store->phone,
                'email' => $store->email,
                'description' => $store->description,
                'status' => $store->status,
                'address' => $store->address,
                'client' => $store->client ? [
                    'id' => $store->client->id,
                    'name' => $store->client->name,
                ] : null,
            ],
        ]);
    }

    /**
     * Atualiza as informações da store.
     */
    public function update(StoreUpdateRequest $request): RedirectResponse
    {
        $storeId = config('app.current_store_id');
        
        if (!$storeId) {
            abort(404, 'Store não encontrada.');
        }

        $store = Store::findOrFail($storeId);
        $store->fill($request->validated());
        $store->save();

        // Atualiza endereço se fornecido
        if ($request->has('address')) {
            $addressData = $request->input('address');
            if ($store->address) {
                $store->address->update($addressData);
            } else {
                $store->address()->create($addressData);
            }
        }

        return to_route('store-settings.edit')
            ->with('success', 'Informações da loja atualizadas com sucesso.');
    }
}

