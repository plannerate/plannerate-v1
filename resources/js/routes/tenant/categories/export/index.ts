import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
export const template = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: template.url(options),
    method: 'get',
})

template.definition = {
    methods: ["get","head"],
    url: '/categories/export/template',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
template.url = (options?: RouteQueryOptions) => {
    return template.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
template.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: template.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
template.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: template.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
const templateForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: template.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
templateForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: template.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::template
* @see app/Http/Controllers/Tenant/CategoryController.php:296
* @route '/categories/export/template'
*/
templateForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: template.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

template.form = templateForm

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
export const data = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: data.url(options),
    method: 'get',
})

data.definition = {
    methods: ["get","head"],
    url: '/categories/export/data',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
data.url = (options?: RouteQueryOptions) => {
    return data.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
data.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: data.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
data.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: data.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
const dataForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: data.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
dataForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: data.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Tenant\CategoryController::data
* @see app/Http/Controllers/Tenant/CategoryController.php:303
* @route '/categories/export/data'
*/
dataForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: data.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

data.form = dataForm

const exportMethod = {
    template: Object.assign(template, template),
    data: Object.assign(data, data),
}

export default exportMethod