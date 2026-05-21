<?php

namespace App\Models;

use App\Enums\ZonePriority;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PlanogramSubtemplate extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, UsesTenantConnection;

    protected $fillable = [
        'tenant_id',
        'template_id',
        'code',
        'num_modules',
        'description',
        'slot_defaults',
        'is_active',
        'hot_zone_priority',
        'cold_zone_priority',
    ];

    protected function casts(): array
    {
        return [
            'num_modules' => 'integer',
            'slot_defaults' => 'array',
            'is_active' => 'boolean',
            'hot_zone_priority' => ZonePriority::class,
            'cold_zone_priority' => ZonePriority::class,
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanogramTemplate::class, 'template_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PlanogramTemplateSlot::class, 'subtemplate_id')
            ->orderBy('module_number')
            ->orderBy('shelf_order')
            ->orderBy('ordering');
    }

    /**
     * Clones this subtemplate into a new one with more modules.
     * All existing slots are copied; cells in the extra module(s) start empty.
     *
     * @throws \InvalidArgumentException when targetModules <= current num_modules
     */
    public function cloneWithSlots(int $targetModules): self
    {
        if ($targetModules <= $this->num_modules) {
            throw new \InvalidArgumentException(
                "targetModules ({$targetModules}) deve ser maior que num_modules atual ({$this->num_modules})"
            );
        }

        return DB::transaction(function () use ($targetModules): self {
            $clone = self::withTrashed()
                ->where('template_id', $this->template_id)
                ->where('num_modules', $targetModules)
                ->lockForUpdate()
                ->first();

            if ($clone === null) {
                $clone = $this->replicate();
                $clone->num_modules = $targetModules;
                $clone->code = $this->code."-{$targetModules}M";
                $clone->description = "Baseado em {$this->code} ({$this->num_modules} módulos)";
                $clone->save();
            } else {
                if ($clone->trashed()) {
                    $clone->restore();
                }

                $clone->code = $this->code."-{$targetModules}M";
                $clone->description = "Baseado em {$this->code} ({$this->num_modules} módulos)";
                $clone->is_active = true;
                $clone->save();

                $clone->slots()->withTrashed()->forceDelete();
            }

            $this->loadMissing('slots');

            foreach ($this->slots as $slot) {
                $newSlot = $slot->replicate();
                $newSlot->subtemplate_id = $clone->getKey();
                $newSlot->save();
            }

            Log::info('PlanogramSubtemplate: clone criado', [
                'source_id' => $this->getKey(),
                'source_code' => $this->code,
                'source_modules' => $this->num_modules,
                'clone_id' => $clone->getKey(),
                'clone_code' => $clone->code,
                'clone_modules' => $targetModules,
                'slots_copiados' => $this->slots->count(),
            ]);

            return $clone;
        });
    }
}
