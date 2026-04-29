<?php

namespace App\Http\Controllers\Tenant\Concerns;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

trait InteractsWithDeferredIndex
{
    protected function requestString(Request $request, string $key): string
    {
        return trim((string) $request->string($key));
    }

    /**
     * @param  list<string>  $allowedValues
     */
    protected function requestEnum(Request $request, string $key, array $allowedValues): string
    {
        $value = $this->requestString($request, $key);

        return in_array($value, $allowedValues, true) ? $value : '';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    protected function renderDeferredIndex(
        string $component,
        string $propName,
        Closure $resolver,
        array $props = [],
    ): Response {
        return Inertia::render($component, [
            ...$props,
            $propName => Inertia::defer($resolver),
        ]);
    }
}
