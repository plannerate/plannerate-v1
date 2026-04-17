<?php

namespace App\Services\Auth;

use App\Data\LoginAsTokenData;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LoginAsTokenBroker
{
    public function issue(User $actor, string $tenantId, string $clientId): ?string
    {
        if (! $actor->hasRole('super-admin')) {
            return null;
        }

        if ((string) $actor->tenant_id !== $tenantId) {
            return null;
        }

        $plainToken = Str::random((int) config('login_as.token_length', 64));
        $tokenHash = hash('sha256', $plainToken);
        $now = CarbonImmutable::now();

        DB::connection($this->connection())
            ->table($this->table())
            ->insert([
                'id' => (string) Str::ulid(),
                'token_hash' => $tokenHash,
                'actor_user_id' => $actor->id,
                'tenant_id' => $tenantId,
                'client_id' => $clientId,
                'expires_at' => $now->addSeconds($this->ttlSeconds()),
                'used_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        return $plainToken;
    }

    public function consume(string $plainToken, ?string $expectedTenantId, ?string $expectedClientId): ?LoginAsTokenData
    {
        $tokenHash = hash('sha256', $plainToken);
        $expectedTenantId = $this->normalizeNullableString($expectedTenantId);
        $expectedClientId = $this->normalizeNullableString($expectedClientId);

        return DB::connection($this->connection())->transaction(function () use ($tokenHash, $expectedTenantId, $expectedClientId) {
            $record = DB::connection($this->connection())
                ->table($this->table())
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                return null;
            }

            if ($record->used_at !== null) {
                return null;
            }

            if (CarbonImmutable::parse($record->expires_at)->isPast()) {
                return null;
            }

            $tokenTenantId = $this->normalizeNullableString($record->tenant_id);
            $tokenClientId = $this->normalizeNullableString($record->client_id);

            if ($expectedTenantId !== $tokenTenantId || $expectedClientId !== $tokenClientId) {
                return null;
            }

            $now = CarbonImmutable::now();

            DB::connection($this->connection())
                ->table($this->table())
                ->where('id', $record->id)
                ->update([
                    'used_at' => $now,
                    'updated_at' => $now,
                ]);

            return new LoginAsTokenData(
                id: (string) $record->id,
                actorUserId: (string) $record->actor_user_id,
                tenantId: (string) $record->tenant_id,
                clientId: (string) $record->client_id
            );
        });
    }

    private function connection(): string
    {
        return (string) config('raptor.database.landlord_connection_name', 'landlord');
    }

    private function table(): string
    {
        return (string) config('login_as.table', 'login_as_tokens');
    }

    private function ttlSeconds(): int
    {
        return max(1, (int) config('login_as.ttl_seconds', 90));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}

