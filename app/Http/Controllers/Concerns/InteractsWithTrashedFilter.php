<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait InteractsWithTrashedFilter
{
    /**
     * Resolves the `trashed` query parameter: without (default), only, with.
     */
    protected function resolveTrashedFilter(Request $request): string
    {
        $value = trim((string) $request->string('trashed'));

        if ($value === '' || $value === 'without') {
            return 'without';
        }

        if (in_array($value, ['only', 'with'], true)) {
            return $value;
        }

        return 'without';
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function applyTrashedToQuery(Builder $query, string $mode): void
    {
        if ($mode === 'only') {
            $query->onlyTrashed();

            return;
        }

        if ($mode === 'with') {
            $query->withTrashed();
        }
    }
}
