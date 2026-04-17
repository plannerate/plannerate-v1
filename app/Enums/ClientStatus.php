<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Enums;

enum ClientStatus: string
{
    case Published = 'published';
    case Draft = 'draft';

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public static function labels(): array
    {
        return [
            self::Published->value => 'Publicado',
            self::Draft->value => 'Rascunho',
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()])
            ->values()
            ->toArray();
    }

    public static function getOptions(): array
    {
        return [
            self::Published->value => self::Published->label(),
            self::Draft->value => self::Draft->label(),
        ];
    }

    public static function variantOptions(): array
    {
        return [
            self::Published->value => 'success',
            self::Draft->value => 'secondary',
        ];
    }
}
