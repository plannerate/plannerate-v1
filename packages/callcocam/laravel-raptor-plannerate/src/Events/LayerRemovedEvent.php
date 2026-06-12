<?php

/**
 * Created by Claudio Campos.
 * User: callcocam, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace Callcocam\LaravelRaptorPlannerate\Events;

use Callcocam\LaravelRaptorPlannerate\Models\Gondola;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando uma layer (produto em prateleira) é removida do planograma.
 *
 * Listeners podem usar este evento para reagir à remoção — por exemplo,
 * inserindo o produto na tabela de rejeitados quando a gôndola é automática ou de template.
 */
class LayerRemovedEvent
{
    use Dispatchable, SerializesModels;

    /**
     * @param  object  $layer  Dados da layer removida (stdClass do banco: id, product_id, segment_id, tenant_id, ...)
     * @param  Gondola  $gondola  Gôndola à qual a layer pertencia (contém generation_mode, planogram_id, tenant_id)
     */
    public function __construct(
        public readonly object $layer,
        public readonly Gondola $gondola,
    ) {}
}
