<?php

namespace App\Support\Authorization;

final class RbacType
{
    public const LANDLORD = 'landlord';

    public const TENANT = 'tenant';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::LANDLORD,
            self::TENANT,
        ];
    }

    public static function fromName(string $name): ?string
    {
        if (str_starts_with($name, self::LANDLORD.'.')) {
            return self::LANDLORD;
        }

        if (str_starts_with($name, self::TENANT.'.')) {
            return self::TENANT;
        }

        return null;
    }
}
