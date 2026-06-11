<?php

namespace Callcocam\LaravelRaptorPlannerate\Enums;

enum ValidationSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informação',
            self::Warning => 'Aviso',
            self::Error => 'Erro',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Error => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Info => 'info-circle',
            self::Warning => 'exclamation-triangle',
            self::Error => 'x-circle',
        };
    }
}
