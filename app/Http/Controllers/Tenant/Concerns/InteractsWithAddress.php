<?php

namespace App\Http\Controllers\Tenant\Concerns;

use App\Models\Address;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait InteractsWithAddress
{
    /**
     * @param  array<string, mixed>|null  $addressData
     */
    private function syncAddress(Model $model, ?array $addressData, Request $request): void
    {
        if ($addressData === null || ! $this->hasAddressData($addressData)) {
            return;
        }

        $address = null;
        $addressId = $addressData['id'] ?? null;

        if (is_string($addressId) && $addressId !== '') {
            $address = $model->addresses()->whereKey($addressId)->first();
        }

        if (! $address instanceof Address) {
            $address = $model->addresses()->orderByDesc('is_default')->latest()->first() ?? $model->addresses()->make();
        }

        $address->fill([
            'type' => (string) ($addressData['type'] ?? 'home'),
            'tenant_id' => $this->tenantId(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'name' => $addressData['name'] ?? null,
            'zip_code' => $addressData['zip_code'] ?? null,
            'street' => $addressData['street'] ?? null,
            'number' => $addressData['number'] ?? null,
            'complement' => $addressData['complement'] ?? null,
            'reference' => $addressData['reference'] ?? null,
            'additional_information' => $addressData['additional_information'] ?? null,
            'district' => $addressData['district'] ?? null,
            'city' => $addressData['city'] ?? null,
            'country' => $addressData['country'] ?? 'Brasil',
            'state' => $addressData['state'] ?? null,
            'is_default' => (bool) ($addressData['is_default'] ?? false),
            'status' => (string) ($addressData['status'] ?? 'draft'),
        ]);

        $model->addresses()->save($address);
    }

    /**
     * @param  array<string, mixed>  $addressData
     */
    private function hasAddressData(array $addressData): bool
    {
        return collect($addressData)
            ->except(['id', 'is_default', 'status', 'country'])
            ->contains(fn ($value): bool => is_string($value) && trim($value) !== '');
    }

    /**
     * @return array<string, mixed>
     */
    private function addressPayload(Address $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'name' => $address->name,
            'zip_code' => $address->zip_code,
            'street' => $address->street,
            'number' => $address->number,
            'complement' => $address->complement,
            'reference' => $address->reference,
            'additional_information' => $address->additional_information,
            'district' => $address->district,
            'city' => $address->city,
            'country' => $address->country,
            'state' => $address->state,
            'is_default' => (bool) $address->is_default,
            'status' => $address->status,
        ];
    }
}
