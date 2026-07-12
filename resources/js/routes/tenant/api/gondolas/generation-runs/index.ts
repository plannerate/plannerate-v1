import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
export const index = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/gondolas/{gondola}/generation-runs',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
index.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return index.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
index.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
index.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
const indexForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
indexForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:23
* @route '/api/gondolas/{gondola}/generation-runs'
*/
indexForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
export const latest = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latest.url(args, options),
    method: 'get',
})

latest.definition = {
    methods: ["get","head"],
    url: '/api/gondolas/{gondola}/generation-runs/latest',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
latest.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return latest.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
latest.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: latest.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
latest.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: latest.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
const latestForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latest.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
latestForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latest.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::latest
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:53
* @route '/api/gondolas/{gondola}/generation-runs/latest'
*/
latestForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: latest.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

latest.form = latestForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
export const pending = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(args, options),
    method: 'get',
})

pending.definition = {
    methods: ["get","head"],
    url: '/api/gondolas/{gondola}/generation-runs/pending',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
pending.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { gondola: args }
    }

    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
    }

    return pending.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
pending.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: pending.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
pending.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: pending.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
const pendingForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pending.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
pendingForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pending.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::pending
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:110
* @route '/api/gondolas/{gondola}/generation-runs/pending'
*/
pendingForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: pending.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

pending.form = pendingForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
export const show = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/gondolas/{gondola}/generation-runs/{run}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
show.url = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
            run: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
        run: args.run,
    }

    return show.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{run}', parsedArgs.run.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
show.get = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
show.head = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
const showForm = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
showForm.get = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\PlanogramGenerationRunController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/PlanogramGenerationRunController.php:38
* @route '/api/gondolas/{gondola}/generation-runs/{run}'
*/
showForm.head = (args: { gondola: string | number, run: string | number } | [gondola: string | number, run: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const generationRuns = {
    index: Object.assign(index, index),
    latest: Object.assign(latest, latest),
    pending: Object.assign(pending, pending),
    show: Object.assign(show, show),
}

export default generationRuns