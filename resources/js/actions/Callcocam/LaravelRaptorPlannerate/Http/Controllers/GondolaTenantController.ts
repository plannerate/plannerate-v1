import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
export const show = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/tenant/gondola/{gondola}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
show.url = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
show.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
show.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
const showForm = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
showForm.get = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:11
* @route '/tenant/gondola/{gondola}'
*/
showForm.head = (args: { gondola: string | number } | [gondola: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
export const showSection = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showSection.url(args, options),
    method: 'get',
})

showSection.definition = {
    methods: ["get","head"],
    url: '/tenant/gondola/{gondola}/section/{section}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showSection.url = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions) => {
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

    return showSection.definition.url
            .replace('{gondola}', parsedArgs.gondola.toString())
            .replace('{section}', parsedArgs.section.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showSection.get = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: showSection.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showSection.head = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: showSection.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
const showSectionForm = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: showSection.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showSectionForm.get = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: showSection.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\GondolaTenantController::showSection
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/GondolaTenantController.php:24
* @route '/tenant/gondola/{gondola}/section/{section}'
*/
showSectionForm.head = (args: { gondola: string | number, section: string | number } | [gondola: string | number, section: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: showSection.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

showSection.form = showSectionForm

const GondolaTenantController = { show, showSection }

export default GondolaTenantController