<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Link público (com token) que permite a alguém sem conta preencher as dimensões
 * dos produtos de um tenant. Vive no banco landlord para ser localizável
 * independentemente de qual banco de tenant esteja ativo; sempre filtrado por
 * tenant_id correspondente ao tenant atual (resolvido pelo subdomínio).
 */
class TenantDimensionShareToken extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $connection = 'landlord';

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'category_id',
        'category_name',
        'issuer_id',
        'issuer_name',
        'issuer_email',
        'label',
        'code_hash',
        'status',
        'expires_at',
        'last_used_at',
        'use_count',
        'revoked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'use_count' => 'integer',
        ];
    }

    /**
     * Tenant dono do banco físico onde os produtos vivem.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verifica se o link já passou do prazo de validade.
     */
    public function isExpired(): bool
    {
        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * Verifica se o link ainda pode ser usado (ativo e dentro do prazo).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    /**
     * Revoga o link imediatamente. No-op se já revogado.
     */
    public function revoke(): void
    {
        if ($this->status === self::STATUS_REVOKED) {
            return;
        }

        $this->update([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => Carbon::now(),
        ]);
    }

    /**
     * Registra atividade no link (último acesso e contador de usos).
     */
    public function registerUse(): void
    {
        $this->forceFill([
            'last_used_at' => Carbon::now(),
            'use_count' => $this->use_count + 1,
        ])->save();
    }
}
