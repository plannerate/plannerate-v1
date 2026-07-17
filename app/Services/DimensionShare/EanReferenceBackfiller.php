<?php

namespace App\Services\DimensionShare;

use App\Models\EanReference;

/**
 * Aproveita as dimensões preenchidas pelo cliente (via link público) para popular a
 * referência de EAN (landlord) — mas apenas quando ela ainda está vazia, para nunca
 * sobrescrever uma referência já curada.
 */
class EanReferenceBackfiller
{
    public function fillIfEmpty(?string $ean, float $width, float $height, float $depth): void
    {
        $normalized = EanReference::normalizeEan((string) ($ean ?? ''));

        if ($normalized === '') {
            return;
        }

        $reference = EanReference::query()
            ->forNormalizedEan($normalized)
            ->whereNull('deleted_at')
            ->first();

        if ($reference instanceof EanReference) {
            // Só preenche se a referência estiver sem dimensões — nunca sobrescreve.
            if ($this->hasDimensions($reference)) {
                return;
            }

            $reference->update([
                'width' => $width,
                'height' => $height,
                'depth' => $depth,
                'unit' => 'cm',
                'has_dimensions' => true,
            ]);

            return;
        }

        // Sem referência para este EAN: cria uma com as medidas informadas pelo cliente.
        EanReference::query()->create([
            'ean' => $normalized,
            'width' => $width,
            'height' => $height,
            'depth' => $depth,
            'unit' => 'cm',
            'has_dimensions' => true,
        ]);
    }

    private function hasDimensions(EanReference $reference): bool
    {
        return (float) $reference->width > 0
            && (float) $reference->height > 0
            && (float) $reference->depth > 0;
    }
}
