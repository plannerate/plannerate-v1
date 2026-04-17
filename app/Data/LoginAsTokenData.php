<?php

namespace App\Data;

final readonly class LoginAsTokenData
{
    public function __construct(
        public string $id,
        public string $actorUserId,
        public string $tenantId,
        public string $clientId
    ) {}
}

