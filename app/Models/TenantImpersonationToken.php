<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class TenantImpersonationToken extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

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
        'ended_reason',
        'expires_at',
        'consumed_at',
        'session_expires_at',
        'ended_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'session_expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Tenant alvo da impersonation (dono do banco físico onde o usuário impersonado vive).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Admin do landlord que emitiu esta impersonation.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_id');
    }

    /**
     * Verifica se o código de handoff (link de consumo) já passou do prazo de validade.
     */
    public function isCodeExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * Verifica se a sessão de impersonation já ativa ultrapassou seu teto de duração.
     */
    public function isSessionExpired(): bool
    {
        return $this->session_expires_at !== null && Carbon::now()->greaterThan($this->session_expires_at);
    }

    /**
     * Marca o token como consumido com sucesso, iniciando a janela da sessão ativa.
     */
    public function markActive(int $sessionMinutes): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'consumed_at' => Carbon::now(),
            'session_expires_at' => Carbon::now()->addMinutes($sessionMinutes),
        ]);
    }

    /**
     * Encerra o token com o motivo informado. No-op se já estiver encerrado, para não
     * sobrescrever o motivo real do encerramento original.
     */
    public function markEnded(string $reason): void
    {
        if ($this->status === self::STATUS_ENDED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_ENDED,
            'ended_reason' => $reason,
            'ended_at' => Carbon::now(),
        ]);
    }
}
