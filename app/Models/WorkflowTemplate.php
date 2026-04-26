<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tall\Sluggable\HasSlug;
use Tall\Sluggable\SlugOptions;

class WorkflowTemplate extends Model
{
    use BelongsToTenant, HasUlids, SoftDeletes, HasSlug;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'template_next_step_id',
        'template_previous_step_id',
        'name',
        'slug',
        'description',
        'suggested_order',
        'estimated_duration_days',
        'default_role_id',
        'color',
        'icon',
        'is_required_by_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'suggested_order' => 'integer',
            'estimated_duration_days' => 'integer',
            'is_required_by_default' => 'boolean',
        ];
    }

    public function nextStep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_next_step_id');
    }

    public function previousStep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_previous_step_id');
    }

    public function suggestedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workflow_template_users')
            ->withTimestamps();
    }

    public function configSteps(): HasMany
    {
        return $this->hasMany(WorkflowPlanogramStep::class);
    }


    /**
     * @return SlugOptions
     */
    public function getSlugOptions()
    {
        if (is_string($this->slugTo())) {
            return SlugOptions::create()
                ->generateSlugsFrom($this->slugFrom())
                ->saveSlugsTo($this->slugTo());
        }
    }

    /**
     * Retorna os templates padrão (ex.: planograma).
     * Use flow:seed-templates para criar no banco.
     *
     * @return array<int, array{name: string, slug: string, description: string, instructions: string, category: string, suggested_order: int, estimated_duration_days: int, is_required_by_default: bool, color: string, icon: string, tags: array}>
     */
    public static function getDefaultTemplates(): array
    {
        $defaults = [
            [
                'name' => 'Criação do planograma',
                'description' => 'Criação inicial do planograma com definição de produtos e layout, Definir produtos, posicionamento e layout inicial do planograma',
                'category' => 'criacao',
                'suggested_order' => 1,
                'estimated_duration_days' => 2,
                'is_required_by_default' => true,
                'color' => 'blue',
                'icon' => 'layout-grid',
                'tags' => ['inicial', 'obrigatoria'],
            ],
            [
                'name' => 'Revisão de imagens',
                'description' => 'Revisão das imagens utilizadas no planograma, Validar qualidade, padrão e consistência visual das imagens',
                'category' => 'revisao',
                'suggested_order' => 2,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'indigo',
                'icon' => 'image',
                'tags' => ['revisao', 'imagens'],
            ],
            [
                'name' => 'Revisão de dimensões',
                'description' => 'Revisão das dimensões e medidas do planograma, Conferir medidas de gôndolas, módulos e espaçamentos',
                'category' => 'revisao',
                'suggested_order' => 3,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'gray',
                'icon' => 'ruler',
                'tags' => ['revisao', 'dimensoes'],
            ],
            [
                'name' => 'Aprovação comercial',
                'description' => 'Validação comercial do planograma proposto, Aprovar estratégia comercial, margem e objetivos de venda',
                'category' => 'aprovacao',
                'suggested_order' => 4,
                'estimated_duration_days' => 2,
                'is_required_by_default' => true,
                'color' => 'yellow',
                'icon' => 'trending-up',
                'tags' => ['aprovacao', 'comercial'],
            ],
            [
                'name' => 'Aprovação da área de GC',
                'description' => 'Aprovação pela área de Gerenciamento de Categoria, Validar alinhamento com estratégia de categoria e políticas',
                'category' => 'aprovacao',
                'suggested_order' => 5,
                'estimated_duration_days' => 2,
                'is_required_by_default' => true,
                'color' => 'purple',
                'icon' => 'check-circle',
                'tags' => ['aprovacao', 'gc'],
            ],
            [
                'name' => 'Execução loja',
                'description' => 'Implementação do planograma na loja, Implementar fisicamente o planograma na loja',
                'category' => 'execucao',
                'suggested_order' => 6,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'red',
                'icon' => 'store',
                'tags' => ['execucao', 'loja'],
            ],
            [
                'name' => 'Revisão periódica',
                'description' => 'Revisão recorrente do planograma em operação, Acompanhar desempenho e realizar ajustes periódicos',
                'category' => 'revisao',
                'suggested_order' => 7,
                'estimated_duration_days' => 1,
                'is_required_by_default' => true,
                'color' => 'blue',
                'icon' => 'refresh-cw',
                'tags' => ['revisao', 'periodica'],
            ],
        ];
 

        return $defaults;
    }
}
