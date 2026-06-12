import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
export const show = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/tenant/gondola/{gondola}/section/{section}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
show.url = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            gondola: args[0],
            section: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        gondola: args.gondola,
        section: args.section,
    }

    return show.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
show.get = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
show.head = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
const showForm = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showForm.get = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showForm.head = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const section = {
    show: Object.assign(show, show),
}

export default section