<?php

namespace App\Enums;

enum ShelfLevel: string
{
    case Eye = 'eye';     // ~60% da altura — nível dos olhos
    case Hand = 'hand';   // ~40% da altura — nível das mãos
    case Low = 'low';     // 0-30% da altura — chão
    case High = 'high';   // >75% da altura — acima dos olhos

    public function label(): string
    {
        return match ($this) {
            self::Eye => 'Nível dos olhos',
            self::Hand => 'Nível das mãos',
            self::Low => 'Nível do chão',
            self::High => 'Nível alto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Eye => 'success',
            self::Hand => 'info',
            self::Low => 'warning',
            self::High => 'secondary',
        };
    }

    public function priorityScore(): int
    {
        return match ($this) {
            self::Eye => 100,
            self::Hand => 80,
            self::Low => 40,
            self::High => 20,
        };
    }

    /**
     * Resolve o ShelfLevel a partir de shelf_position (0=topo) e total de shelves.
     * Posição relativa = shelf_position / (num_shelves - 1)
     * 0.0 = topo, 1.0 = chão
     *
     * Com num_shelves = 5 (típico):
     * - shelf_position 0 → HIGH (topo)
     * - shelf_position 1 → EYE
     * - shelf_position 2 → EYE
     * - shelf_position 3 → HAND
     * - shelf_position 4 → LOW (chão)
     */
    /**
     * Ordem de preferência de fallback para cada nível ABC.
     * Primeiro = ideal, último = último recurso.
     * Produto NUNCA vai para nível fora desta lista.
     *
     * @return array<int, self>
     */
    public function fallbackOrder(): array
    {
        return match ($this) {
            self::Eye => [self::Eye, self::Hand],
            self::Hand => [self::Hand, self::Eye, self::Low],
            self::Low => [self::Low, self::Hand],
            self::High => [self::High, self::Eye],
        };
    }

    public static function fromShelfPosition(int $shelfPosition, int $numShelves): self
    {
        if ($numShelves <= 1) {
            return self::Eye;
        }

        $relative = $shelfPosition / max(1, $numShelves - 1);

        // 0.0 (topo) → HIGH
        // ~0.35 → EYE
        // ~0.6 → HAND
        // 1.0 (chão) → LOW
        return match (true) {
            $relative <= 0.20 => self::High,
            $relative <= 0.50 => self::Eye,
            $relative <= 0.80 => self::Hand,
            default => self::Low,
        };
    }
}
