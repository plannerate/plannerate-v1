import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
export const store = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/executions/{execution}/divergences',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
store.url = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { execution: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { execution: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            execution: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
    }

    return store.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
store.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
const storeForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:76
* @route '/executions/{execution}/divergences'
*/
storeForm.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::update
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
export const update = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/executions/{execution}/divergences/{divergence}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::update
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
update.url = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            execution: args[0],
            divergence: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
        divergence: typeof args.divergence === 'object'
        ? args.divergence.id
        : args.divergence,
    }

    return update.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace('{divergence}', parsedArgs.divergence.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::update
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
update.patch = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::update
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
const updateForm = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::update
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:104
* @route '/executions/{execution}/divergences/{divergence}'
*/
updateForm.patch = (args: { execution: string | { id: string }, divergence: string | number | { id: string | number } } | [execution: string | { id: string }, divergence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

const divergences = {
    store: Object.assign(store, store),
    update: Object.assign(update, update),
}

export default divergences