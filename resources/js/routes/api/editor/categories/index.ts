import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/api/editor/categories',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
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
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
export const show = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/api/editor/{categoryId}/categories',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
show.url = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { categoryId: args }
    }

    if (Array.isArray(args)) {
        args = {
            categoryId: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        categoryId: args.categoryId,
    }

    return show.definition.url
            .replace('{categoryId}', parsedArgs.categoryId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
show.get = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
show.head = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
const showForm = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
showForm.get = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::show
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
showForm.head = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const categories = {
    index: Object.assign(index, index),
    show: Object.assign(show, show),
}

export default categories