import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/AutoPlanogramController.php:167
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
export const destroy = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/gondolas/{gondola}/rejected-products/{rejected}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/AutoPlanogramController.php:167
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
destroy.url = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
            rejected: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
        rejected: args.rejected,
    }

    return destroy.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{rejected}', parsedArgs.rejected.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/AutoPlanogramController.php:167
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
destroy.delete = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/AutoPlanogramController.php:167
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
const destroyForm = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Generation\AutoPlanogramController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Generation/AutoPlanogramController.php:167
* @route '/api/gondolas/{gondola}/rejected-products/{rejected}'
*/
destroyForm.delete = (args: { gondola: string | number, rejected: string | number } | [gondola: string | number, rejected: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const rejectedProducts = {
    destroy: Object.assign(destroy, destroy),
}

export default rejectedProducts