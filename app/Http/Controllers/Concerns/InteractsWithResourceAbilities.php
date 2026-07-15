<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Gate;

trait InteractsWithResourceAbilities
{
    /**
     * Resolve the current user's create/update/delete abilities for a resource,
     * for gating action buttons on index pages.
     *
     * The abilities are resolved at the resource level (one Gate check each),
     * which matches the permission-name based policies in this app (they do not
     * inspect the model instance). An empty instance is passed to update/delete
     * because those policy methods require a model argument.
     *
     * @param  class-string  $modelClass
     * @return array{create: bool, update: bool, delete: bool}
     */
    protected function resolveResourceAbilities(string $modelClass): array
    {
        $model = new $modelClass;

        return [
            'create' => Gate::allows('create', $modelClass),
            'update' => Gate::allows('update', $model),
            'delete' => Gate::allows('delete', $model),
        ];
    }
}
