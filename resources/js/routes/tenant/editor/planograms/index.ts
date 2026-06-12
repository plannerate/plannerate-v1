import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/editor/planograms',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::index
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:23
* @route '/editor/planograms'
*/
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

index.form = indexForm

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
export const gondolas = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: gondolas.url(args, options),
    method: 'get',
})

gondolas.definition = {
    methods: ["get","head"],
    url: '/editor/planograms/{planogram}/gondolas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
gondolas.url = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { planogram: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { planogram: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            planogram: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        planogram: typeof args.planogram === 'object'
        ? args.planogram.id
        : args.planogram,
    }

    return gondolas.definition.url
            .replace('{planogram}', parsedArgs.planogram.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
gondolas.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: gondolas.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
gondolas.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: gondolas.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
const gondolasForm = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: gondolas.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
gondolasForm.get = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: gondolas.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\Editor\ClientPlanogramController::gondolas
* @see app/Http/Controllers/Tenant/Editor/ClientPlanogramController.php:65
* @route '/editor/planograms/{planogram}/gondolas'
*/
gondolasForm.head = (args: { planogram: string | { id: string } } | [planogram: string | { id: string } ] | string | { id: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: gondolas.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

gondolas.form = gondolasForm

const planograms = {
    index: Object.assign(index, index),
    gondolas: Object.assign(gondolas, gondolas),
}

export default planograms