<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ClientUpdateRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientSettingsController extends Controller
{
    /**
     * Exibe a página de configurações do client.
     */
    public function edit(Request $request): Response
    {
        $clientId = config('app.current_client_id');
        
        if (!$clientId) {
            abort(404, 'Client não encontrado.');
        }

        $client = Client::with(['address', 'domain'])->findOrFail($clientId);

        return Inertia::render('settings/ClientInfo', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
                'cnpj' => $client->cnpj,
                'phone' => $client->phone,
                'email' => $client->email,
                'description' => $client->description,
                'status' => $client->status,
                'address' => $client->address,
                'domain' => $client->domain?->domain,
            ],
        ]);
    }

    /**
     * Atualiza as informações do client.
     */
    public function update(ClientUpdateRequest $request): RedirectResponse
    {
        $clientId = config('app.current_client_id');
        
        if (!$clientId) {
            abort(404, 'Client não encontrado.');
        }

        $client = Client::findOrFail($clientId);
        $client->fill($request->validated());
        $client->save();

        // Atualiza endereço se fornecido
        if ($request->has('address')) {
            $addressData = $request->input('address');
            if ($client->address) {
                $client->address->update($addressData);
            } else {
                $client->address()->create($addressData);
            }
        }

        return to_route('client-settings.edit')
            ->with('success', 'Informações do cliente atualizadas com sucesso.');
    }
}

