<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum FacingExpansion: string
{
    case None = 'none';
    case Score = 'score';
    case CurrentStock = 'current_stock';
    case TargetStock = 'target_stock';
    case Equal = 'equal';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Não expandir (usar apenas mínimo)',
            self::Score => 'Por score ABC/vendas (mais relevante ganha mais frentes)',
            self::CurrentStock => 'Por estoque atual (maior estoque ganha mais frentes)',
            self::TargetStock => 'Por déficit de estoque (mais longe do alvo ganha mais frentes)',
            self::Equal => 'Distribuição igual (todos ganham +1 por rodada)',
        };
    }
}
