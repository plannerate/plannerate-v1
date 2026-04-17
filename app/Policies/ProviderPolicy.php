<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Policies;

use Callcocam\LaravelRaptor\Policies\AbstractPolicy;

class ProviderPolicy extends AbstractPolicy
{
    protected ?string $permission = 'providers';
}
