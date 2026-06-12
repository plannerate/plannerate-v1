import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
const indexab25431e228e97e34c5fbbff957dcee0 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indexab25431e228e97e34c5fbbff957dcee0.url(options),
    method: 'get',
})

indexab25431e228e97e34c5fbbff957dcee0.definition = {
    methods: ["get","head"],
    url: '/api/editor/categories',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
indexab25431e228e97e34c5fbbff957dcee0.url = (options?: RouteQueryOptions) => {
    return indexab25431e228e97e34c5fbbff957dcee0.definition.url + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
indexab25431e228e97e34c5fbbff957dcee0.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indexab25431e228e97e34c5fbbff957dcee0.url(options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
indexab25431e228e97e34c5fbbff957dcee0.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: indexab25431e228e97e34c5fbbff957dcee0.url(options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
const indexab25431e228e97e34c5fbbff957dcee0Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indexab25431e228e97e34c5fbbff957dcee0.url(options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
indexab25431e228e97e34c5fbbff957dcee0Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indexab25431e228e97e34c5fbbff957dcee0.url(options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/categories'
*/
indexab25431e228e97e34c5fbbff957dcee0Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indexab25431e228e97e34c5fbbff957dcee0.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

indexab25431e228e97e34c5fbbff957dcee0.form = indexab25431e228e97e34c5fbbff957dcee0Form
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
const indexb8c0de5232540dfd37e2888a75e5ea8e = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indexb8c0de5232540dfd37e2888a75e5ea8e.url(args, options),
    method: 'get',
})

indexb8c0de5232540dfd37e2888a75e5ea8e.definition = {
    methods: ["get","head"],
    url: '/api/editor/{categoryId}/categories',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
indexb8c0de5232540dfd37e2888a75e5ea8e.url = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return indexb8c0de5232540dfd37e2888a75e5ea8e.definition.url
            .replace('{categoryId}', parsedArgs.categoryId.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
indexb8c0de5232540dfd37e2888a75e5ea8e.get = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: indexb8c0de5232540dfd37e2888a75e5ea8e.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
indexb8c0de5232540dfd37e2888a75e5ea8e.head = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: indexb8c0de5232540dfd37e2888a75e5ea8e.url(args, options),
    method: 'head',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
const indexb8c0de5232540dfd37e2888a75e5ea8eForm = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indexb8c0de5232540dfd37e2888a75e5ea8e.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
indexb8c0de5232540dfd37e2888a75e5ea8eForm.get = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indexb8c0de5232540dfd37e2888a75e5ea8e.url(args, options),
    method: 'get',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\CategoryController::index
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/CategoryController.php:22
* @route '/api/editor/{categoryId}/categories'
*/
indexb8c0de5232540dfd37e2888a75e5ea8eForm.head = (args: { categoryId: string | number } | [categoryId: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: indexb8c0de5232540dfd37e2888a75e5ea8e.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

indexb8c0de5232540dfd37e2888a75e5ea8e.form = indexb8c0de5232540dfd37e2888a75e5ea8eForm

export const index = {
    '/api/editor/categories': indexab25431e228e97e34c5fbbff957dcee0,
    '/api/editor/{categoryId}/categories': indexb8c0de5232540dfd37e2888a75e5ea8e,
}

const CategoryController = { index }

export default CategoryController