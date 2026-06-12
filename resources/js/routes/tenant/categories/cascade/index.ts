import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
export const children = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(options),
    method: 'get',
})

children.definition = {
    methods: ["get","head"],
    url: '/categories/cascade/children',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
children.url = (options?: RouteQueryOptions) => {
    return children.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
children.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: children.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
children.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: children.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
const childrenForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
childrenForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::children
* @see app/Http/Controllers/Tenant/CategoryController.php:35
* @route '/categories/cascade/children'
*/
childrenForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: children.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

children.form = childrenForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
export const path = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: path.url(options),
    method: 'get',
})

path.definition = {
    methods: ["get","head"],
    url: '/categories/cascade/path',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
path.url = (options?: RouteQueryOptions) => {
    return path.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
path.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: path.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
path.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: path.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
const pathForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: path.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
pathForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: path.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::path
* @see app/Http/Controllers/Tenant/CategoryController.php:73
* @route '/categories/cascade/path'
*/
pathForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: path.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

path.form = pathForm

const cascade = {
    children: Object.assign(children, children),
    path: Object.assign(path, path),
}

export default cascade