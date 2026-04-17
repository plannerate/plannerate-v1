<?php

/**
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Actions\Raptor;

use Callcocam\LaravelRaptor\Support\Actions\Types\ExecuteAction; 
use Callcocam\LaravelRaptor\Support\Concerns\Interacts\WithActions;

class DropdownAction extends ExecuteAction
{
    use WithActions;

    protected string $actionType = 'actions';

    public function __construct(?string $name = null)
    {
        parent::__construct($name ?? 'dropdown');
        $this->component('action-dropdown');
        $this->icon('EllipsisVertical');
    }

    /**
     * Renderiza a action com as actions internas (itens do dropdown).
     */
    public function render($model = null, $request = null): array
    {
        $result = parent::render($model, $request);
        $result['actions'] = collect($this->getActions($model))->map(function ($action) use ($model, $request) {
           $action->icon('ExternalLink');
            return $action->toArray($model, $request);
        });

        return $result;
    }
}
