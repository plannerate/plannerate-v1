<?php

namespace App\Http\Controllers\Concerns;

trait HasClientVisibilityRule
{
    protected function currentClientId(): ?string
    {
        $clientId = config('app.current_client_id');

        return empty($clientId) ? null : (string) $clientId;
    }

    protected function hasCurrentClientContext(): bool
    {
        return ! empty($this->currentClientId());
    }

    protected function hasNoCurrentClientContext(): bool
    {
        return ! $this->hasCurrentClientContext();
    }
}
