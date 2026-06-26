import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
export const store = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/executions/{execution}/evidences',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
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
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
store.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
const storeForm = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::store
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:35
* @route '/executions/{execution}/evidences'
*/
storeForm.post = (args: { execution: string | { id: string } } | [execution: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroy
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
export const destroy = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/executions/{execution}/evidences/{evidence}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroy
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
destroy.url = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            execution: args[0],
            evidence: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        execution: typeof args.execution === 'object'
        ? args.execution.id
        : args.execution,
        evidence: typeof args.evidence === 'object'
        ? args.evidence.id
        : args.evidence,
    }

    return destroy.definition.url
            .replace('{execution}', parsedArgs.execution.toString())
            .replace('{evidence}', parsedArgs.evidence.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroy
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
destroy.delete = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroy
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
const destroyForm = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\Tenant\GondolaExecutionLayerController::destroy
* @see app/Http/Controllers/Tenant/GondolaExecutionLayerController.php:55
* @route '/executions/{execution}/evidences/{evidence}'
*/
destroyForm.delete = (args: { execution: string | { id: string }, evidence: string | number | { id: string | number } } | [execution: string | { id: string }, evidence: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const evidences = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
}

export default evidences