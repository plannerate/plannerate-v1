<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum AdjacencyRuleType: string
{
    case MustBeNear = 'must_be_near';
    case MustAvoid = 'must_avoid';
    case PreferNear = 'prefer_near';

    public function label(): string
    {
        return match ($this) {
            self::MustBeNear => 'Deve ficar perto',
            self::MustAvoid => 'Nao pode ficar perto',
            self::PreferNear => 'Preferencialmente perto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MustBeNear => 'success',
            self::MustAvoid => 'danger',
            self::PreferNear => 'info',
        };
    }

    public function defaultWeight(): float
    {
        return match ($this) {
            self::MustBeNear => 50.0,
            self::MustAvoid => -100.0,
            self::PreferNear => 10.0,
        };
    }
}
