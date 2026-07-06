<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TenantPasswordSetupToken extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_USED = 'used';

    protected $connection = 'landlord';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'target_user_id',
        'target_user_name',
        'target_user_email',
        'issuer_id',
        'issuer_name',
        'issuer_email',
        'code_hash',
        'status',
        'used_reason',
        'expires_at',
        'used_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Tenant dono do banco físico onde o usuário-alvo vive.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verifica se o link de definição de senha já passou do prazo de validade.
     */
    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * Verifica se o token já foi utilizado (consumido ou invalidado por um reenvio).
     */
    public function isUsed(): bool
    {
        return $this->status === self::STATUS_USED;
    }

    /**
     * Marca o token como usado com o motivo informado. No-op se já estiver usado,
     * para não sobrescrever o motivo original.
     */
    public function markUsed(string $reason = 'consumed'): void
    {
        if ($this->status === self::STATUS_USED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_USED,
            'used_reason' => $reason,
            'used_at' => Carbon::now(),
        ]);
    }

    /**
     * Invalida (marca como usados) todos os tokens pendentes anteriores para este
     * tenant+usuário-alvo, garantindo que nunca haja dois links válidos simultâneos
     * para a mesma pessoa. Chamado sempre no início da emissão, tanto na criação
     * inicial (no-op, nada pendente ainda) quanto no reenvio.
     */
    public static function invalidateOutstandingFor(string $tenantId, string $targetUserId, string $reason = 'superseded'): void
    {
        static::query()
            ->where('tenant_id', $tenantId)
            ->where('target_user_id', $targetUserId)
            ->where('status', self::STATUS_PENDING)
            ->update([
                'status' => self::STATUS_USED,
                'used_reason' => $reason,
                'used_at' => Carbon::now(),
            ]);
    }
}
