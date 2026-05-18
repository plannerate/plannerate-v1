<?php

namespace App\Services;

use App\Models\EanReference;
use App\Models\SimilarGroup;
use Illuminate\Support\Collection;

class EanReferenceSimilarSyncService
{
    /**
     * @param  list<string|null>  $currentEans
     * @param  list<string|null>  $previousEans
     */
    public function sync(SimilarGroup $similarGroup, array $currentEans, ?string $previousGrouperCode = null, array $previousEans = []): void
    {
        $grouperCode = (string) $similarGroup->grouper_code;
        $normalizedCurrentEans = $this->normalizedEans($currentEans);
        $normalizedPreviousEans = $this->normalizedEans($previousEans);

        if ($previousGrouperCode !== null && $previousGrouperCode !== $grouperCode) {
            $this->removeGrouperCode($previousGrouperCode, $normalizedPreviousEans);
        }

        $removedEans = $normalizedPreviousEans
            ->diff($normalizedCurrentEans)
            ->values();

        if ($removedEans->isNotEmpty()) {
            $this->removeGrouperCode($grouperCode, $removedEans);
        }

        if ($normalizedCurrentEans->count() < 2) {
            return;
        }

        $normalizedCurrentEans->each(function (string $ean) use ($grouperCode, $normalizedCurrentEans): void {
            $this->syncReferenceMetadata(
                $ean,
                $grouperCode,
                $normalizedCurrentEans
                    ->reject(fn (string $similarEan): bool => $similarEan === $ean)
                    ->values()
                    ->all(),
            );
        });
    }

    public function remove(SimilarGroup $similarGroup): void
    {
        $similarGroup->loadMissing('products');

        $this->removeGrouperCode(
            (string) $similarGroup->grouper_code,
            $this->normalizedEans($similarGroup->products->pluck('ean')->all()),
        );
    }

    /**
     * @param  list<string>  $similarEans
     */
    private function syncReferenceMetadata(string $ean, string $grouperCode, array $similarEans): void
    {
        $reference = EanReference::withTrashed()->firstOrCreate([
            'ean' => $ean,
        ]);

        if ($reference->trashed()) {
            $reference->restore();
        }

        $metadata = $reference->metadata ?? [];
        $metadata['similares'] = is_array($metadata['similares'] ?? null)
            ? $metadata['similares']
            : [];
        $metadata['similares'][$grouperCode] = $similarEans;

        $reference->forceFill([
            'metadata' => $metadata,
        ])->save();
    }

    /**
     * @param  Collection<int, string>  $eans
     */
    private function removeGrouperCode(string $grouperCode, Collection $eans): void
    {
        if ($eans->isEmpty()) {
            return;
        }

        EanReference::query()
            ->whereIn('ean', $eans->all())
            ->get()
            ->each(function (EanReference $reference) use ($grouperCode): void {
                $metadata = $reference->metadata ?? [];

                if (! isset($metadata['similares']) || ! is_array($metadata['similares'])) {
                    return;
                }

                unset($metadata['similares'][$grouperCode]);

                if ($metadata['similares'] === []) {
                    unset($metadata['similares']);
                }

                $reference->forceFill([
                    'metadata' => $metadata === [] ? null : $metadata,
                ])->save();
            });
    }

    /**
     * @param  list<string|null>  $eans
     * @return Collection<int, string>
     */
    private function normalizedEans(array $eans): Collection
    {
        return collect($eans)
            ->filter(fn (?string $ean): bool => is_string($ean) && trim($ean) !== '')
            ->map(fn (string $ean): string => EanReference::normalizeEan($ean))
            ->filter(fn (string $ean): bool => $ean !== '')
            ->unique()
            ->values();
    }
}
