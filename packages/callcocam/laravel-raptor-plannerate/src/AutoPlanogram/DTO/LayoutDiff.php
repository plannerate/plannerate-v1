<?php

namespace Callcocam\LaravelRaptorPlannerate\AutoPlanogram\DTO;

/**
 * Resultado da comparação entre o layout atual da gôndola e o proposto pela reotimização.
 *
 * `entries` traz SÓ o que mudou — uma gôndola com 300 produtos e 4 mudanças produz 4 linhas.
 * O usuário revisa mudanças, não inventário.
 */
final readonly class LayoutDiff
{
    /**
     * @param  list<LayoutDiffEntry>  $entries
     * @param  array<string, int>  $summary  Contagem por tipo de mudança + totais.
     */
    public function __construct(
        public array $entries,
        public array $summary,
        public bool $hasChanges,
    ) {}

    /** @return array{entries: list<array<string, mixed>>, summary: array<string, int>, has_changes: bool} */
    public function toArray(): array
    {
        return [
            'entries' => array_map(fn (LayoutDiffEntry $e): array => $e->toArray(), $this->entries),
            'summary' => $this->summary,
            'has_changes' => $this->hasChanges,
        ];
    }
}
