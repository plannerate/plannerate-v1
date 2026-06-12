import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:17
* @route '/api/editor/layers/{id}'
*/
export const update = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/api/editor/layers/{id}',
} satisfies RouteDefinition<["put"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:17
* @route '/api/editor/layers/{id}'
*/
update.url = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { id: args }
    }

    if (Array.isArray(args)) {
        args = {
            id: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        id: args.id,
    }

    return update.definition.url
            .replace('{id}', parsedArgs.id.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:17
* @route '/api/editor/layers/{id}'
*/
update.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:17
* @route '/api/editor/layers/{id}'
*/
const updateForm = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::update
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:17
* @route '/api/editor/layers/{id}'
*/
updateForm.put = (args: { id: string | number } | [id: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:35
* @route '/api/editor/layers/{layer}'
*/
export const destroy = (args: { layer: string | number } | [layer: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/api/editor/layers/{layer}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:35
* @route '/api/editor/layers/{layer}'
*/
destroy.url = (args: { layer: string | number } | [layer: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { layer: args }
    }

    if (Array.isArray(args)) {
        args = {
            layer: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        layer: args.layer,
    }

    return destroy.definition.url
            .replace('{layer}', parsedArgs.layer.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:35
* @route '/api/editor/layers/{layer}'
*/
destroy.delete = (args: { layer: string | number } | [layer: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:35
* @route '/api/editor/layers/{layer}'
*/
const destroyForm = (args: { layer: string | number } | [layer: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor\LayerController::destroy
* @see packages/callcocam/laravel-raptor-plannerate/src/Http/Controllers/Editor/LayerController.php:35
* @route '/api/editor/layers/{layer}'
*/
destroyForm.delete = (args: { layer: string | number } | [layer: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const layers = {
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default layers